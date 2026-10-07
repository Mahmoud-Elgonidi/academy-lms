<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendCodeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** An email or a mobile number (in an academy also a local number; for discovery with its country: "+966…"). */
            'contact' => ['required', 'string', 'max:255'],
            /** Leave out for the default (WhatsApp for a phone); "sms" to resend by text. */
            'channel' => ['nullable', 'string', 'in:whatsapp,sms,email'],
        ];
    }
}
