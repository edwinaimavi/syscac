<?php

namespace App\Http\Controllers\Admin;

use App\Models\LoanPaymentDetail;
use Illuminate\Database\Eloquent\Model;

class LoanPaymentDetailController extends SyscacCrudController
{
    protected string $modelClass = LoanPaymentDetail::class;
    protected string $title = 'Detalle de cobros';
    protected string $icon = 'fas fa-list';
    protected array $with = ['payment', 'installment'];
    protected array $permissions = [
        'index' => 'admin.cobros.index',
        'create' => 'admin.cobros.create',
        'edit' => 'admin.cobros.create',
        'show' => 'admin.cobros.show',
        'delete' => 'admin.cobros.delete',
    ];

    protected function rules(?Model $model = null): array
    {
        return [
            'loan_payment_id' => 'required|exists:loan_payments,id',
            'loan_installment_id' => 'nullable|exists:loan_installments,id',
            'principal_paid' => 'required|numeric|min:0',
            'interest_paid' => 'required|numeric|min:0',
            'amount_paid' => 'required|numeric|min:0.01',
            'previous_balance' => 'nullable|numeric|min:0',
            'new_balance' => 'nullable|numeric|min:0',
        ];
    }
}
