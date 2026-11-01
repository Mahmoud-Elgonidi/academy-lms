<?php

namespace Modules\Site\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Models\Subject;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;

/**
 * The demo academy's public page is on, with its words in Arabic and English:
 * the defaults, what two demo families said, every section, and a WhatsApp
 * number. Families book their own free trial, the academy answers within four
 * hours, and "help me choose" starts with Quran memorisation. Once: a page
 * the demo's owner already saved stays as it is (an older demo's page, never
 * saved by its owner, takes the trial subject; DemoCatchUpSeeder runs this).
 */
final class DemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        $quran = Subject::query()->where('name_en', 'Quran memorisation')->value('id');
        $existing = SiteSettings::query()->first();
        if ($existing !== null) {
            if ($existing->updated_by === null && $existing->default_trial_subject_id === null && $quran !== null) {
                $existing->forceFill(['default_trial_subject_id' => $quran])->save();
            }

            return;
        }
        $content = SiteContent::defaults();
        $content['testimonials'] = [
            ['name' => 'أم يوسف', 'role_ar' => 'ولية أمر طالب في لندن', 'role_en' => 'A student\'s mother in London',
                'quote_ar' => 'أصبح يوسف ينتظر حصة القرآن، والتقرير بعد كل حصة يطمئنني أنه يسير على الطريق الصحيح.',
                'quote_en' => 'Yousef looks forward to his Quran lesson now, and the report after each one tells me he is on track.'],
            ['name' => 'ناصر علي', 'role_ar' => 'ولي أمر طالب في الدوحة', 'role_en' => 'A student\'s father in Doha',
                'quote_ar' => 'المواعيد مضبوطة على توقيتنا، وحين فاتتنا حصة عُوّضت دون أن نطلب.',
                'quote_en' => 'The times fit our clock, and when a lesson was missed it was made up without us asking.'],
        ];
        $content['sections'] = SiteContent::SECTIONS;
        $content['whatsapp'] = '+966500000000';

        SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::normalize($content),
            'trial_booking' => 'family', 'response_hours' => 4, 'default_trial_subject_id' => $quran]);
    }
}
