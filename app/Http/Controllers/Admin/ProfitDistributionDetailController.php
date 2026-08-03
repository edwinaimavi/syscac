<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProfitDistributionDetail;
use Illuminate\Database\Eloquent\Model;

class ProfitDistributionDetailController extends SyscacCrudController
{
    protected string $modelClass = ProfitDistributionDetail::class;
    protected string $title = 'Detalle de utilidades';
    protected string $icon = 'fas fa-list';
    protected array $with = ['distribution', 'member'];
    protected array $permissions = [
        'index' => 'admin.utilidades.index',
        'create' => 'admin.utilidades.calculate',
        'edit' => 'admin.utilidades.pay',
        'show' => 'admin.utilidades.show',
        'delete' => 'admin.utilidades.approve',
    ];

    protected function rules(?Model $model = null): array
    {
        return [
            'profit_distribution_id' => 'required|exists:profit_distributions,id',
            'member_id' => 'required|exists:members,id',
            'shares_quantity' => 'required|integer|min:0',
            'participation_percentage' => 'nullable|numeric|min:0',
            'profit_amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'status' => 'required|string|max:50',
            'paid_at' => 'nullable|date',
        ];
    }
}
