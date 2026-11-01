<?php

namespace Modules\Communication\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Money\Money;

/**
 * A message by template to someone who has no account here yet (a visitor
 * who asked for a free trial on the academy's page): their name, number and
 * email as they gave them, in their language and on their clock. It goes by
 * the template's WhatsApp and email only, never to an inbox or a phone's
 * push, and nothing of it is kept.
 */
final readonly class ContactMessage
{
    /**
     * @param  ?string  $phone  E.164
     * @param  string  $locale  ar | en
     * @param  string  $timezone  IANA
     * @param  array<string, string|int|float|CarbonImmutable|Day|WeekdayTime|Money|Localized|Translatable|null>  $data
     */
    public function __construct(
        public string $template,
        public string $name,
        public ?string $phone,
        public ?string $email,
        public string $locale,
        public string $timezone,
        public array $data = [],
    ) {}
}
