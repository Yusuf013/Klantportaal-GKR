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
            // Medewerker: alle ongelezen berichten van klanten.
            // Klant: de ongelezen antwoorden van GKR in het eigen gesprek.
            // De regels zelf staan op één plek: Message::unreadCountFor().
            $unreadCount = Auth::check() ? Message::unreadCountFor(Auth::user()) : 0;

            $view->with('unreadCount', $unreadCount);
        });
    }
}