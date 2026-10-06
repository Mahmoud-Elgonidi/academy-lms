<?php

namespace Modules\Communication\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Money\Money;

/**
 * A message to send by template. Values in $data fill the template's
 * placeholders; an instant is written in each recipient's time zone and
 * language; a Day, a Money amount, a Localized name and a Translatable in
 * their language.
 */
final readonly class Message
{
    /**
     * @param  list<string>  $recipients  user ids; repeats and inactive users are dropped
     * @param  array<string, string|int|float|CarbonImmutable|Day|Money|Localized|Translatable|null>  $data
     * @param  array<string, string>  $action  where the apps open it: ['screen' => 'report', 'id' => '…']
     * @param  ?string  $dedupeKey  sent at most once per recipient with this key ("reminder-1h:{session}")
     * @param  bool  $urgent  goes out even in the person's quiet hours ("join now")
     */
    public function __construct(
        public string $template,
        public array $recipients,
        public array $data = [],
        public array $action = [],
        public ?string $dedupeKey = null,
        public bool $urgent = false,
    ) {}
}
