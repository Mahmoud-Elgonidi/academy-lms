<?php

namespace Modules\Scheduling\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;

final class TimeOffRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** On the teacher's own clock. */
            'first_day' => ['required', 'date_format:Y-m-d'],
            'last_day' => ['nullable', 'date_format:Y-m-d'],
            /** Part of one day: both times, "HH:MM", and last_day left out. */
            'from' => ['nullable', 'date_format:H:i', 'required_with:to'],
            'to' => ['nullable', 'date_format:H:i', 'required_with:from'],
            'reason' => ['required', 'string', 'in:'.implode(',', TeacherTimeOff::REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
