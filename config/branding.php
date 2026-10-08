<?php

/*
|--------------------------------------------------------------------------
| White-label branding (FR-02 / NFR-03, ADR-010)
|--------------------------------------------------------------------------
|
| Defaults voor elk brandingveld. Een installatie zonder brandingrecord (of met lege velden)
| gebruikt deze waarden en toont bewust géén merknaam: de organisatie die het portaal draait
| stelt haar eigen naam, kleuren en logo in via de app (Profiel → Huisstijl), niet in code.
|
| De defaults halen de contrastregels hieronder: #011936 op wit ≈ 17,6:1 en #059669 op
| wit ≈ 3,8:1.
|
*/

return [

    'defaults' => [
        'organization_name' => 'Klantportaal',
        'primary_color' => '#011936',
        'accent_color' => '#059669',
    ],

    // Disk waarop logo's worden opgeslagen. Het API-antwoord bevat een root-relatief pad
    // (bv. /storage/branding/default/<hash>.png), zie Branding::logoPublicPath().
    'disk' => 'public',

    'logo' => [
        'mimes' => ['png', 'jpg', 'jpeg', 'webp'],
        'mimetypes' => ['image/png', 'image/jpeg', 'image/webp'],
        'max_kilobytes' => 2048,
        'min_dimension' => 64,
        'max_dimension' => 2000,
    ],

    // WCAG 2.2: 4,5:1 voor tekst (1.4.3), 3:1 voor grafische elementen (1.4.11).
    // primary draagt witte tekst en is zelf tekstkleur op wit → text.
    // accent is uitsluitend grafisch (stipjes, accenten) op witte vlakken → graphic, tegen wit.
    'contrast' => [
        'text' => 4.5,
        'graphic' => 3.0,
    ],

];
