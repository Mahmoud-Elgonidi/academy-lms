<?php

namespace Modules\People\Contracts;

/** The documents a teacher's vetting rests on; a criminal record check is what teaching minors needs. */
enum CheckKind: string
{
    case CriminalRecord = 'criminal_record';
    case Identity = 'identity';
    case Qualification = 'qualification';
    case Other = 'other';

    public function label(): string
    {
        return __('people::checks.kinds.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $kind) {
            $options[$kind->value] = $kind->label();
        }

        return $options;
    }
}
