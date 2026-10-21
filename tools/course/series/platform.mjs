// The platform team's console (/console): everything across academies.
import { totp } from '../lib/totp.mjs';

// Each lesson has its own Egyptian narrator from the ElevenLabs library (all spoken on eleven_v4).
const VOICES = {
  sattar: '6CdZI3ijDBOuX5Giq3NQ',    // Sattar - Warm Egyptian explainer
  seham: 'YwQu4f7UeJh7h0uWMnEn',     // Seham - Egyptian Woman
  maged: 'MLELiE1ybdYoTtlmFf5r',     // Maged Magdy - Narrative/Educational
  gamal: 'JTMaHm6sHVI3NZgPaWDz',     // Gamal - Authoritative and Resonant
  karim: 'Rs6W7zDzDMy5M4DoQvwj',     // Karim - Bold and Commanding
  yahia: 'rMheqEfwsIJckq2yCdb5',     // Ahmed Yahia
  fathy: 'VMy40598IGgDeaOE8phq',     // Fathy Hammad - Strong and Deep
};
// The console's code for a filmed sign-in: the next window's, since the one the session used is spent.
const consoleCode = () => (process.env.COURSE_TOTP_PLATFORM ? totp(process.env.COURSE_TOTP_PLATFORM, Date.now() + 30000) : '000000');
/** The side menu's item with exactly this label. */
const menuItem = (s, label) => s.page.locator('.fi-sidebar-item').filter({ has: s.page.locator('.fi-sidebar-item-label', { hasText: new RegExp(`^\\s*${label}\\s*$`) }) }).locator('a, button').first();
const DEMO = 'أكاديمية النور التجريبية';

export default {
  key: 'platform',
  title: 'سلسلة أصحاب المنصة',
  audience: 'لفريق المنصة',
  color: '#6366f1',
  device: 'desktop',
  user: 'platform',
  lessons: [
    {
      n: 1, slug: 'console-tour', title: 'الدخول للكونسول وجولة', voice: VOICES.sattar, signsOut: true,
      goal: 'فريق المنصة يدخل بأمان ويعرف أدواته.',
      learn: ['الدخول بالتحقق بخطوتين (إجباري)', 'القائمة والصفحات', 'مين يقدر يعمل إيه'],
      start: '/console',
      async steps(s) {
        await s.say("ده كونسول المنصة: الأكاديميات كلها في مكان واحد. نخرج الأول عشان نشوف الدخول.", { voice: "[warmly] أهلًا بيك. ده كونسول المنصة، المكان اللي فريق المنصة بيشوف منه كل الأكاديميات. هنخرج الأول، عشان نشوف الدخول من أوّله." });
        await s.click(s.page.locator('.fi-topbar .fi-avatar, .fi-user-menu button').first(), { settle: 600 });
        await s.click(s.page.getByRole('button', { name: 'تسجيل الخروج' }).first(), { settle: 1500 });

        await s.say("الإيميل وكلمة السر، وبعدين كود تطبيق المصادقة: إجباري لكل الفريق.", { voice: "الدخول بالإيميل وكلمة السر، وبعدهم كود من تطبيق المصادقة على الموبايل. الخطوة دي إجبارية لكل حد في فريق المنصة، عشان الكونسول بيشوف بيانات كل الأكاديميات." });
        await s.type(s.page.locator('#form\\.email'), 'admin@platform.test');
        await s.type(s.page.locator('#form\\.password'), process.env.COURSE_PASSWORD ?? '');
        await s.click(s.page.getByRole('button', { name: 'تسجيل الدخول' }).first(), { settle: 1800 });
        await s.type(s.page.locator('form input:not([type=hidden]):not([type=checkbox])').first(), consoleCode(), { delay: 120 });
        await s.click(s.page.getByRole('button', { name: 'تأكيد تسجيل الدخول' }).first(), { settle: 2500 });

        await s.say("أول شاشة: الأكاديميات، وأرقام المنصة كلها في آخر تلاتين يوم.", { voice: "وأول شاشة «الأكاديميات»: كام أكاديمية شغّالة، وكام طالب، وكام حصة وساعة اتدرّست في آخر تلاتين يوم. وتحت كارت لكل أكاديمية." });
        await s.wait(1600);

        await s.say("القايمة: الباقات، وأكاديمية جديدة، واستوديو الألعاب.", { voice: "وعلى الجنب القايمة: «الباقات» اللي بنبيع بيها المنصة، و«أكاديمية جديدة»، و«استوديو الألعاب» اللي بنعمل فيه ألعاب الطلاب." });
        await s.show(menuItem(s, 'الباقات'), { hold: 900 });
        await s.show(menuItem(s, 'استوديو الألعاب'), { hold: 900 });

        await s.say("والدعم والمتابعة: الدخول بحساب مستخدم، وسجل المراجعة، وصحة النظام.", { voice: "وبعدهم أدوات الدعم والمتابعة: «الدخول بحساب مستخدم» عشان نساعد حد من جوّه حسابه، و«سجل المراجعة» اللي فيه كل خطوة حساسة، و«صحة النظام»، و«إعدادات المنصة». وكل واحدة فيهم ليها درس." });
        await s.show(menuItem(s, 'الدخول بحساب مستخدم'), { hold: 900 });
        await s.show(menuItem(s, 'سجل المراجعة'), { hold: 900 });
        await s.show(menuItem(s, 'صحة النظام'), { hold: 900 });

        await s.say("الكونسول بيقرا من كل الأكاديميات، لكن مايغيّرش جوّه أكاديمية غير بالدخول بحساب حد، وكل ده متسجّل.", { voice: "وحاجة مهمة: الكونسول بيقرا أرقام كل الأكاديميات، لكن عشان يعمل حاجة جوّه أكاديمية، لازم يدخل بحساب حد فيها، بسبب ولمدة محددة، والأكاديمية والشخص بيشوفوا ده. وكل خطوة بتتسجّل باسمه." });
        await s.wait(1200);
      },
    },
    {
      n: 2, slug: 'academies', title: 'الأكاديميات', voice: VOICES.seham,
      goal: 'تضيف أكاديمية جديدة وتتابع كل أكاديمية.',
      learn: ['إضافة أكاديمية وصاحبها', 'نظرة عامة: الاستخدام والحدود', 'إيقاف أكاديمية وتفعيلها'],
      start: '/console',
      async steps(s) {
        await s.say("كل أكاديمية كارت: المدرسين والطلاب، وباقتها، والحصص، واللي اتحصّل.", { voice: "[warmly] كل أكاديمية ليها كارت: عدد المدرسين والطلاب وأولياء الأمور، وباقتها، والحصص والساعات في آخر شهر، والفلوس اللي اتحصّلت بكل عملة. ولو أكاديمية قرّبت من حد باقتها، بيبان هنا." });
        await s.point(s.page.getByText(/قرّ?بت من الحد/).locator('visible=true').first(), { hold: 1600 });

        await s.say("«موقوفة»: الأكاديميات اللي اتوقفت.", { voice: "ومن فوق نفلتر: «شغّالة» أو «موقوفة». أكاديمية الروضة موقوفة، عشان الاشتراك اتأخر." });
        await s.click(s.page.getByRole('button', { name: 'موقوفة' }), { settle: 1200 });
        await s.click(s.page.getByRole('button', { name: 'الكل' }), { settle: 1200 });

        await s.say("نفتح أكاديمية: أرقامها، وباقتها واستخدامها، وآخر العمليات، والمالك.", { voice: "نفتح أكاديمية النور. فوق أرقامها، وتحت الباقة: كل حد، والمستخدم منه قد إيه. وآخر العمليات فيها، والمالك والمديرين." });
        await s.click(s.page.getByText(DEMO, { exact: true }).first(), { settle: 2000 });
        await s.scroll(s.page.getByRole('heading', { name: /آخر العمليات/ }).first());
        await s.wait(1200);

        await s.say("«أوقف الأكاديمية»: بسبب مكتوب، ومحدش فيها يقدر يدخل لحد ما ترجع.", { voice: "ولو لازم نوقف أكاديمية، فيه «أوقف الأكاديمية»: بسبب مكتوب، ومحدش فيها يقدر يدخل لحد ما نشغّلها تاني من نفس الزرار." });
        await s.scroll(s.page.getByRole('button', { name: 'أوقف الأكاديمية' }));
        await s.point(s.page.getByRole('button', { name: 'أوقف الأكاديمية' }), { hold: 1400 });

        await s.say("«أكاديمية جديدة»: الاسم، والعنوان، والعملة، والتوقيت، والمالك.", { voice: "ودلوقتي نضيف أكاديمية جديدة. نكتب الاسم بالعربي وبالإنجليزي، والعنوان اللي هتبقى عليه، والعملة، والتوقيت." });
        await s.click(menuItem(s, 'أكاديمية جديدة'), { settle: 1600 });
        await s.type(s.page.locator('#form\\.name_ar, [id$="name_ar"]').first(), 'أكاديمية السلام');
        await s.type(s.page.locator('[id$="name_en"]').first(), 'Al Salam Academy');
        await s.type(s.page.locator('[id$=".slug"]').first(), 'salam');

        await s.say("والمالك: بيوصله لينك يختار بيه كلمة السر.", { voice: "وتحت بيانات المالك: اسمه وإيميله. المالك بيوصله لينك ترحيب يختار بيه كلمة السر بنفسه، ومحدش في المنصة بيعرفها." });
        await s.scroll(s.page.locator('[id$="owner_name"]').first());
        await s.type(s.page.locator('[id$="owner_name"]').first(), 'محمد السيد');
        await s.type(s.page.locator('[id$="owner_email"]').first(), 'owner@salam.test');

        await s.say("«اعمل الأكاديمية»: وبتبدأ على الباقة الأساسية.", { voice: "وندوس «اعمل الأكاديمية». الأكاديمية بتتعمل بباقة البداية، والصفحة بتدّينا لينك الترحيب لو حبّينا نبعته للمالك بنفسنا. والمالك بيبدأ من «ابدأ من هنا»، صفحة بتمشّيه خطوة خطوة." });
        await s.click(s.page.getByRole('button', { name: 'اعمل الأكاديمية' }), { settle: 2500 });
        await s.wait(1500);
      },
    },
    {
      n: 3, slug: 'saas-plans', title: 'باقات المنصة', voice: VOICES.maged,
      goal: 'تبيع المنصة بباقات واضحة وتغيّر باقة أي أكاديمية بسبب مسجّل.',
      learn: ['كتالوج الباقات: الحدود والمميزات', 'تغيير باقة أكاديمية واستثناءاتها', 'التحذير لما أكاديمية تقرب من الحد'],
      start: '/console',
      async steps(s) {
        await s.say("«الباقات»: كل باقة حدودها ومميزاتها.", { voice: "المنصة بتتباع بباقات. من القايمة «الباقات»: كل باقة ليها حدود، عدد المدرسين، والطلاب النشطين، ودقايق الفيديو في الشهر، ومساحة الملفات. وتحتها المميزات اللي فيها." });
        await s.click(menuItem(s, 'الباقات'), { settle: 1800 });
        await s.show(s.page.getByText('الطلاب النشطين').first(), { hold: 1400 });

        await s.say("أي تعديل في باقة بيمشي على كل الأكاديميات اللي عليها.", { voice: "ولو عدّلنا باقة، التعديل بيمشي على كل الأكاديميات اللي عليها. والباقة اللي مكتوب عليها «الأكاديميات الجديدة بتبدأ عليها»، هي اللي أي أكاديمية جديدة بتاخدها." });
        await s.point(s.page.getByText('الأكاديميات الجديدة بتبدأ عليها').first(), { hold: 1600 });

        await s.say("باقة أكاديمية معيّنة: من صفحتها، «الباقة».", { voice: "وعشان نغيّر باقة أكاديمية واحدة، نفتح صفحتها. أكاديمية النور على باقة الاحتراف، ومدرسينها تسعة من عشرة، قرّبت من الحد." });
        await s.click(menuItem(s, 'الأكاديميات'), { settle: 1500 });
        await s.click(s.page.getByText(DEMO, { exact: true }).first(), { settle: 2000 });
        await s.show(s.page.getByText(/قرّ?بت من الحد/).locator('visible=true').first(), { hold: 1400 });

        await s.say("نزوّد حد المدرسين لها هي بس، والسبب لازم يتكتب.", { voice: "ندوس «الباقة». ممكن نغيّر الباقة كلها، أو نسيبها ونغيّر حد واحد للأكاديمية دي بس، زي المدرسين. والسبب لازم يتكتب، وبيتسجّل في سجل المراجعة." });
        await s.click(s.page.getByRole('button', { name: 'الباقة', exact: true }), { settle: 1400 });
        const teachers = s.modal().locator('.fi-fo-field').filter({ hasText: 'المدرسين' }).locator('input').first();
        await s.click(teachers, { settle: 200 });
        await teachers.fill('');
        await s.type(teachers, '15');
        await s.type(s.modal().locator('textarea').first(), 'زوّدنا المدرسين لحد تجديد العقد في يناير.');

        await s.say("«احفظ الباقة»: الحد الجديد بيشتغل على طول.", { voice: "وندوس «احفظ الباقة». الحد الجديد بيشتغل على طول، والأكاديمية تقدر تضيف مدرسين. ولما أكاديمية توصل لحدها، بتقف إضافة الجديد بس، والشغل اللي موجود بيكمّل عادي." });
        await s.click(s.modal().getByRole('button', { name: 'احفظ الباقة' }), { settle: 2200 });
        await s.wait(1400);
      },
    },
    {
      n: 4, slug: 'support-sign-in', title: 'الدعم بالدخول كمستخدم', voice: VOICES.gamal,
      goal: 'تساعد مستخدم من جوه حسابه من غير ما تعرف كلمة سره.',
      learn: ['السبب والمدة (15 أو 30 أو 60 دقيقة)', 'اللينك اللي بيتفتح مرة واحدة', 'كل خطوة بتتسجل باسمك'],
      start: '/console',
      async steps(s) {
        await s.say("مدرّسة بتقول إن حصة مش ظاهرة في جدولها: ندخل بحسابها نشوف.", { voice: "مدرّسة في أكاديمية النور بتقول إن حصة مش ظاهرة في جدولها. عشان نشوف اللي هي شايفاه بالظبط، من غير ما نعرف كلمة سرها، ندخل بحسابها. من القايمة «الدخول بحساب مستخدم»." });
        await s.click(menuItem(s, 'الدخول بحساب مستخدم'), { settle: 1600 });

        await s.say("نختار الأكاديمية، والشخص.", { voice: "نختار الأكاديمية، وبعدين الشخص من القايمة." });
        await s.select(s.page.locator('select').first(), { label: DEMO }).catch(async () => {
          await s.click(s.page.locator('.fi-select-input-btn').first(), { settle: 400 });
          await s.click(s.page.getByRole('option', { name: DEMO }).first(), { settle: 900 });
        });
        await s.wait(800);
        await s.click(s.page.getByRole('button', { name: /نور الهدى/ }).first(), { settle: 1200 });

        await s.say("السبب، والمدة: ربع ساعة، أو نص ساعة، أو ساعة.", { voice: "ونكتب السبب بوضوح، ونختار المدة: ربع ساعة، أو نص ساعة، أو ساعة. بعدها الدخول بيخلص لوحده." });
        await s.type(s.page.locator('textarea').first(), 'المدرسة مش شايفة حصة يوسف في جدولها، بنراجع اللي ظاهر عندها.');
        await s.click(s.page.getByRole('button', { name: '15 دقيقة' }), { settle: 500 });

        await s.say("«جهّز لينك الدخول»: لينك بيتفتح مرة واحدة بس، في دقيقتين.", { voice: "وندوس «جهّز لينك الدخول». اللينك بيتفتح مرة واحدة بس، وفي خلال دقيقتين. ولو غيّرنا رأينا، «اقفله دلوقتي»." });
        await s.click(s.page.getByRole('button', { name: 'جهّز لينك الدخول' }), { settle: 1600 });
        await s.point(s.page.getByRole('button', { name: 'اقفله دلوقتي' }), { hold: 1200 });
        const href = await s.page.getByRole('link', { name: /افتح الحساب/ }).first().getAttribute('href');

        await s.say("بيفتح مساحة المدرّسة زي ما هي شايفاها، وفوق علامة إن ده دعم من المنصة.", { voice: "نفتح اللينك: دي مساحة المدرّسة زي ما هي شايفاها بالظبط. وفوق علامة واضحة إننا من فريق المنصة، وفاضل قد إيه. وفي الوقت ده، مانقدرش نغيّر كلمة سر، ولا إيميل، ولا رقم موبايل." });
        await s.point(s.page.getByRole('link', { name: /افتح الحساب/ }).first(), { hold: 900 });
        await s.go(href);
        await s.wait(2600);

        await s.say("وكل خطوة بنعملها بتتسجّل باسمنا، نيابةً عنها.", { voice: "وكل حاجة بنعملها هنا بتتسجّل في سجل الأكاديمية باسمنا، ونيابةً عن مين. والأكاديمية نفسها بتشوف إن فريق المنصة دخل، وليه." });
        await s.wait(1600);
      },
    },
    {
      n: 5, slug: 'system-health', title: 'صحة النظام', voice: VOICES.karim,
      goal: 'تطمن إن كل حاجة شغالة وتعرف المشكلة قبل العميل.',
      learn: ['النسخ الاحتياطي والنسخة خارج السيرفر', 'الأجزاء الشغالة والأخطاء الأخيرة', 'البريد: رسالة تجريبية والتنبيهات'],
      start: '/console/health',
      async steps(s) {
        await s.say("«صحة النظام»: كل جزء في السيرفر شغال ولا لأ، في سطر واحد فوق.", { voice: "[warmly] «صحة النظام» بتقولك كل جزء في السيرفر شغال ولا لأ، قبل ما أي عميل يلاحظ. وفوق خالص سطر واحد يلخّص الحالة كلها، والصفحة بتتحدّث لوحدها كل دقيقة." });
        await s.wait(1400);

        await s.say("كل كارت جزء: قاعدة البيانات، والمهام المجدولة، والطابور، والفصل.", { voice: "وكل كارت جزء من السيرفر: قاعدة البيانات، والكاش، والمهام المجدولة، ومنفّذ المهام اللي بيبعت الرسايل، والاتصال اللحظي، والفصل. الأخضر تمام، والأصفر خلّي بالك، والأحمر واقف. وتحت كل كارت مش سليم، مكتوب تعمل إيه." });
        await s.show(s.page.getByText('المهام المجدولة').first(), { hold: 1200 });
        await s.point(s.page.getByText('مساحة التخزين').first(), { hold: 1600 });

        await s.say("النسخ الاحتياطي: آخر نسخة، والنسخة اللي برّه السيرفر، وآخر تجربة استرجاع.", { voice: "وكروت النسخ الاحتياطي: آخر نسخة كاملة، وأرشيف التغييرات، والنسخة المشفّرة اللي برّه السيرفر، وآخر مرة جرّبنا نسترجع فيها. والنسخة اللي ماجرّبناش نرجّعها، مانقدرش نعتمد عليها." });
        await s.point(s.page.getByText('النسخة اللي برّه السيرفر').first(), { hold: 1400 });
        await s.point(s.page.getByText('آخر تجربة استرجاع').first(), { hold: 1400 });

        await s.say("وتحت: كل نسخة اتعملت، وآخر الأخطاء.", { voice: "وتحت، سجل كل نسخة اتعملت، وحجمها، وأخدت قد إيه. وجنبها آخر الأخطاء اللي حصلت في السيرفر، من غير أي بيانات شخصية." });
        await s.scroll(s.page.getByRole('heading', { name: 'النسخ الاحتياطي' }).first());
        await s.wait(1600);

        await s.say("«ابعت إيميل تجربة»: نتأكد إن الإيميل بيوصل. والتنبيهات بتوصل لوحدها.", { voice: "ومن فوق «ابعت إيميل تجربة»، نتأكد إن الإيميل طالع من السيرفر. ولو أي جزء وقف، فريق المنصة بيوصله إيميل تنبيه لوحده، من غير ما حد يفتح الصفحة." });
        await s.scroll(-4000);
        await s.point(s.page.getByRole('button', { name: 'ابعت إيميل تجربة' }), { hold: 1400 });
        await s.click(s.page.getByRole('button', { name: 'افحص تاني' }), { settle: 1800 });
      },
    },
    {
      n: 6, slug: 'audit-and-2fa', title: 'سجل العمليات والتحقق بخطوتين', voice: VOICES.yahia,
      goal: 'تراجع أي خطوة حساسة وتساعد مستخدم ضيّع موبايله.',
      learn: ['سجل عمليات المنصة', 'إعادة ضبط التحقق بخطوتين بسبب', 'قواعد الأمان اللي لازم تفضل'],
      start: '/console',
      async steps(s) {
        await s.say("«سجل المراجعة»: كل خطوة حساسة في كل الأكاديميات، ومابيتعدّلش ولا بيتمسح.", { voice: "[warmly] «سجل المراجعة» فيه كل خطوة حساسة في كل الأكاديميات: الفلوس، والمرتبات، والسياسات، وكلمات السر، وكل خطوة لفريق المنصة. والسجل ده مابيتعدّلش، ولا بيتمسح." });
        await s.click(menuItem(s, 'سجل المراجعة'), { settle: 1800 });

        await s.say("نفلتر: الأكاديمية، والنوع، ومين: فريق المنصة.", { voice: "ونفلتر بالأكاديمية، ونوع العملية، ومين عملها. نختار «فريق المنصة»، فيبان كل اللي الفريق عمله، وجنب كل سطر اسم اللي عمله، ونيابةً عن مين." });
        await s.select(s.page.locator('.fi-fo-field, label').filter({ hasText: 'مين' }).locator('select').first(), { label: 'فريق المنصة' });
        await s.wait(1600);

        await s.say("حد ضيّع موبايله وأكواد الاسترجاع: «صفّر الدخول بخطوتين».", { voice: "ودلوقتي موقف بيحصل كتير: مالكة أكاديمية ضيّعت موبايلها، ومعاهاش أكواد الاسترجاع. من «الدخول بحساب مستخدم»، نختار الأكاديمية، ونختارها هي." });
        await s.click(menuItem(s, 'الدخول بحساب مستخدم'), { settle: 1600 });
        await s.select(s.page.locator('select').first(), { label: DEMO }).catch(async () => {
          await s.click(s.page.locator('.fi-select-input-btn').first(), { settle: 400 });
          await s.click(s.page.getByRole('option', { name: DEMO }).first(), { settle: 900 });
        });
        await s.wait(800);
        await s.type(s.page.locator('input[placeholder*="دوّر"]').first(), 'هالة');
        await s.wait(900);
        await s.click(s.page.getByRole('button', { name: /هالة منصور/ }).first(), { settle: 1200 });

        await s.say("السبب الأول، وبعدين «صفّر». هتشغّله تاني أول ما تدخل.", { voice: "نكتب السبب الأول، وبعدين «صفّر الدخول بخطوتين». أول ما تدخل، هتشغّله تاني على موبايلها الجديد. وده الطريق الوحيد، وبيتسجّل في سجل المراجعة بالسبب." });
        await s.type(s.page.locator('textarea').first(), 'المالكة ضيّعت موبايلها وأكواد الاسترجاع، واتأكدنا منها بمكالمة.');
        await s.click(s.page.getByRole('button', { name: 'صفّر الدخول بخطوتين' }), { settle: 2000 });

        await s.say("والقواعد: مفيش كلمة سر بتتغيّر وإحنا داخلين بحساب حد، ولا الدخول بخطوتين.", { voice: "والقواعد اللي لازم تفضل: وإحنا داخلين بحساب حد، مانقدرش نغيّر كلمة سره، ولا إيميله، ولا موبايله، ولا الدخول بخطوتين بتاعه. ولو حد من الفريق ساب الشغل، حسابه بيتقفل، وأي دخول كان بدأه بيخلص معاه." });
        await s.click(menuItem(s, 'سجل المراجعة'), { settle: 1800 });
        await s.wait(1400);
      },
    },
    {
      n: 7, slug: 'operations', title: 'التشغيل: النشر والنسخ والاسترجاع', voice: VOICES.fathy,
      goal: 'تفهم إزاي المنصة بتتنشر وبتتحمى وبترجع لو حصلت مشكلة.',
      learn: ['النشر التلقائي بعد نجاح الاختبارات', 'النسخ اليومي والنسخة المشفرة برّه', 'تجربة الاسترجاع لنقطة زمنية'],
      start: '/console/health',
      async steps(s) {
        await s.say("النشر: كل تعديل بيتنشر لوحده، بس بعد ما كل الاختبارات تنجح.", { voice: "آخر درس: إزاي المنصة بتتنشر وبتتحمى. أول حاجة النشر: كل تعديل بيدخل الكود، بيتنشر على السيرفر لوحده، بس بعد ما كل الاختبارات تنجح. ولو اختبار واحد فشل، مفيش نشر." });
        await s.wait(1400);

        await s.say("كل يوم: نسخة كاملة، وتصدير، ونسخة من الملفات.", { voice: "والنسخ الاحتياطي كل يوم: نسخة كاملة من قاعدة البيانات، وتصدير، ونسخة من الملفات. وكل تغيير بيحصل في قاعدة البيانات بيتحفظ أول بأول في الأرشيف، فنقدر نرجع لأي لحظة، مش بس لآخر نسخة." });
        await s.scroll(s.page.getByRole('heading', { name: 'النسخ الاحتياطي' }).first());
        await s.point(s.page.getByText('نسخة كاملة').first(), { hold: 1400 });

        await s.say("ونسخة مشفّرة برّه السيرفر: لو السيرفر نفسه راح.", { voice: "وكل ده بيتبعت كمان لمكان برّه السيرفر، مشفّر بمفتاح مش موجود على السيرفر أصلًا. فلو السيرفر نفسه ضاع، البيانات لسه موجودة، ومحدش يقدر يقراها غير اللي معاه المفتاح." });
        await s.point(s.page.getByText('اتبعت برّه السيرفر').first(), { hold: 1600 });

        await s.say("والاسترجاع: نجرّبه بانتظام، لنقطة زمنية، في مكان جديد.", { voice: "والاسترجاع بيتجرّب بانتظام: بنرجّع البيانات لنقطة زمنية معيّنة، في مكان جديد جنب الأصلي، ونتأكد إنها سليمة. وتعليمات كل خطوة مكتوبة في دليل التشغيل، ومكتوبة هنا تحت كمان." });
        await s.scroll(s.page.getByText('RUNBOOKS').first());
        await s.wait(1400);
        await s.scroll(-4000);
        await s.point(s.page.getByText('آخر تجربة استرجاع').first(), { hold: 1600 });

        await s.say("وصحة النظام بتراقب كل ده، وبتنبّه لو حاجة فاتت ميعادها.", { voice: "وصحة النظام بتراقب كل ده: لو نسخة فات ميعادها، أو الأرشيف وقف، أو تجربة الاسترجاع بقالها كتير، بيوصل تنبيه." });
        await s.wait(1400);
      },
    },
  ],
};
