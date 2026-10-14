<?php

namespace Modules\SharedKernel\Setup;

/** The parts of the setup checklist, in the order they show. */
enum SetupGroup: string
{
    /** The academy itself: its look, its rules, its legal details, its team. */
    case Academy = 'academy';

    /** What it teaches and who teaches it, and when. */
    case Teaching = 'teaching';

    /** Its prices and how families pay. */
    case Money = 'money';

    public function label(): string
    {
        return __('shared::setup.groups.'.$this->value);
    }

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
