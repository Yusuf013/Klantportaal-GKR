<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Message;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.navigation', 'layouts.sidebar'], function ($view) {
            $unreadCount = 0;

            if (Auth::check()) {
                $user = Auth::user();

                if ($user->is_admin) {
                    // ADMIN: Tel alle ongelezen berichten van álle klanten
                    $unreadCount = Message::whereHas('sender', function ($query) {
                        $query->where('is_admin', false);
                    })->whereNull('read_at')->count();
                } else {
                    // KLANT: Tel alleen ongelezen berichten gericht aan deze specifieke klant
                    $unreadCount = Message::where('receiver_id', $user->id)
                        ->whereNull('read_at')
                        ->count();
                }
            }

            $view->with('unreadCount', $unreadCount);
        });
    }
}