<?php

namespace App\Http\Requests\Api;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Klant vraagt een afspraak aan vanuit de app (design "Nieuwe afspraak").
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Alleen klanten; GKR-medewerkers doen een voorstel via /admin/appointments.
        return ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Appointment::TYPE_TELEFOON, Appointment::TYPE_ONLINE, Appointment::TYPE_FYSIEK])],
            'location' => ['nullable', 'required_if:type,'.Appointment::TYPE_FYSIEK, Rule::in([Appointment::LOCATION_BIJ_GKR, Appointment::LOCATION_OP_LOCATIE])],
            // Alleen een eigen project: een project van een ander faalt als "ongeldig".
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('user_id', $this->user()->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:2'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', true))],
            'start_time' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Kies minstens één GKR-medewerker.',
            'employee_ids.max' => 'Kies maximaal twee GKR-medewerkers.',
            'location.required_if' => 'Kies waar de afspraak plaatsvindt.',
            'project_id.exists' => 'Kies een van uw eigen projecten.',
        ];
    }
}
