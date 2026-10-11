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

        {{-- Opmaak van het "skelet": de grijze blokken die je even ziet als een pagina met
             marketingcijfers nog aan het laden is. Bewust los van Tailwind, zodat het er
             altijd goed uitziet, ook zonder de opmaak opnieuw te bouwen. --}}
        <style>
            .gkr-skel { background: #e2e8f0; border-radius: 8px; animation: gkr-skel-pulse 1.4s ease-in-out infinite; }
            .gkr-skel-bar { background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .1); }
            .gkr-skel-wrap { max-width: 80rem; margin: 0 auto; padding: 24px 32px; }
            .gkr-skel-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px; }
            .gkr-skel-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
            .gkr-skel-stack > * + * { margin-top: 24px; }
            @media (min-width: 768px) { .gkr-skel-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            @media (min-width: 1024px) { .gkr-skel-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
            @media (max-width: 640px) { .gkr-skel-wrap { padding: 16px; } }
            @keyframes gkr-skel-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }
            /* Wie in het systeem "minder beweging" heeft aangezet, krijgt stilstaande blokken */
            @media (prefers-reduced-motion: reduce) { .gkr-skel { animation: none; } }
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
                        <header id="page-header" class="bg-white shadow z-10 sticky top-16">
                            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endif

                    <main id="page-main" class="flex-1 p-6">
                        {{ $slot }}
                    </main>

                    {{-- Het skelet. Staat verborgen op elke pagina. Klik je op een pagina met
                         marketingcijfers en duurt het laden even, dan komt dit in de plaats van
                         de inhoud tot de nieuwe pagina binnen is. Er staan geen gegevens in. --}}
                    <div id="page-skeleton" hidden>
                        <span class="sr-only" role="status">De cijfers worden geladen.</span>

                        {{-- Witte balk met de titel (niet bij Analytics, die pagina heeft geen balk) --}}
                        <div id="page-skeleton-bar" class="gkr-skel-bar" aria-hidden="true">
                            <div class="gkr-skel-wrap">
                                <div class="gkr-skel" style="height: 22px; width: 240px; max-width: 70%;"></div>
                                <div class="gkr-skel" style="height: 12px; width: 340px; max-width: 90%; margin-top: 10px;"></div>
                            </div>
                        </div>

                        <div class="gkr-skel-wrap gkr-skel-stack" aria-hidden="true" style="padding-top: 40px;">
                            {{-- Knoppen voor de periode --}}
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                @for ($i = 0; $i < 4; $i++)
                                    <div class="gkr-skel" style="height: 34px; width: 88px; border-radius: 10px;"></div>
                                @endfor
                            </div>

                            {{-- Vijf tegels met een cijfer --}}
                            <div class="gkr-skel-grid">
                                @for ($i = 0; $i < 5; $i++)
                                    <div class="gkr-skel-card">
                                        <div class="gkr-skel" style="height: 10px; width: 60%;"></div>
                                        <div class="gkr-skel" style="height: 26px; width: 75%; margin-top: 14px;"></div>
                                        <div class="gkr-skel" style="height: 10px; width: 45%; margin-top: 12px;"></div>
                                    </div>
                                @endfor
                            </div>

                            {{-- De grafiek --}}
                            <div class="gkr-skel-card">
                                <div class="gkr-skel" style="height: 12px; width: 180px;"></div>
                                <div class="gkr-skel" style="height: 260px; margin-top: 18px; border-radius: 12px;"></div>
                            </div>

                            {{-- De tabel --}}
                            <div class="gkr-skel-card">
                                <div class="gkr-skel" style="height: 12px; width: 200px;"></div>
                                @for ($i = 0; $i < 5; $i++)
                                    <div class="gkr-skel" style="height: 14px; margin-top: 18px;"></div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Skelet tonen als een pagina met marketingcijfers niet meteen binnen is.
             Zo'n pagina moet soms wachten op Google of Meta. In plaats van een scherm dat
             niet lijkt te reageren, zie je dan direct de vorm van de nieuwe pagina. --}}
        @auth
            @php
                // De adressen van de pagina's met marketingcijfers (alleen adressen, geen gegevens).
                // "bar": heeft die pagina een witte balk met de titel?
                $skeletonPages = [
                    ['path' => parse_url(route('google-ads.index'), PHP_URL_PATH), 'bar' => true],
                    ['path' => parse_url(route('meta-ads.index'), PHP_URL_PATH), 'bar' => true],
                    ['path' => parse_url(route('admin.google-ads.index'), PHP_URL_PATH), 'bar' => true],
                    ['path' => parse_url(route('admin.meta-ads.index'), PHP_URL_PATH), 'bar' => true],
                    ['path' => parse_url(route('admin.analytics.index'), PHP_URL_PATH), 'bar' => false],
                ];

                $skeletonConfig = [
                    'pages'   => $skeletonPages,
                    // Pas na een kwart seconde: komt de pagina sneller binnen, dan zie je geen flits
                    'delayMs' => 250,
                ];
            @endphp
            <script id="page-skeleton-script">
                (function () {
                    const config = @js($skeletonConfig);

                    const skeleton = document.getElementById('page-skeleton');
                    const bar = document.getElementById('page-skeleton-bar');
                    const main = document.getElementById('page-main');
                    const header = document.getElementById('page-header');

                    if (!skeleton || !main) return;

                    let timer = null;
                    let swapped = null; // de twee links in de zijbalk waarvan de opmaak is omgewisseld

                    // Is dit het adres van een pagina met marketingcijfers?
                    function findPage(url) {
                        return config.pages.find(function (page) {
                            return url.pathname === page.path || url.pathname.startsWith(page.path + '/');
                        }) || null;
                    }

                    // Opmaak van twee elementen omwisselen (class van de link en van het icoon erin)
                    function swapClasses(a, b) {
                        [[a, b], [a.querySelector('svg'), b.querySelector('svg')]].forEach(function (pair) {
                            if (!pair[0] || !pair[1]) return;

                            const first = pair[0].getAttribute('class') || '';
                            pair[0].setAttribute('class', pair[1].getAttribute('class') || '');
                            pair[1].setAttribute('class', first);
                        });
                    }

                    function restoreHighlight() {
                        if (!swapped) return;

                        swapClasses(swapped[0], swapped[1]);
                        swapped = null;
                    }

                    // In de zijbalk de markering verplaatsen naar de link waarop is geklikt
                    function moveHighlight(link) {
                        // Stond de markering al verplaatst (iemand klikt tijdens het wachten op een
                        // andere pagina)? Dan eerst terugzetten, anders raakt de volgorde in de war.
                        restoreHighlight();

                        const aside = link.closest('aside');
                        if (!aside) return;

                        const active = Array.from(aside.querySelectorAll('a')).find(function (item) {
                            return item.classList.contains('font-bold') && item.classList.contains('bg-white/10');
                        });

                        if (!active || active === link) return;

                        swapClasses(active, link);
                        swapped = [active, link];
                    }

                    function show(link, page) {
                        main.style.display = 'none';
                        if (header) header.style.display = 'none';
                        if (bar) bar.style.display = page.bar ? '' : 'none';

                        skeleton.hidden = false;
                        window.scrollTo(0, 0);
                        moveHighlight(link);
                    }

                    // Alles terugzetten zoals het was
                    function hide() {
                        clearTimeout(timer);
                        timer = null;

                        skeleton.hidden = true;
                        main.style.display = '';
                        if (header) header.style.display = '';

                        restoreHighlight();
                    }

                    document.addEventListener('click', function (event) {
                        // Alleen een gewone klik. Cmd/Ctrl-klik (nieuw tabblad) laten we met rust.
                        if (event.defaultPrevented || event.button !== 0) return;
                        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

                        const link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
                        if (!link || link.hasAttribute('download')) return;
                        if (link.target && link.target !== '_self') return;

                        let url;
                        try { url = new URL(link.href, window.location.href); } catch (error) { return; }

                        if (url.origin !== window.location.origin) return;

                        const page = findPage(url);
                        if (!page) return;

                        clearTimeout(timer);
                        timer = setTimeout(function () { show(link, page); }, Number(config.delayMs) || 250);
                    });

                    // Terug via de terugknop van de browser: nooit met het skelet blijven zitten
                    window.addEventListener('pageshow', hide);

                    // Laden afgebroken met Escape: gewone pagina weer tonen
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && !skeleton.hidden) hide();
                    });
                })();
            </script>
        @endauth

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