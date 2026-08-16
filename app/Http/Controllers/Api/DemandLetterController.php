<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\DemandLetter;
use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DemandLetterController extends Controller
{
    use AuthorizesOwnership;

    /** Every mise en demeure across the owner's active leases, most recent first. */
    public function index(Request $request)
    {
        $propertyIds = $request->user()->properties()->whereNull('archived_at')->pluck('id');

        $letters = DemandLetter::whereHas(
            'lease.unit',
            fn ($q) => $q->whereIn('property_id', $propertyIds)
        )->with('lease.tenant', 'lease.unit.property')
            ->latest('sent_at')
            ->get();

        return response()->json($letters->map(fn (DemandLetter $l) => $this->present($l)));
    }

    /**
     * Spec section 22-23 / Art. 75: la mise en demeure ne peut viser qu'une période
     * réellement impayée, et démarre le délai légal d'un mois (Art. 76).
     */
    public function store(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'sent_at' => ['nullable', 'date'],
        ]);

        abort_if(
            $lease->paymentForPeriod($data['period']),
            422,
            'Cette période est déjà marquée payée — aucune mise en demeure nécessaire.'
        );

        abort_if(
            $lease->demandLetters()->where('period', $data['period'])->exists(),
            422,
            'Une mise en demeure a déjà été envoyée pour cette période.'
        );

        $letter = $lease->demandLetters()->create([
            'period' => $data['period'],
            'amount' => $lease->rent_amount,
            'sent_at' => $data['sent_at'] ?? now(),
        ]);

        $request->user()->alerts()->create([
            'category' => 'paiements',
            'icon' => '⚠️',
            'message' => 'Mise en demeure envoyée à ' . $lease->tenant->name . ' pour ' . $data['period'] . '.',
            'subject_type' => DemandLetter::class,
            'subject_id' => $letter->id,
        ]);

        return response()->json($this->present($letter->load('lease.tenant', 'lease.unit.property')), 201);
    }

    /** Manual resolution (e.g. paid in cash and confirmed outside the payment flow). */
    public function resolve(Request $request, DemandLetter $letter)
    {
        $this->authorizeOwner($request, $letter->lease, via: 'unit.property');

        $letter->update(['resolved_at' => now()]);

        return response()->json($this->present($letter->fresh(['lease.tenant', 'lease.unit.property'])));
    }

    private function present(DemandLetter $letter): array
    {
        $deadline = $letter->legalDeadline();
        $expired = ! $letter->resolved_at && Carbon::now()->greaterThan($deadline);

        return [
            'id' => $letter->id,
            'lease_id' => $letter->lease_id,
            'tenant_name' => $letter->lease->tenant->name,
            'unit_label' => $letter->lease->unit->label,
            'property_name' => $letter->lease->unit->property->name,
            'period' => $letter->period,
            'amount' => $letter->amount,
            'sent_at' => $letter->sent_at->toDateString(),
            'legal_deadline' => $deadline->toDateString(),
            'resolved_at' => $letter->resolved_at?->toDateString(),
            'status' => $letter->resolved_at ? 'resolved' : ($expired ? 'expired' : 'pending'),
        ];
    }
}
