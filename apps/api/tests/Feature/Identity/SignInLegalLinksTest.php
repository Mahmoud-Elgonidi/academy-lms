<?php

use Carbon\CarbonImmutable;
use Modules\Privacy\Http\Controllers\LegalController;

/*
 * Every panel's sign-in links to the academy's own privacy notice and terms
 * (Privacy's LegalController on the academy's host), in the language the
 * page is in, before anyone signs in.
 */

it('links each panel\'s sign-in to the academy\'s own privacy notice and terms, in the page\'s language', function () {
    $this->createAcademy(['slug' => 'huda', 'name_en' => 'Huda']);
    $this->createAcademy(['slug' => 'noor', 'name_en' => 'Noor', 'default_locale' => 'en']);

    foreach (['admin', 'teach', 'my'] as $panel) {
        $this->get("http://huda.lms.test/{$panel}/login")->assertOk()
            ->assertSee('href="/privacy?lang=ar"', false)->assertSee('href="/terms?lang=ar"', false)
            ->assertSee('سياسة الخصوصية')->assertSee('شروط الاستخدام');
    }
    // Before signing in, a panel speaks the academy's language.
    $this->get('http://noor.lms.test/my/login')->assertOk()
        ->assertSee('href="/privacy?lang=en"', false)->assertSee('href="/terms?lang=en"', false)
        ->assertSee('Privacy notice')->assertSee('Terms of use');

    // Where they lead: the academy's pages on its own host.
    $this->get('http://huda.lms.test/privacy?lang=en')->assertOk()->assertSee('Huda');
    $this->get('http://huda.lms.test/terms?lang=ar')->assertOk()->assertSee('شروط الاستخدام');
});

it('tells families in the privacy notice that group classmates see first names only, in the notice\'s current version', function () {
    $this->createAcademy(['slug' => 'huda', 'name_en' => 'Huda']);

    $this->get('http://huda.lms.test/privacy?lang=en')->assertOk()
        ->assertSee(CarbonImmutable::parse(LegalController::VERSION)->locale('en')->translatedFormat('j F Y'))
        ->assertSee(e('Classmates in a group lesson: each other\'s first name only'), false);
    $this->get('http://huda.lms.test/privacy?lang=ar')->assertOk()->assertSee('زملاء الحصة الجماعية: الاسم الأول فقط');
});
