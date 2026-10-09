<?php

namespace Modules\Safeguarding\Enums;

/** How a lead closed a concern (for the academy's record; the reporter reads only the reply the lead wrote). */
enum ConcernOutcome: string
{
    case NoConcern = 'no_concern';         // looked into: nothing to act on
    case Resolved = 'resolved';            // dealt with inside the academy (talked to the family or teacher, changed teacher)
    case TeacherAction = 'teacher_action'; // a teacher was paused, warned or let go
    case Referred = 'referred';            // passed to the authorities or an outside service
    case Duplicate = 'duplicate';          // the same as another concern

    public function label(): string
    {
        return __('safeguarding::concerns.outcomes.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $outcome) {
            $options[$outcome->value] = $outcome->label();
        }

        return $options;
    }
}
