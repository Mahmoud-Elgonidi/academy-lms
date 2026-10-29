<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Disputes\DecideDispute;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Domain\Disputes\DisputeRules;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Models\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Disputes in the demo (docs/architecture/01, section 8), on lessons that
 * ended in the last two days: a family says a held lesson ended early and
 * waits for the academy; a teacher recorded absent was there after all
 * (upheld: the makeup credit goes back, the pay comes); a family says the
 * teacher never came, and the room's log says otherwise (rejected).
 */
final class DemoDisputesSeeder extends Seeder
{
    public function run(DisputeLesson $dispute, DecideDispute $decide, PeopleApi $people, FamilyView $family): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');
        if ($owner === null) {
            return;
        }
        $used = [];
        // A recent lesson with this outcome (open to dispute), and whoever in its family may dispute it.
        $pick = function (string $status) use (&$used, $people, $family): ?array {
            $lessons = Session::query()->with('participants')->where('status', $status)
                ->where('ends_at', '>', CarbonImmutable::now()->subHours(DisputeRules::HOURS - 2))->where('ends_at', '<', CarbonImmutable::now())
                ->whereNotIn('id', $used)->orderByDesc('ends_at')->get();
            foreach ($lessons as $lesson) {
                $student = $lesson->participants->firstWhere('role', ParticipantRole::Student)?->user_id;
                if ($student === null) {
                    continue;
                }
                $who = $people->guardiansOf($student)[0] ?? $student;
                if ($family->stage($who) === LearnerStage::Kids) {
                    continue;
                }
                $used[] = $lesson->id;

                return [$lesson, $who];
            }

            return null;
        };
        $try = function (callable $step): void {
            try {
                $step();
            } catch (ValidationException|HttpException) {
                // A lesson the demo's own story already moved: nothing to show here.
            }
        };

        if (($found = $pick('completed')) !== null) {
            [$lesson, $who] = $found;
            $try(fn () => $dispute->handle($lesson, 'family', $who, 'cut_short', 'شعرنا أن الحصة انتهت قبل موعدها، نرجو مراجعتها.'));
        }

        if (($found = $pick('teacher_absent')) !== null) {
            [$lesson] = $found;
            $try(function () use ($dispute, $decide, $lesson, $owner) {
                $raised = $dispute->handle($lesson, 'teacher', $lesson->teacher_id, 'teacher_attended', 'كنت في الفصل من بداية الحصة، لكن الكاميرا لم تعمل ولم يسجّلني النظام.');
                $decide->handle($raised, 'completed', 'راجعنا سجل الفصل ووجدناك حاضرًا طوال الحصة. سُجّلت الحصة واحتُسب لك أجرها.', $owner);
            });
        }

        if (($found = $pick('completed')) !== null) {
            [$lesson, $who] = $found;
            $try(function () use ($dispute, $decide, $lesson, $who, $owner) {
                $raised = $dispute->handle($lesson, 'family', $who, 'teacher_absent', 'لم يدخل المعلّم الحصة إطلاقًا.');
                $decide->handle($raised, 'completed', 'يُظهر سجل الفصل أن المعلّم والطالب كانا حاضرَين طوال الحصة. إن كان هناك أمر آخر فتواصلوا معنا.', $owner);
            });
        }
    }
}
