<?php

use Illuminate\Support\Arr;

/*
 * What an academy that trains adults only sends (docs/architecture/02,
 * section 29): every notification of the lessons, money, sales, page and
 * pay modules reads in training words there (the adults overlays,
 * lang/{locale}/adults/notifications.php), never of a family, a guardian, a
 * child, a lesson, a teacher or homework; and to the learner in the
 * singular. A families academy's words stay as they are.
 */

const ADULTS_MODULES = ['Scheduling', 'Billing', 'Crm', 'Site', 'Payroll'];

/** Words an adults academy's reader never meets, by language. */
const ADULTS_NEVER = [
    'ar' => ['ولي', 'وليّ', 'أولياء', 'طفل', 'أطفال', 'أبناء', 'ابنك', 'الأسرة', 'أسرت', 'العائلة', 'حصة', 'حصص', 'الحصة', 'حصتك', 'المعلّم', 'معلّم', 'واجب', 'ادخلوا', 'حصتكم', 'كورس', 'الطالب'],
    'en' => ['guardian', 'child', 'family', 'families', 'parent', 'lesson', 'homework', 'teacher', 'student'],
];

it('words every notification of these modules for adults in an adults academy', function (string $locale) {
    $this->actAsTenant($this->createAcademy(['slug' => 'khibra', 'audience' => 'adults']));
    $wrong = [];
    foreach (ADULTS_MODULES as $module) {
        $base = require base_path("modules/{$module}/lang/{$locale}/notifications.php");
        foreach (array_keys(Arr::dot($base)) as $key) {
            $line = (string) __(strtolower($module)."::notifications.{$key}", [], $locale);
            // Placeholders are names, not words (:student, :teacher).
            $text = preg_replace('/:[a-z_]+/', '', $line);
            foreach (ADULTS_NEVER[$locale] as $word) {
                if (mb_stripos($text, $word) !== false) {
                    $wrong[] = "{$module} {$key}: \"{$word}\" in \"{$line}\"";
                }
            }
        }
    }

    expect($wrong)->toBe([]);
})->with(['ar', 'en']);

it('keeps a families academy\'s notifications as they are', function () {
    $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $base = require base_path('modules/Scheduling/lang/ar/notifications.php');
    expect(__('scheduling::notifications.reminder_day.body', [], 'ar'))->toBe($base['reminder_day']['body']);
});
