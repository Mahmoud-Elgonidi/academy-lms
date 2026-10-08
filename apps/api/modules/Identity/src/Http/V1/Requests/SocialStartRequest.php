<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SocialStartRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** SHA-256 (hex) of a secret only the browser that starts holds; the ticket coming back needs the secret itself. */
            'bind' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            /** The portal's language, for the way back. */
            'locale' => ['nullable', 'string', 'in:ar,en'],
        ];
    }
}
