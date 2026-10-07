<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyCodeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:12'],
            'device_name' => [$this->routeIs('identity.accounts.verify') ? 'nullable' : 'required', 'string', 'max:100'],
        ];
    }
}
