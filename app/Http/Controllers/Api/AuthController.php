<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Users naturally type phone numbers with spaces (the field's own placeholder shows
     * "+229 90 00 00 00"), so storage and lookups both normalize to digits-and-leading-+
     * only — otherwise "+229 90 00 00 00" and "+22990000000" would be treated as different
     * accounts and login would fail depending on how the user happened to type it that time.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone);
    }

    /** Spec section 10: name/phone/email, password, then "how many units" for onboarding. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'units_range' => ['nullable', 'string', 'max:50'],
        ]);

        $phone = $this->normalizePhone($data['phone']);

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Ce numéro de téléphone est déjà utilisé.'],
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'phone' => $phone,
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'units_range' => $data['units_range'] ?? null,
        ]);

        $user->alerts()->create([
            'category' => 'systeme',
            'icon' => '✓',
            'message' => 'Votre compte IMMO PILOT a été créé avec succès.',
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    /** Spec section 9: login by phone or email, same field. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $isEmail = filter_var($data['identifier'], FILTER_VALIDATE_EMAIL);
        $user = $isEmail
            ? User::where('email', $data['identifier'])->first()
            : User::where('phone', $this->normalizePhone($data['identifier']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Identifiants incorrects.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateMe(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (isset($data['phone'])) {
            $data['phone'] = $this->normalizePhone($data['phone']);
            if (User::where('phone', $data['phone'])->where('id', '!=', $request->user()->id)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => ['Ce numéro de téléphone est déjà utilisé.'],
                ]);
            }
        }

        $request->user()->update($data);

        return response()->json($request->user()->fresh());
    }
}
