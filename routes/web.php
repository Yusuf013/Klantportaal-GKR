<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CommentController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

// De klant gaat naar de gewone ProjectController
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show')->middleware(['auth']);

// De route voor het goedkeuren van documenten
Route::post('/documents/{document}/approve', [App\Http\Controllers\ProjectController::class, 'approveDocument'])->name('documents.approve');

// --- ADMIN SECTIE ---
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    // Overzicht en Creatie
    Route::get('/admin/projects/create', [AdminProjectController::class, 'create'])->name('admin.projects.create');
    Route::post('/admin/projects', [AdminProjectController::class, 'store'])->name('admin.projects.store');

    Route::get('/admin/projects', [AdminProjectController::class, 'index'])->name('admin.projects.index');
    
    // Specifieke projectbeheer pagina (Stap 5)
    Route::get('/admin/projects/{project}', [AdminProjectController::class, 'show'])->name('admin.projects.show');
    
    // De upload route (Stap 4)
    Route::post('/admin/projects/{project}/upload', [AdminProjectController::class, 'uploadDocument'])->name('admin.projects.upload');

Route::get('/admin/documents/{document}', [AdminProjectController::class, 'showDocument'])->name('admin.documents.show');

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/documents/{document}/comments', [CommentController::class, 'store'])->name('comments.store');
    // Route voor de specifieke document-detailpagina
    Route::get('/documents/{document}', [ProjectController::class, 'showDocument'])->name('documents.show');
});

require __DIR__.'/auth.php';