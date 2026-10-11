<?php

namespace Modules\Scheduling\Domain;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Someone else changed the lesson between reading it and changing it (two
 * people, or a person and the sweeper, deciding the same lesson at once).
 * A validation error, so a screen says why and the API answers 422; the
 * sweeper leaves the lesson to whoever got there first.
 */
final class LessonChanged extends ValidationException
{
    public function __construct(public readonly string $sessionId)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('session', __('scheduling::board.errors.changed'));
        parent::__construct($validator);
    }
}
