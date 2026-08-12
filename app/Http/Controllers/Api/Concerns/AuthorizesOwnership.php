<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait AuthorizesOwnership
{
    /**
     * Aborts with 403 unless the given model belongs to the current user.
     *
     * $via is a dot-path of relations to walk to reach the owning model, e.g. 'unit.property'
     * for a Lease (Lease -> Unit -> Property -> user_id). Omit it when the model has its
     * own user_id column directly.
     */
    private function authorizeOwner(Request $request, Model $model, ?string $via = null): void
    {
        $owner = $model;
        foreach (array_filter(explode('.', (string) $via)) as $relation) {
            $owner = $owner->{$relation};
        }

        abort_if($owner->user_id !== $request->user()->id, 403, 'Cette ressource ne vous appartient pas.');
    }
}
