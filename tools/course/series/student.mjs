// Students in /my on the phone, in their stage's look: kids, middle school, high school.
const tab = (s, label) => s.page.locator('.lms-tab').filter({ hasText: label }).first();

export default {
  key: 'student',
  title: 'سلسلة الطالب',
  audience: 'للطالب في كل مرحلة',
  color: '#ec4899',
  device: 'phone',
  user: 'kid',
  lessons: [
    {
      n: 1, slug: 'kids-journey', title: 'رحلتي: بيت الطالب الصغير',
      who: 'للأطفال (يوسف، 8 سنين)', font: 'kids',
      goal: 'الطفل يعرف بيته في المنصة: رحلته ونجومه ومهامه وحصصه.',
      learn: ['خريطة الرحلة والمحطات والكنز', 'مهام الأسبوع والجواهر والتشجيع', 'الشريط اللي تحت: حصصي، واجباتي، قول لحد كبير'],
      start: '/my',
      async steps(s) {
        await s.say('ده بيت يوسف في المنصة. أول ما يدخل يلاقي ترحيب باسمه، وعدد النجوم اللي جمعها.');
        await s.show(s.page.locator('.jk-hello'), { hold: 2200 });

        await s.say('دي خريطة رحلته في «قرآن · حفظ»: كل جزيرة مستوى، وكل محطة على الطريق سورة.');
        await s.scroll(s.page.locator('.jk-island-head'));
        await s.show(s.page.locator('.jk-island-head'), { hold: 1600 });

        await s.say('النجمة على المحطة معناها إنه خلّصها وأتقنها، و«انت هنا» هي اللي بيذاكرها دلوقتي.');
        await s.scroll(s.page.locator('.jk-bubble'));
        await s.point(s.page.locator('.jk-bubble'), { hold: 1800 });

        await s.say('وفي آخر الطريق الكنز: كل ما يخلّص محطات يقرب منه أكتر.');
        await s.scroll(s.page.locator('.jk-goal'));
        await s.point(s.page.locator('.jk-goal'), { hold: 1800 });

        await s.say('تحت الخريطة: المحطة الجاية، والواجب اللي عليه بزرار «يلا نحله».');
        await s.scroll(s.page.locator('.jk-next'));
        await s.show(s.page.locator('.jk-next'), { hold: 1200 });
        await s.show(s.page.locator('.jk-homework'), { hold: 1400 });

        await s.say('ولما بابا أو ماما يبعتوا تشجيع من حسابهم، بيظهر هنا، ويوسف يرد عليهم بـ«شكرًا!».');
        await s.scroll(s.page.locator('.lc-cheer'));
        await s.click(s.page.locator('.lc-cheer').getByRole('button').first(), { settle: 1200 });

        await s.say('«مهام الأسبوع»: تحديات صغيرة، كل واحدة ليها جواهر. الأسبوع بيبدأ يوم السبت.');
        await s.scroll(s.page.locator('.lc-head'));
        await s.show(s.page.locator('.lc-head'), { hold: 1600 });

        await s.say('لما يخلّص مهمة، يدوس «استلم» وياخد جواهره، وتطلع له احتفالية.');
        await s.click(s.page.locator('.lc-collect').first(), { settle: 2600 });

        await s.say('وتحت كده حصصه الجاية: اليوم والميعاد والمدرس، وإمتى الفصل يفتح.');
        await s.scroll(s.page.getByText('الحصص الجاية').first());
        await s.scroll(260);
        await s.wait(1200);

        await s.say('الشريط اللي تحت معاه في كل صفحة. «حصصي» فيها كل حصصه بمواعيدها.');
        await s.click(tab(s, 'حصصي'), { settle: 1500 });
        await s.scroll(300);

        await s.say('و«واجباتي»: يشوف الواجب ويسلّمه كتابة أو يسجّله بصوته.');
        await s.click(tab(s, 'واجباتي'), { settle: 1500 });

        await s.say('و«قول لحد كبير»: لو حاجة ضايقته أو خوّفته، يقول لحد كبير في الأكاديمية بضغطة، من غير ما يكتب حاجة.');
        await s.click(tab(s, 'قول لحد كبير'), { settle: 1500 });

        await s.say('ويرجع لـ«رحلتي» في أي وقت.');
        await s.click(tab(s, 'رحلتي'), { settle: 1500 });
        await s.wait(1500);
      },
    },
    {
      n: 2, slug: 'kids-lessons-homework', title: 'حصصي وواجباتي للصغار',
      who: 'للأطفال', font: 'kids',
      goal: 'الطفل يدخل حصته ويسلّم واجبه لوحده.',
      learn: ['زرار دخول الحصة لما الفصل يفتح', 'تسليم الواجب بالصوت', 'الواجبات اللي اتصححت والنجوم'],
    },
    {
      n: 3, slug: 'middle-levels', title: 'المستويات والنقاط والشارات',
      who: 'لطلاب الإعدادي (زياد)', user: 'middle',
      goal: 'الطالب يفهم مستواه ونقاطه وسلسلة أيامه وإزاي يكسب شارات.',
      learn: ['المستوى والنقاط (XP)', 'السلسلة: كام أسبوع ورا بعض', 'الشارات والتحديات'],
    },
    {
      n: 4, slug: 'middle-lessons', title: 'حصصي: الإلغاء والتقييم والاعتراض',
      who: 'لطلاب الإعدادي والثانوي', user: 'middle',
      goal: 'الطالب يدير حصصه بنفسه في حدود سياسة الأكاديمية.',
      learn: ['إلغاء حصة ومعرفة أثرها قبل ما تأكد', 'تقييم الحصة بضغطة', 'الاعتراض على نتيجة حصة خلال 48 ساعة'],
    },
    {
      n: 5, slug: 'teen-timeline', title: 'الخط الزمني والهدف والسرعة',
      who: 'لطلاب الثانوي (مريم)', user: 'teen',
      goal: 'الطالب يشوف هو فين من هدفه وإمتى هيوصله بالسرعة دي.',
      learn: ['الهدف ونسبة الإنجاز', 'السرعة والوقت المتوقع للإنهاء', 'التقارير بعد كل حصة'],
    },
    {
      n: 6, slug: 'live-class', title: 'دخول الحصة المباشرة',
      who: 'لكل الطلاب', user: 'teen',
      goal: 'الطالب يدخل الفصل ويستخدمه من غير مساعدة.',
      learn: ['إمتى الفصل يفتح وإزاي تدخل', 'الكاميرا والمايك ومشاركة الشاشة', 'مواد الدرس والتسجيل'],
    },
    {
      n: 7, slug: 'help-and-data', title: 'المساعدة وبياناتي',
      who: 'لكل الطلاب', user: 'teen',
      goal: 'الطالب يعرف يطلب مساعدة ويتحكم في بياناته.',
      learn: ['طلب مساعدة ومتابعته', '«قول لنا» لو فيه حاجة مش مريحة', 'نسخة من بياناتي وإعداداتي'],
    },
  ],
};
