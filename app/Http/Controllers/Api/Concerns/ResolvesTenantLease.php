<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Lease;
use Illuminate\Http\Request;

trait ResolvesTenantLease
{
    /** The authenticated tenant's current active lease — 403/404 if this account isn't one. */
    private function activeLeaseForTenant(Request $request): Lease
    {
        $tenant = $request->user()->tenantProfile;
        abort_if(! $tenant, 403, "Ce compte n'est rattaché à aucun locataire.");

        $lease = $tenant->leases()->where('status', '!=', 'ended')->latest('start_date')->first();
        abort_if(! $lease, 404, 'Aucun bail actif.');

        return $lease;
    }
}
