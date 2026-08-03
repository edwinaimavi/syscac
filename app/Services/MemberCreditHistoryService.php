<?php

namespace App\Services;

use App\Models\Member;

class MemberCreditHistoryService
{
    public function __construct(private CreditHistoryService $history)
    {
    }

    public function evaluate(Member $member): array
    {
        return $this->history->summary($member);
    }
}
