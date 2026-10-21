// Teachers in /teach (the same work is in the portal and the app).

// Each lesson has its own Egyptian narrator from the ElevenLabs library (all spoken on eleven_v4).
const VOICES = {
  soliman: 'oOge1k8044i7H9hCreX6',   // Mahmoud Soliman - Egyptian Story
  tamer: 'mX8opBj7bbi4G2cE9j2w',     // Tamer - Energetic, Clear
  fekry: 'B1W8tTuESCJzhOnd15nC',     // Fekry - Egyptian Arabic
  omar: 'erFJevGCT0rMZPhBbaep',      // Omar
  fattah: 'UDyuvKjPXutvqdCvJ0Oh',    // Fattah
  fatimaLoud: 'Vu9gRjkR23ZG8EWrSmnj', // Fatima - Loud & Engaging
  ghozlanCalm: 'Fzd3U2Lg11XFN32IEcQV', // Ghozlan - Calm Lifestyle Vlogger
  mamdoh: '68MRVrnQAt8vLbu0FCzw',    // Mamdoh - Deep Egyptian Arabic
  hammam: 'VxSsN5NGusWQZXue7VE9',    // Hammam - Calm and Expressive
  ziad: '5REPlS2Ja1VZ7zNA0ykn',      // Ziad - Dynamic and Vibrant
};
/** A form field by its label. */
const labelled = (s, label) => s.page.locator('.fi-fo-field').filter({ has: s.page.locator('.fi-fo-field-label-content', { hasText: label }) }).first();
/** A choice inside a field: toggle buttons and radios are labels. */
const option = (s, field, text) => labelled(s, field).locator('label').filter({ hasText: new RegExp(`^\\s*${text}\\s*$`) }).first();
/** The top menu's item with exactly this label. */
const menuItem = (s, label) => s.page.locator('.fi-topbar-item').filter({ has: s.page.locator('.fi-topbar-item-label', { hasText: new RegExp(`^\\s*${label}\\s*$`) }) }).locator('a, button').first();
export default {
  key: 'teacher',
  title: 'سلسلة المدرس',
  audience: 'للمدرسين',
  color: '#f59e0b',
  device: 'desktop',
  user: 'teacher',
  lessons: [
    {
      n: 1, slug: 'week-and-today', title: 'جدولي وحصصي النهارده', voice: VOICES.soliman, signsOut: true,
      goal: 'المدرس يبدأ يومه وهو عارف كل حصة وكل طالب.',
      learn: ['الدخول وجولة في مساحة المدرس', '«جدولي»: الحصص بتوقيتك', '«حصصي النهارده» وزرار الدخول'],
      start: '/teach',
      async steps(s) {
        await s.say("ده حساب الأستاذة نور الهدى. نخرج الأول عشان نشوف الدخول.", { voice: "[warmly] أهلًا بيك. ده حساب الأستاذة نور الهدى، مدرّسة قرآن وتجويد. هنخرج الأول، عشان نشوف الدخول من أوّله." });
        await s.click(s.page.locator('.fi-topbar .fi-avatar').first(), { settle: 600 });
        await s.click(s.page.getByRole('button', { name: 'تسجيل الخروج' }).first(), { settle: 1500 });

        await s.say("الإيميل أو الموبايل، وكلمة السر. أو «ادخل بكود» على موبايلك.", { voice: "المدرس بيدخل بالإيميل أو رقم الموبايل، وكلمة السر اللي اختارها من رسالة الترحيب. ولو نسيها، يدخل بكود بيوصله على موبايله." });
        await s.type(s.page.locator('#form\\.email'), 'nour@demo.test');
        await s.type(s.page.locator('#form\\.password'), process.env.COURSE_PASSWORD ?? '');
        await s.click(s.page.getByRole('button', { name: 'تسجيل الدخول' }).first(), { settle: 2200 });

        await s.say("أول شاشة: «حصصي النهارده»، والفصل بيفتح قبل كل حصة بعشر دقايق.", { voice: "وأول شاشة «حصصي النهارده»: كل حصة النهارده بميعادها بتوقيت المدرّسة، واسم الطالب، والمادة. والفصل بيفتح قبل الحصة بعشر دقايق، وساعتها بيظهر زرار «ادخل الحصة»." });
        await s.show(s.page.getByRole('link', { name: 'ادخل الحصة' }).first(), { hold: 1600 });

        await s.say("و«لينك الحصص البديل»: لو الفصل عندك فيه مشكلة.", { voice: "ولو حصل مشكلة في الفصل نفسه، فيه «لينك الحصص البديل»، لينك ثابت للمدرّسة تدخل بيه الحصة من برّه." });
        await s.point(s.page.getByRole('button', { name: 'لينك الحصص البديل' }), { hold: 1400 });

        await s.say("القايمة فوق: كل حاجة المدرّسة محتاجاها، والرقم جنب اللي مستنيها.", { voice: "والقايمة كلها فوق: الجدول، والطلاب، والتقارير، والرسايل، والحصص التجريبية، والواجبات، والأوقات، والإجازات، والأداء، والأرباح. والرقم اللي جنب أي واحدة، هو عدد الحاجات اللي مستنياها، زي التقارير اللي لسه ماتكتبتش." });
        await s.show(s.page.locator('.fi-topbar-nav-groups'), { hold: 2200 });

        await s.say("«جدولي»: الأسبوع كله، يوم يوم، بتوقيتها.", { voice: "و«جدولي»: الأسبوع كله يوم يوم، كل حصة بميعادها بتوقيت المدرّسة، والطالب، والمادة، وحالتها: محجوزة، أو اتعملت، أو اتلغت." });
        await s.click(menuItem(s, 'جدولي'), { settle: 1800 });

        await s.say("وحالة كل حصة، ولو فيه اعتراض: رد الأكاديمية تحتها.", { voice: "ولو كان فيه اعتراض على حصة، رد الأكاديمية بيظهر تحتها، زي هنا: الاعتراض اتقبل، والحصة اتحسبت." });
        await s.show(s.page.getByText('الاعتراض', { exact: false }).locator('visible=true').first(), { hold: 2000 });

        await s.say("و«الأسبوع الجاي» و«الأسبوع اللي فات».", { voice: "ومن الزراير اللي فوق، تروح للأسبوع الجاي، أو ترجع للأسبوع اللي فات." });
        await s.click(s.page.getByRole('button', { name: /الأسبوع الجاي/ }), { settle: 1600 });
        await s.wait(1200);
      },
    },
    {
      n: 2, slug: 'my-availability', title: 'أوقاتي: اقتراح الشفتات', voice: VOICES.tamer,
      goal: 'المدرس يحدد الأوقات اللي يقدر يدرّس فيها ويبعتها للإدارة.',
      learn: ['الأوقات أيام وساعات: من ولحد', 'تاريخ البداية والملاحظة', 'متابعة رد الإدارة والتعديل عليه'],
      start: '/teach',
      async steps(s) {
        const row = () => s.page.locator('.fi-fo-repeater-item').last();

        await s.say("«أوقاتي المتاحة»: الأوقات اللي الطلاب بيتحجزوا جوّاها بس.", { voice: "[cheerful] أهم حاجة يظبطها المدرس: أوقاته. من فوق نفتح «أوقاتي المتاحة». الطلاب بيتحجزوا جوّه الأوقات دي بس، وكلها بتوقيت المدرّسة." });
        await s.click(menuItem(s, 'أوقاتي المتاحة'), { settle: 1800 });

        await s.say("على الجنب: الجدول الحالي المعتمد، وساعاته في الأسبوع.", { voice: "على الجنب الجدول الحالي اللي الإدارة معتمداه، وعدد ساعاته في الأسبوع." });
        await s.show(s.page.getByRole('heading', { name: 'الجدول الحالي' }).locator('xpath=ancestor::section[1]'), { hold: 1600 });

        await s.say("«ضيف وقت»: اليوم، ومن الساعة كام، لحد الساعة كام.", { voice: "ولو عايزة تزوّد أو تغيّر، في «اقترح أوقات جديدة»: كل سطر يوم، ومن الساعة كام لحد الساعة كام. ندوس «ضيف وقت»، ونختار الأحد، من أربعة العصر لسبعة بالليل." });
        await s.click(s.page.getByRole('button', { name: 'ضيف وقت' }), { settle: 900 });
        await s.select(row().locator('select').first(), { label: 'الأحد' });
        await s.fill(row().locator('input[type=time]').first(), '16:00');
        await s.fill(row().locator('input[type=time]').last(), '19:00');

        await s.say("«تبدأ من»: ولو بتقلّل وقتك، لازم تبلّغ قبلها بـ 14 يوم.", { voice: "وبعدها «تبدأ من»: اليوم اللي الجدول الجديد يبدأ فيه. ولو المدرّسة بتقلّل وقتها، لازم تبلّغ قبلها بأربعتاشر يوم، عشان الطلاب المحجوزين." });
        await s.show(s.page.locator('input[type=date]').first(), { hold: 1400 });

        await s.say("وملاحظة للإدارة، و«ابعت للإدارة». أو «احفظ مسودة» وتكمّل بعدين.", { voice: "وممكن تكتب ملاحظة للإدارة، زي سبب التغيير، وتدوس «ابعت للإدارة». ولو لسه مش متأكدة، «احفظ مسودة» وتكمّل بعدين." });
        await s.type(s.page.locator('textarea').first(), 'زوّدت يوم الأحد عشان عندي طلاب جداد عايزين الوقت ده.');
        await s.click(s.page.getByRole('button', { name: 'ابعت للإدارة' }), { settle: 2200 });

        await s.say("الإدارة بتراجع: تعتمد، أو تطلب تعديل، أو تبعت عرض مضاد ترد عليه هنا.", { voice: "والإدارة بتراجع المقترح: يا تعتمده، يا تطلب تعديل، يا تبعت عرض مضاد. والرد بيوصل إشعار، وبيظهر هنا، والمدرّسة تقدر تقبل العرض، أو تسحب المقترح وتبعت غيره." });
        await s.scroll(-3000);
        await s.wait(2200);
      },
    },
    {
      n: 3, slug: 'live-class', title: 'الحصة المباشرة',
      goal: 'المدرس يدير الحصة من أول ما الفصل يفتح لحد ما يقفل.',
      learn: ['دخول الفصل والكاميرا والمايك', 'مواد الدرس والواجب اللي فات', 'التسجيل بموافقة الكل'],
    },
    {
      n: 4, slug: 'lesson-report', title: 'تقرير الحصة والواجب', voice: VOICES.fekry,
      goal: 'المدرس يكتب تقرير مفيد في دقيقتين ويدّي واجب.',
      learn: ['خطة اليوم والتقدم في المنهج', 'أسئلة الأكاديمية الخاصة بالمادة', 'الواجب وميعاد تسليمه'],
      start: '/teach',
      async steps(s) {
        await s.say("بعد كل حصة: تقرير. «تقاريري» فيها كل حصة لسه تقريرها ماتكتبش.", { voice: "[warmly] بعد كل حصة، المدرس بيكتب تقرير قصير، والأسرة بتشوفه على طول. من فوق «تقاريري»، والرقم جنبها عدد الحصص اللي لسه تقريرها ماتكتبش. ندوس «اكتب التقرير»." });
        await s.click(menuItem(s, 'تقاريري'), { settle: 1800 });
        await s.click(s.page.getByRole('link', { name: 'اكتب التقرير' }).first(), { settle: 2000 });

        await s.say("على الجنب منهج الطالب، وفوق الدرس الجاي جاهز. نختار اتعمل فيه إيه، والدرجة.", { voice: "على الجنب منهج الطالب، وهو فين فيه. وفوق، المنصّة جهّزت أول درس لسه ماتقنش، والمراجعات اللي جه ميعادها. نختار اتعمل في الدرس إيه: اتشرح، أو اتدرّب عليه، أو اتقن، أو محتاج مراجعة، والدرجة من خمسة." });
        await s.click(s.page.locator('label').filter({ hasText: 'اتدرّب عليه' }).first(), { settle: 400 });
        await s.click(option(s, 'الدرجة', '4'), { settle: 400 });

        await s.say("التقييم: التفاعل والتقييم العام، وملاحظة للأسرة، وملاحظة للإدارة بس.", { voice: "وبعدها التقييم: التفاعل، والتقييم العام. وملاحظة للطالب وولي الأمر، يشوفوها في التطبيق. وتحتها ملاحظات داخلية، دي للإدارة بس، والأسرة مابتشوفهاش." });
        await s.scroll(labelled(s, 'التفاعل'));
        await s.click(option(s, 'التفاعل', '5'), { settle: 300 });
        await s.click(option(s, 'التقييم العام', '4'), { settle: 300 });
        await s.type(labelled(s, 'ملاحظات للطالب وولي الأمر').locator('textarea'), 'تميم ركّز كويس النهارده، ونطق الحروف المفردة بقى أحسن كتير. محتاج يراجع حرف الضاد.');

        await s.say("«أسئلة الأكاديمية»: أسئلة المادة اللي الأكاديمية حطّاها.", { voice: "و«أسئلة الأكاديمية»: أسئلة خاصة بالمادة دي، الأكاديمية بتحددها، زي الأحكام اللي اتطبّقت صح، والقراءة من المصحف، وعدد الصفحات." });
        await s.scroll(labelled(s, 'الأحكام اللي اتطبّقت صح'));
        await s.click(option(s, 'الأحكام اللي اتطبّقت صح', '4'), { settle: 300 });
        await s.click(option(s, 'القراءة من المصحف', 'أخطاء قليلة'), { settle: 300 });
        await s.type(labelled(s, 'صفحات القراءة').locator('input'), '2');

        await s.say("والواجب: يتسلّم إزاي، وفي خلال كام يوم.", { voice: "وفي الآخر الواجب، لو فيه: نكتب الواجب وتعليماته، ونختار يتسلّم إزاي: نص، أو تسجيل صوتي، أو صورة، أو تدريب في الألعاب. وميعاد التسليم." });
        await s.click(s.page.getByText('واجب (اختياري)').first(), { settle: 900 });
        await s.type(labelled(s, 'الواجب').locator('input, textarea').first(), 'اقرا الحروف المفردة وسجّل صوتك');
        await s.click(option(s, 'التعليمات', 'تسجيل صوتي').or(s.page.locator('label').filter({ hasText: /^\s*تسجيل صوتي\s*$/ })).first(), { settle: 400 });

        await s.say("«سلّم التقرير»: يوصل للأسرة إشعار، ويظهر في التطبيق.", { voice: "وندوس «سلّم التقرير». الأسرة بيوصلها إشعار، والتقرير والواجب بيظهروا في التطبيق على طول." });
        await s.click(s.page.getByRole('button', { name: 'سلّم التقرير' }), { settle: 2500 });
      },
    },
    {
      n: 5, slug: 'homework-marking', title: 'تصحيح الواجبات',
      goal: 'المدرس يصحح الواجبات اللي اتسلمت (كتابة أو صوت أو ملف).',
      learn: ['الواجبات المستنية تصحيح', 'سماع التسجيل الصوتي', 'الدرجة والتعليق'],
    },
    {
      n: 6, slug: 'my-students', title: 'طلابي وتقدمهم',
      goal: 'المدرس يعرف كل طالب فين في منهجه وإيه اللي محتاجه.',
      learn: ['قايمة الطلاب', 'خريطة المنهج وتقدم الطالب', 'المواد اللي للمدرسين بس'],
    },
    {
      n: 7, slug: 'trials', title: 'الحصص التجريبية',
      goal: 'المدرس يدّي حصة تجريبية ويقيّم مستوى الطالب.',
      learn: ['الحصص التجريبية الجاية', 'تقييم المستوى والتوصية', 'إزاي ده بيأثر على تحويل الطالب'],
    },
    {
      n: 8, slug: 'time-off-disputes', title: 'إجازاتي والاعتراض على حصة',
      goal: 'المدرس يطلب إجازة صح ويعترض لو نتيجة حصة غلط.',
      learn: ['طلب إجازة ومتابعته', 'الحصص اللي هتتأثر والمدرس البديل', 'الاعتراض على نتيجة حصة خلال 48 ساعة'],
    },
    {
      n: 9, slug: 'performance-earnings', title: 'أدائي وأرباحي',
      goal: 'المدرس يشوف أداءه وأرباحه بشفافية.',
      learn: ['مؤشرات الأداء والتقييمات (من غير أسماء)', 'أرباح الشهر سطر بسطر', 'إمتى الشهر بيتقفل'],
    },
    {
      n: 10, slug: 'documents-support', title: 'مستنداتي والدعم والإبلاغ',
      goal: 'المدرس يرفع مستنداته ويطلب مساعدة ويبلّغ عن أي قلق على طالب.',
      learn: ['مستنداتي (الفحص الجنائي وغيره)', 'طلب دعم ومتابعته', 'الإبلاغ عن قلق على طالب، وبياناتي'],
    },
  ],
};
