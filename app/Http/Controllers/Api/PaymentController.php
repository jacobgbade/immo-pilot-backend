<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    use AuthorizesOwnership;

    /** Spec section 25: history across all properties, most recent first. */
    public function index(Request $request)
    {
        $propertyIds = $request->user()->properties()->whereNull('archived_at')->pluck('id');

        $payments = Payment::whereHas(
            'lease.unit',
            fn ($q) => $q->whereIn('property_id', $propertyIds)
        )->with('lease.tenant', 'lease.unit.property')
            ->latest('paid_at')
            ->get();

        return response()->json($payments);
    }

    /** Spec sections 22-23: expected/collected/overdue for a given (or current) period. */
    public function overview(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));

        $leases = Lease::whereHas('unit', fn ($q) => $q->whereIn(
            'property_id',
            $request->user()->properties()->whereNull('archived_at')->pluck('id')
        ))->where('status', '!=', 'ended')
            ->with('tenant', 'unit.property', 'payments')
            ->get();

        $expected = $leases->sum('rent_amount');
        $collected = $leases->filter(fn (Lease $l) => $l->paymentForPeriod($period))->sum('rent_amount');
        $overdue = $expected - $collected;
        $rate = $expected > 0 ? round(($collected / $expected) * 1000) / 10 : 0;

        return response()->json([
            'period' => $period,
            'expected' => $expected,
            'collected' => $collected,
            'overdue' => $overdue,
            'rate' => $rate,
            'leases' => $leases->map(fn (Lease $l) => [
                'lease_id' => $l->id,
                'tenant_name' => $l->tenant->name,
                'unit_label' => $l->unit->label,
                'property_name' => $l->unit->property->name,
                'rent' => $l->rent_amount,
                'due_day' => $l->due_day,
                'status' => $l->paymentForPeriod($period) ? 'paid' : 'overdue',
            ]),
        ]);
    }

    /** Spec section 25/38 (Enregistrer un paiement): records the period as paid. */
    public function store(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'integer', 'min:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:mobile_money,especes,bancaire'],
        ]);

        abort_if($lease->paymentForPeriod($data['period']), 422, 'Ce mois est déjà marqué payé.');

        $payment = $lease->payments()->create($data + [
            'reference' => 'PAY-' . Str::upper(Str::random(8)),
        ]);

        // A payment settles any mise en demeure open for the same period (Art. 76) —
        // the legal clock stops the moment the debt is actually paid.
        $lease->demandLetters()
            ->where('period', $data['period'])
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        $request->user()->alerts()->create([
            'category' => 'paiements',
            'icon' => '✓',
            'message' => 'Paiement de ' . number_format($data['amount'], 0, ',', ' ') . ' FCFA enregistré.',
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
        ]);

        return response()->json($payment->load('lease.tenant', 'lease.unit.property'), 201);
    }
}
