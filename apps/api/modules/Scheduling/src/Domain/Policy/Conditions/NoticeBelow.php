<?php

namespace Modules\Scheduling\Domain\Policy\Conditions;

use Modules\Scheduling\Domain\Policy\PolicyInput;
use Modules\Scheduling\Domain\Policy\PolicySettings;

final readonly class NoticeBelow implements Condition
{
    public function __construct(private string $setting) {}

    public function isSatisfiedBy(PolicyInput $input, PolicySettings $settings): bool
    {
        return $input->noticeHours < $settings->get($this->setting);
    }

    public function describe(PolicySettings $settings): array
    {
        return ['notice_below', ['hours' => $settings->get($this->setting)]];
    }
}
