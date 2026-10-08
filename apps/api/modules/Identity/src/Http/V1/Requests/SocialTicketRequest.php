<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SocialTicketRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** What came back on the portal's address from the provider's callback. */
            'ticket' => ['required', 'string', 'max:100'],
            /** The secret whose hash started it. */
            'bind' => ['required', 'string', 'max:100'],
            'device_name' => [$this->routeIs('identity.social.ticket') ? 'required' : 'nullable', 'string', 'max:100'],
        ];
    }
}
