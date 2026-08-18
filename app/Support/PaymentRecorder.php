<?php

namespace App\Support;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared by PaymentController::store (owner records a payment directly) and
 * PaymentDeclarationController::confirm (owner confirms a tenant's declared payment) —
 * both end in the same real Payment row, the same demand-letter auto-resolve (Art. 76),
 * and the same alert, so the logic lives once instead of drifting between two call sites.
 */
class PaymentRecorder
{
    public static function record(User $owner, Lease $lease, array $data): Payment
    {
        $payment = $lease->payments()->create([
            'period' => $data['period'],
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'] ?? now(),
            'method' => $data['method'] ?? 'mobile_money',
            'reference' => $data['reference'] ?? ('PAY-' . Str::upper(Str::random(8))),
        ]);

        $lease->demandLetters()
            ->where('period', $data['period'])
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        $owner->alerts()->create([
            'category' => 'paiements',
            'icon' => '✓',
            'message' => 'Paiement de ' . number_format($data['amount'], 0, ',', ' ') . ' FCFA enregistré.',
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
        ]);

        return $payment;
    }
}
