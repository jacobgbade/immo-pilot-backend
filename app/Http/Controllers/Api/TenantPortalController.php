<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesTenantLease;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TenantPortalController extends Controller
{
    use ResolvesTenantLease;

    /** Spec section 0ter "Écran d'accueil locataire": rent due, status, property/unit. */
    public function home(Request $request)
    {
        $lease = $this->activeLeaseForTenant($request);
        $lease->load('unit.property', 'payments', 'tenant');

        $period = now()->format('Y-m');
        $payment = $lease->payments->firstWhere('period', $period);

        return response()->json([
            'tenant_name' => $lease->tenant->name,
            'property_name' => $lease->unit->property->name,
            'unit_label' => $lease->unit->label,
            'rent_amount' => $lease->rent_amount,
            'due_day' => $lease->due_day,
            'period' => $period,
            'payment_status' => $payment ? 'up_to_date' : 'overdue',
        ]);
    }
}
