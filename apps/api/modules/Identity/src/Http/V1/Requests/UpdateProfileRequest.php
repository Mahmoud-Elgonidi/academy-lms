<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\SharedKernel\Time\TimeFormat;

final class UpdateProfileRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            // IANA names only ("Asia/Dubai"); offsets like "+04:00" break on summer time.
            'timezone' => ['sometimes', 'timezone:all'],
            // How the person reads a clock: h12 (5:00 PM) or h24 (17:00); null follows the academy's choice.
            'time_format' => ['sometimes', 'nullable', Rule::in(TimeFormat::values())],
            // Whether the floating time-zone chip shows (false: hidden; it comes back from the account page).
            'zone_chip' => ['sometimes', 'boolean'],
        ];
    }
}
