<?php

use App\Http\Controllers\Api\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Api\Admin\AvailabilityController as AdminAvailabilityController;
use App\Http\Controllers\Api\Admin\CallbackRequestController as AdminCallbackRequestController;
use App\Http\Controllers\Api\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Api\Admin\ClosedDayController;
use App\Http\Controllers\Api\Admin\PreferenceController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CallbackRequestController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ProjectController;
use Illuminate\Support\Facades\Route;

/**
 * Nieuwe API-laag voor de mobiele app (ADR-002/ADR-003). Bestond niet in de AS-IS-codebase
 * (requirements.md §2.2) — dit is de walking-skeleton-scaffolding uit het plan
 * "Infrastructuur, testen en API-laag vóór de iOS-basis".
 *
 * Foutresponsconventie (skill `api-design`): bewust geen eigen exception-handler bovenop
 * Laravel's standaardgedrag voor JSON-requests — dat geeft al consistent `{"message": "..."}`
 * (plus `{"errors": {...}}` bij 422-validatiefouten) voor elke fout op deze routes. Expliciet
 * vastgelegd zodat dit een bewuste keuze is, geen toevallige afwezigheid van een conventie.
 */
Route::post('/login', [AuthController::class, 'login']);

// Publiek: het inlogscherm van de app toont de huisstijl vóór authenticatie (ADR-010). Alleen
// publieke huisstijl in de response (BrandingResource). Eigen throttle, omdat dit endpoint
// zonder token bereikbaar is.
Route::get('/branding', [BrandingController::class, 'show'])->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/documenten', [DocumentController::class, 'index']);

    // Afspraken en Outlook-koppeling (FR-08, ADR-011). Scoping server-side; een afspraak van
    // iemand anders geeft 404 (AppointmentPolicy).
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('/appointments/{appointment}/confirm-option', [AppointmentController::class, 'confirmOption']);
    Route::post('/appointments/{appointment}/alternative', [AppointmentController::class, 'alternative']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('/availability', [AvailabilityController::class, 'index']);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/callback-requests', [CallbackRequestController::class, 'store']);
    Route::get('/contact', [ContactController::class, 'show']);

    Route::middleware('admin')->group(function () {
        // Huisstijl wijzigen: alleen admins (middleware + BrandingPolicy), ADR-010.
        Route::put('/branding', [BrandingController::class, 'update']);
        Route::post('/branding/logo', [BrandingController::class, 'storeLogo']);
        Route::delete('/branding/logo', [BrandingController::class, 'destroyLogo']);

        // Afspraken beheren (ADR-011).
        Route::patch('/me/preferences', [PreferenceController::class, 'update']);

        Route::prefix('admin')->group(function () {
            Route::get('/appointments', [AdminAppointmentController::class, 'index']);
            Route::post('/appointments', [AdminAppointmentController::class, 'store']);
            Route::post('/appointments/{appointment}/approve', [AdminAppointmentController::class, 'approve']);
            Route::post('/appointments/{appointment}/reject', [AdminAppointmentController::class, 'reject']);
            Route::get('/availability', [AdminAvailabilityController::class, 'index']);
            Route::get('/clients', [AdminClientController::class, 'index']);
            Route::get('/clients/{client}/projects', [AdminClientController::class, 'projects']);
            Route::patch('/employees/{employee}', [EmployeeController::class, 'update']);
            Route::get('/callback-requests', [AdminCallbackRequestController::class, 'index']);
            Route::patch('/callback-requests/{callbackRequest}', [AdminCallbackRequestController::class, 'update']);
            Route::get('/closed-days', [ClosedDayController::class, 'index']);
            Route::post('/closed-days', [ClosedDayController::class, 'store']);
            Route::delete('/closed-days/{closedDay}', [ClosedDayController::class, 'destroy']);
        });
    });
});
