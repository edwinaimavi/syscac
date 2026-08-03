<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfitSource;
use App\Services\ProfitAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitSourceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.utilidades.index')->only('index');
        $this->middleware('can:admin.utilidades.create')->only('store');
        $this->middleware('can:admin.utilidades.anular')->only('annul');
    }

    public function index()
    {
        return response()->json(ProfitSource::with(['creator:id,name', 'annuller:id,name'])
            ->latest('source_date')->latest('id')->limit(50)->get()->map(fn ($source) => [
                'id' => $source->id,
                'code' => $source->code,
                'date' => $source->source_date?->format('d/m/Y'),
                'amount_formatted' => 'S/ ' . number_format((float) $source->amount, 2),
                'adjustment_type' => $source->adjustment_type,
                'reason' => $source->reason,
                'observation' => $source->observation,
                'status' => $source->status,
                'status_label' => $source->status === 'activo' ? 'Activo' : 'Anulado',
                'created_by' => $source->creator?->name ?? '-',
                'created_at' => $source->created_at?->format('d/m/Y H:i'),
                'annulled_by' => $source->annuller?->name,
                'annulled_at' => $source->annulled_at?->format('d/m/Y H:i'),
            ]));
    }

    public function store(Request $request)
    {
        $request->merge(['adjustment_type' => $request->input('adjustment_type', 'positive')]);
        $data = $request->validate([
            'source_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'adjustment_type' => ['required', 'in:positive,correction_positive,negative,previous_year_discount,previously_paid,administrative_correction'],
            'reason' => ['required', 'string', 'max:180'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ], [
            'source_date.required' => 'La fecha es obligatoria.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'reason.required' => 'El motivo es obligatorio.',
        ]);

        DB::transaction(fn () => ProfitSource::create($data + [
            'code' => ProfitSource::nextCode(), 'status' => 'activo', 'created_by' => auth()->id(),
        ]));

        return response()->json(['message' => 'Utilidad manual registrada correctamente.']);
    }

    public function annul(ProfitSource $source, ProfitAvailabilityService $availability)
    {
        if ($source->status === 'anulado') return response()->json(['message' => 'La fuente ya está anulada.'], 422);
        $annulled = DB::transaction(function () use ($source, $availability) {
            $month = (int) config('utility.fiscal_start_month', 3);
            $year = $source->source_date->month < $month ? $source->source_date->year - 1 : $source->source_date->year;
            $start = \Carbon\Carbon::create($year, $month, 1);
            $end = $start->copy()->addYear();
            if (in_array($source->adjustment_type, ['positive', 'correction_positive'], true)
                && (float) $source->amount > $availability->summary($start->toDateString(), $end->toDateString())['available']) return false;
            $source->update(['status' => 'anulado', 'annulled_by' => auth()->id(), 'annulled_at' => now()]);
            return true;
        });
        if (! $annulled) return response()->json(['message' => 'No se puede anular esta fuente porque su utilidad ya está comprometida en una distribución vigente.'], 422);
        return response()->json(['message' => 'Fuente de utilidad anulada correctamente.']);
    }
}
