<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\CreditHistoryService;
use Illuminate\Console\Command;

class RecalculateCreditHistories extends Command
{
    protected $signature = 'credit-history:recalculate {member? : ID del socio}';
    protected $description = 'Recalcula el historial crediticio de uno o todos los socios';

    public function handle(CreditHistoryService $service): int
    {
        $query = Member::withTrashed()->orderBy('id');
        if ($this->argument('member')) $query->whereKey($this->argument('member'));
        $total = $query->count();
        if ($total === 0) { $this->warn('No se encontraron socios.'); return self::SUCCESS; }
        $bar = $this->output->createProgressBar($total);
        $query->chunkById(100, function ($members) use ($service, $bar) {
            foreach ($members as $member) { $service->recalculate($member); $bar->advance(); }
        });
        $bar->finish(); $this->newLine(); $this->info("{$total} historial(es) recalculado(s).");
        return self::SUCCESS;
    }
}
