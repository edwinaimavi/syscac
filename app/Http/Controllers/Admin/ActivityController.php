<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityMovement;
use App\Models\CashMovement;
use App\Models\Member;
use App\Models\Receipt;
use App\Services\ShareCashMovementService;
use App\Services\ProfitAvailabilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ActivityController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.actividades.index')->only(['index', 'list', 'summary', 'nextCode']);
        $this->middleware('can:admin.actividades.create')->only(['store']);
        $this->middleware('can:admin.actividades.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.actividades.show')->only(['show']);
        $this->middleware('can:admin.actividades.close')->only(['close']);
        $this->middleware('can:admin.actividades.anular')->only(['annul', 'destroy']);
        $this->middleware('can:admin.actividades.report')->only(['report']);
        $this->middleware('can:admin.actividades.report_pdf')->only(['reportPdf']);
    }

    public function index()
    {
        return view('admin.activities.index', [
            'nextCode' => Activity::nextCode(),
            'members' => Member::where('status', 'vigente')->whereNull('retirement_date')->orderBy('full_name')->get(['id', 'code', 'dni', 'full_name']),
            'nextMovementCode' => ActivityMovement::nextCode(),
        ]);
    }

    public function list(Request $request)
    {
        $activities = Activity::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%' . $request->input('name') . '%'))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('activity_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('activity_date', '<=', $request->input('date_to')))
            ->orderByDesc('activity_date')
            ->orderByDesc('id');

        return DataTables::of($activities)
            ->addIndexColumn()
            ->editColumn('activity_date', fn (Activity $activity) => optional($activity->activity_date)->format('d/m/Y') ?? '-')
            ->editColumn('total_income', fn (Activity $activity) => $this->money($activity->total_income))
            ->editColumn('total_expense', fn (Activity $activity) => $this->money($activity->total_expense))
            ->editColumn('profit', fn (Activity $activity) => $this->profitBadge($activity->profit))
            ->editColumn('status', fn (Activity $activity) => $this->statusBadge($activity->status))
            ->addColumn('acciones', fn (Activity $activity) => view('admin.activities.partials.acciones', compact('activity'))->render())
            ->rawColumns(['profit', 'status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'open' => Activity::where('status', 'abierta')->count(),
            'closed' => Activity::where('status', 'cerrada')->count(),
            'profit' => number_format((float) Activity::where('status', '!=', 'anulada')->sum('profit'), 2),
            'month_movements' => ActivityMovement::where('status', 'registrado')->whereYear('movement_date', now()->year)->whereMonth('movement_date', now()->month)->count(),
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => Activity::nextCode()]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $activity = DB::transaction(function () use ($data) {
            return Activity::create($data + [
                'code' => Activity::nextCode(),
                'total_income' => 0,
                'total_expense' => 0,
                'profit' => 0,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Actividad registrada correctamente.', 'id' => $activity->id]);
    }

    public function show(Activity $actividade)
    {
        return response()->json($this->activityPayload($actividade));
    }

    public function edit(Activity $actividade)
    {
        return response()->json($this->activityPayload($actividade));
    }

    public function update(Request $request, Activity $actividade)
    {
        if ($actividade->status === 'anulada') {
            return response()->json(['message' => 'No se puede editar una actividad anulada.'], 422);
        }

        $data = $this->validatedData($request, $actividade);
        $actividade->update($data + ['updated_by' => auth()->id()]);

        return response()->json(['message' => 'Actividad actualizada correctamente.']);
    }

    public function close(Activity $actividade)
    {
        if ($actividade->status !== 'abierta') {
            return response()->json(['message' => 'Solo se pueden cerrar actividades abiertas.'], 422);
        }

        DB::transaction(function () use ($actividade) {
            $this->recalculateTotals($actividade);
            $actividade->refresh()->update([
                'status' => 'cerrada',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Actividad cerrada correctamente.']);
    }

    public function destroy(Activity $actividade)
    {
        return $this->annul($actividade);
    }

    public function annul(Activity $actividade, ProfitAvailabilityService $profitAvailability)
    {
        if ($actividade->status === 'anulada') {
            return response()->json(['message' => 'La actividad ya se encuentra anulada.'], 422);
        }

        if ($actividade->status === 'cerrada' && (float) $actividade->profit > $profitAvailability->summary()['available']) {
            return response()->json(['message' => 'No se puede anular esta actividad porque su utilidad ya está comprometida en una distribución vigente.'], 422);
        }

        DB::transaction(function () use ($actividade) {
            $actividade->movements()->where('status', 'registrado')->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
            ]);

            $movementIds = $actividade->movements()->pluck('id');

            CashMovement::where('related_type', ActivityMovement::class)->whereIn('related_id', $movementIds)->update([
                'status' => 'anulado',
                'balance_before' => null,
                'balance_after' => null,
                'updated_by' => auth()->id(),
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
            ]);

            Receipt::where('related_type', ActivityMovement::class)->whereIn('related_id', $movementIds)->update([
                'status' => 'anulado',
                'updated_by' => auth()->id(),
            ]);

            $actividade->update([
                'status' => 'anulada',
                'total_income' => 0,
                'total_expense' => 0,
                'profit' => 0,
                'updated_by' => auth()->id(),
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
            ]);

            app(ShareCashMovementService::class)->recalculateBalances();
        });

        return response()->json(['message' => 'Actividad anulada correctamente.']);
    }

    public function report(Activity $actividade)
    {
        $actividade->load(['movements.member', 'creator', 'closer']);

        return view('admin.activities.report', ['activity' => $actividade]);
    }

    public function reportPdf(Activity $actividade)
    {
        $actividade->load(['movements.member', 'creator', 'closer']);

        return Pdf::loadView('admin.activities.report', ['activity' => $actividade, 'pdfMode' => true])
            ->setPaper('a4', 'landscape')
            ->stream('Reporte Actividad ' . $actividade->code . '.pdf');
    }

    private function validatedData(Request $request, ?Activity $activity = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'activity_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['abierta', 'cerrada', 'anulada'])],
        ], $this->messages());
    }

    private function activityPayload(Activity $activity): array
    {
        $activity->load(['movements.member', 'creator', 'closer']);

        return [
            'id' => $activity->id,
            'code' => $activity->code,
            'name' => $activity->name,
            'activity_date' => optional($activity->activity_date)->format('Y-m-d'),
            'activity_date_formatted' => optional($activity->activity_date)->format('d/m/Y'),
            'description' => $activity->description,
            'total_income_formatted' => $this->money($activity->total_income),
            'total_expense_formatted' => $this->money($activity->total_expense),
            'profit' => number_format((float) $activity->profit, 2, '.', ''),
            'profit_formatted' => $this->money($activity->profit),
            'profit_class' => (float) $activity->profit > 0 ? 'success' : ((float) $activity->profit < 0 ? 'danger' : 'secondary'),
            'status' => $activity->status,
            'status_label' => $this->statusLabel($activity->status),
            'closed_at' => optional($activity->closed_at)->format('d/m/Y H:i'),
            'closed_by_name' => $activity->closer?->name,
            'created_at' => optional($activity->created_at)->format('d/m/Y H:i'),
            'created_by_name' => $activity->creator?->name,
            'report_url' => route('admin.actividades.report', $activity),
            'report_pdf_url' => route('admin.actividades.report.pdf', $activity),
            'movements' => $activity->movements->sortByDesc('movement_date')->map(fn (ActivityMovement $movement) => [
                'id' => $movement->id,
                'code' => $movement->code,
                'movement_date' => optional($movement->movement_date ?: $movement->date)->format('d/m/Y'),
                'type_label' => ucfirst($movement->type),
                'member_name' => $movement->member?->full_name ?? '-',
                'concept' => $movement->concept,
                'amount' => $this->money($movement->amount),
                'status_label' => ucfirst($movement->status),
            ])->values(),
        ];
    }

    private function recalculateTotals(Activity $activity): void
    {
        $query = $activity->movements()->where('status', 'registrado');
        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');

        $activity->update([
            'total_income' => $income,
            'total_expense' => $expense,
            'profit' => (float) $income - (float) $expense,
            'updated_by' => auth()->id(),
        ]);
    }

    private function profitBadge(mixed $amount): string
    {
        $value = (float) $amount;
        $class = $value > 0 ? 'success' : ($value < 0 ? 'danger' : 'secondary');

        return '<span class="badge badge-' . $class . '">' . e($this->money($value)) . '</span>';
    }

    private function statusBadge(?string $status): string
    {
        $class = match ($status) {
            'cerrada' => 'info',
            'anulada' => 'danger',
            default => 'success',
        };

        return '<span class="badge badge-' . $class . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'abierta' => 'Abierta',
            'cerrada' => 'Cerrada',
            'anulada' => 'Anulada',
            default => '-',
        };
    }

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }

    private function messages(): array
    {
        return [
            'name.required' => 'El nombre de la actividad es obligatorio.',
            'activity_date.required' => 'La fecha de la actividad es obligatoria.',
            'activity_date.date' => 'La fecha de la actividad debe ser valida.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
        ];
    }
}
