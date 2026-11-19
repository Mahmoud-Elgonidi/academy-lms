<?php

namespace Modules\Site\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Catalog\Models\Subject;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;
use Modules\Site\Models\SiteSettings;

/**
 * The demo academy's public page is on: families book their own free trial,
 * the academy answers within four hours, and "help me choose" starts with
 * Quran memorisation. Its page is built with the page builder
 * (docs/architecture/site-builder.md): a text-and-picture opening, numbers,
 * courses, reasons, a picture with text, teachers, a short video, prices,
 * what learners said, a gallery, partners, questions, a call to start and
 * the form, with the demo's own pictures, logos and clip in its library
 * (database/seeders/media), published once. Once: a page the demo already
 * has stays as it is (an older demo's page became blocks in the migration).
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
        } else {
            // The page's old words (frozen since the builder; kept one release for a rollback).
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

        if (SitePage::query()->exists()) {
            return;
        }
        $media = $this->media();
        $doc = PageDoc::normalize($this->page($media));
        $now = now();
        $page = new SitePage;
        $page->forceFill(['draft' => $doc, 'published' => $doc, 'draft_revision' => 1, 'published_revision' => 1, 'template' => 'online_courses',
            'draft_saved_at' => $now, 'published_at' => $now])->save();
        SitePagePublish::query()->create(['revision' => 1, 'page' => $doc, 'published_at' => $now]);
    }

    /** @return array<string, string> the demo's files in its library: key => id */
    private function media(): array
    {
        $academy = app(TenantContext::class)->require()->id;
        $files = [
            'hero' => ['hero.webp', 'image/webp', 2400, 1600, ['ar' => 'نافذة على هيئة قوس تطل على سماء الغروب', 'en' => 'An arched window opening onto a dusk sky']],
            'arches' => ['arches.webp', 'image/webp', 1600, 1200, ['ar' => 'ثلاثة أقواس متجاورة بلون الرمل', 'en' => 'Three arches side by side in sand colours']],
            'study' => ['study.webp', 'image/webp', 1600, 1200, ['ar' => 'دائرتان متداخلتان بالأخضر المائي والكهرماني', 'en' => 'Two overlapping circles in teal and amber']],
            'poster' => ['intro-poster.webp', 'image/webp', 1280, 720, ['ar' => 'لقطة من جولة في حصصنا', 'en' => 'A still from a tour of our lessons']],
            'logo1' => ['logo-1.png', 'image/png', 600, 240, ['ar' => 'نوفا', 'en' => 'Nova']],
            'logo2' => ['logo-2.png', 'image/png', 600, 240, ['ar' => 'أطلس', 'en' => 'Atlas']],
            'logo3' => ['logo-3.png', 'image/png', 600, 240, ['ar' => 'أوربت', 'en' => 'Orbit']],
            'intro' => ['intro.mp4', 'video/mp4', null, null, []],
        ];
        $ids = [];
        foreach ($files as $key => [$file, $mime, $width, $height, $alt]) {
            $id = (string) Str::uuid7();
            $extension = SiteMedia::IMAGES[$mime] ?? SiteMedia::VIDEOS[$mime];
            $path = SiteMedia::directory($academy)."/{$id}.{$extension}";
            $bytes = (string) file_get_contents(__DIR__.'/media/'.$file);
            Storage::disk(SiteMedia::DISK)->put($path, $bytes);
            $row = new SiteMedia;
            $row->forceFill(['id' => $id, 'kind' => str_starts_with($mime, 'video/') ? 'video' : 'image', 'mime' => $mime, 'path' => $path,
                'bytes' => strlen($bytes), 'width' => $width, 'height' => $height, 'name' => pathinfo($file, PATHINFO_FILENAME), 'alt' => $alt])->save();
            $ids[$key] = $id;
        }

        return $ids;
    }

    /**
     * @param  array<string, string>  $m
     * @return array<string, mixed>
     */
    private function page(array $m): array
    {
        $t = fn (string $ar, string $en) => ['ar' => $ar, 'en' => $en];
        $ref = fn (string $key) => ['media' => $m[$key], 'alt' => null];
        $block = fn (string $type, string $variant, array $style, array $data, ?array $nav = null) => [
            'type' => $type, 'variant' => $variant, 'hidden' => false, 'nav' => $nav ?? $t('', ''), 'style' => $style, 'data' => $data,
        ];
        $courses = Subject::query()->whereIn('name_en', ['English', 'Arabic', 'Arabic grammar'])->orderBy('name_en', 'desc')->pluck('id')->all();

        return [
            'settings' => [
                'audience' => 'families', 'accent' => 'primary', 'mood' => 'light', 'corners' => 'soft', 'motion' => 'subtle',
                'header' => ['style' => 'solid', 'cta' => ['label' => $t('', ''), 'action' => ['kind' => 'trial']], 'sign_in' => true, 'language_switch' => true],
                'footer' => [
                    'text' => $t('أكاديمية تعليم عبر الإنترنت: حصص مباشرة مع معلّمين مختارين، لكل متعلّم خطته.', 'An online academy: live lessons with chosen teachers, and a plan for every learner.'),
                    'links' => [],
                    'social' => [['network' => 'instagram', 'url' => 'https://instagram.com/academy.demo'], ['network' => 'youtube', 'url' => 'https://www.youtube.com/@academy.demo']],
                ],
                'whatsapp' => '+966500000000', 'floating_whatsapp' => true,
                'seo' => [
                    'title' => $t('دورات مباشرة عبر الإنترنت مع معلّمين مختارين · حصة تجريبية مجانية', 'Live online courses with chosen teachers · A free trial lesson'),
                    'description' => $t('حصص فردية وجماعية مباشرة في اللغة العربية والإنجليزية والنحو، بمواعيد تناسبك وتقرير بعد كل حصة. احجز حصتك التجريبية المجانية.',
                        'Live one-to-one and group lessons in Arabic, English and grammar, at times that suit you, with a report after every lesson. Book your free trial.'),
                    'image' => $ref('hero'),
                ],
            ],
            'blocks' => [
                $block('hero', 'split', ['tone' => 'paper', 'space' => 'roomy', 'width' => 'wide'], [
                    'kicker' => $t('حصص مباشرة عبر الإنترنت', 'Live online lessons'),
                    'title' => $t('تعلّم بثقة مع معلّم يعرفك ويتابع تقدّمك', 'Learn with confidence, with a teacher who knows you'),
                    'lead' => $t('دورات في اللغة العربية والإنجليزية والنحو، بحصص فردية أو في مجموعات صغيرة، ومواعيد تناسب يومك، وتقرير واضح بعد كل حصة.',
                        'Courses in Arabic, English and grammar, one to one or in small groups, at times that fit your day, with a clear report after every lesson.'),
                    'primary' => ['label' => $t('احجز حصة تجريبية مجانية', 'Book a free trial lesson'), 'action' => ['kind' => 'trial']],
                    'secondary' => ['label' => $t('تعرّف إلى دوراتنا', 'See our courses'), 'action' => ['kind' => 'block', 'block' => 'courses1']],
                    'trust' => [$t('الحصة الأولى مجانية', 'The first lesson is free'), $t('مواعيد بتوقيت بلدك', 'Times on your own clock'), $t('تقرير بعد كل حصة', 'A report after every lesson')],
                    'media' => $ref('hero'), 'media_side' => 'end', 'media_shape' => 'arch',
                ]),
                $block('stats', 'band', ['tone' => 'tint', 'space' => 'compact', 'align' => 'center'], ['items' => [
                    ['value' => $t('1:1', '1:1'), 'label' => $t('معلّم لكل متعلّم', 'One teacher per learner')],
                    ['value' => $t('4 ساعات', '4 hours'), 'label' => $t('نرد على طلبك خلالها', 'We answer your request within')],
                    ['value' => $t('+20', '20+'), 'label' => $t('معلّمًا ومعلّمة', 'Teachers')],
                    ['value' => $t('100%', '100%'), 'label' => $t('حصص مباشرة', 'Live lessons')],
                ]]),
                ['id' => 'courses1', 'anchor' => 'subjects'] + $block('subjects', 'cards', ['tone' => 'white'], [
                    'title' => $t('دوراتنا', 'Our courses'),
                    'lead' => $t('اختر الدورة التي تناسب هدفك، وابدأ من المستوى الذي يناسبك.', 'Choose the course that fits your goal, and start at the level that suits you.'),
                    'only' => $courses,
                ], $t('الدورات', 'Courses')),
                ['anchor' => 'why'] + $block('features', 'icons', ['tone' => 'paper'], [
                    'title' => $t('لماذا يختارنا المتعلّمون وأولياء الأمور', 'Why learners and parents choose us'),
                    'items' => [
                        ['icon' => 'laptop', 'title' => $t('تعلّم من أي مكان', 'Learn from anywhere'), 'text' => $t('حصص مباشرة بالصوت والصورة من الحاسوب أو الهاتف.', 'Live lessons with sound and video, from a computer or a phone.')],
                        ['icon' => 'users', 'title' => $t('معلّمون مختارون', 'Chosen teachers'), 'text' => $t('نختار معلّمينا بعناية ونتابع جودة حصصهم.', 'We choose our teachers with care and follow every lesson.')],
                        ['icon' => 'clock', 'title' => $t('وقتك بيدك', 'Your time, your call'), 'text' => $t('اختر الأيام والساعات، والحصة التي تفوتك تُعوَّض.', 'Pick your days and hours; a missed lesson is made up.')],
                        ['icon' => 'report', 'title' => $t('تقرير بعد كل حصة', 'A report after every lesson'), 'text' => $t('ما أُنجز، والواجب القادم، وكيف يتقدّم المستوى.', 'What was done, the next homework, and how the level moves.')],
                        ['icon' => 'award', 'title' => $t('شهادة لكل مستوى', 'A certificate per level'), 'text' => $t('تحصل على شهادة إتمام عند إنهاء كل مستوى.', 'A certificate each time a level is complete.')],
                        ['icon' => 'chat', 'title' => $t('نجيبك حين تحتاج', 'Answers when you need them'), 'text' => $t('فريقنا يرد على أسئلتك قبل التسجيل وبعده.', 'Our team answers your questions before and after you join.')],
                    ],
                    'columns' => 3,
                ], $t('لماذا نحن', 'Why us')),
                $block('image_text', 'side', ['tone' => 'white'], [
                    'title' => $t('منهج واضح وخطة على مقاسك', 'A clear course, and a plan made for you'),
                    'body' => [
                        'ar' => [['t' => 'p', 'c' => [['text' => 'نبدأ بحصة نحدّد فيها مستواك وهدفك، ثم نبني معك خطة بعدد الحصص الذي يناسبك. '], ['text' => 'لا تبدأ من الصفر إن لم تكن عنده.', 'b' => true]]]],
                        'en' => [['t' => 'p', 'c' => [['text' => 'We start with a lesson to find your level and your goal, then build a plan with the number of lessons that suits you. '], ['text' => 'You never start from zero if you are not there.', 'b' => true]]]],
                    ],
                    'media' => $ref('arches'), 'media_side' => 'start',
                    'bullets' => [$t('مستويات واضحة من البداية إلى الإتقان', 'Clear levels from the start to mastery'), $t('واجب قصير بعد كل حصة', 'Short homework after every lesson'), $t('متابعة يراها ولي الأمر في التطبيق', 'Progress parents see in the app')],
                    'link' => ['label' => $t('ابدأ بحصة مجانية', 'Start with a free lesson'), 'action' => ['kind' => 'trial']],
                ]),
                ['anchor' => 'teachers'] + $block('teachers', 'portraits', ['tone' => 'paper'], ['title' => $t('تعرّف إلى معلّمينا', 'Meet our teachers'), 'max' => 8], $t('المعلّمون', 'Teachers')),
                $block('video', 'framed', ['tone' => 'night', 'align' => 'center'], [
                    'title' => $t('جولة قصيرة في حصصنا', 'A short tour of our lessons'),
                    'lead' => $t('شاهد كيف تسير الحصة المباشرة من البداية إلى التقرير.', 'See how a live lesson runs, from the start to the report.'),
                    'source' => ['kind' => 'upload', 'video' => $ref('intro'), 'poster' => $ref('poster')],
                    'caption' => $t('ثلاث ثوانٍ من أجواء حصصنا.', 'Three seconds from our lessons.'), 'ratio' => '16:9',
                ]),
                ['anchor' => 'pricing'] + $block('pricing', 'cards', ['tone' => 'white'], ['per_lesson' => true, 'note' => $t('الأسعار بعملة منطقتك، والدفع عبر الإنترنت أو بالتحويل.', 'Prices in your region\'s currency, paid online or by transfer.')], $t('الأسعار', 'Prices')),
                $block('testimonials', 'grid', ['tone' => 'paper'], ['items' => [
                    ['name' => 'سارة م.', 'role' => $t('تتعلّم الإنجليزية للعمل', 'Learning English for work'), 'quote' => $t('أصبحت أتحدّث في الاجتماعات بثقة بعد ثلاثة أشهر. المعلّمة تعرف ما أحتاجه بالضبط.', 'I speak up in meetings with confidence after three months. My teacher knows exactly what I need.'), 'photo' => null, 'rating' => 5],
                    ['name' => 'أبو عمر', 'role' => $t('ولي أمر طالب في الرياض', 'A student\'s father in Riyadh'), 'quote' => $t('التقرير بعد كل حصة يطمئنني، والمواعيد تناسب يومنا المزدحم.', 'The report after every lesson reassures me, and the times fit our busy day.'), 'photo' => null, 'rating' => 5],
                    ['name' => 'Lina K.', 'role' => $t('تتعلّم العربية من لندن', 'Learning Arabic from London'), 'quote' => $t('حصص ممتعة ومنظّمة، وأرى تقدّمي أسبوعًا بعد أسبوع.', 'Enjoyable, well-run lessons, and I can see my progress week after week.'), 'photo' => null, 'rating' => 5],
                ]]),
                $block('gallery', 'masonry', ['tone' => 'white'], [
                    'title' => $t('من أجواء أكاديميتنا', 'From our academy'),
                    'items' => [['media' => $ref('hero'), 'caption' => $t('نافذة على يوم جديد', 'A window on a new day')], ['media' => $ref('arches'), 'caption' => $t('خطوة بعد خطوة', 'Step by step')], ['media' => $ref('study'), 'caption' => $t('معلّم ومتعلّم', 'Teacher and learner')]],
                    'ratio' => 'auto',
                ]),
                $block('logos', 'row', ['tone' => 'white', 'space' => 'compact', 'align' => 'center', 'eyebrow' => true], [
                    'eyebrow' => $t('جهات نتعاون معها', 'Organisations we work with'),
                    'items' => [['media' => $ref('logo1'), 'name' => 'Nova', 'url' => null], ['media' => $ref('logo2'), 'name' => 'Atlas', 'url' => null], ['media' => $ref('logo3'), 'name' => 'Orbit', 'url' => null]],
                    'mono' => true,
                ]),
                ['anchor' => 'faq'] + $block('faq', 'two_columns', ['tone' => 'paper'], ['items' => [
                    ['q' => $t('هل الحصة التجريبية مجانية فعلًا؟', 'Is the trial lesson really free?'), 'a' => $t('نعم. حصة كاملة مع معلّم دون دفع أو التزام، ثم نقترح عليك الخطة المناسبة.', 'Yes. A full lesson with a teacher, no payment and no commitment; then we suggest the right plan.')],
                    ['q' => $t('هل الحصص فردية أم جماعية؟', 'Are lessons one to one or in groups?'), 'a' => $t('الاثنان. اختر معلّمًا لك وحدك أو مجموعة صغيرة بسعر أقل.', 'Both. Choose a teacher of your own, or a small group at a lower price.')],
                    ['q' => $t('كيف أعرف المستوى المناسب؟', 'How do I find the right level?'), 'a' => $t('نحدّده في الحصة التجريبية، ويبدأ المنهج من حيث أنت.', 'We find it in the trial lesson, and the course starts where you are.')],
                    ['q' => $t('ماذا لو فاتتني حصة؟', 'What if I miss a lesson?'), 'a' => $t('أبلغنا قبلها بوقت كافٍ وتُعوَّض في موعد آخر يناسبك.', 'Tell us early enough and it is made up at another time that suits you.')],
                    ['q' => $t('كيف يكون الدفع؟', 'How do I pay?'), 'a' => $t('بالباقة: عدد من الحصص بسعر واضح بعملة بلدك، عبر الإنترنت أو بالتحويل.', 'By package: a number of lessons at a clear price in your currency, online or by transfer.')],
                ]], $t('الأسئلة', 'Questions')),
                $block('cta', 'banner', ['tone' => 'brand', 'align' => 'center'], [
                    'title' => $t('ابدأ اليوم بحصة مجانية', 'Start today with a free lesson'),
                    'lead' => $t('احجز حصتك الأولى، وتعرّف إلى معلّمك، واخرج بخطة واضحة.', 'Book your first lesson, meet your teacher, and leave with a clear plan.'),
                    'primary' => ['label' => $t('احجز حصتك المجانية', 'Book your free lesson'), 'action' => ['kind' => 'trial']],
                    'secondary' => ['label' => $t('تحدّث معنا على واتساب', 'Talk to us on WhatsApp'), 'action' => ['kind' => 'whatsapp']],
                ]),
                $block('form', 'split', ['tone' => 'night'], ['teach' => true, 'whatsapp_button' => true]),
                $block('contact', 'band', ['tone' => 'white', 'space' => 'compact'], [
                    'title' => $t('تواصل معنا', 'Contact us'),
                    'hours' => $t('يوميًا من 9 صباحًا إلى 9 مساءً بتوقيت مكة', 'Daily, 9 am to 9 pm Makkah time'),
                    'address' => $t('عبر الإنترنت، من أي مكان في العالم', 'Online, from anywhere in the world'),
                    'whatsapp' => true,
                ]),
            ],
        ];
    }
}
