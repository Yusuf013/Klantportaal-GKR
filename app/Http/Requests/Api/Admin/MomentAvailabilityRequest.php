<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\AppointmentRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Beschikbaarheid van medewerkers op de voorgestelde momenten (admin-formulier).
 * Een moment is een datum ("2026-10-12") of een begin- en eindtijd.
 */
class MomentAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'employee_ids' => AppointmentRules::employees(1, 10),
            'employee_ids.*' => AppointmentRules::employee(),
            'moments' => ['required', 'array', 'min:1', 'max:3'],
            'moments.*.start' => ['required', 'date'],
            'moments.*.end' => ['nullable', 'date', 'after:moments.*.start'],
            'duration_minutes' => AppointmentRules::durationMinutes(),
            'travel_minutes' => AppointmentRules::travelMinutes(),
            'ignore_appointment_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return AppointmentRules::messages('employee_ids', forAdmin: true);
    }
}
