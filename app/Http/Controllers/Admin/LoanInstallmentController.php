<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanInstallment;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class LoanInstallmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.prestamos.index')->only(['index', 'list']);
        $this->middleware('can:admin.prestamos.show')->only(['show']);
        $this->middleware('can:admin.prestamos.edit')->only(['update']);
    }

    public function index()
    {
        $loans = \App\Models\Loan::with('member')
            ->orderByDesc('id')
            ->get();

        $members = \App\Models\Member::query()
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name']);

        return view('admin.loan_installments.index', compact('loans', 'members'));
    }

    public function list(Request $request)
    {
        $installments = LoanInstallment::with(['loan.member'])
            ->when($request->filled('loan_id'), fn ($query) => $query->where('loan_id', $request->integer('loan_id')))
            ->when($request->filled('member_id'), fn ($query) => $query->whereHas('loan', fn ($loan) => $loan->where('member_id', $request->integer('member_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('due_date')
            ->orderBy('installment_number');

        return DataTables::of($installments)
            ->addIndexColumn()
            ->addColumn('loan_number', fn (LoanInstallment $installment) => $installment->loan?->loan_number ?? '-')
            ->addColumn('member_name', fn (LoanInstallment $installment) => $installment->loan?->member?->full_name ?? '-')
            ->editColumn('due_date', fn (LoanInstallment $installment) => optional($installment->due_date)->format('d/m/Y') ?? '-')
            ->editColumn('principal_amount', fn (LoanInstallment $installment) => 'S/ ' . number_format((float) $installment->principal_amount, 2))
            ->editColumn('interest_amount', fn (LoanInstallment $installment) => 'S/ ' . number_format((float) $installment->interest_amount, 2))
            ->editColumn('installment_amount', fn (LoanInstallment $installment) => 'S/ ' . number_format((float) $installment->installment_amount, 2))
            ->editColumn('remaining_amount', fn (LoanInstallment $installment) => 'S/ ' . number_format((float) $installment->remaining_amount, 2))
            ->editColumn('status', fn (LoanInstallment $installment) => '<span class="badge badge-' . ($installment->status === 'pagado' ? 'success' : 'warning') . '">' . ucfirst($installment->status) . '</span>')
            ->rawColumns(['status'])
            ->make(true);
    }

    public function show(LoanInstallment $loanInstallment)
    {
        return response()->json($loanInstallment);
    }

    public function update(Request $request, LoanInstallment $loanInstallment)
    {
        $data = $request->validate([
            'due_date' => 'nullable|date',
            'opening_balance' => 'nullable|numeric',
            'principal_amount' => 'nullable|numeric',
            'interest_amount' => 'nullable|numeric',
            'installment_amount' => 'nullable|numeric',
            'paid_amount' => 'nullable|numeric',
            'remaining_amount' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
            'status' => 'nullable|string|max:50',
            'paid_at' => 'nullable|date',
        ]);

        $loanInstallment->update($data);

        return response()->json(['message' => 'Cuota actualizada correctamente']);
    }
}
