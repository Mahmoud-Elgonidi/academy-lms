<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DiscoveredSignInRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** From POST /accounts/code/verify. */
            'discovery_token' => ['required', 'string', 'max:100'],
            /** One of the accounts listed there, in the academy named by X-Academy. */
            'user_id' => ['required', 'string', 'max:64'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
