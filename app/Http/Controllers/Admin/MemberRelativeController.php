<?php

namespace App\Http\Controllers\Admin;

use App\Models\MemberRelative;
use Illuminate\Database\Eloquent\Model;

class MemberRelativeController extends SyscacCrudController
{
    protected string $modelClass = MemberRelative::class;
    protected string $title = 'Familiares';
    protected string $icon = 'fas fa-user-friends';
    protected array $with = ['member'];
    protected array $permissions = [
        'index' => 'admin.socios.index',
        'create' => 'admin.socios.create',
        'edit' => 'admin.socios.edit',
        'show' => 'admin.socios.show',
        'delete' => 'admin.socios.delete',
    ];

    protected function rules(?Model $model = null): array
    {
        return [
            'member_id' => 'required|exists:members,id',
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'birth_date' => 'nullable|date',
            'observation' => 'nullable|string',
        ];
    }
}
