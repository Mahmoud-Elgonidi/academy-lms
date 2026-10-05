<?php

namespace Modules\Scheduling\Domain\Enums;

enum ParticipantRole: string
{
    case Teacher = 'teacher';
    case Student = 'student';
}
