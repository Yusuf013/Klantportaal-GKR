<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProjectController as ClientProjectController;
use App\Http\Controllers\DashboardController as ClientDashboardController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
});


/*
|--------------------------------------------------------------------------
| CLIENT OMVANG (Ingelogde Klanten / Algemeen Auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Klant Dashboard (Home)
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    // Het totale documentenoverzicht voor de klant
    Route::get('/mijn-documenten', [ClientProjectController::class, 'allDocuments'])->name('documents.index');

    // Het totale projectenoverzicht voor de klant
    Route::get('/mijn-projecten', [ClientProjectController::class, 'allProjects'])->name('client.projects.index');

    // Route voor het overzicht en de modal
    Route::get('/mijn-agenda', [App\Http\Controllers\Client\AppointmentController::class, 'index'])->name('client.appointments.index');

// Route voor het opvangen van het formulier (Afspraak aanvragen)
    Route::post('/mijn-agenda', [App\Http\Controllers\Client\AppointmentController::class, 'store'])->name('client.appointments.store');


    // Klant Projecten & Documenten
    Route::get('/projects/{project}', [ClientProjectController::class, 'show'])->name('projects.show');
    Route::get('/documents/{document}', [ClientProjectController::class, 'showDocument'])->name('documents.show');
    Route::post('/documents/{document}/approve', [ClientProjectController::class, 'approveDocument'])->name('documents.approve');

    // Feedback & Reacties (Gedeeld of Klant)
    Route::post('/documents/{document}/comments', [CommentController::class, 'store'])->name('comments.store');

    // Gebruikersprofiel
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


/*
|--------------------------------------------------------------------------
| ADMIN OMVANG (Team GKR / Volledige Admin Beveiliging)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin') // Plakt automatisch '/admin' voor elke URL in deze groep
    ->name('admin.')  // Plakt automatisch 'admin.' voor elke routenaam in deze groep
    ->group(function () {
        
        // Admin Dashboard (Home met de nieuwe widget)
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/klanten', [App\Http\Controllers\Admin\DashboardController::class, 'clientsIndex'])->name('clients.index');

        // Projecten Overzicht & Creatie
        Route::get('/projects', [AdminProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/create', [AdminProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [AdminProjectController::class, 'store'])->name('projects.store');

        // Specifiek Projectbeheer & Uploads
        Route::get('/projects/{project}', [AdminProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}/upload', [AdminProjectController::class, 'uploadDocument'])->name('projects.upload');

        // Specifieke Document Detailpagina & Feedback (Admin zijde)
        Route::get('/documents/{document}', [AdminProjectController::class, 'showDocument'])->name('documents.show');
});


/*
|--------------------------------------------------------------------------
| Auth Routes (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';