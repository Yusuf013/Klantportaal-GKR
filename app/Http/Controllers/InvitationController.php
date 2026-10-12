<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

/**
 * De klant stelt via de link in de welkomstmail zelf een wachtwoord in.
 *
 * Hoe de link beveiligd is:
 * - Hij is ondertekend met de geheime sleutel van het portaal (APP_KEY). Verandert
 *   iemand iets aan de link, bijvoorbeeld het nummer van de klant, dan werkt hij niet meer.
 * - Hij verloopt na 7 dagen.
 * - Hij werkt maar één keer. In de link zit een vingerafdruk van het huidige wachtwoord.
 *   Zodra de klant een wachtwoord heeft gekozen, klopt die niet meer en is de link ongeldig.
 */
class InvitationController extends Controller
{
    // Zo lang is de link in de welkomstmail geldig
    public const VALID_DAYS = 7;

    /**
     * De link voor de welkomstmail.
     */
    public static function urlFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'invitation.show',
            now()->addDays(self::VALID_DAYS),
            ['user' => $user->id, 'v' => self::fingerprint($user)]
        );
    }

    /**
     * Een vingerafdruk van het huidige wachtwoord. Hieraan zie je niets van het wachtwoord
     * zelf, maar hij verandert zodra er een nieuw wachtwoord is.
     */
    private static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', $user->id . '|' . $user->getAuthPassword(), (string) config('app.key')), 0, 32);
    }

    /**
     * Is deze link echt van ons, nog niet verlopen en nog niet gebruikt?
     */
    private function linkIsValid(Request $request, User $user): bool
    {
        return ! $user->isAdmin()
            && $request->hasValidSignature()
            && hash_equals(self::fingerprint($user), (string) $request->query('v'));
    }

    public function show(Request $request, User $user)
    {
        if (! $this->linkIsValid($request, $user)) {
            return response()->view('auth.invitation-expired', [], 410);
        }

        return view('auth.invitation', [
            'name'   => $user->name,
            'email'  => $user->email,
            // Het formulier stuurt terug naar precies deze (ondertekende) link
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user)
    {
        if (! $this->linkIsValid($request, $user)) {
            return response()->view('auth.invitation-expired', [], 410);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.required'  => 'Kies een wachtwoord.',
            'password.confirmed' => 'De twee wachtwoorden zijn niet gelijk.',
            'password.min'       => 'Een wachtwoord moet minimaal :min tekens lang zijn.',
        ]);

        // Het wachtwoord wordt vanzelf versleuteld opgeslagen (zie de casts in het User-model).
        // De klant heeft de link uit de mail gebruikt, dus het e-mailadres klopt.
        $user->forceFill([
            'password'          => $request->input('password'),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welkom! Je wachtwoord is ingesteld.');
    }
}