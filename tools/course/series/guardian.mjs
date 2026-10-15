// Guardians in /my on the phone, in the classic look: Ziad's and Laila's father (ziad.guardian).
// Each lesson has its own Egyptian narrator from the ElevenLabs library (all spoken on eleven_v4).
const VOICES = {
  hany: '1hEuyycI7VQwusZo3Qbe',      // Hany - Calm & Friendly
  heba: 'JHdGl5PsEzushIzzVSd1',      // Heba - Soothing and Gracious
  amr: 'yrPIy5b3iLnVLIBfUSw8',       // Amr - Natural and Clear
  ghozlan: 'xPcC3nehhziQaOrIeAwv',   // Ghozlan - Soft Clear Conversational
  haytham: 'IES4nrmZdUBHByLBde0P',   // Haytham - Energetic, Warm and Cheerful
  sawsan: 'mS4cERRqrNy5Kmlx8Udf',    // Sawsan - Dignified and Warm
  maged: 'amSNjVC0vWYiE8iGimVb',     // Maged Magdy - Calm, Natural and Balanced
  hoda: 'meAbY2VpJkt1q46qk56T',      // Hoda - Expressive and Distinctive
};
/** A page from the side menu, opened from the phone's menu button. */
async function menu(s, label) {
  await s.click(s.page.locator('.fi-topbar-open-sidebar-btn').first(), { settle: 700 });
  await s.click(s.page.locator('.fi-sidebar-item').filter({ hasText: label }).locator('a').first(), { settle: 1500 });
}
const widget = (s, n) => s.page.locator('.fi-wi-widget').nth(n);

export default {
  key: 'guardian',
  title: 'سلسلة ولي الأمر',
  audience: 'لأولياء الأمور',
  color: '#0ea5e9',
  device: 'phone',
  user: 'guardian',
  lessons: [
    {
      n: 1, slug: 'sign-in-home', title: 'الدخول والصفحة الرئيسية', voice: VOICES.hany,
      who: 'لولي الأمر (عمرو، أبو زياد وليلى)',
      goal: 'ولي الأمر يدخل بسهولة، ويعرف كل حاجة عن ولاده من أول صفحة، ويتنقل بينهم.',
      learn: ['الدخول بالإيميل أو الموبايل، بكلمة السر أو بكود', 'الصفحة الرئيسية: الرحلة والحصص والواجبات والرصيد', 'التبديل بين الأولاد', 'القايمة وكل صفحاتها'],
      start: '/my',
      async steps(s) {
        await s.say("ده حساب عمرو، أبو زياد وليلى. نخرج الأول عشان نشوف الدخول من الأول.", { voice: "[warmly] أهلًا بيك. ده حساب عمرو، أبو زياد وليلى. هنخرج الأول من الحساب، عشان نشوف الدخول من أوّله." });
        await s.click(s.page.locator('.fi-topbar .fi-avatar').first(), { settle: 600 });
        await s.click(s.page.getByRole('button', { name: 'تسجيل الخروج' }).first(), { settle: 1500 });

        await s.say("يدخل بالإيميل أو رقم الموبايل، وكلمة السر.", { voice: "صفحة الدخول بسيطة: يكتب الإيميل أو رقم الموبايل، وكلمة السر، ويدوس «تسجيل الدخول»." });
        await s.type(s.page.locator('#form\\.email'), 'ziad.guardian@demo.test');
        await s.type(s.page.locator('#form\\.password'), process.env.COURSE_PASSWORD ?? '');

        await s.say("ولو ناسي كلمة السر: «ادخل بكود» يوصله على موبايله أو إيميله.", { voice: "ولو نسي كلمة السر، مش مشكلة: يدوس «ادخل بكود بدل كلمة السر»، فيوصله كود على موبايله أو إيميله، يكتبه ويدخل على طول." });
        await s.point(s.page.getByText('ادخل بكود بدل كلمة السر').first(), { hold: 1600 });
        await s.click(s.page.getByRole('button', { name: 'تسجيل الدخول' }).first(), { settle: 2200 });

        await s.say("أول صفحة: «أهلًا»، وفوق ولاده. اللي محدد هو اللي الصفحة بتتكلم عنه.", { voice: "وده أول ما يدخل: ترحيب باسمه، وفوق أسامي ولاده. الاسم اللي متحدد، هو الابن اللي الصفحة كلها بتتكلم عنه دلوقتي." });
        await s.show(s.page.locator('.pf-switch'), { hold: 1600 });

        await s.say("«رحلة زياد»: هو فين في المنهج، ونجومه، والمهمات المستنياه.", { voice: "أول كارت «رحلة زياد»: هو فين في المنهج دلوقتي، وعدد نجومه ورتبته، وكام مهمة مستنياه. ولو داس «افتح الرحلة» يشوف رحلته بالتفصيل." });
        await s.show(widget(s, 0), { hold: 1800 });

        await s.say("«الحصص الجاية» بتوقيته هو، وحالة كل حصة.", { voice: "وبعده كارت الحصص اللي جاية: كل حصة بيومها وساعتها بتوقيت ولي الأمر نفسه، واسم المدرّس، وحالتها، ولو مستنية الدفع بيتكتب عليها." });
        await s.scroll(widget(s, 1));
        await s.show(widget(s, 1), { hold: 1800 });

        await s.say("وتحت: التقدم في المادة، والواجبات، وآخر تقرير من المدرّس.", { voice: "وتحت، التقدم في المادة بنسبة، والواجبات المطلوبة، وآخر تقرير كتبه المدرّس، بتقييمه وكلمته." });
        await s.scroll(widget(s, 2));
        await s.show(widget(s, 2), { hold: 1800 });

        await s.say("وفي الآخر: الحصص الباقية في الرصيد، والمطلوب دفعه.", { voice: "وفي الآخر، كام حصة باقية في رصيد زياد، والمبلغ المطلوب دفعه. ولو داس عليه، بيروح للفواتير على طول." });
        await s.scroll(widget(s, 3));
        await s.show(widget(s, 3), { hold: 1800 });

        await s.say("ولو عايز يشوف ليلى، يدوس على اسمها فوق، والصفحة كلها تبقى عنها.", { voice: "[cheerful] ولو عايز يشوف أخبار ليلى، يدوس على اسمها فوق، والصفحة كلها بتتحوّل عنها هي: رحلتها، وحصصها، وواجباتها." });
        await s.scroll(-3000);
        await s.click(s.page.locator('.pf-switch').getByText('ليلى عمرو'), { settle: 1800 });
        await s.wait(1200);

        await s.say("وكل الصفحات في القايمة: الحصص، والرحلة، والرسايل، والتقارير، والواجبات، والفواتير، وغيرهم.", { voice: "وكل حاجة تانية في القايمة، من الزرار اللي فوق: الحصص، والرحلة، والرسايل مع المدرّسين، والتقارير، والواجبات، والرصيد والفواتير، والخصوصية، والمساعدة. وكل صفحة منهم ليها درس في السلسلة دي." });
        await s.click(s.page.locator('.fi-topbar-open-sidebar-btn').first(), { settle: 900 });
        await s.show(s.page.locator('.fi-sidebar-nav').first(), { hold: 2200 });
        await s.key('Escape');
      },
    },
    {
      n: 2, slug: 'lessons', title: 'حصص ابني', voice: VOICES.heba,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يتابع مواعيد ابنه، ويلغي في حدود السياسة، ويقيّم ويعترض لو فيه غلط.',
      learn: ['المواعيد بتوقيتك وحالة كل حصة', 'إلغاء حصة ومعرفة أثرها قبل ما تأكد', 'تقييم الحصة بالنجوم وبكلمة', 'الاعتراض على نتيجة حصة خلال 48 ساعة'],
      start: '/my',
      async steps(s) {
        const booked = () => s.page.locator('.fl-card').filter({ has: s.page.getByRole('button', { name: 'إلغاء الحصة' }) }).first();
        const rated = () => s.page.locator('.fl-card').filter({ has: s.page.locator('.ev-stars') }).first();

        await s.say("من القايمة ← «الحصص»: كل حصص زياد، بتوقيت ولي الأمر.", { voice: "[warmly] من القايمة نفتح «الحصص». هنا كل حصص زياد، اللي جاية واللي فاتت، والمواعيد بتوقيت ولي الأمر نفسه، حتى لو المدرّس في بلد تانية." });
        await menu(s, 'الحصص');

        await s.say("كل حصة: يومها وساعتها، والمدرّس، وحالتها: محجوزة، مستنية الدفع، اتعملت.", { voice: "كل حصة عليها يومها وساعتها، واسم المدرّس، وحالتها: محجوزة، أو مستنية الدفع لو الفاتورة لسه، أو اتعملت، أو اتلغت." });
        await s.show(booked(), { hold: 1800 });

        await s.say("لو زياد مش هيقدر يحضر: «إلغاء الحصة».", { voice: "ولو زياد مش هيقدر يحضر حصة، يدوس «إلغاء الحصة» عليها." });
        await s.click(booked().getByRole('button', { name: 'إلغاء الحصة' }), { settle: 1200 });

        await s.say("قبل ما يأكد بيعرف الأثر: قبل الحصة بأكتر من 12 ساعة، الحصة بترجع للرصيد.", { voice: "وقبل ما يأكّد، المنصّة بتقوله أثر الإلغاء حسب سياسة الأكاديمية: هنا الحصة هترجع للرصيد، لأنه بيلغي قبلها بأكتر من اتناشر ساعة. ولو الإلغاء متأخر، ممكن تتحسب." });
        await s.show(s.modal(), { hold: 2000 });

        await s.say("يختار السبب، ويدوس «أيوه، ألغيها». المدرّس والأكاديمية بيعرفوا على طول.", { voice: "يختار السبب، زي سفر أو امتحانات، ويدوس «أيوه، ألغيها». والمدرّس والأكاديمية بيوصلهم إشعار على طول." });
        await s.select(s.modal().locator('select').first(), 'travel');
        await s.click(s.modal().locator('.fi-modal-footer').getByRole('button', { name: 'أيوه، ألغيها' }), { settle: 1800 });

        await s.say("وعلى الحصص اللي اتعملت: تقييم بالنجوم، وكلمة بضغطة زي «شرحه واضح».", { voice: "[warmly] وعلى الحصص اللي اتعملت، يقدر يقيّم الحصة بالنجوم، ويختار كلمة بضغطة، زي «شرحه واضح» أو «صبور». التقييم بيوصل للأكاديمية، والمدرّس بيشوفه من غير اسم." });
        await s.scroll(rated());
        await s.click(rated().locator('.ev-stars button').nth(4), { settle: 1200 });
        await s.click(rated().locator('.ev-words button').first(), { settle: 1000 });
        await s.click(rated().locator('.ev-words button').nth(1), { settle: 1200 });

        await s.say("ولو نتيجة حصة غلط، زي غياب اتسجّل، يعترض خلال 48 ساعة.", { voice: "ولو نتيجة حصة اتسجّلت غلط، زي إن زياد اتسجّل غايب وهو كان حاضر، بيظهر زرار «اعترض» خلال تمانية وأربعين ساعة. يكتب اللي حصل، والأكاديمية بتراجع." });
        await s.scroll(s.page.locator('.fl-dispute').first());
        await s.point(s.page.locator('.fl-dispute').first(), { hold: 1800 });

        await s.say("والحصة بتفضل «بتتراجع» لحد القرار، ولو معاه حق الرصيد بيتصلّح لوحده.", { voice: "والحصة بتفضل «بتتراجع» لحد ما الأكاديمية تقرّر، والقرار بيظهر هنا. ولو طلع معاه حق، الرصيد بيتصلّح لوحده." });
        await s.wait(1800);
      },
    },
    {
      n: 3, slug: 'progress-reports', title: 'التقدم والتقارير والواجبات', voice: VOICES.amr,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يعرف ابنه وصل لفين في المنهج، والمدرّس قال إيه بعد كل حصة، وإيه الواجب المطلوب.',
      learn: ['التقدم في المنهج والوقت المتوقع للإنهاء', 'مواد الدروس اللي ينفع تذاكر منها', 'تقرير المدرّس بعد كل حصة', 'الواجبات: المطلوب، واللي اتسلّم، واللي اتصحح'],
      start: '/my',
      async steps(s) {
        const card = () => s.page.locator('.lf-card').first();

        await s.say("من كارت التقدم في الصفحة الرئيسية: زياد وصل لفين في المنهج.", { voice: "[warmly] عشان يعرف زياد وصل لفين في المنهج، يفتح من الصفحة الرئيسية كارت التقدم، أو من القايمة." });
        await s.click(s.page.locator('.fi-wi-widget').nth(2).locator('a').first(), { settle: 1600 });

        await s.say("فوق: النسبة اللي خلّصها من المادة، وهو فين دلوقتي، والوقت المتوقع للإنهاء.", { voice: "فوق، النسبة اللي خلّصها من المادة كلها، وهو فين دلوقتي بالظبط، وكام درس خلّص من كام، والوقت المتوقع عشان يخلّص، على عدد حصصه في الأسبوع." });
        await s.show(card(), { hold: 2200 });

        await s.say("وتحت المستويات والوحدات: ✓ الدروس اللي خلّصها، ● اللي فيه، ○ اللي جاية.", { voice: "وتحت، المنهج كله بمستوياته ووحداته. علامة الصح يعني زياد خلّص الدرس ده، والنقطة المليانة هو فيه دلوقتي، والدايرة الفاضية لسه جاي." });
        await s.scroll(s.page.locator('.rm-level').first());
        await s.show(s.page.locator('.rm-level').first(), { hold: 2000 });

        await s.say("وجنب الدروس موادها، زي ملفات PDF، يذاكر منها مع ابنه.", { voice: "وجنب الدروس، المواد اللي المدرّس رفعها، زي جداول وتمارين، يفتحها ويذاكر منها مع زياد في البيت." });
        await s.scroll(s.page.locator('.rm-mats').first());
        await s.point(s.page.locator('.rm-mat').first(), { hold: 1600 });

        await s.say("ومن القايمة ← «التقارير»: اللي المدرّس كتبه بعد كل حصة.", { voice: "وبعد كل حصة، المدرّس بيكتب تقرير. يفتحه من القايمة، من «التقارير»." });
        await menu(s, 'التقارير');

        await s.say("كل تقرير: التقييم، وكلمة المدرّس، والدروس اللي اتاخدت ودرجة كل درس.", { voice: "كل تقرير عليه يوم الحصة واسم المدرّس، وتقييمه للحصة، وكلمته عن زياد، زي إيه اللي محتاج حصة كمان. وتحت الدروس اللي اتاخدت، وكل درس اتقنه ولا اتدرّب عليه، بدرجته." });
        await s.show(card().locator('.lf-head'), { hold: 1200 });
        await s.point(card().locator('.lf-note'), { hold: 1400 });
        await s.show(card().locator('.lf-items'), { hold: 1600 });

        await s.say("ومن القايمة ← «الواجبات»: المطلوب، وميعاده، ولو اتأخر.", { voice: "والواجبات من القايمة برضه. فوق الواجب المطلوب، وميعاد تسليمه، ولو الميعاد عدّى بيتكتب بالأحمر، فيفكّر زياد يسلّمه." });
        await menu(s, 'الواجبات');
        await s.show(card(), { hold: 1800 });

        await s.say("وتحت اللي اتسلّم، واللي اتصحح بدرجته وتعليق المدرّس.", { voice: "وتحت، الواجبات اللي زياد سلّمها، واللي اتصححت بدرجتها، وتعليق المدرّس عليها، زي «ممتاز، انتبه للتنوين»." });
        await s.scroll(s.page.locator('.lf-card').last());
        await s.show(s.page.locator('.lf-card').last(), { hold: 2000 });
      },
    },
    {
      n: 4, slug: 'billing', title: 'الرصيد والفواتير والدفع', voice: VOICES.ghozlan,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يعرف رصيد كل ابن، ويدفع فواتيره بالطريقة اللي تناسبه.',
      learn: ['الرصيد: كام حصة باقية، وشراء حصص زيادة', 'الفواتير وخصم الإخوات', 'الدفع أونلاين من صفحة البوابة', 'الدفع بالتحويل وإرسال الإيصال'],
      start: '/my',
      async steps(s) {
        const invoice = () => s.page.locator('.bf-inv').first();

        await s.say("من القايمة ← «الرصيد والفواتير».", { voice: "[warmly] كل حاجة تخص الفلوس في مكان واحد: من القايمة، «الرصيد والفواتير»." });
        await menu(s, 'الرصيد والفواتير');

        await s.say("فوق رصيد زياد: كام حصة باقية في الباقة.", { voice: "فوق، رصيد زياد في كل مادة: كام حصة باقية له في الباقة، عشان يعرف إمتى هيحتاج يجدّد." });
        await s.show(s.page.locator('.bf-card').first(), { hold: 1800 });

        await s.say("ولو محتاج حصص أكتر من الباقة: «حصص زيادة»، يختار العدد وتطلع فاتورة.", { voice: "ولو زياد محتاج حصص أكتر من الباقة، زي قبل الامتحانات، يدوس «حصص زيادة»، ويختار العدد، فتطلع فاتورة بيها، وبعد الدفع الأكاديمية بتحجز الحصص معاه." });
        await s.click(s.page.getByRole('button', { name: 'حصص زيادة' }).first(), { settle: 1000 });
        await s.show(s.modal(), { hold: 1600 });
        await s.hush();
        await s.key('Escape');
        await s.wait(600);

        await s.say("وتحت الفواتير: لمين، وفي أنهي مادة، وخصم الإخوات، وآخر ميعاد.", { voice: "وتحت، الفواتير. كل فاتورة مكتوب عليها رقمها، ولمين من الولاد، وفي أنهي مادة، والمبلغ، ولو فيه خصم الإخوات بيظهر سطر لوحده، وآخر ميعاد للدفع، ولو اتأخرت بيتكتب «متأخرة»." });
        await s.scroll(invoice());
        await s.show(invoice(), { hold: 2200 });

        await s.say("«اعرض الفاتورة واطبعها» بتفتح الفاتورة كاملة.", { voice: "و«اعرض الفاتورة واطبعها» بتفتح الفاتورة كاملة، بكل بياناتها، ويقدر يطبعها أو يحفظها." });
        await s.point(invoice().getByText('اعرض الفاتورة واطبعها').first(), { hold: 1400 });

        await s.say("للدفع أونلاين: «ادفع دلوقتي»، فتفتح صفحة الدفع، والكارت بيتكتب هناك بس.", { voice: "ولو حابب يدفع أونلاين، يدوس «ادفع دلوقتي». بتفتح صفحة الدفع بتاعة الأكاديمية، والكارت بيتكتب هناك بس، والمنصّة نفسها عمرها ما بتشوفه. هنا دي بوابة تجريبية." });
        await s.click(invoice().getByRole('button', { name: 'ادفع دلوقتي' }), { settle: 2500 });
        await s.show(s.page.locator('form, main').first(), { hold: 1600 });

        await s.say("يدفع، ويرجع للفواتير: «وصلنا الدفع، شكرًا!»، والحصص اتأكدت.", { voice: "[cheerful] يدوس «ادفع»، ويرجع للفواتير لوحده، ويلاقي «وصلنا الدفع، شكرًا!». والحصص اللي كانت مستنية الدفع اتأكدت، والإيصال بيوصله في رسالة." });
        await s.click(s.page.getByRole('button', { name: 'ادفع', exact: true }), { settle: 3000 });
        await s.wait(1600);

        await s.say("ولو بيدفع بالتحويل: «بيانات التحويل» فيها كل حاجة، وزرار «انسخ».", { voice: "ولو بيحب يدفع بالتحويل البنكي أو المحفظة، هنا «بيانات التحويل» بتاعة الأكاديمية، وزرار «انسخ» ينسخها مرّة واحدة." });
        await s.scroll(s.page.locator('.bf-transfer').first());
        await s.point(s.page.locator('.bf-copy').first(), { hold: 1600 });

        await s.say("وبعد التحويل: «دفعت بالتحويل»، يكتب المبلغ واليوم ويرفع صورة الإيصال.", { voice: "وبعد ما يحوّل، يدوس «دَفَعْت بالتحويل» على الفاتورة، ويكتب دفع إزاي، والمبلغ، واليوم، ويرفع صورة الإيصال. والأكاديمية بتتأكد إن الفلوس وصلت، وتأكّد الدفعة." });
        await s.click(s.page.getByRole('button', { name: 'دفعت بالتحويل' }).first(), { settle: 1000 });
        await s.show(s.modal(), { hold: 2200 });
        await s.hush();
        await s.key('Escape');
        await s.wait(800);
      },
    },
    {
      n: 5, slug: 'cheers-challenges', title: 'التشجيع والتحديات',
      goal: 'ولي الأمر يشجّع ابنه ويتابع تحدياته.',
      learn: ['مهام الأسبوع عند ابنك', 'إرسال تشجيع', 'إزاي ده بيفرق مع الأطفال'],
    },
    {
      n: 6, slug: 'consent-privacy', title: 'الموافقة والخصوصية',
      goal: 'ولي الأمر يتحكم في حساب ابنه وبياناته.',
      learn: ['الموافقة على دخول ابنك', 'موافقة تسجيل الحصص', 'نسخة البيانات وطلب الحذف'],
    },
    {
      n: 7, slug: 'help', title: 'المساعدة والشكاوى',
      goal: 'ولي الأمر يوصل للأكاديمية بسرعة لو فيه مشكلة.',
      learn: ['طلب مساعدة أو شكوى', 'متابعة الرد', '«قول لنا» لو فيه قلق على ابنك'],
    },
    {
      n: 8, slug: 'settings-app', title: 'الإعدادات والإشعارات والتطبيق',
      goal: 'ولي الأمر يختار إزاي توصله الأخبار.',
      learn: ['تفضيلات الإشعارات والقنوات', 'اللغة والتوقيت', 'تطبيق الموبايل وبوابة الويب'],
    },
  ],
};
