<?php

namespace Modules\Scheduling\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveShiftProposalRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'blocks' => ['present', 'array', 'max:50'],
            'blocks.*.weekday' => ['required', 'integer', 'between:1,7'],
            'blocks.*.start' => ['required', 'date_format:H:i'],
            'blocks.*.end' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:2000'],
            'min_weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'max_weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }
}
