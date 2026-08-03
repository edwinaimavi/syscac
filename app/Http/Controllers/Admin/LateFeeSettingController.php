<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LateFeeSetting;
use App\Models\LoanPaymentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class LateFeeSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:mora.index')->only(['index', 'list', 'summary']);
        $this->middleware('can:mora.create')->only('store');
        $this->middleware('can:mora.view')->only('show');
        $this->middleware('can:mora.edit')->only(['edit', 'update']);
        $this->middleware('can:mora.delete')->only('destroy');
        $this->middleware('can:mora.activate')->only('activate');
        $this->middleware('can:mora.report')->only('report');
    }

    public function index() { return view('admin.late-fees.index'); }

    public function list()
    {
        return DataTables::of(LateFeeSetting::query()->with(['creator', 'updater'])->latest('id'))
            ->addIndexColumn()->addColumn('code', fn ($s) => $this->code($s))
            ->editColumn('grace_days', fn ($s) => $s->grace_days . ' días')
            ->editColumn('calculation_type', fn ($s) => $this->typeLabel($s->calculation_type))
            ->editColumn('value', fn ($s) => $this->formattedValue($s))
            ->editColumn('max_amount', fn ($s) => $s->max_amount === null ? 'Sin límite' : 'S/ ' . number_format((float) $s->max_amount, 2))
            ->editColumn('auto_apply', fn ($s) => $this->booleanBadge($s->auto_apply))
            ->editColumn('allow_waiver', fn ($s) => $this->booleanBadge($s->allow_waiver))
            ->editColumn('is_active', fn ($s) => '<span class="badge badge-' . ($s->is_active ? 'success' : 'secondary') . '">' . ($s->is_active ? 'Activo' : 'Inactivo') . '</span>')
            ->addColumn('audit', fn ($s) => '<small><strong>' . e($s->updater?->name ?? $s->creator?->name ?? '-') . '</strong><br><span class="text-muted">' . optional($s->updated_at)->format('d/m/Y H:i') . '</span></small>')
            ->addColumn('actions', fn ($s) => view('admin.late-fees.partials.actions', ['setting' => $s])->render())
            ->rawColumns(['auto_apply', 'allow_waiver', 'is_active', 'audit', 'actions'])->make(true);
    }

    public function summary()
    {
        $active = LateFeeSetting::where('is_active', true)->latest('id')->first();
        return response()->json(['total' => LateFeeSetting::count(), 'active' => $active?->name ?? 'Sin configuración', 'grace_days' => $active ? $active->grace_days . ' días' : '-', 'type' => $active ? $this->typeLabel($active->calculation_type) : '-']);
    }

    public function store(Request $request)
    {
        $setting = DB::transaction(function () use ($request) {
            $data = $this->data($request);
            if ($data['is_active']) LateFeeSetting::where('is_active', true)->update(['is_active' => false, 'updated_by' => auth()->id()]);
            return LateFeeSetting::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        });
        return response()->json(['message' => 'Configuración de mora registrada correctamente.', 'id' => $setting->id]);
    }

    public function show(LateFeeSetting $mora) { return response()->json($this->payload($mora)); }
    public function edit(LateFeeSetting $mora) { return response()->json($this->payload($mora)); }

    public function update(Request $request, LateFeeSetting $mora)
    {
        DB::transaction(function () use ($request, $mora) {
            $data = $this->data($request);
            if ($data['is_active']) LateFeeSetting::whereKeyNot($mora->id)->where('is_active', true)->update(['is_active' => false, 'updated_by' => auth()->id()]);
            $mora->update($data + ['updated_by' => auth()->id()]);
        });
        return response()->json(['message' => 'Configuración de mora actualizada correctamente.']);
    }

    public function activate(Request $request, LateFeeSetting $mora)
    {
        $active = $request->boolean('active');
        if (! $active && ! $mora->is_active) throw ValidationException::withMessages(['active' => ['La configuración ya está inactiva.']]);
        DB::transaction(function () use ($mora, $active) {
            if ($active) LateFeeSetting::whereKeyNot($mora->id)->where('is_active', true)->update(['is_active' => false, 'updated_by' => auth()->id()]);
            $mora->update(['is_active' => $active, 'updated_by' => auth()->id()]);
        });
        return response()->json(['message' => $active ? 'Configuración activada correctamente.' : 'Configuración desactivada. La mora automática puede quedar sin una regla activa.']);
    }

    public function destroy(LateFeeSetting $mora)
    {
        if ($mora->is_active) return response()->json(['message' => 'No se puede eliminar la configuración activa. Active otra primero.'], 422);
        $mora->delete();
        return response()->json(['message' => 'Configuración eliminada correctamente.']);
    }

    public function report(Request $request)
    {
        $rows = LoanPaymentDetail::with(['payment.member', 'payment.loan', 'installment'])->whereHas('payment', fn ($q) => $q->where('status', 'registrado')->when($request->filled('date_from'), fn ($x) => $x->whereDate('payment_date', '>=', $request->date_from))->when($request->filled('date_to'), fn ($x) => $x->whereDate('payment_date', '<=', $request->date_to)))->where(fn ($q) => $q->where('late_fee_paid', '>', 0)->orWhere('late_fee_waived', '>', 0))->latest('id')->paginate(50)->withQueryString();
        return view('admin.late-fees.report', compact('rows'));
    }

    private function data(Request $request): array { return $request->validate(['name' => 'required|string|max:120', 'grace_days' => 'required|integer|min:0|max:365', 'calculation_type' => ['required', Rule::in(['fixed_daily', 'percentage_daily', 'fixed_once'])], 'value' => 'required|numeric|min:0.0001', 'max_amount' => 'nullable|numeric|min:0', 'observation' => 'nullable|string|max:1000', 'is_active' => 'required|boolean', 'allow_waiver' => 'required|boolean', 'auto_apply' => 'required|boolean']); }
    private function payload(LateFeeSetting $s): array { $s->loadMissing(['creator', 'updater']); return [...$s->only(['id', 'name', 'grace_days', 'calculation_type', 'value', 'max_amount', 'is_active', 'auto_apply', 'allow_waiver', 'observation']), 'code' => $this->code($s), 'type_label' => $this->typeLabel($s->calculation_type), 'formatted_value' => $this->formattedValue($s), 'created_by_name' => $s->creator?->name ?? '-', 'updated_by_name' => $s->updater?->name ?? '-', 'created_at_label' => optional($s->created_at)->format('d/m/Y H:i'), 'updated_at_label' => optional($s->updated_at)->format('d/m/Y H:i')]; }
    private function code(LateFeeSetting $s): string { return 'MOR-' . str_pad((string) $s->id, 6, '0', STR_PAD_LEFT); }
    private function typeLabel(string $type): string { return ['fixed_daily' => 'Monto fijo diario', 'percentage_daily' => 'Porcentaje diario', 'fixed_once' => 'Monto fijo único'][$type] ?? $type; }
    private function formattedValue(LateFeeSetting $s): string { return $s->calculation_type === 'percentage_daily' ? number_format((float) $s->value, 4) . ' %' : 'S/ ' . number_format((float) $s->value, 2); }
    private function booleanBadge(bool $value): string { return '<span class="badge badge-' . ($value ? 'success' : 'light border') . '">' . ($value ? 'Sí' : 'No') . '</span>'; }
}
