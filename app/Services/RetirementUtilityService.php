<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberShare;
use Carbon\Carbon;
use App\Models\MonthlyProfitAccrualDetail;

class RetirementUtilityService
{
    public function __construct(private ProfitAvailabilityService $availability) {}

    public function calculate(Member $member, string $retirementDate, string $mode = 'pending'): array
    {
        $retirement = Carbon::parse($retirementDate);
        $monthlyDetails = MonthlyProfitAccrualDetail::query()->with('accrual')
            ->where('member_id', $member->id)
            ->whereHas('accrual', fn($query) => $query->whereIn('status',['calculada','aprobada','pagada'])
                ->whereDate('month','<',$retirement->copy()->startOfMonth()))
            ->get();
        if ($monthlyDetails->isNotEmpty()) {
            $estimated=round((float)$monthlyDetails->sum(fn($d)=>(float)$d->profit_amount-(float)$d->paid_amount),2);
            $paidNow=$mode==='provisional'?$estimated:0;
            $breakdown=$monthlyDetails->map(fn($d)=>['month'=>$d->accrual->month->format('Y-m'),'actions'=>(float)$d->shares_quantity,
                'profit'=>(float)$d->profit_amount,'paid'=>(float)$d->paid_amount,'pending'=>round((float)$d->profit_amount-(float)$d->paid_amount,2),'status'=>$d->status])->all();
            return ['mode'=>$mode,'status'=>$paidNow>0?'provisional':($estimated>0?'pendiente_cierre_anual':'no_calculada'),
                'period_year'=>$retirement->month<3?$retirement->year-1:$retirement->year,'actions_considered'=>(float)$monthlyDetails->sum('shares_quantity'),
                'productive_months'=>$monthlyDetails->count(),'action_month'=>(float)$monthlyDetails->sum('shares_quantity'),'total_action_month'=>(float)$monthlyDetails->sum('shares_quantity'),
                'available'=>$estimated,'estimated'=>$estimated,'paid_now'=>$paidNow,'pending_amount'=>$mode==='pending'?$estimated:0,'breakdown'=>$breakdown];
        }
        $periodStart = $retirement->copy()->startOfYear();
        $productiveEnd = $retirement->copy()->startOfMonth()->subMonth()->endOfMonth();
        $financial = $this->availability->summary($periodStart->toDateString(), $retirement->copy()->endOfYear()->toDateString());

        $rows = MemberShare::query()->where('status', 'registrado')->where('shares_quantity', '>', 0)
            ->whereDate('date', '<', $productiveEnd->copy()->startOfMonth())->with('member:id,status,retirement_date')->get()
            ->groupBy('member_id')->map(function ($shares) use ($periodStart, $productiveEnd) {
                $owner = $shares->first()->member;
                $ownerEnd = $productiveEnd->copy();
                if ($owner?->retirement_date) $ownerEnd = $ownerEnd->min(Carbon::parse($owner->retirement_date)->startOfMonth()->subMonth()->endOfMonth());
                $breakdown = $shares->map(function ($share) use ($periodStart, $ownerEnd) {
                    $start = Carbon::parse($share->date)->addMonthNoOverflow()->startOfMonth()->max($periodStart);
                    if ($start->gt($ownerEnd)) return null;
                    $months = (int) $start->diffInMonths($ownerEnd->copy()->startOfMonth()) + 1;
                    $actions = round((float) $share->shares_quantity, 4);
                    return ['share_id' => $share->id, 'date' => $share->date?->format('Y-m-d'), 'actions' => $actions, 'from' => $start->format('Y-m-d'), 'to' => $ownerEnd->format('Y-m-d'), 'months' => $months, 'action_month' => round($actions * $months, 4)];
                })->filter()->values();
                return ['actions' => round((float) $breakdown->sum('actions'), 4), 'action_month' => round((float) $breakdown->sum('action_month'), 4), 'breakdown' => $breakdown->all()];
            });

        $memberRow = $rows->get($member->id, ['actions' => 0, 'action_month' => 0, 'breakdown' => []]);
        $totalActionMonth = round((float) $rows->sum('action_month'), 4);
        $productiveMonths = collect($memberRow['breakdown'])->pluck('from')->filter()->map(fn ($date) => Carbon::parse($date)->format('Y-m'))->unique()->count();
        if ($memberRow['breakdown']) {
            $first = Carbon::parse(collect($memberRow['breakdown'])->min('from'));
            $productiveMonths = $first->lte($productiveEnd) ? (int) $first->diffInMonths($productiveEnd->copy()->startOfMonth()) + 1 : 0;
        }
        $estimated = $totalActionMonth > 0 ? round($financial['available'] * $memberRow['action_month'] / $totalActionMonth, 2) : 0;
        $paidNow = $mode === 'provisional' ? $estimated : 0;

        return [
            'mode' => $mode, 'status' => $paidNow > 0 ? 'provisional' : ($memberRow['action_month'] > 0 ? 'pendiente_cierre_anual' : 'no_calculada'),
            'period_year' => $retirement->year, 'actions_considered' => $memberRow['actions'], 'productive_months' => $productiveMonths,
            'action_month' => $memberRow['action_month'], 'total_action_month' => $totalActionMonth,
            'available' => $financial['available'], 'estimated' => $estimated, 'paid_now' => $paidNow,
            'pending_amount' => $mode === 'pending' ? $estimated : 0, 'breakdown' => $memberRow['breakdown'],
        ];
    }
}
