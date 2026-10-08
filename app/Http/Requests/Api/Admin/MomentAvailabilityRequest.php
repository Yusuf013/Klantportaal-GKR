<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'employee_ids' => ['required', 'array', 'min:1', 'max:10'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', true))],
            'moments' => ['required', 'array', 'min:1', 'max:3'],
            'moments.*.start' => ['required', 'date'],
            'moments.*.end' => ['nullable', 'date', 'after:moments.*.start'],
            'duration_minutes' => ['nullable', 'integer', Rule::in(config('appointments.allowed_durations'))],
            'travel_minutes' => ['nullable', 'integer', Rule::in(config('appointments.allowed_travel_minutes'))],
            'ignore_appointment_id' => ['nullable', 'integer'],
        ];
    }
}
