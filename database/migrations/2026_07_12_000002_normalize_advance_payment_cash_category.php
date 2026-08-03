<?php

use App\Models\LoanPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $paymentIds = DB::table('loan_payments')->where('payment_type', 'adelanto_cuotas')->pluck('id');
        DB::table('cash_movements')->where('related_type', LoanPayment::class)->whereIn('related_id', $paymentIds)->update(['category' => 'cobro_prestamo']);
    }

    public function down(): void
    {
        $paymentIds = DB::table('loan_payments')->where('payment_type', 'adelanto_cuotas')->pluck('id');
        DB::table('cash_movements')->where('related_type', LoanPayment::class)->whereIn('related_id', $paymentIds)->update(['category' => 'adelanto_cuotas']);
    }
};
