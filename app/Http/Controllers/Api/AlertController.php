<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    use AuthorizesOwnership;

    /** Spec section 33: feed, optionally filtered by category. */
    public function index(Request $request)
    {
        $alerts = $request->user()->alerts()
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->latest()
            ->get();

        return response()->json($alerts);
    }

    public function markRead(Request $request, Alert $alert)
    {
        $this->authorizeOwner($request, $alert);
        $alert->update(['read_at' => now()]);

        return response()->json($alert->fresh());
    }

    public function markAllRead(Request $request)
    {
        $request->user()->alerts()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Tout est marqué comme lu.']);
    }
}
