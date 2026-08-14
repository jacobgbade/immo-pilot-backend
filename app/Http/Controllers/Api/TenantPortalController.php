<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TenantPortalController extends Controller
{
    /** Spec section 0ter "Écran d'accueil locataire": rent due, status, property/unit. */
    public function home(Request $request)
    {
        $tenant = $request->user()->tenantProfile;
        abort_if(! $tenant, 403, "Ce compte n'est rattaché à aucun locataire.");

        $lease = $tenant->leases()->where('status', '!=', 'ended')->with('unit.property', 'payments')->latest('start_date')->first();
        abort_if(! $lease, 404, 'Aucun bail actif.');

        $period = now()->format('Y-m');
        $payment = $lease->payments->firstWhere('period', $period);

        return response()->json([
            'tenant_name' => $tenant->name,
            'property_name' => $lease->unit->property->name,
            'unit_label' => $lease->unit->label,
            'rent_amount' => $lease->rent_amount,
            'due_day' => $lease->due_day,
            'period' => $period,
            'payment_status' => $payment ? 'up_to_date' : 'overdue',
        ]);
    }
}
