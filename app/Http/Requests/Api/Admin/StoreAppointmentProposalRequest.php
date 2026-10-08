<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'client_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', false))],
            // Het project moet van de gekozen klant zijn.
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('user_id', (int) $this->input('client_id'))],
            'type' => ['required', Rule::in([Appointment::TYPE_TELEFOON, Appointment::TYPE_ONLINE, Appointment::TYPE_FYSIEK])],
            'location' => ['nullable', 'required_if:type,'.Appointment::TYPE_FYSIEK, Rule::in([Appointment::LOCATION_BIJ_GKR, Appointment::LOCATION_OP_LOCATIE])],
            'travel_minutes' => ['nullable', 'integer', Rule::in(config('appointments.allowed_travel_minutes'))],
            'duration_minutes' => ['required', 'integer', Rule::in(config('appointments.allowed_durations'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'employee_ids' => ['nullable', 'array', 'max:5'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', true))],
            'options' => ['required', 'array', 'min:1', 'max:3'],
            'options.*' => ['required', 'date', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'Kies een klant uit de lijst.',
            'project_id.exists' => 'Kies een project van deze klant.',
            'options.required' => 'Kies minstens één moment voor de klant.',
            'options.max' => 'Kies maximaal drie momenten.',
            'location.required_if' => 'Kies waar de afspraak plaatsvindt.',
        ];
    }
}
