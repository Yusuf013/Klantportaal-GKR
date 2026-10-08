<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het JavaScript van de afsprakenpagina's bouwt de kalender en de detailpopups in de browser op.
 * Een servertest ziet dat niet uitgevoerd worden; daarom controleert deze test de bron zelf.
 */
class AppointmentPageScriptTest extends TestCase
{
    use RefreshDatabase;

    private const VIEWS = [
        'resources/views/admin/appointments/index.blade.php',
        'resources/views/client/appointments/index.blade.php',
    ];

    public function test_afspraakgegevens_komen_nooit_via_innerhtml_in_de_pagina(): void
    {
        // Titels komen van de klant en namen uit de database: alleen via textContent of
        // createTextNode tonen. Een HTML-template-string met ${app.…} of ${att.…} wordt vroeg of laat
        // aan innerHTML gegeven (direct, of eerst verzameld in een variabele) en voert die tekst dan
        // als HTML uit (stored XSS). Daarom: geen enkele template met HTML-tags én afspraakgegevens.
        foreach (self::VIEWS as $view) {
            $source = file_get_contents(base_path($view));

            preg_match_all('/`([^`]*)`/s', $source, $templates);

            foreach ($templates[1] as $template) {
                if (! str_contains($template, '<')) {
                    continue; // geen HTML, bv. `Klik voor details: ${app.title}` als title-attribuut
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/\$\{\s*(app|att|emp|appointment)\b/',
                    $template,
                    "$view bouwt HTML met afspraakgegevens erin: ".trim(mb_substr($template, 0, 120))
                );
            }
        }
    }

    public function test_adminpagina_haalt_statusgroepen_uit_de_enum(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.appointments.index'))
            ->assertOk()
            ->assertSee('const STATUS_BLOCKING = '.json_encode(AppointmentStatus::blockingValues()), false)
            ->assertSee('const STATUS_TENTATIVE = '.json_encode(AppointmentStatus::tentativeValues()), false);
    }
}
