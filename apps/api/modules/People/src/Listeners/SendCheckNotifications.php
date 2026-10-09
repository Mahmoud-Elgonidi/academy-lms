<?php

namespace Modules\People\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Checks\Events\TeacherCheckDecided;
use Modules\People\Checks\Events\TeacherCheckExpiring;
use Modules\People\Checks\Events\TeacherCheckSubmitted;
use Modules\People\Models\TeacherCheck;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Messages about teachers' documents (wording in lang/{ar,en}/notifications.php):
 * a new one to check for the academy's safeguarding leads, the answer for the
 * teacher, and a month before one runs out (and the day after) for both.
 */
final class SendCheckNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'people::notifications.audience.';
        foreach ([
            ['check_submitted', $a.'safeguarding', ['push', 'email'], ['teacher' => 'أحمد', 'kind' => 'الفيش الجنائي']],
            ['check_verified', $a.'teacher', ['push', 'email'], ['kind' => 'الفيش الجنائي', 'until' => '2027-04-30']],
            ['check_rejected', $a.'teacher', ['push', 'email'], ['kind' => 'الفيش الجنائي', 'reason' => 'الصورة مش واضحة']],
            ['check_expiring', $a.'teacher_and_safeguarding', ['push', 'email'], ['teacher' => 'أحمد', 'kind' => 'الفيش الجنائي', 'until' => '2026-11-08']],
            ['check_expired', $a.'teacher_and_safeguarding', ['push', 'email'], ['teacher' => 'أحمد', 'kind' => 'الفيش الجنائي', 'until' => '2026-10-08']],
        ] as [$name, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "people.{$name}", category: 'staff',
                title: "people::notifications.{$name}.title", body: "people::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function submitted(TeacherCheckSubmitted $event): void
    {
        $check = TeacherCheck::query()->find($event->checkId);
        if ($check === null || $check->status !== 'pending') {
            return;
        }
        $this->notifications->send(new Message('people.check_submitted', $this->leads([$check->uploaded_by]), [
            'teacher' => $this->name($check->teacher_id), 'kind' => new Translatable('people::checks.kinds.'.$check->kind->value),
        ], ['screen' => 'teacher_checks', 'id' => $check->id], "check-submitted:{$check->id}"));
    }

    public function decided(TeacherCheckDecided $event): void
    {
        $check = TeacherCheck::query()->find($event->checkId);
        if ($check === null) {
            return;
        }
        $kind = new Translatable('people::checks.kinds.'.$check->kind->value);
        $this->notifications->send($event->verified
            ? new Message('people.check_verified', [$check->teacher_id], [
                'kind' => $kind, 'until' => $check->expires_on ? new Day($check->expires_on->toDateString()) : new Translatable('people::checks.no_end'),
            ], ['screen' => 'my_checks', 'id' => $check->id], "check-verified:{$check->id}")
            : new Message('people.check_rejected', [$check->teacher_id], [
                'kind' => $kind, 'reason' => (string) $check->decision_note,
            ], ['screen' => 'my_checks', 'id' => $check->id], "check-rejected:{$check->id}"));
    }

    public function expiring(TeacherCheckExpiring $event): void
    {
        $check = TeacherCheck::query()->find($event->checkId);
        if ($check === null || $check->expires_on === null) {
            return;
        }
        $key = $event->expired ? 'check_expired' : 'check_expiring';
        $data = ['teacher' => $this->name($check->teacher_id), 'kind' => new Translatable('people::checks.kinds.'.$check->kind->value), 'until' => new Day($check->expires_on->toDateString())];
        $this->notifications->send(new Message("people.{$key}", [$check->teacher_id], $data, ['screen' => 'my_checks', 'id' => $check->id], "{$key}:{$check->id}:teacher"));
        $this->notifications->send(new Message("people.{$key}", $this->leads([$check->teacher_id]), $data, ['screen' => 'teacher_checks', 'id' => $check->id], "{$key}:{$check->id}:leads"));
    }

    /**
     * The academy's safeguarding leads, but not whoever this is about.
     *
     * @param  list<string>  $except
     * @return list<string>
     */
    private function leads(array $except): array
    {
        return array_values(array_diff($this->identity->usersWithAbility(Ability::HandleSafeguarding), $except));
    }

    private function name(string $userId): string
    {
        return (string) DB::table('users')->where('id', $userId)->value('name');
    }
}
