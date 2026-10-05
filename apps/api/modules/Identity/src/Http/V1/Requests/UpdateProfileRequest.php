<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
