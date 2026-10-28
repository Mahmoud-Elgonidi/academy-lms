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
      n: 1, slug: 'week-and-today', title: 'أسبوعي وحصصي النهارده', voice: VOICES.soliman, signsOut: true,
      goal: 'المدرس يبدأ يومه وهو عارف كل حصة وكل طالب.',
      learn: ['الدخول وجولة في مساحة المدرس', '«أسبوعي»: الحصص بتوقيتك', '«حصصي النهارده» وزرار الدخول'],
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

        await s.say("«أسبوعي»: الأسبوع كله، يوم يوم، بتوقيتها.", { voice: "و«أسبوعي»: الأسبوع كله يوم يوم، كل حصة بميعادها بتوقيت المدرّسة، والطالب، والمادة، وحالتها: محجوزة، أو اتعملت، أو اتلغت." });
        await s.click(menuItem(s, 'أسبوعي'), { settle: 1800 });

        await s.say("وحالة كل حصة، ولو فيه اعتراض: رد الأكاديمية تحتها.", { voice: "ولو كان فيه اعتراض على حصة، رد الأكاديمية بيظهر تحتها، زي هنا: الاعتراض اتقبل، والحصة اتحسبت." });
        await s.show(s.page.getByText('الاعتراض', { exact: false }).locator('visible=true').first(), { hold: 2000 });

        await s.say("و«الأسبوع الجاي» و«الأسبوع اللي فات».", { voice: "ومن الزراير اللي فوق، تروح للأسبوع الجاي، أو ترجع للأسبوع اللي فات." });
        await s.click(s.page.getByRole('button', { name: /الأسبوع الجاي/ }), { settle: 1600 });
        await s.wait(1200);
      },
    },
    {
      n: 2, slug: 'my-availability', title: 'أوقاتي: اقتراح الشفتات', voice: VOICES.tamer,
      goal: 'المدرس يحدد الأوقات اللي يقدر يدرّس فيها ويبعتها للأكاديمية.',
      learn: ['الأوقات أيام وساعات: من ولحد', 'تاريخ البداية والملاحظة', 'متابعة رد الإدارة والتعديل عليه'],
      start: '/teach',
      async steps(s) {
        const row = () => s.page.locator('.fi-fo-table-repeater tbody tr, .fi-fo-repeater-item').last();

        await s.say("«مواعيدي»: الأوقات اللي الطلاب بيتحجزوا جوّاها بس.", { voice: "[cheerful] أهم حاجة يظبطها المدرس: أوقاته. من فوق نفتح «مواعيدي». الطلاب بيتحجزوا جوّه الأوقات دي بس، وكلها بتوقيت المدرّسة." });
        await s.click(menuItem(s, 'مواعيدي'), { settle: 1800 });

        await s.say("على الجنب: الجدول الحالي المعتمد، وساعاته في الأسبوع.", { voice: "على الجنب الجدول الحالي اللي الإدارة معتمداه، وعدد ساعاته في الأسبوع." });
        await s.show(s.page.getByRole('heading', { name: 'الجدول الحالي' }).locator('xpath=ancestor::section[1]'), { hold: 1600 });

        await s.say("«ضيف وقت»: اليوم، ومن الساعة كام، لحد الساعة كام.", { voice: "ولو عايزة تزوّد أو تغيّر، في «اقترح أوقات جديدة»: كل سطر يوم، ومن الساعة كام لحد الساعة كام. ندوس «ضيف وقت»، ونختار الأحد، من أربعة العصر لسبعة بالليل." });
        await s.click(s.page.getByRole('button', { name: 'ضيف وقت' }), { settle: 900 });
        await s.select(row().locator('select').first(), { label: 'الأحد' });
        await s.fill(row().locator('input[type=time]').first(), '16:00');
        await s.fill(row().locator('input[type=time]').last(), '19:00');

        await s.say("«تبدأ من»: ولو بتقلّل وقتك، لازم تبلّغ قبلها بـ 14 يوم.", { voice: "وبعدها «تبدأ من»: اليوم اللي الجدول الجديد يبدأ فيه. ولو المدرّسة بتقلّل وقتها، لازم تبلّغ قبلها بأربعتاشر يوم، عشان الطلاب المحجوزين." });
        await s.show(s.page.locator('input[type=date]').first(), { hold: 1400 });

        await s.say("وملاحظة للأكاديمية، و«ابعت للأكاديمية». أو «احفظ مسودة» وتكمّل بعدين.", { voice: "وممكن تكتب ملاحظة للأكاديمية، زي سبب التغيير، وتدوس «ابعت للأكاديمية». ولو لسه مش متأكدة، «احفظ مسودة» وتكمّل بعدين." });
        await s.type(s.page.locator('textarea').first(), 'زوّدت يوم الأحد عشان عندي طلاب جداد عايزين الوقت ده.');
        await s.click(s.page.getByRole('button', { name: 'ابعت للأكاديمية' }), { settle: 2200 });

        await s.say("الإدارة بتراجع: تعتمد، أو تطلب تعديل، أو تبعت عرض مضاد ترد عليه هنا.", { voice: "والإدارة بتراجع المقترح: يا تعتمده، يا تطلب تعديل، يا تبعت عرض مضاد. والرد بيوصل إشعار، وبيظهر هنا، والمدرّسة تقدر تقبل العرض، أو تسحب المقترح وتبعت غيره." });
        await s.scroll(-3000);
        await s.wait(2200);
      },
    },
    {
      n: 3, slug: 'live-class', title: 'الحصة المباشرة', voice: VOICES.ziad,
      goal: 'المدرس يدير الحصة من أول ما الفصل يفتح لحد ما يقفل.',
      learn: ['دخول الفصل والكاميرا والمايك', 'مواد الدرس والواجب اللي فات', 'التسجيل بموافقة الكل'],
      start: '/teach',
      async steps(s) {
        const button = (name) => s.page.getByRole('button', { name, exact: true }).first();
        const join = () => s.page.getByRole('link', { name: 'ادخل الحصة' }).first();

        await s.say("الفصل بيفتح قبل الحصة بعشر دقايق: «ادخل الحصة».", { voice: "[cheerful] جه ميعاد الحصة. الفصل بيفتح قبلها بعشر دقايق، وساعتها في «حصصي النهارده» بيظهر زرار «ادخل الحصة». الحصة دي مع يوسف خالد، بيحفظ جزء عمّ." });
        await s.point(join(), { hold: 1400 });
        const href = await join().getAttribute('href');
        await s.go(href);
        await s.wait(1500);

        await s.say("«جهّز نفسك»: الكاميرا والمايك. وعلى الجنب خطة اليوم، وآخر واجب.", { voice: "أول حاجة «جهّز نفسك»: نتأكد إن الكاميرا والمايك شغالين. وعلى الجنب، «خطة اليوم» لو الطالب ماشي على منهج، و«آخر واجب» واتسلّم ولا لأ، فالمدرّسة تبدأ الحصة وهي عارفة هتعمل إيه." });
        await s.point(s.page.getByText('آخر واجب').first(), { hold: 1600 });

        await s.say("«ادخل الحصة». ولو النت ضعيف، «ادخل بالصوت بس».", { voice: "وندوس «ادخل الحصة». ولو النت ضعيف، فيه «ادخل بالصوت بس»، والحصة تكمّل بالصوت." });
        await s.point(button('ادخل بالصوت بس'), { hold: 1000 });
        await s.click(button('ادخل الحصة'), { settle: 3500 });

        await s.say("جوّه الفصل. وأول ما الطالب يدخل، بيظهر قدامك.", { voice: "وكده المدرّسة جوّه الفصل. وأول ما يوسف يدخل، بيظهر قدّامها على طول." });
        const student = await s.joinAs('kid', href);
        await student.getByRole('button', { name: 'ادخل الحصة', exact: true }).click().catch(() => {});
        await s.wait(4500);

        await s.say("تحت: المايك، والكاميرا، وشارك الشاشة.", { voice: "وتحت الأزرار: «المايك»، و«الكاميرا»، و«شارك الشاشة» لو عايزة توريه المصحف أو ورقة الدرس." });
        await s.point(button('المايك'), { hold: 900 });
        await s.point(button('الكاميرا'), { hold: 900 });
        await s.point(button('شارك الشاشة'), { hold: 1000 });

        await s.say("«سجّل الحصة»: بس لو كل اللي في الحصة موافقين.", { voice: "ولو الأكاديمية بتسجّل الحصص، فيه «سجّل الحصة». والتسجيل مايشتغلش غير لو كل اللي في الحصة موافقين: المدرّسة، وولي أمر الطالب الصغير. وطول ما هو شغال، الكل بيشوف علامة «بيسجّل» فوق." });
        await s.point(s.page.getByRole('button', { name: /سجّل الحصة/ }).first(), { hold: 1800 });

        await s.say("ولو حاجة مش شغالة: «فيه حاجة مش مظبوطة؟ قولّنا».", { voice: "ولو حاجة مش شغالة، زي الصوت أو الصورة، «فيه حاجة مش مظبوطة؟ قولّنا»، والرسالة بتوصل للأكاديمية على طول." });
        await s.point(s.page.getByText('فيه حاجة مش مظبوطة').first(), { hold: 1400 });

        await s.say("و«اخرج»: الحضور بيتسجّل لوحده، وبعدها التقرير.", { voice: "ولما الحصة تخلص، «اخرج». حضور المدرّسة والطالب بيتسجّل لوحده: دخلوا إمتى، وفضلوا لحد إمتى. والخطوة الجاية: تقرير الحصة." });
        await s.click(button('اخرج'), { settle: 2000 });
      },
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
        await s.scroll(labelled(s, 'الأحكام اللي اتطبقت صح'));
        await s.click(option(s, 'الأحكام اللي اتطبقت صح', '4'), { settle: 300 });
        await s.click(option(s, 'القراءة من المصحف', 'أخطاء قليلة'), { settle: 300 });
        await s.type(labelled(s, 'صفحات القراءة').locator('input'), '2');

        await s.say("والواجب: يتسلّم إزاي، وفي خلال كام يوم.", { voice: "وفي الآخر الواجب، لو فيه: نكتب الواجب وتعليماته، ونختار يتسلّم إزاي: نص، أو تسجيل صوتي، أو صورة، أو تدريب في الألعاب. وميعاد التسليم." });
        await s.click(s.page.getByText('واجب (اختياري)').first(), { settle: 900 });
        await s.type(labelled(s, 'الواجب').locator('input, textarea').first(), 'اقرا الحروف المفردة وسجّل صوتك');
        await s.click(option(s, 'التعليمات', 'تسجيل صوتي').or(s.page.locator('label').filter({ hasText: /^\s*تسجيل صوتي\s*$/ })).first(), { settle: 400 });

        await s.say("«سلّم التقرير»: يوصل للأسرة إشعار، ويظهر في التطبيق.", { voice: "وندوس «سلّم التقرير». الأسرة بيوصلها إشعار، والتقرير والواجب بيظهروا في التطبيق على طول." });
        await s.click(s.page.getByRole('button', { name: 'سلّم التقرير' }), { settle: 2500 });

        await s.say("ولو الواجب معاه تدريب في الألعاب: تختار اللعبة، والتدريبات من الدرس نفسه.", { voice: "[cheerful] ولو الواجب معاه تدريب في الألعاب، بتظهر الصفحة دي: تختار اللعبة اللي الطالب هيتدرّب بيها، والأسئلة جاية من الدرس اللي اتاخد. والطالب بيلاقيها في رحلته، وميعادها قبل الحصة الجاية." });
        await s.point(s.page.getByText('بوابات ريشة').first(), { hold: 1800 });
      },
    },
    {
      n: 5, slug: 'homework-marking', title: 'تصحيح الواجبات', voice: VOICES.omar, user: 'teacher2',
      goal: 'المدرس يصحح الواجبات اللي اتسلمت (كتابة أو صوت أو ملف).',
      learn: ['الواجبات المستنية تصحيح', 'سماع التسجيل الصوتي', 'الدرجة والتعليق'],
      start: '/teach',
      async steps(s) {
        await s.say("ده حساب الأستاذ خالد. «الواجبات»: المستنية تصحيح، والمطلوبة، واللي فات ميعادها.", { voice: "[warmly] المرة دي مع الأستاذ خالد يوسف، مدرّس النحو. من فوق «الواجبات»: المستنية تصحيح، والمطلوبة من الطلاب لسه، واللي فات ميعادها، واللي اتصحّحت." });
        await s.click(menuItem(s, 'الواجبات'), { settle: 1800 });

        await s.say("زياد سلّم تسجيل صوتي: «صحّح».", { voice: "زياد سلّم واجبه تسجيل صوتي، وهو بيقرا الفقرة ويحدد الأفعال المضارعة. ندوس «صحّح»." });
        await s.click(s.page.getByRole('button', { name: 'صحّح' }).first(), { settle: 1500 });

        await s.say("نسمع التسجيل من هنا، ونحط الدرجة، وتعليق للطالب.", { voice: "التسجيل بيشتغل من هنا على طول. نسمعه، ونختار الدرجة من خمسة، ونكتب تعليق للطالب، زي إيه اللي عمله كويس، وإيه اللي يراجعه." });
        await s.show(s.modal().locator('audio').first(), { hold: 1400 });
        await s.click(s.modal().locator('label').filter({ hasText: /^\s*4\s*$/ }).first(), { settle: 400 });
        await s.type(s.modal().locator('textarea').first(), 'قراءة حلوة يا زياد، وحددت أغلب الأفعال صح. راجع «يكتبون» و«تدرسين».');

        await s.say("«صحّح»: الطالب والأسرة بيوصلهم إشعار، والدرجة بتظهر في التطبيق.", { voice: "وندوس «صحّح». الطالب وولي أمره بيوصلهم إشعار، والدرجة والتعليق بيظهروا عندهم في التطبيق، والواجب بينزل في «اتصححت»." });
        await s.click(s.modal().getByRole('button', { name: 'صحّح' }), { settle: 2000 });
        await s.click(s.page.getByRole('button', { name: /^اتصححت/ }), { settle: 1600 });
        await s.wait(1400);
      },
    },
    {
      n: 6, slug: 'my-students', title: 'طلابي وتقدمهم', voice: VOICES.fattah,
      goal: 'المدرس يعرف كل طالب فين في منهجه وإيه اللي محتاجه.',
      learn: ['قايمة الطلاب', 'خريطة المنهج وتقدم الطالب', 'المواد اللي للمدرسين بس'],
      start: '/teach',
      async steps(s) {
        await s.say("«طلابي»: كارت لكل طالب بتدرّسه.", { voice: "[warmly] قبل أي حصة، المدرس محتاج يعرف الطالب وصل لفين. من فوق «طلابي»: كارت لكل طالب بيدرّسه، فيه المادة والمنهج." });
        await s.click(menuItem(s, 'طلابي'), { settle: 1800 });

        await s.say("نفتح يوسف خالد: المنهج، وهو خلّص منه قد إيه.", { voice: "نفتح يوسف خالد، بيحفظ جزء عمّ. فوق، هو خلّص قد إيه من المنهج: خمس دروس من تمانية." });
        await s.click(s.page.getByText('يوسف خالد', { exact: true }).first(), { settle: 2000 });
        await s.show(s.page.getByText('من 8 درس').first(), { hold: 1600 });

        await s.say("كل مستوى ونسبته، وجواه الدروس: ✓ اتقن، والنقطة هو فين دلوقتي.", { voice: "وتحتها كل مستوى ونسبته. «قصار السور» خلصت كلها، و«سور أطول» لسه في أولها. وجوّا المستوى الدروس: علامة الصح يعني الدرس اتقن، والنقطة يعني هو واقف فين دلوقتي، وشكل دراع اللعب يعني اتدرّب عليه في الألعاب، والنسبة جنبه هي درجته." });
        await s.point(s.page.getByText('قريش والفيل', { exact: true }).first(), { hold: 2200 });

        await s.say("ومواد كل درس: ورق، وتسجيلات، ولينكات.", { voice: "وتحت الدروس، مواد كل درس: ورقة متابعة، أو تلاوة مسجّلة، أو لينك. والطالب بيلاقيها هو كمان في الدرس عنده." });
        await s.point(s.page.getByText('ورقة متابعة قريش والفيل').locator('visible=true').first(), { hold: 1600 });

        await s.say("و«للمدرس بس»: دليل للمدرس، الأسرة مابتشوفهوش.", { voice: "واللي مكتوب جنبه «للمدرس بس»، ده دليل للمدرس يحضّر بيه الحصة، والأسرة مابتشوفهوش خالص." });
        await s.point(s.page.getByText('للمدرس بس').locator('visible=true').first(), { hold: 1800 });

        await s.say("وتحت: تقارير كل حصة، وحالة الواجب.", { voice: "ولو نزلنا تحت، تقارير كل الحصص اللي فاتت، وملاحظة كل حصة، وحالة الواجب ودرجته. يعني قبل الحصة، دقيقة واحدة هنا تعرّفك كل حاجة." });
        await s.scroll(s.page.getByRole('heading', { name: 'التقارير' }));
        await s.wait(1600);
      },
    },
    {
      n: 7, slug: 'trials', title: 'الحصص التجريبية', voice: VOICES.fatimaLoud,
      goal: 'المدرس يدّي حصة تجريبية ويقيّم مستوى الطالب.',
      learn: ['الحصص التجريبية الجاية', 'تقييم المستوى والتوصية', 'إزاي ده بيأثر على تحويل الطالب'],
      start: '/teach',
      async steps(s) {
        await s.say("«الحصص التجريبية»: الجاية، واللي محتاجة تقييمك، واللي اتقيّمت.", { voice: "[cheerful] الحصة التجريبية هي أول مرة الأسرة بتشوف الأكاديمية، وتقييم المدرس بعدها مهم جدًا. من فوق «الحصص التجريبية»: الجاية، واللي خلصت ومحتاجة تقييمك، واللي اتقيّمت." });
        await s.click(menuItem(s, 'الحصص التجريبية'), { settle: 1800 });

        await s.say("يحيى، تسع سنين، خلّص حصته: «قيّم».", { voice: "يحيى، عنده تسع سنين، خلّص حصته التجريبية امبارح. ندوس «قيّم»." });
        await s.click(s.page.getByRole('button', { name: 'قيّم' }).first(), { settle: 1400 });

        await s.say("الأسرة مش هتشوف الكلام ده: المبيعات هي اللي هتكلّمهم بيه.", { voice: "الكلام ده الأسرة مش هتشوفه. ده للمبيعات، هي اللي بتكلّم الأسرة بعد الحصة. نكتب المستوى اللي لقيناه." });
        await s.type(s.modal().locator('input[type=text]').first(), 'بيقرا كويس، محتاج يظبط المدود');

        await s.say("والباقة اللي ننصح بيها، وكام حصة في الأسبوع.", { voice: "ونختار الباقة اللي ننصح بيها، وكام حصة في الأسبوع تناسبه." });
        const pick = async (label, option) => {
          const f = s.modal().locator('.fi-fo-field').filter({ hasText: label }).first();
          await s.click(f.locator('.fi-select-input-btn'), { settle: 400 });
          const o = f.locator('[role=option]');
          await s.click(option ? o.filter({ hasText: new RegExp(`^\\s*${option}\\s*$`) }).first() : o.first(), { settle: 500 });
        };
        await pick('الباقة اللي تنصح بيها');
        await pick('كام حصة في الأسبوع', '2');

        await s.say("وملاحظاتك: نقاط قوته، والمحتاج يشتغل عليه.", { voice: "وملاحظاتنا للأكاديمية: نقاط قوته، والمحتاج يشتغل عليه، وأي حاجة المبيعات لازم تعرفها." });
        await s.type(s.modal().locator('textarea').first(), 'متحمّس وبيحب يسمّع. حافظ الفاتحة والإخلاص، محتاج يشتغل على المدود. يناسبه مدرس صبور.');

        await s.say("«إرسال»: التقييم بيروح للمبيعات على طول.", { voice: "وندوس «إرسال». التقييم بيروح للمبيعات على طول، ولو الأسرة اشتركت، الأكاديمية بتعرف إن حصتك التجريبية هي اللي جابتها." });
        await s.click(s.modal().getByRole('button', { name: 'إرسال' }), { settle: 2000 });
        await s.show(s.page.getByRole('heading', { name: /اتقيّمت/ }), { hold: 1600 });
      },
    },
    {
      n: 8, slug: 'time-off-disputes', title: 'الإجازات والاعتراض على حصة', voice: VOICES.ghozlanCalm,
      goal: 'المدرس يطلب إجازة صح ويعترض لو نتيجة حصة غلط.',
      learn: ['طلب إجازة ومتابعته', 'الحصص اللي هتتأثر والمدرس البديل', 'الاعتراض على نتيجة حصة خلال 48 ساعة'],
      start: '/teach',
      async steps(s) {
        // A day in the open date picker: the month first when it is another one.
        const pickDay = async (label, date) => {
          const f = s.modal().locator('.fi-fo-field').filter({ hasText: label }).first();
          await s.click(f.locator('.fi-fo-date-time-picker-trigger, .fi-fo-date-time-picker-display-text-input').first(), { settle: 500 });
          const month = f.locator('.fi-fo-date-time-picker-month-select');
          if (Number(await month.inputValue()) !== date.getMonth()) await month.selectOption(String(date.getMonth()));
          if (date.getMonth() === 0 && new Date().getMonth() === 11) await f.locator('.fi-fo-date-time-picker-year-input').fill(String(date.getFullYear()));
          await s.click(f.locator('.fi-fo-date-time-picker-calendar-day:not(.fi-disabled)').filter({ hasText: new RegExp(`^${date.getDate()}$`) }).first(), { settle: 600 });
          await s.click(s.modal().locator('.fi-modal-heading'), { settle: 400 });
        };
        const inDays = (n) => new Date(Date.now() + n * 86400000);

        await s.say("«الإجازات»: طلباتك وردود الأكاديمية، وإجازات الأكاديمية الجاية.", { voice: "[warmly] لو المدرس محتاج إجازة، مايكلّمش حد على جنب. من فوق «الإجازات»: طلباته اللي فاتت ورد الأكاديمية على كل واحد، وفوق إجازات الأكاديمية نفسها الجاية." });
        await s.click(menuItem(s, 'الإجازات'), { settle: 1800 });

        await s.say("«اطلب إجازة»: يوم أو أكتر، أو ساعات في يوم.", { voice: "ندوس «اطلب إجازة». الإجازة ممكن تبقى يوم أو أكتر، أو ساعات في يوم واحد. والتواريخ بتوقيت المدرّسة." });
        await s.click(s.page.getByRole('button', { name: 'اطلب إجازة' }).first(), { settle: 1400 });
        await pickDay('من يوم', inDays(7));
        await pickDay('لحد يوم', inDays(8));

        await s.say("السبب، وتحت: الحصص اللي في الوقت ده.", { voice: "ونختار السبب. وتحت على طول بيبان الحصص اللي في الوقت ده، والأكاديمية هي اللي هتدبّر لها مدرس بديل أو حصة تعويض." });
        const reason = s.modal().locator('.fi-fo-field').filter({ hasText: 'السبب' }).first();
        await s.click(reason.locator('.fi-select-input-btn'), { settle: 400 });
        await s.click(reason.locator('[role=option]').filter({ hasText: 'ظرف شخصي' }).first(), { settle: 900 });
        await s.show(s.modal().getByText('الحصص اللي في الوقت ده').first(), { hold: 1800 });

        await s.say("«اطلب إجازة»: الطلب بيستنى رد الأكاديمية، وتقدر تسحبه.", { voice: "وندوس «اطلب إجازة». الطلب بيستنى رد الأكاديمية، وبيوصلك إشعار لما ترد. ولحد ما ترد، تقدر تسحبه من هنا." });
        await s.click(s.modal().getByRole('button', { name: 'اطلب إجازة' }), { settle: 2000 });

        await s.say("وفي «أسبوعي»: «اعترض» تحت أي حصة نتيجتها غلط، خلال تمانية وأربعين ساعة.", { voice: "وحاجة تانية. لو حصة اتسجلت غلط، زي إن الطالب دخل دقيقتين وخرج، واتسجلت إنها اتعملت، المدرس يقدر يعترض خلال تمانية وأربعين ساعة من آخرها. من «أسبوعي» نروح للأسبوع اللي فات، وتحت الحصة ندوس «اعترض»." });
        await s.click(menuItem(s, 'أسبوعي'), { settle: 1600 });
        await s.click(s.page.getByRole('button', { name: 'الأسبوع اللي فات' }), { settle: 1600 });
        await s.click(s.page.getByRole('button', { name: 'اعترض' }).last(), { settle: 1400 });

        await s.say("إيه اللي حصل، والتفاصيل. الأكاديمية بس اللي بتشوفها.", { voice: "نختار إيه اللي حصل، ونكتب التفاصيل بالظبط. الكلام ده الأكاديمية بس اللي بتشوفه، والأسرة مابتعرفش هو قال إيه." });
        await s.click(s.modal().locator('label').filter({ hasText: 'الطالب ما حضرش' }).first(), { settle: 400 });
        await s.type(s.modal().locator('textarea').first(), 'تميم دخل دقيقتين بس وخرج، واستنيته لآخر الحصة.');

        await s.say("«ابعت الاعتراض»: الحصة بتبقى تحت المراجعة لحد ما الأكاديمية تقرّر.", { voice: "وندوس «ابعت الاعتراض». الحصة بتبقى تحت المراجعة، والأكاديمية بتراجع حضور الفصل، وتقرّر، والرصيد والمرتب بيمشوا ورا قرارها. والرد بيوصلك في الإشعارات وتحت الحصة." });
        await s.click(s.modal().getByRole('button', { name: 'ابعت الاعتراض' }), { settle: 2200 });
        await s.wait(1200);
      },
    },
    {
      n: 9, slug: 'performance-earnings', title: 'أدائي وأرباحي', voice: VOICES.mamdoh,
      goal: 'المدرس يشوف أداءه وأرباحه بشفافية.',
      learn: ['مؤشرات الأداء والتقييمات (من غير أسماء)', 'أرباح الشهر سطر بسطر', 'إمتى الشهر بيتقفل'],
      start: '/teach',
      async steps(s) {
        await s.say("«أدائي»: أرقامك في آخر تسعين يوم، وقدّام كل رقم هدف الأكاديمية.", { voice: "المدرس بيشوف أرقامه زي ما الأكاديمية بتشوفها بالظبط. من فوق «أدائي»: أرقامه في آخر تسعين يوم، وقدّام كل رقم هدف الأكاديمية. الأخضر يعني تمام، والأحمر يعني محتاج شغل." });
        await s.click(menuItem(s, 'أدائي'), { settle: 2000 });

        await s.say("الالتزام: المواعيد، والغياب، والإلغاء المتأخر، والوقت اللي اتدرّس.", { voice: "أول حاجة الالتزام: الالتزام بالمواعيد، ومتوسط التأخير، والغياب، والإلغاء المتأخر، والوقت اللي اتدرّس فعلًا من وقت الحصة." });
        await s.show(s.page.getByText('الالتزام بالمواعيد').first(), { hold: 1600 });

        await s.say("والجودة: تقييم الأسر، والتقارير في ميعادها.", { voice: "وبعدها الجودة: تقييم الأسر والشكاوي المؤكدة. والاحترافية: التقارير اللي اتسلّمت في ميعادها، والواجبات اللي اتصحّحت في خلال يومين. هنا مثلًا التقارير أحمر، عشان فيه تقارير لسه ماتسلّمتش." });
        await s.scroll(s.page.getByText('التقارير في ميعادها').first());
        await s.point(s.page.getByText('التقارير في ميعادها').first(), { hold: 1600 });

        await s.say("«اللي أثّر على الأرقام»: كل حصة بسببها. و«كلام الأسر» من غير أسماء.", { voice: "وتحت، «اللي أثّر على الأرقام»: كل حصة نزّلت رقم، وليه. و«كلام الأسر»: التقييمات بالنجوم والكلام، من غير اسم أي حد. ولو حاجة اتحسبت عليك وسببها مش منك، كلّم الإدارة." });
        await s.scroll(s.page.getByText('كلام الأسر').first());
        await s.wait(1800);

        await s.say("«أرباحي»: الشهر، والحصص، والساعات، والإجمالي.", { voice: "ودلوقتي «أرباحي». فوق الشهر، وعدد الحصص، والساعات، وإجمالي الأرباح." });
        await s.click(menuItem(s, 'أرباحي'), { settle: 2000 });

        await s.say("كل حصة سطر: ميعادها، والطالب، والمدة، والسعر.", { voice: "وكل حصة سطر لوحدها: ميعادها، والطالب، والمادة، والمدّة، وسعر الساعة، والمبلغ. والحصة التجريبية ليها سعرها لوحدها. وعلى الجنب المكافآت والخصومات، كل واحدة بسببها." });
        await s.show(s.page.getByText('المكافآت والخصومات').first(), { hold: 1800 });

        await s.say("الشهر مفتوح: الأرقام ممكن تتغيّر. بعد ما يخلص بيتقفل، وأرقامه بتبقى نهائية.", { voice: "وطول ما الشهر مفتوح، الأرقام ممكن تتغيّر، لو حصة اتراجعت مثلًا. وبعد ما الشهر يخلص، الأكاديمية بتقفله، وأرقامه بتبقى نهائية. وتحت خالص: أسعارك." });
        await s.point(s.page.getByText('مفتوح', { exact: true }).first(), { hold: 1400 });
        await s.scroll(s.page.getByRole('heading', { name: 'أسعارك' }));
        await s.wait(1400);
      },
    },
    {
      n: 10, slug: 'documents-support', title: 'مستنداتي والمساعدة والإبلاغ', voice: VOICES.hammam,
      goal: 'المدرس يرفع مستنداته ويطلب مساعدة ويبلّغ عن أي قلق على طالب.',
      learn: ['مستنداتي (الفيش الجنائي وغيره)', 'طلب دعم ومتابعته', 'الإبلاغ عن قلق على طالب، وبياناتي'],
      start: '/teach',
      async steps(s) {
        await s.say("«مستنداتي»: الفيش الجنائي وغيره. الأكاديمية بتراجعها على الأصل.", { voice: "[warmly] آخر درس في السلسلة. من فوق «مستنداتي»: المستندات اللي المدرّسة بعتتها، زي الفيش الجنائي، والأكاديمية بتراجعها على الأصل. والفيش الجنائي هو اللي بيخلّيها تدرّس لطلاب أقل من تمنتاشر سنة." });
        await s.click(menuItem(s, 'مستنداتي'), { settle: 1800 });
        await s.show(s.page.getByText('الفيش الجنائي بتاعك').first(), { hold: 1600 });

        await s.say("«ابعت مستند»: نوعه، وصالح لحد إمتى، والملف.", { voice: "ولو حاجة قربت تخلص، ندوس «ابعت مستند»: نختار نوعه، وصالح لحد إمتى، ونرفع الملف. والمنصّة بتفكّرك قبل ما يخلص بشهر." });
        await s.click(s.page.getByRole('button', { name: 'ابعت مستند' }), { settle: 1400 });
        await s.hush();
        await s.key('Escape');
        await s.wait(600);

        await s.say("«المساعدة»: تذكرة للأكاديمية، ومتابعة الرد.", { voice: "ولو فيه مشكلة، من «المساعدة» نفتح تذكرة. ندوس «تذكرة جديدة»، ونختار نوعها: مشكلة تقنية، أو فلوس، أو حاجة تانية." });
        await s.click(menuItem(s, 'المساعدة'), { settle: 1600 });
        await s.click(s.page.getByRole('button', { name: 'تذكرة جديدة' }).first(), { settle: 1400 });
        await s.click(s.modal().locator('label').filter({ hasText: 'مشكلة تقنية' }).first(), { settle: 400 });

        await s.say("العنوان، وحصل إيه. والرد بيوصلك هنا وفي الإشعارات.", { voice: "نكتب عنوان، ونحكي حصل إيه، وممكن نرفع صورة للشاشة. وندوس «ابعت». والرد بيوصلنا هنا وفي الإشعارات." });
        await s.type(s.modal().locator('.fi-fo-field').filter({ hasText: 'العنوان' }).locator('input').first(), 'الصوت بيقطع في الفصل');
        await s.type(s.modal().locator('textarea').first(), 'من امبارح الصوت بيقطع في نص الحصة، والنت عندي كويس.');
        await s.click(s.modal().getByRole('button', { name: 'ابعت' }), { settle: 2000 });

        await s.say("«بلّغ عن قلق»: لو حاجة في أمان طالب قلقاك.", { voice: "وأهم حاجة: «بلّغ عن قلق». لو حاجة في أمان طالب أو حالته قلقاك، تقول لمسؤولين الحماية في الأكاديمية هنا، وهم بس اللي بيقروه. ماتحقّقش فيه بنفسك، ولا تكلّم الأسرة عنه." });
        await s.click(menuItem(s, 'بلّغ عن قلق'), { settle: 1800 });

        await s.say("الموضوع، والطالب، والحصة، وحصل إيه. ولو حد في خطر: الطوارئ الأول.", { voice: "نختار الموضوع، والطالب، والحصة لو فيه، ونكتب اللي حصل بكلامنا. ولو حد في خطر دلوقتي، كلّم الطوارئ الأول، وبعدين اكتب هنا. واللي بلّغت بيه بيفضل على الجنب، بحالته." });
        await s.point(s.page.getByText('حاجة عملها حد كبير تاني قلقاني').first(), { hold: 1400 });
        await s.point(s.page.getByText('اللي قولتهولنا').first(), { hold: 1400 });

        await s.say("و«بياناتي»: تصحّح بياناتك، أو تاخد نسخة منها.", { voice: "وآخر حاجة «بياناتي»: تصحّح اسمك، أو تطلب تصحيح حاجة تانية، أو تجهّز نسخة من كل بياناتك. وبكده المدرّس عارف كل حاجة في مساحته." });
        await s.click(menuItem(s, 'بياناتي'), { settle: 2600 });
        await s.wait(2000);
      },
    },
  ],
};
