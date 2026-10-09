<?php

namespace Modules\Safeguarding\Enums;

/**
 * What a concern is about. A family and a teacher choose from their own
 * short lists (ConcernRules::categoriesFor); a child in the kids' look sees
 * the same few in a child's words.
 */
enum ConcernCategory: string
{
    case AdultBehaviour = 'adult_behaviour';   // a teacher or another adult said or did something that is not OK
    case OutsideContact = 'outside_contact';   // asked to talk off the platform, for personal details or photos
    case Content = 'content';                  // something unsuitable was shown or shared
    case Bullying = 'bullying';                // someone bullied or upset them
    case Wellbeing = 'wellbeing';              // a child is not OK or not safe (at home or elsewhere)
    case Other = 'other';

    public function label(string $look = 'classic'): string
    {
        $key = "safeguarding::concerns.categories.{$look}.{$this->value}";

        return trans()->has($key) ? __($key) : __("safeguarding::concerns.categories.classic.{$this->value}");
    }
}
