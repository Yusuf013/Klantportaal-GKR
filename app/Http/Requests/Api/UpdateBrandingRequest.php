<?php

namespace App\Http\Requests\Api;

use App\Models\Branding;
use App\Support\ContrastRatio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validatie bij opslaan, niet bij renderen (skill `white-label-branding`): een admin kan geen
 * onleesbaar palet of een naam met markup opslaan. De iOS-app toont dezelfde regels live;
 * deze hier zijn de autoritatieve (ADR-010).
 */
class UpdateBrandingRequest extends FormRequest
{
    private const HEX = '/^#[0-9A-F]{6}$/';

    public function authorize(): bool
    {
        return $this->user()?->can('update', Branding::current()) ?? false;
    }

    /**
     * Eén opslagvorm: naam zonder omringende spaties, kleuren in hoofdletters.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('organization_name'))) {
            $normalized['organization_name'] = trim($this->input('organization_name'));
        }

        foreach (['primary_color', 'accent_color'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = strtoupper(trim($this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            // Whitelist in plaats van blacklist: letters, cijfers en gangbare leestekens van
            // bedrijfsnamen. Daarmee kan de naam nooit markup of een CSS-declaratie worden.
            'organization_name' => ['required', 'string', 'min:2', 'max:40', "regex:/^[\\p{L}\\p{N} .,&'\\-]+$/u"],
            'primary_color' => ['required', 'string', 'regex:'.self::HEX],
            'accent_color' => ['required', 'string', 'regex:'.self::HEX],
        ];
    }

    /** Gewone taal, zonder verhoudingen of normcodes; dezelfde teksten als de iOS-app. */
    public const PRIMARY_TOO_LIGHT = 'Witte tekst is slecht leesbaar op deze kleur. Kies een donkerdere primaire kleur.';

    public const ACCENT_FADES_ON_WHITE = 'Het accent valt bijna weg op een witte achtergrond. Kies een donkerdere accentkleur.';

    /**
     * Contrastregels (WCAG 2.2, ADR-010) over twee velden tegelijk. Afwijzen, niet stil
     * corrigeren: een automatisch aangepaste kleur is een huisstijl die niemand koos. De melding
     * zegt wat er misgaat en welke kant de beheerder op moet; de app biedt daarnaast een voorstel.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return; // ongeldige hex: geen dubbele contrastfout
                }

                $primary = $this->input('primary_color');
                $accent = $this->input('accent_color');

                if (ContrastRatio::between($primary, '#FFFFFF') < config('branding.contrast.text')) {
                    $validator->errors()->add('primary_color', self::PRIMARY_TOO_LIGHT);
                }

                // Het accent staat in de app alleen op witte vlakken, dus alleen daartegen toetsen.
                // Een regel "accent op primair" maakte bij bijna elke merkkleur het accent bijna
                // zwart; die komt pas terug als het accent ergens op de primaire kleur komt (ADR-010).
                if (ContrastRatio::between($accent, '#FFFFFF') < config('branding.contrast.graphic')) {
                    $validator->errors()->add('accent_color', self::ACCENT_FADES_ON_WHITE);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'organization_name.required' => 'Vul een organisatienaam in.',
            'organization_name.min' => 'De organisatienaam moet minimaal 2 tekens hebben.',
            'organization_name.max' => 'De organisatienaam mag maximaal 40 tekens hebben.',
            'organization_name.regex' => 'De organisatienaam mag alleen letters, cijfers, spaties en . , & \' - bevatten.',
            'primary_color.required' => 'Kies een primaire kleur.',
            'primary_color.regex' => 'Kies een geldige kleur, bijvoorbeeld #011936.',
            'accent_color.required' => 'Kies een accentkleur.',
            'accent_color.regex' => 'Kies een geldige kleur, bijvoorbeeld #059669.',
        ];
    }
}
