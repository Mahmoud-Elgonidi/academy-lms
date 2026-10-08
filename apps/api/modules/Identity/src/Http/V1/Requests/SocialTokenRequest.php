<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SocialTokenRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['google', 'apple'])],
            /** The ID token the phone's Google or Apple sign-in gave the app. */
            'id_token' => ['required', 'string', 'max:8000'],
            /** The nonce the app started the sign-in with, if any (Apple's: the raw one, before hashing). */
            'nonce' => ['nullable', 'string', 'max:200'],
            'device_name' => [$this->routeIs('identity.social.token') ? 'required' : 'nullable', 'string', 'max:100'],
        ];
    }
}
