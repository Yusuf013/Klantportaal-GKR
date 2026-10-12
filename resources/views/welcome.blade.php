{{--
    De startpagina van het klantportaal (voor wie nog niet is ingelogd).
    Eigen opmaak in de huisstijl van GKR: donkerblauw #011936 met lichtblauw #94b8ff,
    Maven Pro voor koppen en Inter voor tekst. Geen Tailwind nodig, dus deze pagina
    ziet er altijd hetzelfde uit.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GKR Klantportaal</title>
    <meta name="description" content="Het klantportaal van GKR Digital Agency: projecten, documenten, afspraken, chat en advertentiecijfers op één plek.">

    {{-- Het icoon in het tabblad van de browser --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=maven-pro:600,700|inter:400,500,600&display=swap" rel="stylesheet" />

    <style>
        :root {
            --navy: #011936;
            --navy-deep: #000f22;
            --accent: #94b8ff;
            --ink: #0f172a;
            --muted: #475569;
            --line: #e2e8f0;
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: var(--ink); background: #fff; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        a:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 6px; }

        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 24px; }

        /* Bovenkant: logo en inlogknop op donkerblauw */
        .top { background: var(--navy); color: #fff; }
        .nav { display: flex; align-items: center; justify-content: space-between; height: 80px; }
        .nav img { height: 34px; width: auto; display: block; }

        .button { display: inline-block; font-weight: 600; font-size: 15px; text-decoration: none; padding: 12px 22px; border-radius: 10px; transition: background-color .15s; }
        .button-light { background: #fff; color: var(--navy); }
        .button-light:hover { background: #e8efff; }
        .button-small { padding: 9px 16px; font-size: 14px; }

        /* De belofte van het portaal. Alleen boven en onder: links en rechts komt van .wrap */
        .hero { padding-top: 72px; padding-bottom: 96px; }
        .hero h1 { font-family: "Maven Pro", Inter, sans-serif; font-weight: 700; font-size: clamp(34px, 5.4vw, 60px); line-height: 1.06; letter-spacing: -0.02em; max-width: 15ch; margin: 0; }
        .hero p { font-size: 18px; line-height: 1.6; color: #c9d7f2; max-width: 56ch; margin: 24px 0 36px; }
        .hero .note { font-size: 14px; color: #9fb3d9; margin: 20px 0 0; }
        .hero .note a { color: var(--accent); }

        /* Wat je in het portaal doet */
        .features { padding-top: 80px; padding-bottom: 80px; }
        .features h2 { font-family: "Maven Pro", Inter, sans-serif; font-weight: 700; font-size: 28px; color: var(--navy); margin: 0 0 40px; }
        .list { display: grid; grid-template-columns: 1fr; column-gap: 56px; margin: 0; padding: 0; list-style: none; }
        .list li { border-top: 1px solid var(--line); padding: 22px 0 26px; }
        .list h3 { font-family: "Maven Pro", Inter, sans-serif; font-weight: 600; font-size: 19px; color: var(--navy); margin: 0 0 6px; }
        .list p { margin: 0; color: var(--muted); line-height: 1.6; max-width: 46ch; }
        @media (min-width: 768px) { .list { grid-template-columns: 1fr 1fr; } }

        /* Onderkant */
        .footer { background: var(--navy-deep); color: #9fb3d9; font-size: 14px; }
        .footer .wrap { display: flex; flex-wrap: wrap; gap: 12px 28px; align-items: center; justify-content: space-between; padding-top: 28px; padding-bottom: 28px; }
        .footer nav { display: flex; gap: 24px; }
        .footer a { color: #fff; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }

        @media (max-width: 640px) {
            .wrap { padding: 0 16px; }
            .nav { height: 68px; }
            .nav img { height: 28px; }
            .hero { padding-top: 48px; padding-bottom: 64px; }
            .hero p { font-size: 16px; }
            .features { padding-top: 56px; padding-bottom: 56px; }
        }

        @media (prefers-reduced-motion: reduce) { .button { transition: none; } }
    </style>
</head>
<body>
    <header class="top">
        <div class="wrap nav">
            <a href="{{ url('/') }}" aria-label="GKR Klantportaal, naar de startpagina">
                <img src="{{ asset('images/' . rawurlencode('gkr logo wit.webp')) }}" alt="GKR Digital Agency" width="88" height="34">
            </a>

            @auth
                <a href="{{ route('dashboard') }}" class="button button-light button-small">Naar je dashboard</a>
            @else
                <a href="{{ route('login') }}" class="button button-light button-small">Inloggen</a>
            @endauth
        </div>

        <section class="wrap hero">
            <h1>Je project bij GKR, overzichtelijk op één plek.</h1>
            <p>In het klantportaal volg je de voortgang van je projecten, keur je documenten goed, plan je afspraken met het team en zie je hoe je advertenties presteren.</p>

            @auth
                <a href="{{ route('dashboard') }}" class="button button-light">Naar je dashboard</a>
            @else
                <a href="{{ route('login') }}" class="button button-light">Inloggen</a>
                <p class="note">Nog geen account? GKR maakt je account aan en stuurt je een uitnodiging. Vraag ernaar via <a href="mailto:info@gkr.nl">info@gkr.nl</a>.</p>
            @endauth
        </section>
    </header>

    <main class="wrap features">
        <h2>Wat je in het portaal doet</h2>

        <ul class="list">
            <li>
                <h3>Projecten volgen</h3>
                <p>Zie per project hoe ver het team is en wanneer de deadline is.</p>
            </li>
            <li>
                <h3>Documenten goedkeuren</h3>
                <p>Bekijk ontwerpen en offertes, laat een reactie achter of geef direct digitaal akkoord.</p>
            </li>
            <li>
                <h3>Afspraken plannen</h3>
                <p>Vraag een gesprek aan op een moment dat jou past, online, telefonisch of op kantoor.</p>
            </li>
            <li>
                <h3>Chatten met het team</h3>
                <p>Stel je vraag en deel bestanden, zonder dat het tussen je andere mail verdwijnt.</p>
            </li>
            <li>
                <h3>Advertentiecijfers bekijken</h3>
                <p>Klikken, kosten en resultaten van je campagnes op Google en Meta, in gewone taal uitgelegd.</p>
            </li>
        </ul>
    </main>

    <footer class="footer">
        <div class="wrap">
            <span>&copy; {{ date('Y') }} GKR Digital Agency, Breda</span>
            <nav aria-label="Contact">
                <a href="https://gkr.nl/" target="_blank" rel="noopener">gkr.nl</a>
                <a href="mailto:info@gkr.nl">info@gkr.nl</a>
            </nav>
        </div>
    </footer>
</body>
</html>