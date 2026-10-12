<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvitationController;
use App\Mail\ClientInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * GKR maakt de accounts van klanten aan. Zelf registreren kan niet meer.
 *
 * De klant krijgt een welkomstmail met een link om zelf een wachtwoord te kiezen.
 * Zo gaat er nooit een wachtwoord via mail of chat, en bepaalt alleen GKR wie binnenkomt.
 */
class ClientAccountController extends Controller
{
    public function store(Request $request)
    {
        // E-mailadressen altijd in kleine letters en zonder spaties eromheen
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        // Eigen "bag": zo komen deze foutmeldingen alleen bij het formulier "Klant toevoegen"
        $validated = $request->validateWithBag('invite', [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        ], [
            'name.required'   => 'Vul de naam van de klant in.',
            'email.required'  => 'Vul het e-mailadres van de klant in.',
            'email.email'     => 'Dit is geen geldig e-mailadres.',
            'email.lowercase' => 'Gebruik alleen kleine letters in het e-mailadres.',
            'email.unique'    => 'Er bestaat al een account met dit e-mailadres.',
        ]);

        $client = new User();
        $client->forceFill([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            // Een lang willekeurig wachtwoord dat niemand kent. De klant kiest zelf een
            // eigen wachtwoord via de link in de welkomstmail.
            'password'          => Str::random(64),
            'is_admin'          => false,
            // GKR heeft het adres zelf ingevuld; zonder dit kan de klant niet verder
            'email_verified_at' => now(),
        ])->save();

        return $this->sendInvitation($client, "Het account van {$client->name} is aangemaakt en de uitnodiging is verstuurd.");
    }

    /**
     * Uitnodiging (opnieuw) sturen, bijvoorbeeld als de link verlopen is.
     */
    public function resend(User $user)
    {
        // Alleen voor klanten. Een medewerker beheert zijn wachtwoord zelf.
        abort_if($user->isAdmin(), 404);

        return $this->sendInvitation($user, "Er is een nieuwe uitnodiging gestuurd naar {$user->email}.");
    }

    private function sendInvitation(User $client, string $success)
    {
        try {
            Mail::to($client->email)->send(new ClientInvitation($client, InvitationController::urlFor($client)));
        } catch (Throwable $e) {
            Log::error('Uitnodiging voor het klantportaal kon niet worden verstuurd', [
                'user_id' => $client->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('admin.users.index')
                ->with('error', "De uitnodiging naar {$client->email} kon niet worden verstuurd. Probeer het later opnieuw met \"Uitnodiging sturen\".");
        }

        return redirect()->route('admin.users.index')->with('success', $success);
    }
}