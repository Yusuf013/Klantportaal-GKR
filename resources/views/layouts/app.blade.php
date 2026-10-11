<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
     <meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
        [x-cloak] { display: none !important; }
    </style>

        {{-- Extra vangnet bij de terugknop. Sommige browsers halen een pagina in zijn geheel
             uit het geheugen terug, zonder de server iets te vragen. Gebeurt dat, dan laden
             we de pagina opnieuw, zodat de server beslist wie er is ingelogd. --}}
        <script>
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        </script>
    </head>
    <body class="font-sans antialiased bg-gray-100 overflow-x-hidden">
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            <div class="flex flex-1 relative pt-16">
                @include('layouts.sidebar')

                <div class="flex-1 w-full md:pl-64 flex flex-col min-h-[calc(100vh-4rem)]">
                    @if (isset($header))
                        <header class="bg-white shadow z-10 sticky top-16">
                            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endif

                    <main class="flex-1 p-6">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>

        {{-- De rode teller bij "Chat / Support" bijwerken zonder de pagina te herladen.
             Zo zie je op elke pagina dat er een bericht is binnengekomen.
             Niet op de chatpagina zelf: die werkt de teller al bij (elke 5 seconden). --}}
        @auth
            @unless(request()->routeIs('chat.*'))
                @php
                    // Alleen een adres en twee tijden, geen gegevens van gebruikers
                    $unreadConfig = [
                        'url'    => route('chat.unread'),
                        // Zo vaak kijken we of er iets nieuws is: elke 20 seconden
                        'pollMs' => 20 * 1000,
                        // Na 30 minuten zonder muis of toetsenbord stoppen we met kijken
                        'idleMs' => 30 * 60 * 1000,
                    ];
                @endphp
                <script id="chat-unread-poll">
                    (function () {
                        const config = @js($unreadConfig);
                        const badge = document.getElementById('chat-unread-badge');

                        if (!badge) return;

                        let lastActive = Date.now(); // laatste keer dat de gebruiker iets deed
                        let busy = false;            // er loopt al een verzoek
                        let stopped = false;         // niet meer ingelogd: stoppen

                        function show(count) {
                            if (typeof count !== 'number') return;

                            badge.textContent = count > 99 ? '99+' : String(count);
                            badge.style.display = count > 0 ? '' : 'none';
                        }

                        function idle() {
                            return Date.now() - lastActive > config.idleMs;
                        }

                        async function check() {
                            // Alleen als het tabblad in beeld is en er echt iemand achter zit.
                            // Anders zou een vergeten, open tabblad de sessie eindeloos actief houden.
                            if (stopped || busy || document.visibilityState !== 'visible' || idle()) return;

                            busy = true;

                            try {
                                const response = await fetch(config.url, {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                });

                                // Niet meer ingelogd: stoppen. We herladen de pagina hier niet,
                                // want iemand kan net een formulier aan het invullen zijn.
                                if (response.status === 401 || response.status === 419) {
                                    stopped = true;
                                    return;
                                }

                                if (response.ok) {
                                    const data = await response.json();
                                    show(data.unread_total);
                                }
                            } catch (error) {
                                // Even geen verbinding: de volgende ronde proberen we het opnieuw
                            } finally {
                                busy = false;
                            }
                        }

                        // De gebruiker doet iets. Was het lang stil, dan meteen even kijken.
                        function active() {
                            const wasIdle = idle();
                            lastActive = Date.now();

                            if (wasIdle) check();
                        }

                        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (name) {
                            window.addEventListener(name, active, { passive: true, capture: true });
                        });

                        setInterval(check, Number(config.pollMs) || 20000);

                        // Kom je terug op dit tabblad? Dan meteen bijwerken
                        document.addEventListener('visibilitychange', check);
                    })();
                </script>
            @endunless
        @endauth
    </body>
</html>