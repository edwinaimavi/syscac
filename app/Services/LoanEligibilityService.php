<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberEnrollment;
use Illuminate\Validation\ValidationException;

class LoanEligibilityService
{
    public const WITHDRAWAL_LOAN_MESSAGE = 'No se puede generar préstamo. El socio tiene un proceso de retiro/cierre pendiente o confirmado.';
    public const WITHDRAWAL_GUARANTOR_MESSAGE = 'El socio seleccionado no puede ser aval/garante porque no está vigente, está en proceso de retiro o no cumple las condiciones requeridas.';
    public const MINOR_GUARANTOR_MESSAGE = 'Un socio menor de edad no puede ser registrado como aval/garante.';

    public function memberType(Member $member): string
    {
        return $member->syncCalculatedMemberType();
    }

    public function evaluate(Member $member, float $amount, ?int $guarantorMemberId = null): array
    {
        $withdrawal = $this->loanEligibilitySummary($member);
        $type = $this->memberType($member);
        $shares = $member->shares()->where('status', 'registrado');
        $count = (clone $shares)->count();
        $total = (float) (clone $shares)->sum('share_capital_amount');
        $limit = $total * ($type === 'nuevo' ? 2 : 3);
        $reasons = [];
        if ($amount > $limit) $reasons[] = 'supera_limite_aportes';
        if ($amount > 7000) $reasons[] = 'supera_7000';
        $requires = $reasons !== [];
        $guarantor = $guarantorMemberId ? Member::find($guarantorMemberId) : null;
        $guarantorEligible = ! $guarantor || $guarantor->canBeGuarantor();
        $guarantorTotal = $guarantor ? (float) $guarantor->shares()->where('status', 'registrado')->sum('share_capital_amount') : 0;

        return [
            'member_type' => $type,
            'admission_date' => optional($member->admission_date)->format('Y-m-d'),
            'membership_time' => $member->admission_date ? $member->admission_date->diffForHumans(now(), true) : '-',
            'contribution_count' => $count,
            'total_contributions' => $total,
            'loan_limit_without_guarantor' => $limit,
            'requires_guarantor' => $requires,
            'guarantor_requirement_reason' => implode(',', $reasons) ?: null,
            'guarantor_total_contributions' => $guarantorTotal,
            'eligible_by_contribution_count' => $type === 'antiguo' || $count >= 3,
            'has_required_enrollment' => $type === 'antiguo' || MemberEnrollment::where('member_id', $member->id)->where('status', 'registrado')->exists(),
            'can_request_loan' => ! $withdrawal['withdrawal_blocked'],
            'can_be_guarantor' => $member->canBeGuarantor(),
            'is_minor' => $member->isMinor(),
            'guarantor_eligible' => $guarantorEligible,
            ...$withdrawal,
        ];
    }

    public function validate(Member $member, float $amount, ?int $guarantorMemberId = null): array
    {
        $this->assertCanRequestLoan($member);

        $evaluation = $this->evaluate($member, $amount, $guarantorMemberId);
        if (! $evaluation['has_required_enrollment']) {
            throw ValidationException::withMessages(['member_id' => ['La inscripcion del socio nuevo es obligatoria.']]);
        }
        if (! $evaluation['eligible_by_contribution_count']) {
            throw ValidationException::withMessages(['member_id' => ['Este socio es nuevo y aun no cumple con los 3 aportes minimos para solicitar prestamo.']]);
        }
        if ($evaluation['requires_guarantor'] && ! $guarantorMemberId) {
            $motives = [];
            if (str_contains((string) $evaluation['guarantor_requirement_reason'], 'supera_limite_aportes')) {
                $motives[] = 'Supera el limite permitido segun aportes.';
            }
            if (str_contains((string) $evaluation['guarantor_requirement_reason'], 'supera_7000')) {
                $motives[] = 'Supera S/ 7,000.';
            }
            $message = 'Este prestamo requiere garante.' . ($motives ? ' ' . implode(' ', $motives) : '');
            throw ValidationException::withMessages(['guarantor_member_id' => [$message]]);
        }
        if ($guarantorMemberId) {
            if ($guarantorMemberId === $member->id) {
                throw ValidationException::withMessages(['guarantor_member_id' => ['El garante no puede ser el mismo socio solicitante.']]);
            }
            $guarantor = Member::find($guarantorMemberId);
            if (! $guarantor) {
                throw ValidationException::withMessages(['guarantor_member_id' => ['El garante debe ser un socio vigente.']]);
            }
            $this->assertCanBeGuarantor($guarantor);
            if ($evaluation['guarantor_total_contributions'] <= 0) {
                throw ValidationException::withMessages(['guarantor_member_id' => ['El garante no tiene aportes registrados.']]);
            }
            if ($evaluation['guarantor_total_contributions'] < $amount) {
                throw ValidationException::withMessages(['guarantor_member_id' => ['El garante seleccionado no tiene aportes suficientes para respaldar este prestamo.']]);
            }
        }

        return $evaluation;
    }

    public function assertCanRequestLoan(Member $member): void
    {
        if (! $member->canRequestLoan()) {
            throw ValidationException::withMessages(['member_id' => [self::WITHDRAWAL_LOAN_MESSAGE]]);
        }
    }

    public function assertCanBeGuarantor(Member $member): void
    {
        if (! $member->canRequestLoan()) {
            throw ValidationException::withMessages(['guarantor_member_id' => [self::WITHDRAWAL_GUARANTOR_MESSAGE]]);
        }
        if ($member->isMinor()) {
            throw ValidationException::withMessages(['guarantor_member_id' => [self::MINOR_GUARANTOR_MESSAGE]]);
        }
    }

    public function loanEligibilitySummary(Member $member): array
    {
        $closure = $member->accountClosures()
            ->whereIn('status', ['calculado', 'pendiente_regularizacion', 'en_proceso', 'cerrado'])
            ->latest('id')
            ->first();
        $confirmed = $member->status !== 'vigente' || $member->retirement_date !== null || $closure?->status === 'cerrado';
        $pending = $closure && in_array($closure->status, ['calculado', 'pendiente_regularizacion', 'en_proceso'], true);
        $blocked = $confirmed || $pending;
        $balanceAgainst = $closure ? max(
            0,
            -(float) $closure->final_balance,
            (float) $closure->total_against - (float) $closure->total_in_favor
        ) : 0;
        $pendingDebt = $pending && $balanceAgainst > 0.009;

        $reason = null;
        if ($confirmed) {
            $reason = 'Retiro/cierre confirmado';
        } elseif ($pendingDebt) {
            $reason = 'Retiro pendiente de regularización';
        } elseif ($pending) {
            $reason = 'Proceso de retiro/cierre en curso';
        }

        return [
            'withdrawal_blocked' => $blocked,
            'withdrawal_status' => $blocked ? 'No habilitado' : 'Habilitado',
            'withdrawal_reason' => $reason,
            'withdrawal_balance_against' => round($balanceAgainst, 2),
            'withdrawal_balance_against_formatted' => 'S/ ' . number_format($balanceAgainst, 2),
            'withdrawal_action' => $blocked ? 'Regularizar deuda o anular el cierre si corresponde.' : null,
            'withdrawal_message' => $blocked
                ? ($pendingDebt
                    ? 'Socio no habilitado para préstamo. Tiene un cierre de cuenta pendiente de regularización por deuda pendiente. Debe regularizar su situación antes de solicitar un nuevo préstamo.'
                    : 'Socio no habilitado para préstamo. Tiene un proceso de retiro/cierre pendiente o confirmado.')
                : null,
            'withdrawal_closure_id' => $closure?->id,
            'withdrawal_closure_code' => $closure?->code,
        ];
    }

    public function snapshot(array $evaluation): array
    {
        return [
            'requires_guarantor' => $evaluation['requires_guarantor'],
            'member_type_at_evaluation' => $evaluation['member_type'],
            'member_contribution_count_at_evaluation' => $evaluation['contribution_count'],
            'member_total_contributions_at_evaluation' => $evaluation['total_contributions'],
            'loan_limit_without_guarantor' => $evaluation['loan_limit_without_guarantor'],
            'guarantor_total_contributions_at_evaluation' => $evaluation['guarantor_total_contributions'],
            'guarantor_requirement_reason' => $evaluation['guarantor_requirement_reason'],
        ];
    }
}
