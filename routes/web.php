<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController; // Naam veranderd om verwarring te voorkomen
use App\Http\Controllers\ProjectController; // De controller voor de klant toegevoegd
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

// De klant gaat naar de gewone ProjectController
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show')->middleware(['auth']);

Route::post('/admin/projects/{project}/upload', [AdminProjectController::class, 'uploadDocument'])->name('admin.projects.upload');

Route::middleware(['auth', 'verified'])->group(function () {
    // De Admin gebruikt de AdminProjectController
    Route::get('/admin/projects/create', [AdminProjectController::class, 'create'])->name('admin.projects.create');
    Route::post('/admin/projects', [AdminProjectController::class, 'store'])->name('admin.projects.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';