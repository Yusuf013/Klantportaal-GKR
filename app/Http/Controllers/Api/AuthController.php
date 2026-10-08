<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;

/**
 * Sanctum-gebaseerde authenticatie voor de mobiele app (ADR-003).
 * Losstaand van AuthenticatedSessionController: dat geeft een sessie-cookie
 * terug (web), dit geeft een personal access token terug (API/mobiel).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $request->authenticate();

        $user = $request->user();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // De app bepaalt hiermee welke tabs en beheerschermen zichtbaar zijn. Autorisatie
                // blijft server-side (admin-middleware); dit is alleen UI-informatie. Expliciete
                // cast omdat SQLite 0/1 teruggeeft.
                'is_admin' => (bool) $user->is_admin,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Uitgelogd.']);
    }
}
