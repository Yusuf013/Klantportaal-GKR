<?php

namespace App\Exceptions\Appointments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Basis voor fouten uit het afsprakendomein. De melding is altijd in gewone taal en direct
 * toonbaar aan klant of admin (geen vaktermen, geen vendor-fouten).
 *
 * JSON (app en de fetch-aanroepen van de website): `{"status": "error", "message": ...}`. Het
 * `status`-veld houdt de bestaande website-JavaScript werkend; de app leest `message`.
 */
abstract class AppointmentException extends RuntimeException
{
    abstract protected function httpStatus(): int;

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'message' => $this->getMessage()], $this->httpStatus());
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
