<?php

/**
 * Planningsregels voor afspraken (FR-08, ADR-011).
 *
 * Eén plek voor werktijden en keuzelijsten, zodat website, API en app dezelfde blokken tonen.
 * Voorheen stonden de tijdsloten hardcoded in beide Blade-views en in de iOS-app.
 */
return [

    // Werkdagen volgens ISO-8601 (1 = maandag ... 7 = zondag).
    'working_days' => [1, 2, 3, 4, 5],

    // Een afspraak valt volledig binnen deze tijden, inclusief reistijd.
    'day_start' => '09:00',
    'day_end' => '17:00',

    // Afstand tussen de begintijden die klanten en admins kunnen kiezen.
    'slot_minutes' => 60,

    // Een klant kiest geen duur: "Afspraken worden altijd ~1 uur geschat" (design).
    'client_duration_minutes' => 60,

    // Keuzes in het admin-formulier (design: "Tijdsduur" en "Reistijd").
    'allowed_durations' => [30, 60, 90, 120],
    'allowed_travel_minutes' => [0, 15, 30, 45, 60, 90, 120],

    // Hoe ver vooruit beschikbaarheid in één verzoek mag worden opgevraagd. Microsoft Graph
    // accepteert voor getSchedule maximaal 62 dagen.
    'max_range_days' => 62,

    // Hoe lang een beschikbaarheidsopvraag uit Outlook hergebruikt mag worden tijdens het
    // plannen. Bij het definitief vastleggen wordt altijd vers gecontroleerd (stap 4b).
    'availability_cache_seconds' => 300,

    // Hoe lang een verzoek wacht op een collega die op hetzelfde moment dezelfde medewerker
    // inplant, voordat het opgeeft met een "probeer het zo opnieuw"-melding.
    'lock_wait_seconds' => 5,

    'locations' => [
        'bij_gkr' => env('GKR_OFFICE_ADDRESS', 'Bij GKR'),
        'op_locatie' => 'Op locatie bij de klant',
    ],

    // "Bel direct" in het design. Zonder nummer toont de app alleen het belverzoek.
    'contact' => [
        'phone' => env('GKR_CONTACT_PHONE'),
    ],

    // Bevestigingsmail na goedkeuring (bestaande Resend-mail). Uit tenzij expliciet aangezet;
    // `only_to` beperkt verzending tot één adres, voor een Resend-sandbox. Dit vervangt het
    // hardcoded adres dat eerder in Admin\AppointmentController stond.
    'confirmation_mail' => [
        'enabled' => (bool) env('APPOINTMENT_MAIL_ENABLED', false),
        'only_to' => env('APPOINTMENT_MAIL_ONLY_TO'),
    ],
];
