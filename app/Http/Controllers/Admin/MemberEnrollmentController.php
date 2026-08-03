<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberEnrollment;
use App\Services\LoanEligibilityService;
use App\Services\MemberEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberEnrollmentController extends Controller
{
    public function __construct(private readonly MemberEnrollmentService $service, private readonly LoanEligibilityService $eligibility)
    {
        $this->middleware('can:admin.inscripciones.create')->only('store');
        $this->middleware('can:admin.inscripciones.anular')->only('annul');
        $this->middleware('can:admin.inscripciones.voucher')->only(['voucher', 'viewVoucher']);
    }

    public function store(Request $request, Member $member)
    {
        if ($this->eligibility->memberType($member) !== 'nuevo') {
            return response()->json(['message' => 'La inscripcion obligatoria solo corresponde a socios nuevos.'], 422);
        }
        if ($member->enrollments()->where('status', 'registrado')->exists()) {
            return response()->json(['message' => 'El socio ya tiene una inscripcion registrada.'], 422);
        }
        $data = $request->validate([
            'enrollment_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'in:50,50.00'],
            'payment_method' => ['required', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'otro'])],
            'payment_reference' => ['nullable', 'required_if:payment_method,yape,plin,transferencia', 'string', 'max:255'],
            'voucher' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'], 'observation' => ['nullable', 'string'],
        ], [
            'amount.in' => 'El monto de inscripcion debe ser S/ 50.00.', 'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.', 'payment_reference.required_if' => 'La referencia es obligatoria para este metodo de pago.',
            'voucher.mimes' => 'El comprobante debe ser una imagen o PDF valido.', 'voucher.max' => 'El comprobante no debe superar los 4 MB.',
        ]);

        $enrollment = DB::transaction(function () use ($request, $member, $data) {
            if ($request->hasFile('voucher')) $data['voucher_path'] = $request->file('voucher')->store('member-enrollments', 'public');
            $data += ['code' => MemberEnrollment::nextCode(), 'member_id' => $member->id, 'status' => 'registrado', 'created_by' => auth()->id(), 'updated_by' => auth()->id()];
            $enrollment = MemberEnrollment::create($data);
            $this->service->sync($enrollment);
            return $enrollment;
        });
        return response()->json(['message' => 'Inscripcion registrada correctamente.', 'code' => $enrollment->code]);
    }

    public function annul(MemberEnrollment $enrollment) { $this->service->annul($enrollment); return response()->json(['message' => 'Inscripcion anulada correctamente.']); }
    public function voucher(MemberEnrollment $enrollment) { abort_unless($enrollment->voucher_path && Storage::disk('public')->exists($enrollment->voucher_path), 404); return Storage::disk('public')->download($enrollment->voucher_path); }
    public function viewVoucher(MemberEnrollment $enrollment)
    {
        abort_unless($enrollment->voucher_path && Storage::disk('public')->exists($enrollment->voucher_path), 404, 'Comprobante no encontrado.');
        return response()->file(Storage::disk('public')->path($enrollment->voucher_path));
    }
}
