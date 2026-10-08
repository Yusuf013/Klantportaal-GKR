<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Validation\Rule;

/**
 * Validatieregels voor afspraken die website en API delen (FR-08, ADR-011). Eén plek, zodat een
 * klant of admin via de website dezelfde regels en dezelfde meldingen krijgt als via de app.
 *
 * Alleen de vorm van de invoer verschilt per kanaal (website: `date` + `time_slot` en
 * `employees`; API: `start_time` en `employee_ids`). Die kanaalspecifieke velden blijven in de
 * controller of FormRequest zelf. De afsprakenservice controleert daarna opnieuw (klant van het
 * project, alleen medewerkers, werktijden), maar zonder deze regels zou de website een andere
 * melding geven dan de app.
 */
final class AppointmentRules
{
    public static function type(): array
    {
        return ['required', Rule::in([Appointment::TYPE_TELEFOON, Appointment::TYPE_ONLINE, Appointment::TYPE_FYSIEK])];
    }

    public static function location(): array
    {
        return ['nullable', 'required_if:type,'.Appointment::TYPE_FYSIEK, Rule::in([Appointment::LOCATION_BIJ_GKR, Appointment::LOCATION_OP_LOCATIE])];
    }

    public static function title(): array
    {
        return ['required', 'string', 'max:255'];
    }

    public static function description(): array
    {
        return ['nullable', 'string', 'max:500'];
    }

    /** Een klant (geen medewerker). */
    public static function client(): array
    {
        return ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', false))];
    }

    /** Een project van deze klant; een project van een ander faalt als "ongeldig". */
    public static function projectOf(int $clientId): array
    {
        return ['required', 'integer', Rule::exists('projects', 'id')->where('user_id', $clientId)];
    }

    /** De lijst medewerkers; `$min = 0` maakt hem optioneel. */
    public static function employees(int $min, int $max): array
    {
        return $min > 0
            ? ['required', 'array', "min:{$min}", "max:{$max}"]
            : ['nullable', 'array', "max:{$max}"];
    }

    /** Eén medewerker uit die lijst: alleen GKR-medewerkers, elk maar één keer. */
    public static function employee(): array
    {
        return ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', true))];
    }

    public static function durationMinutes(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'integer', Rule::in(config('appointments.allowed_durations'))];
    }

    public static function travelMinutes(): array
    {
        return ['nullable', 'integer', Rule::in(config('appointments.allowed_travel_minutes'))];
    }

    /**
     * Meldingen in gewone taal, gelijk voor website en API.
     *
     * @param  string  $employees  veldnaam van de medewerkerslijst (`employees` of `employee_ids`)
     * @param  bool  $forAdmin  een admin kiest een project "van deze klant", een klant "van zichzelf"
     */
    public static function messages(string $employees = 'employee_ids', bool $forAdmin = false): array
    {
        return [
            'client_id.exists' => 'Kies een klant uit de lijst.',
            'project_id.exists' => $forAdmin ? 'Kies een project van deze klant.' : 'Kies een van uw eigen projecten.',
            'location.required_if' => 'Kies waar de afspraak plaatsvindt.',
            "{$employees}.required" => 'Kies minstens één GKR-medewerker.',
            "{$employees}.min" => 'Kies minstens één GKR-medewerker.',
            "{$employees}.max" => 'Kies maximaal :max GKR-medewerkers.',
            "{$employees}.*.exists" => 'Kies GKR-medewerkers uit de lijst.',
            "{$employees}.*.distinct" => 'Kies elke medewerker maar één keer.',
        ];
    }
}
