<?php

namespace Modules\Site\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;

/**
 * The demo academy's public page is on, with its words in Arabic and English:
 * the defaults, what two demo families said, every section, and a WhatsApp
 * number. Once: a page the demo's owner already saved stays as it is.
 */
final class DemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        if (SiteSettings::query()->exists()) {
            return;
        }
        $content = SiteContent::defaults();
        $content['testimonials'] = [
            ['name' => 'أم يوسف', 'role_ar' => 'ولية أمر طالب في لندن', 'role_en' => 'A student\'s mother in London',
                'quote_ar' => 'يوسف بقى بيستنى حصة القرآن، والتقرير بعد كل حصة بيطمّنّي إنه ماشي صح.',
                'quote_en' => 'Yousef looks forward to his Quran lesson now, and the report after each one tells me he is on track.'],
            ['name' => 'ناصر علي', 'role_ar' => 'ولي أمر طالب في الدوحة', 'role_en' => 'A student\'s father in Doha',
                'quote_ar' => 'المواعيد مظبوطة على توقيتنا، ولما حصة فاتت اتعوّضت من غير ما نطلب.',
                'quote_en' => 'The times fit our clock, and when a lesson was missed it was made up without us asking.'],
        ];
        $content['sections'] = SiteContent::SECTIONS;
        $content['whatsapp'] = '+966500000000';

        SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::normalize($content)]);
    }
}
