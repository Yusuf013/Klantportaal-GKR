<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\AppointmentRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin stelt 1-3 momenten voor aan een klant (design "Plan een meeting").
 * Toegang tot deze route loopt via de `admin`-middleware.
 */
class StoreAppointmentProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'client_id' => AppointmentRules::client(),
            'project_id' => AppointmentRules::projectOf((int) $this->input('client_id')),
            'type' => AppointmentRules::type(),
            'location' => AppointmentRules::location(),
            'travel_minutes' => AppointmentRules::travelMinutes(),
            'duration_minutes' => AppointmentRules::durationMinutes(required: true),
            'title' => AppointmentRules::title(),
            'description' => AppointmentRules::description(),
            'employee_ids' => AppointmentRules::employees(0, 5),
            'employee_ids.*' => AppointmentRules::employee(),
            'options' => ['required', 'array', 'min:1', 'max:3'],
            'options.*' => ['required', 'date', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            ...AppointmentRules::messages('employee_ids', forAdmin: true),
            'options.required' => 'Kies minstens één moment voor de klant.',
            'options.max' => 'Kies maximaal drie momenten.',
        ];
    }
}
