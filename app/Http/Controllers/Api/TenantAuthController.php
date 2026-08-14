<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\NormalizesPhone;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAuthController extends Controller
{
    use NormalizesPhone;

    /**
     * Spec section 0bis: a tenant links their app account to an existing lease by phone
     * number — the landlord already created the Tenant record when adding them to a unit,
     * so there's no free-form signup here, only matching an existing record + setting a password.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $phone = $this->normalizePhone($data['phone']);

        $tenant = Tenant::where('phone', $phone)->whereNull('app_user_id')->first();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'phone' => ['Aucun locataire trouvé avec ce numéro. Contactez votre propriétaire.'],
            ]);
        }

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Ce numéro de téléphone est déjà utilisé.'],
            ]);
        }

        $user = User::create([
            'name' => $tenant->name,
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'role' => 'tenant',
        ]);

        $tenant->update(['app_user_id' => $user->id]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }
}
