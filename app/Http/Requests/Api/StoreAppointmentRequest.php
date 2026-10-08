<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\AppointmentRules;
use Illuminate\Foundation\Http\FormRequest;

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
            'type' => AppointmentRules::type(),
            'location' => AppointmentRules::location(),
            'project_id' => AppointmentRules::projectOf($this->user()->id),
            'title' => AppointmentRules::title(),
            'description' => AppointmentRules::description(),
            'employee_ids' => AppointmentRules::employees(1, 2),
            'employee_ids.*' => AppointmentRules::employee(),
            'start_time' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return AppointmentRules::messages('employee_ids');
    }
}
