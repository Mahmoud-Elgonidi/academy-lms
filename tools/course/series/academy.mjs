// The academy's back office (/admin): owners, admins, schedulers, sales and the accountant.
import { totp } from '../lib/totp.mjs';

// Each lesson has its own Egyptian narrator from the ElevenLabs library (all spoken on eleven_v4).
const VOICES = {
  mostafa: 'FXJhBZJ1DJMO0U4ZPSur',   // Mostafa Mahmoud - Friendly
  farida: '6aXW46RTUz6Y2lkBGQ1a',    // Farida - Lively and Radiant
  hanafi: 'DWMVT5WflKt0P8OPpIrY',    // Hanafi
  yasmine: 'L10lEremDiJfPicq5CPh',   // Yasmine - Human-Like
  tarek: 'ckGEQg6YnSVooU5uDRsF',     // Tarek - Pleasant and Professional
  nadia: '80le32Uz2DfWFo4Foz0p',     // Nadia - Sweet and Melodic
  ramy: 'i2bK1jTwxz2ZhfV2NANO',      // Ramy Ibrahim - Natural and Smooth
  hazem: 'BEK0cM5ocuhluB2yKvMa',     // Hazem - Confident News Presenter
  fatima: 'I3u6waC588j43py1kDN2',    // Fatima - Expressive Egyptian
  momen: 'x6fvBLXv9YxzoVJQ0wp6',     // Mr. Momen - Warm, Mature, Calm
  ashraf: 'QfMIySsRuJWjZPZCnDQp',    // Ashraf - Warm and Genial
  masry: 'LXrTqFIgiubkrMkwvOUr',     // Masry - Narrative, Clear and Strong
  fatimaBook: 'vWDp3PLsTWjIhBxxUKh9', // Fatima - Smooth Audiobook Narrator
  ghozlanSupport: 'kQ2VDreF7XEea8InkZmA', // Ghozlan - Professional Support Agent
  marco: 'Ojb0nFbyzZn95u0i5a5p',     // Marco Nady - Confident and Calm
  mahmoud: '5b1u5jJPwwEj4YaHoUak',   // Mahmoud Hassan - Calm
  ahmed: 'bHCN6EPPyN5hYpU9UVUz',     // Ahmed - Clear, Natural and Neutral
};
const field = (s, name) => s.page.locator(`.fi-fo-field:has(label[for="form.${name}"])`);
/** A field by its label (toggle buttons have no <label for>). */
const labelled = (s, label) => s.page.locator('.fi-fo-field').filter({ has: s.page.locator('.fi-fo-field-label-content', { hasText: label }) }).first();

/** Opens a searchable select, types to narrow it (when asked) and picks the option with this text. */
async function choose(s, name, option, search) {
  const f = field(s, name);
  await s.click(f.locator('.fi-select-input-btn'), { settle: 300 });
  if (search) await s.page.keyboard.type(search, { delay: 110 });
  await s.wait(search ? 700 : 300);
  await s.click(f.locator('[role=option]').filter({ hasText: option }).first(), { settle: 600 });
}

const STUDENT = 'رنا محمود';

/** The side menu's item with exactly this label (s.nav matches any item that contains it). */
const menuItem = (s, label) => s.page.locator('.fi-sidebar-item').filter({ has: s.page.locator('.fi-sidebar-item-label', { hasText: new RegExp(`^\\s*${label}\\s*$`) }) }).locator('a, button').first();

/**
 * The owner's next authenticator code: the next half minute's, since the sign-in off camera may just
 * have used this one and a code is taken once. Without COURSE_TOTP_OWNER (writing the narration) a stand-in.
 */
const ownerCode = () => (process.env.COURSE_TOTP_OWNER ? totp(process.env.COURSE_TOTP_OWNER, Date.now() + 30000) : '000000');

export default {
  key: 'academy',
  title: 'سلسلة الأكاديمية',
  audience: 'لصاحب الأكاديمية وفريق الإدارة',
  color: '#14b8a6',
  device: 'desktop',
  user: 'owner',
  lessons: [
    {
      n: 1, slug: 'first-sign-in', title: 'أول دخول وجولة في لوحة الإدارة', voice: VOICES.mostafa, signsOut: true,
      goal: 'تدخل لوحة الأكاديمية بأمان وتعرف كل حاجة فين.',
      learn: ['الدخول بالإيميل أو الموبايل والتحقق بخطوتين', 'القائمة الجانبية ومجموعاتها والعدّادات', 'البحث والإشعارات و«إعداداتي»'],
      start: '/admin',
      async steps(s) {
        await s.say("ده حساب هالة، صاحبة الأكاديمية. نخرج الأول عشان نشوف الدخول من أوّله.", { voice: "[warmly] أهلًا بيك. ده حساب هالة، صاحبة الأكاديمية. هنخرج الأول من الحساب، عشان نشوف الدخول من أوّله." });
        await s.click(s.page.locator('.fi-topbar .fi-avatar').first(), { settle: 600 });
        await s.click(s.page.getByRole('button', { name: 'تسجيل الخروج' }).first(), { settle: 1500 });

        await s.say("الدخول بالإيميل أو رقم الموبايل، وكلمة المرور.", { voice: "صفحة الدخول: نكتب الإيميل أو رقم الموبايل، وكلمة المرور، وندوس «تسجيل الدخول». ولو حد نسي كلمة السر، يقدر يدخل بكود يوصله على موبايله." });
        await s.type(s.page.locator('#form\\.email'), 'owner@demo.test');
        await s.type(s.page.locator('#form\\.password'), process.env.COURSE_PASSWORD ?? '');
        await s.click(s.page.getByRole('button', { name: 'تسجيل الدخول' }).first(), { settle: 2000 });

        await s.say("صاحب الأكاديمية والمدير والمحاسب لازم التحقق بخطوتين: كود من تطبيق على الموبايل.", { voice: "وعشان الحساب ده فيه فلوس وبيانات ناس، صاحب الأكاديمية والمدير والمحاسب لازم يدخلوا بخطوتين: بعد كلمة السر، كود من ست أرقام من تطبيق المصادقة على الموبايل، وبيتغيّر كل نص دقيقة." });
        await s.type(s.page.locator('form input:not([type=hidden]):not([type=checkbox])').first(), ownerCode(), { delay: 120 });
        await s.click(s.page.getByRole('button', { name: 'تأكيد تسجيل الدخول' }).first(), { settle: 2500 });

        await s.say("أول شاشة: «العمليات اللحظية»، كل حصص النهارده وإيه اللي محتاج تدخّل.", { voice: "وده أول ما ندخل: شاشة «العمليات اللحظية». فيها كل حصص النهارده، ومين دخل ومين اتأخر، وإيه اللي محتاج تدخّل دلوقتي. ودي ليها درس لوحدها في السلسلة." });
        await s.wait(1500);

        await s.say("القائمة على الجنب: الشغل اليومي فوق، والعدّاد جنب كل حاجة مستنياك.", { voice: "وعلى الجنب القائمة. فوق الشغل اليومي: التقويم، وابحث عن مدرس، ومقترحات الشفتات، وإجازات المدرسين، والاعتراضات. والرقم اللي جنب أي واحدة، هو عدد الحاجات اللي مستنياك فيها." });
        await s.show(s.nav('مقترحات الشفتات'), { hold: 1400 });
        await s.show(s.nav('الاعتراضات'), { hold: 1200 });

        await s.say("وتحت مجموعات: المبيعات، والمدرسين والأسر، والتعليم، والتواصل، والإعدادات.", { voice: "وتحت، باقي الحاجات متقسّمة مجموعات: المبيعات، والمدرسين والأسر، والتعليم، والتواصل، وفي الآخر الإعدادات، وفيها «ابدأ هنا»، ودي قايمة بكل اللي لازم يتظبط قبل ما الأسر تيجي." });
        await s.scroll(s.nav('المدرسين'));
        await s.wait(900);
        await s.scroll(s.nav('ابدأ هنا'));
        await s.show(s.nav('ابدأ هنا'), { hold: 1600 });

        await s.say("البحث فوق: اسم طالب أو مدرس أو رقم فاتورة.", { voice: "وفوق خانة البحث: نكتب اسم طالب، أو مدرس، أو رقم فاتورة، والنتايج بتطلع على طول، ودوسة بتفتح الصفحة بتاعته." });
        await s.type(s.page.locator('.fi-global-search-field input'), 'رنا');
        await s.wait(1200);
        await s.show(s.page.locator('.fi-global-search-results-ctn'), { hold: 1600 });
        await s.key('Escape');

        await s.say("الجرس: كل الإشعارات، ودوسة على أي واحد بتفتح مكانه.", { voice: "وجنبها الجرس: كل الإشعارات اللي وصلت، زي فاتورة اتدفعت، أو مدرس طلب إجازة. ودوسة على أي إشعار بتفتح مكانه." });
        await s.click(s.page.locator('.fi-topbar .fi-icon-btn').filter({ has: s.page.locator('.fi-badge') }).first(), { settle: 1200 });
        await s.wait(1400);
        await s.key('Escape');

        await s.say("ومن الصورة ← «إعداداتي»: اللغة، والتوقيت، والإشعارات، والتحقق بخطوتين.", { voice: "وآخر حاجة: من الصورة اللي فوق، «إعداداتي». هنا اللغة، والتوقيت، وإزاي توصلك الإشعارات، والتحقق بخطوتين، وكلمة السر." });
        await s.click(s.page.locator('.fi-topbar .fi-avatar').first(), { settle: 600 });
        await s.click(s.page.locator('.fi-dropdown-list-item').filter({ hasText: 'إعداداتي' }).first(), { settle: 1800 });
        await s.wait(1500);
      },
    },
    {
      n: 2, slug: 'branding-and-plan', title: 'هوية الأكاديمية وباقتها', voice: VOICES.farida,
      outro: 'كده خلّصنا درس الهوية والباقة. في الدرس الجاي: المواد والمستويات والمناهج.',
      goal: 'تخلّي المنصة شبه أكاديميتك: الاسم والشعار والألوان، وتعرف حدود باقتك.',
      learn: ['الاسم والشعار وقوالب الألوان', 'شكل المنصة عند المدرسين والأسر والتطبيق', 'صفحة «باقة الأكاديمية» والحدود والاستهلاك'],
      start: '/admin',
      async steps(s) {
        await s.say("الإعدادات ← «الهوية والألوان»: شكل الأكاديمية في كل مكان.", { voice: "[cheerful] أول حاجة نخلّي المنصة شبه أكاديميتنا. من الإعدادات، نفتح «الهوية والألوان». اللي بنظبطه هنا بيظهر في لوحة الإدارة، وعند المدرسين، وعند الأسر، وفي التطبيق كمان." });
        await s.scroll(s.nav('الهوية والألوان'));
        await s.click(s.nav('الهوية والألوان'), { settle: 1800 });

        await s.say("القوالب: ألوان وخطوط جاهزة، والمعاينة بتتغيّر قدامك.", { voice: "فوق، قوالب جاهزة: كل قالب فيه ألوان وخطوط متناسقة. ولما ندوس على واحد، المعاينة اللي تحت بتتغيّر قدامنا على طول، ومفيش حاجة بتتغيّر عند حد غير لما نحفظ." });
        await s.click(s.page.getByRole('button', { name: /^البنفسج/ }), { settle: 1200 });
        await s.scroll(s.page.getByText('معاينة', { exact: true }).first());
        await s.wait(1600);

        await s.say("الهوية: اسم الأكاديمية بالعربي والإنجليزي، واللوجو، وأيقونة المتصفح.", { voice: "وفي «الهوية»: اسم الأكاديمية بالعربي والإنجليزي، واللوجو اللي بيظهر في كل الشاشات والإيميلات، وأيقونة المتصفح الصغيرة." });
        await s.show(labelled(s, 'اسم الأكاديمية بالعربي'), { hold: 1400 });

        await s.say("الألوان: الأساسي، والتنبيه، والخطر، والمعلومات، وكل لون ليه استخدام ثابت.", { voice: "وبعدها الألوان: اللون الأساسي للزراير والقايمة، ولون التنبيه للحاجات اللي محتاجة متابعة، ولون الخطر للغياب والإلغاء، ولون المعلومات. كل لون ليه استخدام ثابت في كل الشاشات." });
        await s.scroll(labelled(s, 'اللون الأساسي'));
        await s.show(labelled(s, 'اللون الأساسي'), { hold: 1500 });

        await s.say("والخطوط وشكل الحواف: حادة، أو ناعمة، أو دائرية.", { voice: "وفي الآخر الخطوط، خط للعناوين وخط للنصوص، وشكل الحواف: حادة، أو ناعمة، أو دائرية." });
        await s.scroll(s.page.locator('label').filter({ hasText: 'دائرية' }).first());
        await s.click(s.page.locator('label').filter({ hasText: 'دائرية' }).first(), { settle: 1200 });

        await s.say("«احفظ وطبّق»، أو «احفظ كقالب جديد» عشان ترجعله بعدين.", { voice: "ولما يعجبنا الشكل، ندوس «احفظ وطبّق»، فيتطبّق على الكل. أو «احفظ كقالب جديد»، عشان نرجعله بعدين." });
        await s.scroll(-4000);
        await s.point(s.page.getByRole('button', { name: 'احفظ وطبّق' }), { hold: 1600 });

        await s.say("و«باقة الأكاديمية»: اللي باقتك بتسمح بيه، واستخدمت منه قد إيه.", { voice: "و«باقة الأكاديمية»، آخر حاجة في الإعدادات: هنا اللي باقتنا بتسمح بيه، واستخدمنا منه قد إيه دلوقتي." });
        await s.click(s.nav('باقة الأكاديمية'), { settle: 1800 });

        await s.say("المدرسين، والطلاب النشطين، ودقايق الفيديو، والمساحة، والمميزات اللي في الباقة.", { voice: "عدد المدرسين، والطلاب النشطين، ودقايق الفيديو في الشهر، ومساحة الملفات. ولما نقرّب من الحد، بيظهر تنبيه فوق في كل الشاشات، زي هنا: تسعة مدرسين من عشرة. وتحت، المميزات اللي في الباقة، واللي مش فيها." });
        await s.show(s.page.locator('.pp-meters').first(), { hold: 2000 });
        await s.show(s.page.locator('.pp-features-row, .pp-features').first(), { hold: 1400 });

        await s.say("عايز تكبّر الباقة؟ فريق المنصة بيغيّرها من عنده.", { voice: "ولو عايزين نكبّر الباقة، أو نضيف ميزة، بنكلّم فريق المنصة، وهمّا بيغيّروها من عندهم." });
        await s.wait(1500);
      },
    },
    {
      n: 3, slug: 'subjects-and-curriculum', title: 'المواد والمستويات والمناهج', voice: VOICES.hanafi,
      goal: 'تبني كتالوج المواد ومنهج كل مادة بالدروس والمواد التعليمية.',
      learn: ['مادة بمستويات مرتبة وتصنيفات (مسارات ومناهج)', 'منشئ المنهج: برنامج ← مستوى ← وحدة ← درس', 'ملفات ولينكات الدرس ومين يشوفها، والنشر'],
      start: '/admin',
      async steps(s) {
        await s.say("التعليم ← «المواد»: كل مادة بمستوياتها وتصنيفاتها، وعدد مدرسينها.", { voice: "[warmly] نبدأ بالمواد. من مجموعة التعليم نفتح «المواد». كل مادة قدامها مستوياتها بالترتيب، وتصنيفاتها، وعدد المدرسين اللي بيدرّسوها." });
        await s.scroll(s.nav('المواد'));
        await s.click(s.nav('المواد'), { settle: 1800 });
        await s.show(s.page.locator('.fi-ta-row').first(), { hold: 1600 });

        await s.say("«تعديل»: المستويات بالترتيب، والتصنيفات زي المسارات والمناهج.", { voice: "ولو دوسنا «تعديل» على مادة: المستويات بترتيبها، من الأسهل للأصعب، وزرار «ضيف مستوى». وتحتها التصنيفات، زي محادثة، أو منهج بريطاني. والمستويات والتصنيفات دي هي اللي بنبحث بيها عن المدرس المناسب بعدين." });
        await s.click(s.page.getByRole('button', { name: 'تعديل' }).first(), { settle: 1500 });
        await s.show(s.modal(), { hold: 2200 });
        await s.click(s.modal().getByRole('button', { name: 'إلغاء' }), { settle: 900 });

        await s.say("و«المناهج»: كل برنامج بنسخته، ودروسه، وطلابه.", { voice: "وبعدها «المناهج». كل برنامج هنا منهج لمادة: نسخته، وعدد دروسه، وعدد الطلاب اللي ماشيين فيه. ندوس «المحتوى» عشان نشوفه من جوّه." });
        await s.click(s.nav('المناهج'), { settle: 1800 });
        await s.click(s.page.locator('.fi-ta-row').first().getByRole('link', { name: 'المحتوى' }), { settle: 2000 });

        await s.say("المنهج: مستويات، جوّاها وحدات، جوّاها دروس، والأسهم بترتّبهم.", { voice: "وده منشئ المنهج: البرنامج مقسوم مستويات، وكل مستوى فيه وحدات، وكل وحدة فيها دروس. والأسهم اللي جنب كل حاجة بتغيّر ترتيبها، وزراير «درس» و«وحدة» و«مستوى جديد» بتضيف." });
        await s.show(s.page.locator('.cb-level, [class*=cb-]').first(), { hold: 1800 });

        await s.say("لكل درس: مواده، وتدريباته اللي الطالب بيلعب بيها.", { voice: "ولكل درس: المواد بتاعته، والتدريبات اللي الطالب بيراجع بيها في الألعاب. ندوس «من غير مواد» على درس الألوان، ونضيف لينك." });
        await s.click(s.page.locator('button.cb-mats').nth(2), { settle: 1200 });
        await s.click(s.page.getByRole('button', { name: 'ضيف لينك' }).first(), { settle: 1400 });

        await s.say("اللينك واسمه، ومين يشوفه: كل اللي في الحصة، أو المدرسين بس.", { voice: "نكتب اللينك واسمه، ونختار مين يشوفه: كل اللي في الحصة، يعني المدرس والطالب وأسرته، أو المدرسين بس، زي ملف الإجابات. وندوس «إرسال»." });
        await s.type(s.modal().locator('input').first(), 'https://learnenglish.britishcouncil.org/vocabulary/a1-a2-vocabulary/colours', { delay: 18 });
        await s.type(s.modal().locator('input').nth(1), 'الألوان بالصور');
        await s.click(s.modal().locator('label').filter({ hasText: 'كل اللي في الحصة' }).first(), { settle: 400 });
        await s.click(s.modal().getByRole('button', { name: 'إرسال' }), { settle: 1800 });

        await s.say("والنسخة المنشورة: تعدّل وتزوّد، لكن المسح مقفول عشان تقدّم الطلاب.", { voice: "وفوق مكتوب إن النسخة دي منشورة. يعني نقدر نعدّل العناوين ونزوّد دروس ومواد، لكن المسح مقفول، عشان تقدّم الطلاب اللي ماشيين فيها ما يضيعش." });
        await s.scroll(-4000);
        await s.show(s.page.getByText('النسخة دي منشورة', { exact: false }).first(), { hold: 2000 });
      },
    },
    {
      n: 4, slug: 'teachers', title: 'المدرسين: الإضافة والملف والفحص', voice: VOICES.yasmine,
      goal: 'تضيف مدرس وتظبط ملفه عشان يظهر صح في البحث.',
      learn: ['إضافة مدرس ورسالة الترحيب', 'المواد والمستويات والأعمار والحد اليومي', 'فحص المدرسين: المستندات والاعتماد والانتهاء'],
      start: '/admin',
      async steps(s) {
        const subject = s.page.locator('.fi-fo-repeater .fi-fo-field').filter({ has: s.page.locator('.fi-fo-field-label-content', { hasText: 'المادة' }) }).first();

        await s.say("«المدرسين والأسر» ← «المدرسين»: كل مدرس بمواده، والأعمار، وساعاته في الأسبوع.", { voice: "[warmly] من «المدرسين والأسر» نفتح «المدرسين». كل مدرس قدامه مواده، ونوعه، والأعمار اللي بيدرّسها، وأقصى ساعات في الأسبوع. و«ابحث عن مدرس» بيدوّر بالبيانات دي بالظبط." });
        await s.scroll(menuItem(s, 'المدرسين'));
        await s.click(menuItem(s, 'المدرسين'), { settle: 1800 });

        await s.say("«مدرس جديد»: الاسم، والإيميل، والموبايل، والتوقيت.", { voice: "نضيف مدرسة جديدة: ندوس «مدرس جديد»، ونكتب الاسم، والإيميل، ورقم الموبايل اللي عليه واتساب. والتوقيت بيتحط لوحده من كود الدولة." });
        await s.click(s.page.getByRole('link', { name: 'مدرس جديد' }), { settle: 1800 });
        await s.type(field(s, 'name'), 'أ. ياسمين عادل');
        await s.type(field(s, 'email'), 'yasmine@demo.test', { delay: 35 });
        await s.type(field(s, 'phone'), '+201001230011', { delay: 45 });

        await s.say("التدريس: النوع، واللغات، والأعمار، وأقصى ساعات وحصص.", { voice: "وفي «التدريس»: نوعها، ولغات الشرح، والأعمار اللي بتدرّسها، وأقصى ساعات في الأسبوع، وأقصى حصص في اليوم. ولما توصل للحد اليومي، البحث مابيقترحش اليوم ده." });
        await s.click(labelled(s, 'النوع').locator('label').filter({ hasText: 'ست' }).first(), { settle: 300 });
        await s.click(labelled(s, 'الأعمار اللي بيدرّسها').locator('label').filter({ hasText: 'أطفال' }).first(), { settle: 300 });
        await s.click(labelled(s, 'الأعمار اللي بيدرّسها').locator('label').filter({ hasText: 'إعدادي' }).first(), { settle: 300 });
        await s.type(field(s, 'max_weekly_hours'), '20');
        await s.type(field(s, 'max_daily_lessons'), '6');

        await s.say("المواد: المادة، ومن مستوى لحد مستوى، والتصنيفات.", { voice: "وبعدها المواد: نختار المادة، وممكن نحدد من أنهي مستوى لحد أنهي مستوى، والتصنيفات اللي بتدرّسها. ولو بتدرّس أكتر من مادة، «ضيف مادة»." });
        await s.scroll(subject);
        await s.select(subject.locator('select'), { label: 'قرآن · حفظ' });

        await s.say("ورسالة الترحيب: لينك تختار بيه كلمة السر، ونضغط «إضافة».", { voice: "وفي الآخر رسالة الترحيب: بتوصلها على واتساب والإيميل، وفيها لينك تختار بيه كلمة السر بنفسها. ندوس «إضافة»." });
        await s.scroll(s.page.getByRole('button', { name: 'إضافة' }).last());
        await s.click(s.page.getByRole('button', { name: 'إضافة' }).last(), { settle: 2500 });

        await s.say("«فحص المدرسين»: المستندات اللي المدرسين رفعوها، تراجعها وتأكّدها.", { voice: "وأمان الطلاب الصغيرين مهم جدًا. في «فحص المدرسين»: المستندات اللي المدرسين رفعوها، زي الفيش الجنائي والشهادات. نراجع كل مستند على الأصل، ونأكّده أو نرفضه." });
        await s.click(s.nav('فحص المدرسين'), { settle: 1800 });
        await s.show(s.page.getByRole('heading', { name: 'مستني المراجعة' }), { hold: 1200 });

        await s.say("«أكّد»، وتحدد صالح لحد إمتى، والمنصّة بتفكّرك قبل ما يخلص بشهر.", { voice: "ندوس «أكّد» على الفيش الجنائي بتاع أستاذ خالد. ولو ليه تاريخ انتهاء نحدده، والمنصّة بتفكّرنا قبل ما يخلص بشهر." });
        await s.click(s.page.getByRole('button', { name: 'أكّد' }).first(), { settle: 1200 });
        await s.click(s.modal().getByRole('button', { name: 'إرسال' }), { settle: 2000 });

        await s.say("وقاعدة أقل من 18: من غير فيش متأكّد، مفيش حصص جديدة مع الصغيرين.", { voice: "وتحت، كل المدرسين اللي معاهم طلاب أقل من تمنتاشر سنة. ولما القاعدة دي شغّالة، المدرس اللي مالوش فيش جنائي متأكّد وساري، مايقدرش ياخد حصص جديدة مع طالب صغير. وزرار «وقّف» بيوقّفه عنهم لو فيه سبب." });
        await s.scroll(s.page.getByRole('heading', { name: /كل المدرسين مع طلاب/ }));
        await s.wait(2200);
      },
    },
    {
      n: 5, slug: 'shifts-and-time-off', title: 'الشفتات والإجازات والمدرس البديل', voice: VOICES.tarek,
      goal: 'تعتمد أوقات المدرسين وتدير إجازاتهم، وكل حصة تلاقي مدرس.',
      learn: ['مراجعة مقترح شفتات: المقارنة وتحليل الأثر والاعتماد', 'طلبات الإجازة وتعيين مدرس بديل', 'إجازات الأكاديمية والعطلات الرسمية'],
      start: '/admin',
      async steps(s) {
        const visible = (text) => s.page.getByText(text).locator('visible=true').first();

        await s.say("«مقترحات الشفتات»: المدرس بيقترح مواعيد شغله، وانت بتراجع وتعتمد.", { voice: "[warmly] المدرس بيقترح مواعيد شغله من عنده، والإدارة بتراجعها وتعتمدها. من القايمة نفتح «مقترحات الشفتات». فوق اللي مستني ردّنا، وكل واحد قدامه الساعات قبل وبعد، وفيه تعارضات ولا لأ." });
        await s.click(menuItem(s, 'مقترحات الشفتات'), { settle: 1800 });

        await s.say("نفتح مقترح أستاذة سارة: فيه 3 تعارضات.", { voice: "نفتح مقترح أستاذة سارة، عليه تلات تعارضات." });
        await s.click(visible('أ. سارة عبد الرحمن'), { settle: 2000 });

        await s.say("الجدول الحالي مقابل المقترح: الأخضر مضاف، والمخطط محذوف، والأحمر حصة محجوزة برّه الشفت.", { voice: "هنا الجدول الحالي جنب المقترح: الرمادي زي ما هو، والأخضر مضاف، والمخطّط بالأحمر محذوف. والنقطة الحمرا معناها حصة محجوزة هتبقى برّه الشفت الجديد." });
        await s.show(s.page.getByRole('heading', { name: 'الجدول الحالي مقابل المقترح' }).locator('xpath=ancestor::section[1]'), { hold: 2200 });

        await s.say("«الأثر على الطلاب»: كل حصة متأثرة وحلّها: مدرس تاني، أو استثناء، أو تأخير البداية.", { voice: "وتحت «الأثر على الطلاب المحجوزين»: كل طالب حصته هتتأثر، ولكل واحد حل: ننقله لمدرس تاني، أو استثناء متفق عليه مع المدرس، أو نأخّر تاريخ بداية الجدول الجديد. ولازم كله يتحل قبل الاعتماد." });
        await s.scroll(s.page.getByRole('heading', { name: 'الأثر على الطلاب المحجوزين' }));
        await s.wait(2200);

        await s.say("والمحادثة مع المدرس على المقترح، ورفض، أو طلب تعديل، أو عرض مضاد.", { voice: "وجنبها محادثة مع المدرس على المقترح نفسه. ومن فوق: نرفض، أو نطلب تعديل، أو نعدّل بنفسنا ونبعت عرض مضاد." });
        await s.scroll(-3000);
        await s.show(s.page.getByRole('button', { name: /عدّل وأرسل/ }), { hold: 1600 });

        await s.say("ومقترح أستاذ أحمد مفيش فيه تعارض: «اعتماد الجدول».", { voice: "ونرجع للقايمة. مقترح أستاذ أحمد مفيش فيه أي تعارض، فنفتحه وندوس «اعتماد الجدول»، ونأكّد. الجدول الجديد بيبدأ في ميعاده، والحصص المحجوزة بتفضل زي ما هي." });
        await s.click(menuItem(s, 'مقترحات الشفتات'), { settle: 1600 });
        await s.click(visible('أ. أحمد فؤاد'), { settle: 1800 });
        await s.click(s.page.getByRole('button', { name: 'اعتماد الجدول' }), { settle: 1000 });
        await s.click(s.modal().getByRole('button', { name: 'تأكيد' }), { settle: 2000 });

        await s.say("«إجازات المدرسين»: الطلبات، واللي محتاجة بديل.", { voice: "وبعدها الإجازات. «إجازات المدرسين»: الطلبات اللي مستنية ردّنا، والإجازات اللي اتوافق عليها ولسه فيها حصص محتاجة بديل." });
        await s.click(menuItem(s, 'إجازات المدرسين'), { settle: 1800 });
        await s.click(s.page.getByText('افتح').locator('visible=true').nth(1), { settle: 1800 });

        await s.say("كل حصة في الإجازة: بديل اتعيّن، أو تتلغي بتعويض للطالب.", { voice: "دي إجازة أستاذة نور. كل حصة في أيام الإجازة قدامنا: الأولى أستاذة سارة هتغطيها كبديل. والباقيين مفيش مدرس فاضي للمادة في الوقت ده، فنلغيهم بتعويض، والطالب بياخد حصته في ميعاد تاني. وزرار «غطّي كل الحصص» بيدوّر على بديل للكل مرة واحدة." });
        await s.show(s.page.getByText('مع أ. سارة عبد الرحمن').first(), { hold: 1400 });
        await s.click(s.page.getByRole('button', { name: 'ألغِ بتعويض' }).first(), { settle: 2000 });

        await s.say("و«إجازات الأكاديمية»: العطلات الرسمية، وحصصها بتترحّل والأيام بترجع للرصيد.", { voice: "وفي الإعدادات، «إجازات الأكاديمية»: العطلات الرسمية والإجازات العامة. الحصص اللي فيها بتتلغي، ودقايقها بترجع لرصيد الطلاب، والأسر بيوصلها إشعار." });
        await s.scroll(menuItem(s, 'إجازات الأكاديمية'));
        await s.click(menuItem(s, 'إجازات الأكاديمية'), { settle: 1800 });
        await s.wait(1600);
      },
    },
    {
      n: 6, slug: 'students-and-guardians', title: 'الطلاب وأولياء الأمور', voice: VOICES.nadia,
      goal: 'تضيف طالب وولي أمره وتظبط مرحلته وموافقته.',
      learn: ['طالب صغير تحت ولي أمر، وطالب كبير بحسابه', 'المرحلة: أطفال، إعدادي، ثانوي، كبار', 'موافقات أولياء الأمور وسن الموافقة'],
      start: '/admin',
      async steps(s) {
        await s.say("«الطلاب» ← «طالب جديد»: الاسم، وهل بيدخل بنفسه.", { voice: "[warmly] نضيف طالب جديد. من «المدرسين والأسر» نفتح «الطلاب»، وندوس «طالب جديد». نكتب الاسم. ولو الطالب صغير ومالوش إيميل ولا رقم، بنسيب الزرار ده مقفول، فحسابه بيبقى تحت ولي أمره، وولي الأمر هو اللي بيدخل." });
        await s.scroll(menuItem(s, 'الطلاب'));
        await s.click(menuItem(s, 'الطلاب'), { settle: 1800 });
        await s.click(s.page.getByRole('link', { name: 'طالب جديد' }), { settle: 1800 });
        await s.type(field(s, 'name'), 'مالك حسن');
        await s.show(labelled(s, 'الطالب بيدخل بنفسه'), { hold: 1400 });

        await s.say("«عن الطالب»: الميلاد، وشكل حسابه، والنوع، والصف، وجنس المدرس.", { voice: "وفي «عن الطالب»: تاريخ الميلاد، و«شكل حسابه»: أطفال، أو إعدادي، أو ثانوي، أو عادي للكبار. ولو سبناه «من سنّه»، المنصّة بتختار لوحدها من تاريخ الميلاد. وبعدها النوع، والصف، ولو الأسرة عايزة مدرس راجل أو ست." });
        await s.select(field(s, 'stage').locator('select'), 'kids');
        await s.click(labelled(s, 'النوع').locator('label').filter({ hasText: 'ولد' }).first(), { settle: 300 });
        await s.select(field(s, 'school_grade').locator('select'), 'g2');

        await s.say("ولي الأمر: موجود عندنا، أو جديد بالاسم والموبايل.", { voice: "وبعدها ولي الأمر: لو موجود عندنا، زي أخ تاني متسجّل، نختاره. ولو جديد، نكتب اسمه، وموبايله، وإيميله، وصلة القرابة." });
        await s.scroll(field(s, 'guardian_name'));
        await s.type(field(s, 'guardian_name'), 'حسن إبراهيم');
        await s.type(field(s, 'guardian_phone'), '+966501112233', { delay: 45 });

        await s.say("ورسالة الترحيب بتروح لولي الأمر، و«إضافة».", { voice: "ورسالة الترحيب بتروح لولي الأمر، فيها لينك يختار بيه كلمة السر، والطفل نفسه مابيوصلوش رسايل. ندوس «إضافة»." });
        await s.scroll(s.page.getByRole('button', { name: 'إضافة' }).last());
        await s.click(s.page.getByRole('button', { name: 'إضافة' }).last(), { settle: 2500 });

        await s.say("«موافقات أولياء الأمور»: الصغيرين محتاجين موافقة قبل ما يدخلوا.", { voice: "وأي طالب أصغر من سن الموافقة، محتاج ولي أمره يوافق قبل ما يدخل حسابه أو يحضر بنفسه. ده كله في «موافقات أولياء الأمور»." });
        await s.scroll(menuItem(s, 'موافقات أولياء الأمور'));
        await s.click(menuItem(s, 'موافقات أولياء الأمور'), { settle: 1800 });

        await s.say("سن الموافقة، والمستنيين، و«ابعت الطلب تاني» أو «سجّل موافقة عندنا».", { voice: "فوق سن الموافقة اللي الأكاديمية حددته، وده بيتغيّر من الزرار. وتحت الطلاب المستنيين: نبعت لولي الأمر الطلب تاني، أو لو وافق ورقيًا أو في التليفون، نسجّل موافقته عندنا بملاحظة. وكل خطوة متسجّلة في «السجل»." });
        await s.show(s.page.getByRole('button', { name: /سن الموافقة/ }), { hold: 1400 });
        await s.show(s.page.locator('.fi-ta-row').first(), { hold: 2000 });
      },
    },
    {
      n: 7, slug: 'find-teacher-enroll', title: 'ابحث عن مدرس وسجّل طالب',
      goal: 'تلاقي المدرس المناسب في الأوقات اللي تناسب الطالب، وتسجّله في اشتراك أسبوعي وتطلّع له الفاتورة.',
      learn: ['تختار الطالب والمادة والشروط', 'تحدد الأوقات المناسبة وتقرا النتايج', 'تحجز مبدئيًا وتسجّل وتصدر الفاتورة'],
      start: '/admin',
      async steps(s) {
        await s.say("من القائمة الجانبية افتح «ابحث عن مدرس». من الصفحة دي بتسجّل أي طالب في مادة جديدة.", { voice: "من القائمة اللي على الجنب، هندوس على «ابحث عن مدرس». الصفحة دي هي المكان اللي بنسجّل منه أي طالب في أي مادة، سواء هياخد حصص ثابتة كل أسبوع، أو هيجرّب الأول بحصة واحدة." });
        await s.click(s.nav('ابحث عن مدرس'), { settle: 1200 });

        await s.say("فوق بتختار نوع الحجز: «اشتراك أسبوعي» لحصص ثابتة كل أسبوع، أو «حصة تجريبية» لحصة واحدة.", { voice: "فوق خالص فيه اختيارين: «اشتراك أسبوعي»، ودي الحصص الثابتة اللي بتتكرر كل أسبوع في نفس المواعيد. و«حصة تجريبية»، ودي حصة واحدة الطالب بيجرّب فيها المدرس قبل ما يشترك. إحنا هنكمّل بالاشتراك الأسبوعي." });
        await s.point(s.page.locator('.fi-fo-toggle-buttons').first(), { hold: 1600 });

        await s.say("اختار الطالب. ابدأ اكتب اسمه، وتوقيته وسنّه بيتحطوا لوحدهم من ملفه.", { voice: "أول حاجة نختار الطالب: نكتب أول حروف من اسمه، ونختاره من القايمة. لاحظ إن توقيت الطالب اتحط لوحده من ملفه، وكمان سنّه. وده مهم جدًا، لأن كل المواعيد هتظهر بتوقيت الطالب هو، مش بتوقيتك إنت." });
        await choose(s, 'student_id', STUDENT, 'رنا');
        await s.show(field(s, 'timezone'), { hold: 1200 });

        await s.say("اختار المادة. لو المادة ليها مستويات أو تصنيفات، حددها هنا عشان توصل للمدرس المناسب بالظبط.", { voice: "بعد كده نختار المادة، وهنا هنختار قرآن حفظ. ولو المادة ليها مستويات أو تصنيفات، زي المبتدئين أو منهج معيّن، نحددها من هنا، فالبحث يجيب لنا بس المدرسين اللي بيدرّسوا المستوى ده." });
        await choose(s, 'subject_id', 'قرآن');
        await s.show(field(s, 'level_id'), { hold: 1100 });

        await s.say("حدد جنس المدرس، وعدد الحصص في الأسبوع، ومدة الحصة، وتاريخ البداية.", { voice: "وتحت، بنحدد شروط الحصص نفسها: جنس المدرس، لو الأسرة عندها تفضيل. وعدد الحصص في الأسبوع. ومدة الحصة، نص ساعة، أو خمسة وأربعين دقيقة، أو ساعة. وتاريخ بداية الاشتراك." });
        await s.point(labelled(s, 'جنس المدرس'), { hold: 900 });
        await s.point(field(s, 'per_week'), { hold: 700 });
        await s.point(labelled(s, 'مدة الحصة'), { hold: 700 });

        await s.say("«الحد الأدنى للتوافر»: الميعاد لازم يكون فاضي 8 أسابيع ورا بعض، أو تقبل أسبوع واحد ناقص.", { voice: "و«الحد الأدنى للتوافر» معناه إن النظام بيتأكد إن الميعاد فاضي عند المدرس تمن أسابيع ورا بعض. ولو عايز نتايج أكتر، ممكن تقبل إن أسبوع واحد بس يكون فيه تعارض، والنظام هيقولك عليه." });
        await s.point(labelled(s, 'الحد الأدنى للتوافر'), { hold: 1400 });

        await s.say("في «الأوقات المناسبة للطالب» دوس على الساعات اللي تناسبه، بتوقيته هو.", { voice: "ودلوقتي أهم جزء: شبكة الأوقات. كل عمود يوم، وكل صف ساعة، وكله بتوقيت الطالب. بندوس على الساعات اللي تناسب الطالب وأسرته. هنا اخترنا السبت والاتنين والأربع، من خمسة لسبعة بالليل." });
        await s.scroll(s.page.locator('.ft-grid'));
        for (const day of [6, 1, 3]) {
          for (const hour of [17, 18, 19]) await s.click(s.page.locator(`button.ft-cell[wire\\:click="toggleCell(${day}, ${hour})"]`), { settle: 120, ringFirst: false });
        }

        await s.say("ودوس «ابحث».", { voice: "ولما نخلّص، ندوس «ابحث»." });
        await s.click(s.page.locator('.ft-grid-tools').getByRole('button', { name: 'ابحث' }), { settle: 1200 });
        await s.scroll(-2000);

        await s.say("النتيجة: المدرسين مترتبين حسب «الملاءمة» (قد إيه مواعيدهم فاضية في الأوقات دي)، و«الإشغال» يعني قد إيه جدوله مليان.", { voice: "ودول المدرسين المناسبين. الترتيب حسب «الملاءمة»، يعني قد إيه مواعيد المدرس الفاضية بتغطي الأوقات اللي اخترناها. وجنبها «الإشغال»، وده بيقولك جدول المدرس مليان قد إيه، عشان توزّع الطلاب على المدرسين بعدل." });
        await s.show(s.page.locator('.ft-teacher').first().locator('.ft-teacher-head'), { hold: 2200 });

        await s.say("كل ميعاد مكتوب بتوقيت الطالب بالخط الكبير، وتحته بتوقيت المدرس، فمفيش لخبطة في فرق التوقيت.", { voice: "كل ميعاد في المربعات دي مكتوب بالخط الكبير بتوقيت الطالب، وتحته بخط صغير بتوقيت المدرس. يعني لو الطالب في السعودية والمدرس في مصر، كل واحد شايف الميعاد بتوقيته، ومفيش أي لخبطة." });
        await s.point(s.page.locator('.ft-teacher').first().locator('.ft-chip').first(), { hold: 1800 });

        await s.say("النظام بيقترح «نمط» جاهز على قد عدد الحصص. دوس «اختاره»، أو اختار المواعيد بنفسك من تحت.", { voice: "وعشان نوفّر وقت، النظام بيقترح «نمط» جاهز: مواعيد ثابتة على قد عدد الحصص اللي طلبناها. ندوس «اختاره». ولو حابب، تقدر تختار المواعيد بنفسك من المربعات اللي تحت." });
        await s.click(s.page.locator('.ft-teacher').first().getByRole('button', { name: 'اختاره' }));

        await s.say("دوس «احجز مبدئيًا»: المواعيد دي بتتحجز للطالب 10 دقايق، محدش تاني ياخدها لحد ما تخلّص.", { voice: "بعد ما اخترنا، ندوس «احجز مبدئيًا». المواعيد دي بتتقفل للطالب ده عشر دقايق، ومحدش تاني في الأكاديمية يقدر ياخدها لحد ما نخلّص التسجيل. وفوق فيه عداد بيوضح الوقت الباقي." });
        await s.click(s.page.locator('.ft-select').getByRole('button').first(), { settle: 900 });
        await s.show(s.page.locator('.ft-select'), { hold: 1500 });

        await s.say("دوس «سجّل الطالب». الرقم اللي جنبه هو عدد الحصص اللي هتتعمل في الفترة.", { voice: "ودلوقتي ندوس «سجّل الطالب». الرقم اللي جنب الزرار هو عدد الحصص اللي هتتعمل في الفترة كلها." });
        await s.click(s.page.locator('.ft-select').getByRole('button', { name: /سجّل الطالب/ }), { settle: 1200 });

        await s.say("اختار المنطقة السعرية والباقة. الأسعار بتظهر بعملة المنطقة، والباقات اللي مش مناسبة بتبقى مقفولة.", { voice: "بيظهر لنا شباك التسجيل. نختار المنطقة السعرية، وبعدها الباقة. الأسعار بتظهر بعملة المنطقة، والباقات اللي مش مناسبة لعدد الحصص أو مدتها بتبقى مقفولة. هنا هنختار باقة تَمَن حِصَص." });
        await s.click(s.modal().getByText('8 حصص', { exact: false }).first(), { settle: 900 });

        await s.say("اختار مين اللي هيدفع: ولي الأمر أو الطالب نفسه. ولو فيه كوبون خصم اكتبه هنا، والإجمالي بيتحسب قدامك.", { voice: "ونحدد مين اللي هيدفع: ولي الأمر، أو الطالب نفسه لو كبير. ولو الأسرة معاها كوبون خصم، نكتبه في الخانة دي، والإجمالي بيتحسب قدامنا على طول." });
        await s.point(s.modal().getByText('ولي أمر', { exact: false }).first(), { hold: 900 });
        await s.point(s.modal().locator('input[type=text]').last(), { hold: 1200 });

        await s.say("دوس «سجّل وأصدر الفاتورة».", { voice: "ونأكد بـ«سجّل وأصدر الفاتورة»." });
        await s.click(s.modal().getByRole('button', { name: 'سجّل وأصدر الفاتورة' }), { settle: 1800 });

        await s.say("خلاص: الطالب اتسجّل والحصص اتعملت، والفاتورة راحت لولي الأمر. الحصص بتتأكد أول ما الفاتورة تتدفع.", { voice: "وكده الطالب اتسجّل، والحصص اتعملت في الجدول، والفاتورة اتبعتت لولي الأمر في رسالة. الحصص بتفضل مستنية الدفع، وأول ما الفاتورة تتدفع، بتتأكد لوحدها." });
        await s.show(s.page.locator('.fi-no-notification').first(), { hold: 2600 });

        await s.say("ومن نفس الرسالة تفتح الفاتورة أو حساب الطالب على طول.", { voice: "ومن نفس الرسالة، نقدر نفتح الفاتورة على طول، أو نروح لحساب الطالب. ودي الفاتورة: فيها بيانات ولي الأمر والطالب، والباقة، والإجمالي، وزرار «سجّل دفعة» لو الأسرة دفعت كاش أو بتحويل." });
        await s.click(s.page.locator('.fi-no-notification').first().getByRole('link', { name: /الفاتورة/ }).first(), { settle: 1500 });
        await s.wait(2500);
      },
    },
    {
      n: 8, slug: 'leads-and-trials', title: 'العملاء المحتملين والحصة التجريبية',
      goal: 'تتابع أي استفسار لحد ما يبقى طالب مشترك.',
      learn: ['لوحة العملاء ومراحلهم', 'تسجيل التواصل مع العميل', 'حجز حصة تجريبية من «ابحث عن مدرس»', 'تقييم المدرس للحصة والتحويل لاشتراك'],
      start: '/admin',
      async steps(s) {
        const card = (name) => s.page.locator('.crm-card', { hasText: name }).first();
        const header = (name) => s.page.locator('.fi-header-actions-ctn').getByRole('button', { name }).first();

        await s.say("من القائمة افتح «العملاء المحتملين»: كل اللي سأل عن الحصص، من أول تواصل لحد ما يشترك.", { voice: "من القايمة اللي على الجنب، هنفتح «العملاء المحتملين». هنا بيتجمّع كل واحد سأل عن الحصص، سواء من واتساب أو الموقع أو إعلان، ونتابعه من أول تواصل لحد ما يبقى طالب مشترك." });
        await s.click(s.nav('العملاء المحتملين'), { settle: 1400 });

        await s.say("فوق أرقام آخر 30 يوم: عملاء جداد، وحصص تجريبية، وكام واحد اشترك بعد التجربة.", { voice: "فوق، أرقام آخر تلاتين يوم: كام عميل جديد، وكام حصة تجريبية اتحجزت واتحضرت، وكام واحد اشترك، ونسبة اللي اشتركوا بعد التجربة." });
        await s.show(s.page.locator('.crm-funnel'), { hold: 1800 });

        await s.say("وتحتها لوحة المراحل: جديد، اتواصلنا، حجز تجريبية، حضر، اتقيّم.", { voice: "وتحتها لوحة المراحل. كل عميل كارت، والكارت بيتنقل لوحده من مرحلة للتانية: جديد، وبعدين اتواصلنا، وبعدين حجز تجريبية، وحضر، واتقيّم." });
        await s.scroll(s.page.locator('.crm-board'));
        await s.show(s.page.locator('.crm-board'), { hold: 1800 });

        await s.say("افتح عميل جديد: منى السيد، سألت لابنها عمر من إعلان إنستجرام.", { voice: "نفتح عميلة جديدة: مُنى السيّد. سألت لابنها عمر، ووصلت لنا من إعلان على إنستجرام." });
        await s.click(card('منى السيد'), { settle: 1400 });
        await s.show(s.page.locator('.crm-steps'), { hold: 1200 });

        await s.say("على الجنب بياناتها: الساعة عندها كام، والطالب وسنّه، والمادة والأوقات اللي تناسبهم، والمصدر.", { voice: "على الجنب كل بياناتها: الساعة عندها كام دلوقتي، واسم الطالب وسنّه، والمادة، والأيام والساعات اللي تناسبهم، وجات منين، ومين المسؤول عنها، وملاحظاتها." });
        await s.show(s.page.locator('.crm-facts'), { hold: 2000 });

        await s.say("كلّمها واتساب أو اتصل بيها من هنا، وبعدها سجّل اللي حصل.", { voice: "نقدر نكلّمها واتساب، أو نتصل بيها من الزراير دي على طول. وبعد ما نكلّمها، نكتب اللي اتفقنا عليه في خانة «اللي حصل»." });
        await s.point(s.page.locator('.crm-contact-actions'), { hold: 1200 });
        await s.click(s.page.locator('.crm-log').getByText('واتساب', { exact: true }).first(), { settle: 300 });
        await s.type(s.page.locator('.crm-log textarea'), 'بعتنا الباقات، وعايزة تجرّب مع مدرّسة يوم الأحد.');

        await s.say("دوس «سجّل»: التواصل بيتسجّل، والعميلة بتتنقل لـ«اتواصلنا».", { voice: "ندوس «سجّل». التواصل بيتسجّل في التاريخ تحت، والعميلة بتتنقل لوحدها لمرحلة «اتواصلنا»." });
        await s.click(s.page.locator('.crm-log').getByRole('button', { name: 'سجّل' }), { settle: 1200 });
        await s.show(s.page.locator('.crm-steps'), { hold: 1200 });

        await s.say("دوس «احجز حصة تجريبية»: بيفتح «ابحث عن مدرس» على التجربة، ومتعبّي من بيانات العميلة.", { voice: "ودلوقتي ندوس «احجز حصة تجريبية». بيفتح لنا «ابحث عن مدرس» على الحصة التجريبية، ومتعبّي لوحده من بيانات العميلة: الطالب، والمادة، والتوقيت، والأوقات اللي تناسبهم." });
        await s.click(header('احجز حصة تجريبية'), { settle: 1800 });
        await s.show(s.page.locator('.ft-grid'), { hold: 1400 });

        await s.say("هي عايزة مدرّسة، فاختار «أنثى فقط» ودوس «ابحث».", { voice: "مُنى قالت إنها عايزة مدرّسة، مش مدرّس، فهنختار «أنثى فقط»، وندوس «ابحث»." });
        await s.scroll(-2000);
        await s.click(s.page.locator('label', { hasText: 'أنثى فقط' }).first(), { settle: 600 });
        await s.scroll(s.page.locator('.ft-grid-tools'));
        await s.click(s.page.locator('.ft-grid-tools').getByRole('button', { name: 'ابحث' }), { settle: 1400 });
        await s.scroll(-2000);

        await s.say("في التجربة المدرسين مترتبين كمان حسب «اشتركوا بعد التجربة»: قد إيه طلابهم بيكمّلوا.", { voice: "في الحصة التجريبية، جنب الملاءمة فيه رقم «اشتركوا بعد التجربة»، يعني قدّ إيه الطلاب اللي جرّبوا مع المدرّسة دي كمّلوا واشتركوا. والمواعيد كلها في الأسبوعين الجايين." });
        await s.show(s.page.locator('.ft-teacher').first().locator('.ft-teacher-head'), { hold: 2000 });

        await s.say("اختار ميعاد، ودوس «احجز مبدئيًا»، وبعدها «أكّد حصة تجريبية».", { voice: "نختار ميعاد يناسبهم، وندوس «احجز مَبدَئيًّا»، فيتقفل لنا عشر دقايق. وبعدين نأكّد بـ«أكّد حصة تجريبية». الحصة التجريبية ببلاش، ومش محتاجة فاتورة." });
        await s.click(s.page.locator('.ft-teacher').first().locator('.ft-chip').first(), { settle: 700 });
        await s.click(s.page.locator('.ft-select').getByRole('button').first(), { settle: 900 });
        await s.click(s.page.locator('.ft-select').getByRole('button', { name: 'أكّد حصة تجريبية' }), { settle: 1800 });

        await s.say("الحصة اتحجزت، والمدرّسة وولي الأمر عرفوا. ومنى بقت في «حجز تجريبية».", { voice: "وكده الحصة اتحجزت، والمدرّسة وولي الأمر جالهم رسالة بالميعاد. ولو رجعنا للوحة، هنلاقي مُنى اتنقلت لوحدها لمرحلة «حجز تجريبية»." });
        await s.go('/admin/leads');
        await s.scroll(s.page.locator('.crm-board'));
        await s.point(card('منى السيد'), { hold: 1600 });

        await s.say("بعد الحصة، المدرس بيقيّمها من «حصصي التجريبية»: المستوى، والباقة اللي يقترحها، وملاحظاته.", { voice: "بعد الحصة، المدرس بيقيّم الطالب من صفحة «حِصَصي التجريبيّة» اللي عنده: مستواه إيه، والباقة اللي يقترحها، وملاحظاته. وده بيظهر عندنا هنا. نفتح أم جنى، اللي حصتها اتقيّمت." });
        await s.click(card('أم جنى'), { settle: 1400 });
        await s.show(s.page.locator('.crm-box').first(), { hold: 2400 });

        await s.say("وتحت تاريخ كل اللي حصل مع العميل، خطوة بخطوة.", { voice: "وتحت، تاريخ كامل لكل اللي حصل مع العميلة، خطوة بخطوة: جات منين، وكلّمناها إمتى، والحجز، والحضور، وتقييم المدرس." });
        await s.scroll(s.page.locator('.crm-timeline'));
        await s.show(s.page.locator('.crm-timeline'), { hold: 1800 });

        await s.say("لما توافق، دوس «سجّله في باقة»: بيفتح «ابحث عن مدرس» على الاشتراك الأسبوعي، زي الدرس اللي فات.", { voice: "ولما الأسرة توافق، ندوس «سجّله في باقة». بيفتح «ابحث عن مدرس» على الاشتراك الأسبوعي، متعبّي ببياناتها، ونكمّل التسجيل والفاتورة زي ما عملنا في الدرس اللي فات." });
        await s.scroll(-2000);
        await s.point(header('سجّله في باقة'), { hold: 1600 });

        await s.say("ولو العميل مش هيكمّل، «اتفقد» بسبب، وتقدر ترجّعه بعدين.", { voice: "ولو العميل قرّر ما يكمّلش، ندوس «اتفقد» ونكتب السبب، زي السعر أو المواعيد. وده بيساعدنا نعرف بنخسر العملاء ليه، ونقدر نرجّعه في أي وقت لو رجع سأل." });
        await s.point(header('اتفقد'), { hold: 1800 });
        await s.wait(1200);
      },
    },
    {
      n: 9, slug: 'plans-prices-coupons', title: 'الباقات والأسعار والكوبونات',
      goal: 'تسعّر خدمتك بمرونة لكل منطقة وتعمل عروض.',
      learn: ['باقة بعدد حصص وأسعار لكل منطقة وعملة', 'الباقة المشتركة بين أكتر من مادة', 'الكوبونات وخصم الإخوات'],
      start: '/admin',
      async steps(s) {
        const head = (name) => s.page.locator('.fi-header-actions-ctn').locator('button, a').filter({ hasText: name }).first();
        // A field in the open dialog by its name (toggle buttons' inputs are named name-option).
        const inModal = (name) => s.modal().locator(`.fi-fo-field:has([id$=".${name}"], [id*=".${name}-"])`).first();
        const pick = async (name, option, exact = true) => {
          await s.click(inModal(name).locator('.fi-select-input-btn'), { settle: 400 });
          await s.click(inModal(name).getByRole('option', { name: option, exact }).first(), { settle: 500 });
        };
        // A dialog by its title: waiting for it is what lets it open on film before it is shown.
        const dialog = (title) => s.page.locator('.fi-modal-window:visible', { has: s.page.locator('.fi-modal-heading', { hasText: title }) }).last();
        // Closed once the narrator has said what it shows.
        const close = async () => { await s.hush(); await s.key('Escape'); await s.wait(700); };

        await s.say("من القائمة افتح «الباقات والأسعار»: كل باقة عدد حصص بمدة ثابتة، وسعرها في كل منطقة.", { voice: "من القايمة، هنفتح «الباقات والأسعار». كل باقة هنا عبارة عن عدد حصص بمدّة ثابتة، زي أربع حصص في الشهر، كل حصة نص ساعة، ولها سعر في كل منطقة." });
        await s.click(s.nav('الباقات والأسعار'), { settle: 1400 });

        await s.say("في كل صف: الأسعار لكل منطقة، والصلاحية، وإمتى فاتورة التجديد بتطلع.", { voice: "في كل صف بنشوف أسعار الباقة في كل منطقة بعُملتها، وصلاحيتها كام يوم، وإمتى فاتورة التجديد بتطلع لوحدها، يعني لما يفضل للطالب حصّتين مثلًا." });
        await s.show(s.page.locator('.fi-ta-row').first(), { hold: 2000 });

        await s.say("«المناطق السعرية»: كل منطقة ليها عملتها، والأسرة بتدفع بعملة منطقتها.", { voice: "من «المناطق السعرية» بنحدّد المناطق اللي بنبيع فيها، وكل منطقة ليها عُملتها: السعودية والخليج بالريال، والإمارات بالدرهم، وبريطانيا بالجنيه الإسترليني. وكل أسرة بتدفع بعُملة منطقتها." });
        await s.click(head('المناطق السعرية'), { settle: 1000 });
        await s.show(dialog('المناطق السعرية'), { hold: 2000 });
        await close();

        await s.say("دوس «باقة جديدة»، واكتب اسمها بالعربي والإنجليزي.", { voice: "نعمل باقة جديدة. ندوس «باقة جديدة»، ونكتب اسمها بالعربي وبالإنجليزي، عشان الأسرة تشوفها بلغتها." });
        await s.click(head('باقة جديدة'), { settle: 1000 });
        await s.type(inModal('name_ar').locator('input'), 'باقة الساعة ونص');
        await s.type(inModal('name_en').locator('input'), 'Long lessons plan');

        await s.say("«لكل كورس» رصيد لمادة واحدة. «باقة مشتركة» رصيد واحد الطالب يوزّعه على كذا مادة.", { voice: "بعدها نختار نوع الرصيد. «لكل كورس» يعني الرصيد لمادّة واحدة بس. و«باقة مشتركة» يعني رصيد واحد، الطالب يصرف منه على أكتَر من مادّة، زي القرآن والعربي مع بعض. ونوع الباقة ده مابيتغيّرش بعد ما حد يشترك فيها." });
        await s.point(inModal('credit_scope'), { hold: 1800 });

        await s.say("اختار مدة الحصة، وعدد الحصص في الأسبوع وفي البلوك.", { voice: "نختار مدّة الحصة، هنا ساعة ونص. وعدد الحصص في الأسبوع، حصّة واحدة. وعدد الحصص في البلوك، يعني في كل فاتورة، أربع حصص." });
        await s.click(inModal('session_minutes').locator('label', { hasText: '90' }), { settle: 400 });
        await pick('sessions_per_week', '1');
        await pick('sessions_per_block', '4');

        await s.say("والصلاحية بتتحسب لوحدها، و«فاتورة التجديد» بتطلع لما يفضل عدد الحصص ده.", { voice: "والصلاحية بتتحسب لوحدها من عدد الحصص، وفيها أسبوعين سماح. و«فاتورة التجديد» معناها إن الفاتورة اللي بعدها بتطلع لوحدها لمّا يفضل للطالب العدد ده من الحصص، عشان يدفع قبل ما رصيده يخلص." });
        await s.point(inModal('validity_days'), { hold: 1000 });
        await s.point(inModal('renew_at_sessions'), { hold: 1200 });

        await s.say("ضيف سعر لكل منطقة: اختار المنطقة واكتب السعر بعملتها.", { voice: "ونضيف سعر لكل منطقة: ندوس «ضيف سعر لمنطقة»، ونختار المنطقة، ونكتب السعر بعُملتها. هنا السعودية والخليج، بأربعميّة وخمسين ريال." });
        await s.click(s.modal().getByRole('button', { name: 'ضيف سعر لمنطقة' }), { settle: 900 });
        await pick('price_region_id', 'السعودية والخليج', false);
        await s.type(inModal('amount').locator('input'), '450');

        await s.say("دوس «إضافة»: الباقة بقت متاحة في التسجيل.", { voice: "وندوس «إضافة». الباقة اتحفظت، وبقت متاحة في شبّاك التسجيل، اللي شُفناه في درس «ابحث عن مدرس»." });
        await s.click(s.modal().locator('.fi-modal-footer').getByRole('button', { name: 'إضافة', exact: true }), { settle: 1400 });

        await s.say("«العيلة»: خصم الابن التاني والتالت، وفاتورة واحدة للأسرة.", { voice: "وزرار «العيلة»: هنا خصم الإخوات. الابن الأول بيدفع كامل، والابن التّاني بياخُد الخصم ده، والتّالِت واللي بعده بياخدوا ده. وتحت، لو تجديد ابن طلع، تجديدات إخواته اللي جاية خلال الأيام دي بتنزل معاه في نفس الفاتورة، فالأسرة تدفع مرّة واحدة." });
        await s.click(head('العيلة'), { settle: 1000 });
        await s.show(dialog('الفاتورة الواحدة'), { hold: 2600 });
        await close();

        await s.say("ومن القائمة افتح «الكوبونات»: أكواد خصم بنسبة أو بمبلغ.", { voice: "ونروح للكوبونات من القايمة. الكوبون كود بتوزّعه، بيخصم نسبة أو مبلغ من فواتير الكورس. وفي الجدول بنشوف كل كوبون: بيخصم كام، على كام فاتورة، صالح لحد إمتى، واستخدموه كام مرّة." });
        await s.click(s.nav('الكوبونات'), { settle: 1400 });
        await s.show(s.page.locator('.fi-ta-table'), { hold: 1800 });

        await s.say("دوس «كوبون جديد»: اكتب الكود، والنسبة، وعلى كام فاتورة.", { voice: "نعمل كوبون جديد لعرض العيد: ندوس «كوبون جديد»، ونكتب الكود، واسم لينا إحنا، ونسبة الخصم خمسة وعشرين في الميّة، على أول فاتورة بس." });
        await s.click(head('كوبون جديد'), { settle: 1000 });
        await s.type(inModal('code').locator('input'), 'EID25');
        await s.type(inModal('name').locator('input'), 'عرض العيد');
        await s.type(inModal('percent').locator('input'), '25');
        await s.type(inModal('blocks').locator('input'), '1');

        await s.say("وحدد أقصى عدد مرات، والتواريخ، والباقات اللي يشتغل عليها.", { voice: "ونقدر نحدّد أقصى عدد مرات الناس يستخدموه فيها، هنا خمسين مرّة. والتواريخ اللي شغّال فيها، والباقات اللي ينفع عليها. وكل أسرة بتستخدم الكوبون مرّة واحدة بس." });
        await s.type(inModal('max_uses').locator('input'), '50');
        await s.point(inModal('plan_ids'), { hold: 1000 });

        await s.say("«فوق خصم الإخوات»: لو مقفول، الأسرة بتاخد الخصم الأكبر بس.", { voice: "و«فوق خصم الإخوات»: لو شغّلناه، الكوبون بيتجمع مع خصم الإخوات. ولو سبناه مقفول، الأسرة بتاخد الخصم الأكبر فيهم بس." });
        await s.point(inModal('with_sibling'), { hold: 1600 });

        await s.say("دوس «إضافة». الكود بيتكتب في شباك التسجيل، والخصم بيظهر في الإجمالي على طول.", { voice: "وندوس «إضافة». الكوبون جاهز: لمّا الأسرة تقولّنا الكود، بنكتبه في خانة الكوبون في شبّاك التسجيل، والخصم بيظهر في الإجمالي قدّامنا على طول." });
        await s.click(s.modal().locator('.fi-modal-footer').getByRole('button', { name: 'إضافة', exact: true }), { settle: 1400 });
        await s.show(s.page.locator('.fi-ta-row', { hasText: 'EID25' }), { hold: 1800 });
      },
    },
    {
      n: 10, slug: 'invoices-and-payments', title: 'الفواتير والمدفوعات',
      goal: 'تحصّل فلوسك أونلاين أو بالتحويل وتطلع فاتورة ضريبية سليمة.',
      learn: ['الدفع الإلكتروني وبوابات الدفع', 'مراجعة إثبات التحويل واعتماده أو رفضه', 'الفاتورة الضريبية وكود QR والإلغاء بإشعار دائن'],
    },
    {
      n: 11, slug: 'live-board-calendar', title: 'العمليات اللحظية والتقويم', voice: VOICES.hazem, user: 'scheduler',
      goal: 'تتابع كل حصة لحظة بلحظة وتتدخل في الوقت المناسب.',
      learn: ['مين دخل ومين اتأخر والتنبيهات', 'التقويم: يوم وأسبوع وشهر وفلترة', 'تصدير المواعيد'],
      start: '/admin',
      async steps(s) {
        await s.say("ده حساب منسّق الجداول. أول شاشة: «العمليات اللحظية».", { voice: "[warmly] المرة دي داخلين بحساب منسّق الجداول، وده اللي بيتابع الحصص طول اليوم. أول شاشة «العمليات اللحظية»، وبتتحدّث لوحدها أول ما أي حاجة تتغيّر." });
        await s.wait(800);

        await s.say("فوق: حصص النهارده، والجارية دلوقتي، واللي محتاجة تدخّل، والغياب.", { voice: "فوق أربع أرقام: حصص النهارده، واللي شغّالة دلوقتي، واللي محتاجة تدخّل، والغياب، سواء مدرس أو طالب." });
        await s.show(s.page.locator('.fi-wi-stats-overview, .lb-kpis, section').first(), { hold: 1600 });

        await s.say("«يحتاج تدخّلك الآن»: كل مشكلة بالوقت الباقي، وزرار الحل.", { voice: "وبعدها «يحتاج تدخّلك الآن»: مدرس مادخلش، أو طالب متأخر، أو حصة محتاجة قرار. كل كارت عليه الوقت الباقي قبل ما المنصّة تتصرّف لوحدها، وزراير الحل: نتصل بالمدرس، أو بولي الأمر، أو نلغي بتعويض." });
        await s.scroll(s.page.getByRole('heading', { name: 'يحتاج تدخّلك الآن' }));
        await s.wait(2200);

        await s.say("وتحت كل حصص الساعة دي والجاية: الحضور، والحالة، وفلتر «مشاكل».", { voice: "وتحت، كل الحصص من دلوقتي لحد ساعة قدّام: مين المدرس والطالب، ودخلوا إمتى، والحضور بيتملي، والحالة. ولو عايزين نشوف المشاكل بس، ندوس «مشاكل»." });
        await s.scroll(s.page.getByRole('heading', { name: /الحصص الآن/ }));
        await s.click(s.page.getByRole('button', { name: /^مشاكل/ }).first(), { settle: 1400 });

        await s.say("وشكل العرض: «جدول»، أو «التدخّل أولًا».", { voice: "وشكل العرض نفسه بيتغيّر من فوق: «جدول»، أو «التدخّل أولًا»، اللي بيحط المشاكل في الصدارة. وكل واحد في الفريق بيختار الشكل اللي يريّحه." });
        await s.scroll(-3000);
        await s.click(s.page.getByRole('button', { name: 'التدخّل أولًا' }), { settle: 1600 });
        await s.click(s.page.getByRole('button', { name: 'جدول' }).first(), { settle: 1200 });

        await s.say("و«التقويم»: كل الحصص على الأسبوع، بتوقيتك.", { voice: "وبعدها «التقويم»: كل حصص الأكاديمية على الأسبوع، من السبت للجمعة، بتوقيتنا إحنا. وفوق عدد الحصص وساعاتها، وكل حالة بلونها." });
        await s.click(menuItem(s, 'التقويم'), { settle: 2000 });

        await s.say("دوسة على حصة: تفاصيلها، ولينكات لحساب الطالب وتقويمه وتقويم المدرس.", { voice: "ولو دوسنا على أي حصة، تظهر تفاصيلها: إمتى، والمدرس، والطالب، والمادة، والدفع، ولينكات لحساب الطالب في الكورس، وتقويم المدرس، وتقويم الطالب." });
        await s.click(s.page.getByRole('button', { name: /^\d\d:\d\d / }).first(), { settle: 1500 });
        await s.show(s.modal(), { hold: 1800 });
        await s.click(s.modal().getByRole('button', { name: 'قفل' }), { settle: 900 });

        await s.say("يوم، أسبوع، شهر، وفلترة بالمدرس والطالب والمادة والحالة.", { voice: "ونقدر نشوف يوم واحد، أو أسبوع، أو الشهر كله. ونفلتر بالمدرس، أو الطالب، أو المادة، أو نوع الحصة، أو بالحالة من الزراير الملوّنة." });
        await s.click(s.page.getByRole('button', { name: 'شهر' }), { settle: 1600 });
        await s.click(s.page.getByRole('button', { name: /^خلصت/ }).first(), { settle: 1400 });

        await s.say("و«نزّل Excel»: كل حصص الفترة في شيت.", { voice: "و«نزّل Excel» بينزّل كل حصص الفترة اللي قدامنا في شيت، بنفس الفلاتر." });
        await s.point(s.page.getByRole('button', { name: /نزّل Excel/ }), { hold: 1600 });
      },
    },
    {
      n: 12, slug: 'student-account', title: 'حساب الطالب المسجّل',
      goal: 'تدير اشتراك الطالب: الرصيد والحصص والتغييرات.',
      learn: ['الرصيد والحصص المدفوعة والمستنية', 'إلغاء أو تأجيل حصة حسب السياسة، والتعويض', 'تغيير المدرس وبيع حصص إضافية'],
    },
    {
      n: 13, slug: 'policy-and-disputes', title: 'سياسة الإلغاء والتعويض والاعتراضات',
      goal: 'تحط قواعد واضحة للغياب والإلغاء وتحسم أي اعتراض بعدل.',
      learn: ['قواعد السياسة ومُعاينة أثرها', 'الحصص اللي محتاجة مراجعة', 'الحكم في اعتراض وتصحيح الرصيد'],
    },
    {
      n: 14, slug: 'reports-and-learning', title: 'التقارير وأسئلة التقرير',
      goal: 'تتأكد إن كل حصة ليها تقرير مفيد وتتابع تقدم الطلاب.',
      learn: ['أسئلة التقرير الخاصة بكل مادة', 'متابعة التقارير المتأخرة', 'خريطة تقدم الطالب'],
    },
    {
      n: 15, slug: 'performance-and-payroll', title: 'أداء المدرسين والمرتبات',
      goal: 'تقيس أداء كل مدرس وتحسب مرتبه من غير أي حسابات يدوي.',
      learn: ['مؤشرات الأداء والتقييمات', 'أسعار المدرسين', 'كشف الشهر والتعديلات والقفل والتصدير'],
    },
    {
      n: 16, slug: 'revenue', title: 'الإيرادات',
      user: 'accountant',
      goal: 'تشوف فلوس الأكاديمية بوضوح: اللي اتحصّل واللي مستني واللي جاي.',
      learn: ['المحصّل والمفوتر والمتأخر لكل فترة', 'التقسيم حسب المادة والباقة وطريقة الدفع', 'التوقع وتصدير الشيت'],
    },
    {
      n: 17, slug: 'messages-and-support', title: 'الرسائل والدعم',
      goal: 'تتواصل مع الأسر والمدرسين صح وترد على طلباتهم في وقتها.',
      learn: ['قوالب الرسائل وقنواتها (واتساب، SMS، إيميل، إشعار)', 'سجل الرسائل', 'تذاكر الدعم والشكاوى'],
    },
    {
      n: 18, slug: 'classroom-and-recordings', title: 'الفصل المباشر والتسجيلات',
      goal: 'تظبط الفصل والكاميرا وتسجيل الحصص بموافقة الكل.',
      learn: ['إعدادات الفصل والكاميرا للصغار', 'التسجيل: يدوي أو تلقائي، ومدة الاحتفاظ', 'موافقات التسجيل ومين يشوف'],
    },
    {
      n: 19, slug: 'safeguarding-and-privacy', title: 'حماية الأطفال والخصوصية',
      goal: 'تتعامل مع أي بلاغ بمسؤولية وتحترم بيانات الناس.',
      learn: ['لوحة البلاغات وخطواتها', 'إيقاف مدرس عن تدريس الصغار', 'طلبات نسخة البيانات والحذف'],
    },
    {
      n: 20, slug: 'dst-import-audit', title: 'تغيير التوقيت والاستيراد وسجل العمليات',
      goal: 'تنقل بياناتك القديمة بسهولة وتعدّي تغيير التوقيت من غير لخبطة.',
      learn: ['تغيير التوقيت الصيفي وقرار كل حصة', 'استيراد المدرسين والطلاب والجداول من Excel', 'سجل العمليات: مين عمل إيه وإمتى'],
    },
  ],
};
