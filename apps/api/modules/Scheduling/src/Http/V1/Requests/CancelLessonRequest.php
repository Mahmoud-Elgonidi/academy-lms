<?php

namespace Modules\Scheduling\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Scheduling\Actions\CancelSession;

final class CancelLessonRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** One of the reasons GET /lessons/{id}/cancellation lists. */
            'reason' => ['required', 'string', 'in:'.implode(',', CancelSession::REASONS['student'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
