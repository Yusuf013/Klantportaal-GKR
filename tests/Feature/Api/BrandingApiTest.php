<?php

namespace Tests\Feature\Api;

use App\Http\Requests\Api\UpdateBrandingRequest;
use App\Models\Branding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * White-label branding API (FR-02 / NFR-03, ADR-010). Dekt de testeisen van de skill
 * `white-label-branding`: alleen defaults rendert correct, een niet-default configuratie komt
 * terug, een slecht palet wordt geweigerd, en een wijziging werkt zonder deploy.
 */
class BrandingApiTest extends TestCase
{
    use RefreshDatabase;

    // --- Lezen (publiek) ---------------------------------------------------------------------

    public function test_without_configuration_the_defaults_are_returned(): void
    {
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertExactJson([
                'organization_name' => 'Klantportaal',
                'primary_color' => '#011936',
                'accent_color' => '#059669',
                'logo_path' => null,
                'updated_at' => null,
            ]);

        $this->assertSame(0, Branding::count(), 'Lezen mag geen record aanmaken.');
    }

    public function test_configured_branding_replaces_the_defaults(): void
    {
        Branding::factory()->create();

        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJson([
                'organization_name' => 'Acme Bouw',
                'primary_color' => '#431407',
                'accent_color' => '#EA580C',
            ]);
    }

    public function test_branding_is_readable_without_a_token(): void
    {
        // Bewust publiek: het inlogscherm heeft de huisstijl nodig vóór authenticatie.
        $this->getJson('/api/branding')->assertOk();
    }

    public function test_response_does_not_leak_internal_fields(): void
    {
        Branding::factory()->create();

        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('klant_id')
            ->assertJsonMissingPath('created_at')
            ->assertJsonMissingPath('data');
    }

    // --- Wijzigen (admin) --------------------------------------------------------------------

    private const VALID = [
        'organization_name' => 'Acme Bouw',
        'primary_color' => '#431407',
        'accent_color' => '#EA580C',
    ];

    public function test_admin_can_update_branding_and_it_takes_effect_without_deploy(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', self::VALID)
            ->assertOk()
            ->assertJson(self::VALID);

        // Een verse, publieke read ziet de wijziging direct.
        $this->getJson('/api/branding')->assertJson(self::VALID);
        $this->assertSame(1, Branding::count());
    }

    public function test_second_update_changes_the_same_record(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', self::VALID)->assertOk();
        $this->putJson('/api/branding', [...self::VALID, 'organization_name' => 'Acme Wonen'])->assertOk();

        $this->assertSame(1, Branding::count());
        $this->assertSame('Acme Wonen', Branding::current()->organization_name);
    }

    public function test_colours_are_normalized_to_uppercase_and_name_is_trimmed(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [
            'organization_name' => '  Acme Bouw  ',
            'primary_color' => '#431407',
            'accent_color' => '#ea580c',
        ])->assertOk()->assertJson(['organization_name' => 'Acme Bouw', 'accent_color' => '#EA580C']);
    }

    public function test_deliberately_bad_palette_is_rejected_and_nothing_is_saved(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [
            'organization_name' => 'Acme Bouw',
            'primary_color' => '#FFFFFF',
            'accent_color' => '#FFFF00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color', 'accent_color']);

        $this->assertSame(0, Branding::count());
    }

    public function test_contrast_errors_are_plain_language_and_say_which_way_to_go(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        // #767676 op wit haalt 4,5:1 net; #777777 net niet.
        $response = $this->putJson('/api/branding', [...self::VALID, 'primary_color' => '#777777'])
            ->assertUnprocessable();

        $message = $response->json('errors.primary_color.0');
        $this->assertSame(UpdateBrandingRequest::PRIMARY_TOO_LIGHT, $message);
        $this->assertStringNotContainsString(':1', $message, 'Geen verhoudingen voor beheerders');
        $this->assertStringNotContainsString('WCAG', $message, 'Geen normcodes voor beheerders');

        $this->putJson('/api/branding', [...self::VALID, 'primary_color' => '#767676', 'accent_color' => '#059669'])
            ->assertJsonMissingValidationErrors(['primary_color']);
    }

    public function test_accent_is_only_checked_against_white(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        // #EA580C naast middenbruin wordt geaccepteerd: het accent staat nooit op de primaire kleur.
        $this->putJson('/api/branding', [...self::VALID, 'primary_color' => '#7C2D12'])->assertOk();

        $this->putJson('/api/branding', [...self::VALID, 'accent_color' => '#FDE68A'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.accent_color.0', UpdateBrandingRequest::ACCENT_FADES_ON_WHITE);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidInput(): array
    {
        return [
            'naam met markup' => ['organization_name', '<script>alert(1)</script>'],
            'naam met css' => ['organization_name', 'Acme; color: red'],
            'naam te lang' => ['organization_name', str_repeat('a', 41)],
            'naam te kort' => ['organization_name', 'A'],
            'naam leeg' => ['organization_name', '   '],
            'drie-cijferige hex' => ['primary_color', '#abc'],
            'rgb-notatie' => ['primary_color', 'rgb(1,2,3)'],
            'kleurnaam' => ['accent_color', 'red'],
            'css-injectie' => ['primary_color', '#011936; background:url(x)'],
            'geen string' => ['accent_color', ['#059669']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(string $field, mixed $value): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [...self::VALID, $field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, Branding::count());
    }

    public function test_missing_field_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', ['organization_name' => 'Acme Bouw'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color', 'accent_color']);
    }

    public function test_klant_id_in_the_body_is_ignored(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/branding', [...self::VALID, 'klant_id' => 99, 'logo_path' => '../.env'])
            ->assertOk();

        $this->assertNull(Branding::current()->klant_id);
        $this->assertNull(Branding::current()->logo_path);
    }

    // --- Autorisatie ---------------------------------------------------------------------------

    public function test_update_without_token_is_unauthorized(): void
    {
        $this->putJson('/api/branding', self::VALID)->assertUnauthorized();

        $this->assertSame(0, Branding::count());
    }

    public function test_non_admin_cannot_update_branding(): void
    {
        $existing = Branding::factory()->create(['organization_name' => 'Origineel']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/branding', self::VALID)
            ->assertStatus(403); // expliciet 403, geen 302-redirect naar HTML

        $this->assertSame('Origineel', $existing->fresh()->organization_name);
    }

    // --- Logo ----------------------------------------------------------------------------------

    public function test_admin_can_upload_a_logo_to_a_server_chosen_path(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->post('/api/branding/logo', [
            'logo' => UploadedFile::fake()->image('mijn logo.png', 512, 512),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $response->json('logo_path');
        $this->assertMatchesRegularExpression('#^/storage/branding/default/[A-Za-z0-9]{40}\.png$#', $path);
        Storage::disk('public')->assertExists(Branding::current()->logo_path);
        $this->assertStringNotContainsString('mijn logo', $path, 'Nooit de bestandsnaam van de client.');

        // Naam en kleuren blijven op de defaults: een logo-upload raakt alleen het logo.
        $response->assertJson(['organization_name' => 'Klantportaal', 'primary_color' => '#011936']);
    }

    public function test_replacing_the_logo_changes_its_url_and_removes_the_old_file(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->post('/api/branding/logo', ['logo' => UploadedFile::fake()->image('a.png', 256, 256)], ['Accept' => 'application/json'])->assertOk();
        $first = Branding::current()->logo_path;

        $this->post('/api/branding/logo', ['logo' => UploadedFile::fake()->image('b.jpg', 256, 256)], ['Accept' => 'application/json'])->assertOk();
        $second = Branding::current()->logo_path;

        $this->assertNotSame($first, $second, 'Een nieuwe URL laat client-caches vanzelf invalideren.');
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->getJson('/api/branding')->assertJsonPath('logo_path', '/storage/'.$second);
    }

    public function test_admin_can_remove_the_logo(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->post('/api/branding/logo', ['logo' => UploadedFile::fake()->image('a.png', 256, 256)], ['Accept' => 'application/json'])->assertOk();
        $path = Branding::current()->logo_path;

        $this->deleteJson('/api/branding/logo')->assertOk()->assertJsonPath('logo_path', null);

        Storage::disk('public')->assertMissing($path);
    }

    /**
     * @return array<string, array{0: \Closure(): UploadedFile}>
     */
    public static function invalidLogos(): array
    {
        return [
            'svg' => [fn () => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml')],
            'svg met script vermomd als png' => [fn () => UploadedFile::fake()->createWithContent(
                'logo.png',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            )],
            'tekstbestand als png' => [fn () => UploadedFile::fake()->create('logo.png', 10, 'text/plain')],
            'pdf' => [fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')],
            'te groot in pixels' => [fn () => UploadedFile::fake()->image('logo.png', 2400, 400)],
            'te klein in pixels' => [fn () => UploadedFile::fake()->image('logo.png', 32, 32)],
            'te groot in bytes' => [fn () => UploadedFile::fake()->image('logo.png', 512, 512)->size(3000)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLogos')]
    public function test_invalid_logo_is_rejected_and_nothing_is_stored(\Closure $makeFile): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->post('/api/branding/logo', ['logo' => $makeFile()], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, Branding::count());
    }

    public function test_non_admin_cannot_upload_or_remove_a_logo(): void
    {
        Storage::fake('public');
        $branding = Branding::factory()->create();
        $branding->logo_path = UploadedFile::fake()->image('a.png', 256, 256)->store('branding/default', 'public');
        $branding->save();

        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/branding/logo', ['logo' => UploadedFile::fake()->image('b.png', 256, 256)], ['Accept' => 'application/json'])
            ->assertStatus(403);
        $this->deleteJson('/api/branding/logo')->assertStatus(403);

        $this->assertCount(1, Storage::disk('public')->allFiles());
        Storage::disk('public')->assertExists($branding->fresh()->logo_path);
    }

    public function test_logo_endpoints_require_a_token(): void
    {
        $this->postJson('/api/branding/logo')->assertUnauthorized();
        $this->deleteJson('/api/branding/logo')->assertUnauthorized();
    }
}
