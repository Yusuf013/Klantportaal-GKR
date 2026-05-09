<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
       <div class="min-h-screen bg-white">
    @include('layouts.navigation')

    <div class="flex">
        
        <aside class="w-64 min-h-[calc(100vh-64px)] bg-gkr-dark hidden md:flex flex-col border-r border-gray-100">
            <div class="p-6 space-y-4">
                <p class="text-xs font-semibold text-gkr-accent uppercase tracking-widest">Project Management</p>
                
                <nav class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-3 text-white rounded-lg bg-white/10 font-maven">
                        Dashboard
                    </a>
                    <a href="#" class="flex items-center px-4 py-3 text-white/70 hover:text-white hover:bg-white/5 rounded-lg transition font-inter text-sm">
                        Mijn Projecten
                    </a>
                    <a href="#" class="flex items-center px-4 py-3 text-white/70 hover:text-white hover:bg-white/5 rounded-lg transition font-inter text-sm">
                        Bestanden & Media
                    </a>
                    <a href="#" class="flex items-center px-4 py-3 text-white/70 hover:text-white hover:bg-white/5 rounded-lg transition font-inter text-sm">
                        Facturatie
                    </a>
                </nav>
            </div>
        </aside>

        <main class="flex-1">
            @if (isset($header))
                <header class="bg-white shadow-sm border-b border-gray-100">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 font-maven text-black">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <div class="py-12 px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
    </body>
</html>
