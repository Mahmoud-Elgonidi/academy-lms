<?php

namespace Modules\Scheduling\Domain\Policy\Conditions;

use Modules\Scheduling\Domain\Policy\PolicyInput;
use Modules\Scheduling\Domain\Policy\PolicySettings;

/** At least the setting's hours before the start ("student_notice_hours" or "teacher_notice_hours"). */
final readonly class NoticeAtLeast implements Condition
{
    public function __construct(private string $setting) {}

    public function isSatisfiedBy(PolicyInput $input, PolicySettings $settings): bool
    {
        return $input->noticeHours >= $settings->get($this->setting);
    }

    public function describe(PolicySettings $settings): array
    {
        return ['notice_at_least', ['hours' => $settings->get($this->setting)]];
    }
}
