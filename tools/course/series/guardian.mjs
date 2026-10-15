import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
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
/** A transfer receipt to upload (made once, off camera, into .media/). */
async function receiptImage(s) {
  const file = join(dirname(fileURLToPath(import.meta.url)), '../.media/receipt.png');
  if (existsSync(file)) return file;
  const page = await s.page.context().browser().newPage({ viewport: { width: 420, height: 560 } });
  await page.setContent(`<body style="margin:0;font:18px system-ui;direction:rtl;background:#fff"><div style="padding:28px">
    <div style="font-size:22px;font-weight:700;color:#5b2d8e">InstaPay</div><div style="margin:18px 0;font-size:40px;font-weight:700">758 ر.س.</div>
    <div>تم التحويل بنجاح</div><div style="color:#555;margin-top:10px">إلى: أكاديمية النور التجريبية</div><div style="color:#555">رقم العملية: 3058 2271 994</div>
    <div style="color:#555">الخميس 15 أكتوبر · 7:12 م</div></div></body>`);
  await page.screenshot({ path: file });
  await page.close();
  return file;
}
const widget = (s, n) => s.page.locator('.fi-wi-widget').nth(n);

export default {
  key: 'guardian',
  title: 'سلسلة ولي الأمر',
  audience: 'لأولياء الأمور',
  color: '#0ea5e9',
  device: 'phone',
  // Filmed in the phone app itself (apps/mobile's web build, lib/recorder.mjs).
  app: true,
  user: 'guardian',
  lessons: [
    {
      n: 1, slug: 'sign-in-home', title: 'الدخول والصفحة الرئيسية', voice: VOICES.hany,
      who: 'لولي الأمر (عمرو، أبو زياد وليلى)',
      goal: 'ولي الأمر يدخل التطبيق بسهولة، ويعرف كل حاجة عن ولاده من أول شاشة، ويتنقل بينهم.',
      learn: ['الدخول بكود على الموبايل أو الإيميل، أو بكلمة السر', 'الرئيسية: الحصة الجاية، والرصيد، والرحلة، والتقارير والواجبات', 'التبديل بين الأولاد', 'التبويبات تحت، و«المزيد»'],
      start: '/family/home',
      async steps(s) {
        await s.say("ده تطبيق الأكاديمية على الموبايل، بحساب عمرو، أبو زياد وليلى. نخرج الأول عشان نشوف الدخول.", { voice: "[warmly] أهلًا بيك. ده تطبيق الأكاديمية على الموبايل، وده حساب عمرو، أبو زياد وليلى. هنخرج الأول من الحساب، عشان نشوف الدخول من أوّله." });
        await s.click(s.tab('المزيد'), { settle: 1200 });
        await s.click(s.button('اخرج من الحساب ده'), { settle: 900 });
        await s.click(s.button('خرّجه'), { settle: 2000 });
        await s.semantics();

        await s.say("يكتب رقم موبايله أو إيميله، ويوصله كود يدخل بيه، من غير كلمة سر.", { voice: "أسهل طريقة: يكتب رقم موبايله أو إيميله، ويدوس «ابعتلي كود». بيوصله كود على واتساب أو رسالة أو الإيميل، يكتبه ويدخل، من غير ما يفتكر أي كلمة سر." });
        await s.point(s.button('ابعتلي كود'), { hold: 1800 });

        await s.say("ولو عنده كلمة سر: كود الأكاديمية، والإيميل، وكلمة السر.", { voice: "ولو عنده كلمة سر، يدوس «عندك كلمة سر؟». يكتب كود الأكاديمية اللي في رسالة الترحيب، والإيميل، وكلمة السر، ويدوس «ادخل»." });
        await s.click(s.button('عندك كلمة سر؟ ادخل بيها'), { settle: 1200 });
        await s.semantics();
        await s.type(s.field('كود الأكاديمية'), 'demo');
        await s.type(s.field('الإيميل'), 'ziad.guardian@demo.test');
        await s.type(s.field('كلمة السر'), process.env.COURSE_PASSWORD ?? '');
        await s.click(s.button(/^ادخل$/), { settle: 4000 });
        await s.semantics();

        await s.say("أول شاشة: ولاده فوق، واللي متحدد هو اللي الشاشة بتتكلم عنه.", { voice: "وده أول ما يدخل: ترحيب باسمه، وتحته أسامي ولاده. الاسم اللي متعلّم عليه، هو الابن اللي الشاشة كلها بتتكلم عنه دلوقتي." });
        await s.show(s.button('زياد'), { hold: 1600 });

        await s.say("«الحصة الجاية» بتوقيته هو، والزرار بيفتح الفصل في ميعاده.", { voice: "أول كارت: الحصة الجاية، بيومها وساعتها بتوقيته هو، واسم المدرّس. وقبل الحصة بشوية، الزرار اللي فيه بيفتح الفصل على طول." });
        await s.show(s.page.getByRole('group', { name: /الحصة الجاية/ }).first(), { hold: 2000 });

        await s.say("الرصيد، واللي عليه يدفعه، وزرار «ادفع».", { voice: "وجنبه رصيد زياد: كام حصة باقية. ولو عليه فلوس، المبلغ وآخر ميعاد، وزرار «ادفع» على طول." });
        await s.show(s.page.getByRole('group', { name: /رصيد زياد/ }).first(), { hold: 2000 });

        await s.say("ورحلة زياد، وآخر تقرير، والواجبات.", { voice: "وتحت: رحلة زياد في المنهج ونجومه، وآخر تقرير كتبه المدرّس، والواجبات المطلوبة منه." });
        await s.scroll(s.button('كل التقارير'));
        await s.wait(1200);
        await s.scroll(s.button('كل الواجبات'));
        await s.wait(1000);

        await s.say("ولو عايز يشوف ليلى، يدوس على اسمها، والشاشة كلها تبقى عنها.", { voice: "[cheerful] ولو عايز يشوف أخبار ليلى، يدوس على اسمها فوق، والشاشة كلها بتتحوّل عنها هي: حصصها، ورصيدها، ورحلتها." });
        await s.scroll(s.button('ليلى'));
        await s.click(s.button('ليلى'), { settle: 2200 });

        await s.say("وتحت خمس تبويبات: الرئيسية، والحصص، والرحلة، والفلوس، والمزيد.", { voice: "وتحت فيه خمس تبويبات: الرئيسية، والحصص، والرحلة، والفلوس، والمزيد. وكل واحدة منهم ليها درس في السلسلة دي." });
        await s.click(s.tab('الحصص'), { settle: 1400 });
        await s.click(s.tab('الرحلة'), { settle: 1400 });
        await s.click(s.tab('الفلوس'), { settle: 1400 });

        await s.say("وفي «المزيد»: الرسايل، والمساعدة، والخصوصية، والإعدادات.", { voice: "وفي «المزيد»: الرسايل مع المدرّسين، والمساعدة، وخصوصية الأولاد، والإشعارات، وكل إعدادات الحساب." });
        await s.click(s.tab('المزيد'), { settle: 1600 });
        await s.wait(1200);
      },
    },
    {
      n: 2, slug: 'lessons', title: 'حصص ابني', voice: VOICES.heba,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يتابع مواعيد ابنه، ويلغي في حدود السياسة، ويقيّم ويعترض لو فيه غلط.',
      learn: ['المواعيد بتوقيتك وحالة كل حصة', 'إلغاء حصة ومعرفة أثرها قبل ما تأكد', 'تقييم الحصة بالنجوم وبكلمة', 'الاعتراض على نتيجة حصة خلال 48 ساعة'],
      start: '/family/home',
      async steps(s) {
        const lesson = (status) => s.page.getByRole('button', { name: new RegExp(`مع أ\\. .*${status}$`) }).first();

        await s.say("تبويب «الحصص»: كل حصص زياد، بتوقيت ولي الأمر.", { voice: "[warmly] من تحت نفتح «الحصص». هنا كل حصص زياد، اللي جاية واللي فاتت، والمواعيد بتوقيت ولي الأمر نفسه، حتى لو المدرّس في بلد تانية." });
        await s.click(s.tab('الحصص'), { settle: 1600 });

        await s.say("كل حصة: ساعتها، والمدرّس، وحالتها.", { voice: "كل حصة عليها ساعتها ومدّتها، واسم المدرّس، وحالتها: محجوزة، أو مستنية الدفع لو الفاتورة لسه، أو اتعملت، أو اتلغت." });
        await s.show(lesson('مستنية الدفع'), { hold: 1800 });

        await s.say("يدوس على الحصة: تفاصيلها، و«إلغاء الحصة».", { voice: "ولو داس على أي حصة، تفتح تفاصيلها. ولو زياد مش هيقدر يحضر، فيه تحت زرار «إلغاء الحصة»." });
        await s.click(lesson('مستنية الدفع'), { settle: 1400 });
        await s.click(s.button('إلغاء الحصة'), { settle: 1400 });

        await s.say("قبل ما يأكد بيعرف الأثر: قبل الحصة بأكتر من 12 ساعة، الحصة بترجع للرصيد.", { voice: "وقبل ما يأكّد، التطبيق بيقوله أثر الإلغاء حسب سياسة الأكاديمية: هنا الحصة هترجع للرصيد، لأنه بيلغي قبلها بأكتر من اتناشر ساعة. ولو الإلغاء متأخر، ممكن تتحسب." });
        await s.show(s.says('الإلغاء قبل الحصة'), { hold: 2000 });

        await s.say("يختار السبب، ويدوس «أيوه، ألغيها».", { voice: "يختار السبب، زي سفر أو امتحانات، ويدوس «أيوه، ألغيها». والمدرّس والأكاديمية بيوصلهم إشعار على طول." });
        await s.click(s.page.getByRole('checkbox', { name: 'سفر' }).first(), { settle: 600 });
        await s.click(s.button('أيوه، ألغيها'), { settle: 2200 });

        await s.say("وفي «اللي فاتت»: تقييم الحصة بالنجوم، وبكلمة بضغطة.", { voice: "[warmly] وفي «اللي فاتت»، يقدر يقيّم أي حصة خلصت: يفتحها، ويدوس «قيّم الحصة»." });
        await s.click(s.button('اللي فاتت'), { settle: 1400 });
        await s.click(lesson('اتعملت'), { settle: 1400 });
        await s.click(s.button('قيّم الحصة'), { settle: 1400 });

        await s.say("خمس نجوم، و«شرحه واضح» و«صبور»، و«ابعت التقييم».", { voice: "يختار النجوم، وكلمة بضغطة زي «شرحه واضح» أو «صبور»، ويدوس «ابعت التقييم». التقييم بيوصل للأكاديمية، والمدرّس بيشوفه من غير اسم." });
        await s.click(s.button('5 من 5'), { settle: 600 });
        await s.click(s.page.getByRole('checkbox', { name: 'شرحه واضح' }).first(), { settle: 400 });
        await s.click(s.page.getByRole('checkbox', { name: 'صبور' }).first(), { settle: 400 });
        await s.click(s.button('ابعت التقييم'), { settle: 2000 });

        await s.say("ولو نتيجة حصة غلط، يدوس «اعترض» خلال 48 ساعة.", { voice: "ولو نتيجة حصة اتسجّلت غلط، زي إن زياد اتسجّل غايب وهو كان حاضر، بيظهر في الحصة زرار «اعترض» خلال تمانية وأربعين ساعة. يختار اللي حصل، ويكتبه، والأكاديمية بتراجع." });
        await s.show(lesson('بتتراجع'), { hold: 1800 });

        await s.say("والحصة بتفضل «بتتراجع» لحد القرار، ولو معاه حق الرصيد بيتصلّح لوحده.", { voice: "والحصة بتفضل «بتتراجع» لحد ما الأكاديمية تقرّر، والقرار بيوصله إشعار ويظهر هنا. ولو طلع معاه حق، الرصيد بيتصلّح لوحده." });
        await s.click(lesson('بتتراجع'), { settle: 1400 });
        await s.wait(1800);
      },
    },
    {
      n: 3, slug: 'progress-reports', title: 'التقدم والتقارير والواجبات', voice: VOICES.amr,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يعرف ابنه وصل لفين في المنهج، والمدرّس قال إيه بعد كل حصة، وإيه الواجب المطلوب.',
      learn: ['التقدم في المنهج والوقت المتوقع للإنهاء', 'مواد الدروس اللي ينفع يذاكر منها', 'تقرير المدرّس بعد كل حصة', 'الواجبات: المطلوب، واللي اتسلّم، واللي اتصحح'],
      start: '/family/home',
      async steps(s) {
        await s.say("من الرئيسية ← «كل التقارير»: المادة، ونسبتها، وموادها.", { voice: "[warmly] عشان يعرف زياد وصل لفين في المنهج، يدوس من الرئيسية «كل التقارير»." });
        await s.scroll(s.button('كل التقارير'));
        await s.click(s.button('كل التقارير'), { settle: 1800 });

        await s.say("النسبة اللي خلّصها، وهو فين دلوقتي، والوقت الباقي.", { voice: "هنا كل مادة بكارت: النسبة اللي خلّصها من المنهج، وهو فين دلوقتي بالظبط، والوقت المتوقع عشان يخلّص، على عدد حصصه في الأسبوع. ولو عليه واجب مفتوح، بيتكتب كمان." });
        await s.show(s.page.getByRole('progressbar').first(), { hold: 2200 });

        await s.say("و«مواد الدرس»: ملفات المدرّس، يذاكر منها مع ابنه.", { voice: "وتحت «مواد الدرس»: الملفات اللي المدرّس رفعها للدرس اللي زياد فيه، زي جداول وتمارين، يفتحها ويذاكر منها معاه في البيت." });
        await s.point(s.page.getByRole('checkbox').first(), { hold: 1800 });

        await s.say("وبعد كل حصة، تقرير المدرّس: من كارت «آخر تقرير».", { voice: "وبعد كل حصة، المدرّس بيكتب تقرير. نرجع للرئيسية، ونفتح كارت «آخر تقرير»." });
        await s.click(s.button('رجوع'), { settle: 1400 });
        await s.scroll(s.button(/^الأربعاء 7 أكتوبر/));
        await s.click(s.button(/^الأربعاء 7 أكتوبر/), { settle: 1800 });

        await s.say("كل تقرير: الدروس واتعمل فيها إيه، والتقييم، وكلمة المدرّس.", { voice: "كل تقرير عليه يوم الحصة واسم المدرّس، والدروس اللي اتاخدت، وكل درس اتقنه ولا اتدرّب عليه، وتقييم المدرّس بالنجوم، وكلمته عن زياد، زي إيه اللي محتاج حصة كمان." });
        await s.wait(1500);
        await s.scroll(450);
        await s.wait(1200);

        await s.say("والواجبات: من الرئيسية ← «كل الواجبات».", { voice: "والواجبات من الرئيسية برضه، من «كل الواجبات»." });
        await s.click(s.button('رجوع'), { settle: 1400 });
        await s.scroll(s.button('كل الواجبات'));
        await s.click(s.button('كل الواجبات'), { settle: 1800 });

        await s.say("فوق: المستني، واللي اتسلّم، واللي اتصحّح. واللي فات ميعاده بيتكتب عليه.", { voice: "فوق، عدد الواجبات المستنية، واللي اتسلّمت، واللي اتصحّحت. والواجب اللي فات ميعاده بيتكتب عليه، فيفكّر زياد يسلّمه." });
        await s.show(s.page.getByRole('group', { name: /فات ميعاده/ }).first(), { hold: 2000 });

        await s.say("زياد يقدر يسلّم كتابة، أو بصوته، أو بصورة.", { voice: "وزياد يقدر يسلّم الواجب من هنا: يكتبه، أو يسجّل صوته، أو يصوّر الكرّاسة ويرفعها." });
        await s.point(s.button('سجّل صوتك'), { hold: 1600 });

        await s.say("وآخر تصحيح: الدرجة، وتعليق المدرّس.", { voice: "وتحت آخر واجب اتصحّح: درجته، وتعليق المدرّس عليه، زي «ممتاز، انتبه للتنوين»." });
        await s.show(s.button(/^4\/5/), { hold: 2200 });
      },
    },
    {
      n: 4, slug: 'billing', title: 'الرصيد والفواتير والدفع', voice: VOICES.ghozlan,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يعرف رصيد كل ابن، ويدفع فواتيره بالطريقة اللي تناسبه.',
      learn: ['الرصيد: كام حصة باقية، وشراء حصص زيادة', 'الفواتير وخصم الإخوات', 'الدفع أونلاين من صفحة البوابة', 'الدفع بالتحويل وإرسال الإيصال'],
      start: '/family/home',
      async steps(s) {
        const receipt = await receiptImage(s);

        await s.say("كل حاجة تخص الفلوس في تبويب «الفلوس».", { voice: "[warmly] كل حاجة تخص الفلوس في مكان واحد: تبويب «الفلوس» تحت." });
        await s.click(s.tab('الفلوس'), { settle: 1800 });

        await s.say("فوق: المطلوب دلوقتي، وآخر ميعاد، وطريقتين للدفع.", { voice: "فوق، المبلغ المطلوب دلوقتي، ورقم الفاتورة، وآخر ميعاد. وتحته طريقتين للدفع: أونلاين، أو بالتحويل." });
        await s.show(s.page.getByRole('group', { name: /^عليك دلوقتي/ }).first(), { hold: 2000 });

        await s.say("«ادفع»: بتفتح صفحة الدفع بتاعة الأكاديمية، والكارت بيتكتب هناك بس.", { voice: "لو داس «ادفع»، بتفتح صفحة الدفع بتاعة الأكاديمية، والكارت بيتكتب هناك بس، والتطبيق نفسه عمره ما بيشوفه. ولما يخلّص، بيرجع للتطبيق ويلاقي الدفعة اتسجّلت." });
        await s.point(s.button(/^ادفع/), { hold: 1800 });

        await s.say("أو بالتحويل: «بيانات التحويل» بتاعة الأكاديمية، و«انسخ».", { voice: "ولو بيحب يدفع بالتحويل البنكي أو المحفظة، هنا «بيانات التحويل» بتاعة الأكاديمية، وزرار «انسخ» ينسخها مرّة واحدة." });
        await s.scroll(s.button('انسخ'));
        await s.click(s.button('انسخ'), { settle: 1200 });

        await s.say("وبعد ما يحوّل: «دفعت بالتحويل»، ويرفع الإيصال.", { voice: "وبعد ما يحوّل، يدوس «دَفَعْت بالتحويل»، ويختار دفع إزاي، ويكتب المبلغ، ويرفع صورة الإيصال." });
        await s.scroll(s.button('دفعت بالتحويل'));
        await s.click(s.button('دفعت بالتحويل'), { settle: 1500 });
        await s.click(s.page.getByRole('checkbox', { name: 'InstaPay' }).first(), { settle: 500 });
        await s.type(s.field('المبلغ اللي دفعته'), '758');
        const [chooser] = await Promise.all([s.page.waitForEvent('filechooser'), s.click(s.button('اختار صورة أو PDF'), { settle: 300 })]);
        await chooser.setFiles(receipt);
        await s.wait(1500);

        await s.say("«ابعت الإثبات»: الأكاديمية بتتأكد إن الفلوس وصلت، وتأكّد الدفعة.", { voice: "ويدوس «ابعت الإثبات». الأكاديمية بتتأكد إن الفلوس وصلت، وتأكّد الدفعة، ويوصله إشعار. ولو فيه حاجة ناقصة، بتقوله السبب، ويبعت تاني." });
        await s.click(s.button('ابعت الإثبات'), { settle: 2500 });

        await s.say("والرصيد: الحصص الفاضلة لكل مادة، و«حصص زيادة».", { voice: "وتحت، رصيد كل مادة: كام حصة فاضلة في الباقة. ولو زياد محتاج حصص أكتر، زي قبل الامتحانات، يدوس «حصص زيادة»." });
        await s.scroll(s.button(/^حصص زيادة/));
        await s.click(s.button(/^حصص زيادة/), { settle: 1400 });

        await s.say("يختار العدد، وتطلع فاتورة يدفعها، والأكاديمية بتحجز الحصص.", { voice: "يختار العدد، والسعر بيتحسب قدامه، ويدوس «اعمل الفاتورة». تطلع فاتورة يدفعها زي أي فاتورة، وبعد الدفع الأكاديمية بتحجز الحصص معاه." });
        await s.click(s.page.getByRole('checkbox', { name: '2', exact: true }).first(), { settle: 900 });
        await s.show(s.says('اعمل الفاتورة'), { hold: 1200 });
        await s.key('Escape');
        await s.wait(800);

        await s.say("والفواتير: لمين، وفي أنهي مادة، وخصم الإخوات، وحالتها.", { voice: "وفي الآخر، الفواتير. كل فاتورة مكتوب عليها لمين من الولاد، وفي أنهي مادة، ورقمها، والمبلغ، ولو فيه خصم الإخوات بيظهر، وحالتها: مستحقة، أو متأخرة، أو مدفوعة." });
        await s.scroll(s.button(/^ليلى عمرو/));
        await s.show(s.button(/^ليلى عمرو/), { hold: 2000 });
        await s.scroll(s.button(/مدفوعة$/));
        await s.wait(1200);
      },
    },
    {
      n: 5, slug: 'cheers-challenges', title: 'التشجيع والتحديات', voice: VOICES.haytham,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يتابع رحلة ابنه ومهماته، ويشجّعه، ويلعب معاه، ويظبط وقت اللعب.',
      learn: ['رحلة ابنك ومهماته', 'إرسال تشجيع بضغطة', '«العبوا سوا» على درس من دروسه', 'وقت اللعب: أقصى وقت في اليوم وقفل الليل، وإيه اللي اتراجع'],
      start: '/family/home',
      async steps(s) {
        await s.say("تبويب «الرحلة»: رحلة زياد في المادة، بعين ولي الأمر.", { voice: "[cheerful] دلوقتي الحتة الحلوة! من تحت نفتح «الرحلة». دي رحلة زياد في المادة، بس بعين ولي الأمر: آخر حصة خد فيها كام نجمة، وهو فين في المستوى، ونجومه، ورتبته." });
        await s.click(s.tab('الرحلة'), { settle: 2000 });
        await s.show(s.page.getByRole('progressbar').first(), { hold: 1800 });

        await s.say("«مهمات زياد»: حصته الجاية، والدروس اللي جه ميعاد مراجعتها.", { voice: "وتحت، «مهمات زياد»: حصته الجاية، والدروس اللي جه ميعاد مراجعتها. كده ولي الأمر عارف ابنه مطلوب منه إيه." });
        await s.scroll(s.button(/^مراجعة · الفعل الماضي/));
        await s.wait(1200);

        await s.say("«شجّع زياد»: تصفيقة أو نجمة أو قلب أو شعلة حماس، بضغطة.", { voice: "[cheerful] و«شجّع زياد»: بضغطة واحدة يبعت له تصفيقة، أو نجمة، أو قلب، أو شعلة حماس. وزياد بيلاقيها أول ما يفتح رحلته. ينفع خمس تشجيعات في اليوم." });
        await s.scroll(s.button('نجمة نجمة'));
        await s.click(s.button('نجمة نجمة'), { settle: 2200 });

        await s.say("و«العبوا سوا»: جولة لعب مع بعض على درس من دروسه.", { voice: "[excited] و«العبوا سوا»! يختار درس من دروس زياد، ويدوس «العب»، ويبدأوا جولة مع بعض. طريقة لطيفة يراجعوا بيها، ويقضّوا وقت حلو." });
        await s.scroll(s.button(/^الفعل المضارع انت هنا/));
        await s.point(s.button('العب'), { hold: 1600 });

        await s.say("«اتراجع إيه في اللعب»: الدرس، وكام مرّة، وأصعب سؤال.", { voice: "وجنبها، «اتراجع إيه في اللعب»: الدرس اللي زياد راجعه، وكام مرّة، وأصعب سؤال وقف قدامه. كده ولي الأمر عارف يراجع معاه إيه بالظبط." });
        await s.show(s.says('أصعب سؤال'), { hold: 2000 });

        await s.say("ووقت اللعب: أقصى وقت في اليوم، يختاره ولي الأمر.", { voice: "[warmly] وتحت، وقت اللعب: زياد لعب النهارده كام دقيقة، من أقصى وقت مسموح. ولي الأمر يقدر يغيّره: عشر دقايق، أو ربع ساعة، أو تلت ساعة، أو نص ساعة، أو يسيبه على اختيار الأكاديمية." });
        await s.scroll(s.page.getByRole('radio', { name: '20 د' }).first());
        await s.click(s.page.getByRole('radio', { name: '20 د' }).first(), { settle: 1400 });

        await s.say("وقفل اللعب بالليل، من 9 بالليل لـ 7 الصبح.", { voice: "وكمان قفل اللعب بالليل: من تسعة بالليل لسبعة الصبح، الألعاب بتقفل لوحدها، فالنوم يفضل في ميعاده. ولو حب يوقف الألعاب خالص، فيه زرار لده كمان." });
        await s.click(s.page.getByRole('switch', { name: /قفل اللعب/ }).first(), { settle: 1400 });
        await s.wait(1000);
      },
    },
    {
      n: 6, slug: 'consent-privacy', title: 'الموافقة والخصوصية', voice: VOICES.sawsan,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يتحكم في دخول ولاده وتسجيل حصصهم وبياناتهم.',
      learn: ['موافقتك على دخول ابنك لحسابه، وسحبها', 'الموافقة على تسجيل الحصص: إيه بيتسجل ومين يشوفه', 'بياناتي: تصحيح، ونسخة، وطلب مسح'],
      start: '/my',
      async steps(s) {
        await s.say("من القايمة ← «خصوصية الأولاد»: موافقتك على دخول كل ابن لحسابه.", { voice: "[warmly] الولاد اللي أقل من تمنتاشر سنة، محتاجين موافقة ولي الأمر عشان يدخلوا حساباتهم ويحضروا بنفسهم. ده كله في القايمة، في «خصوصية الأولاد»." });
        await menu(s, 'خصوصية الأولاد');

        await s.say("كل ابن: وافقت إمتى، ولو حابب «اسحب الموافقة» في أي وقت.", { voice: "لكل ابن مكتوب إن ولي الأمر وافق، ويوم الموافقة. ولو في أي وقت حب يوقّف دخوله، يدوس «اسحب الموافقة»، فحسابه بيقفل لحد ما يوافق تاني." });
        await s.show(s.page.locator('.pp-card').first(), { hold: 2000 });

        await s.say("ومن القايمة ← «تسجيلات الحصص»: موافقتك على تسجيل حصص ابنك.", { voice: "وبعض الأكاديميات بتسجّل الحصص عشان المتابعة والجودة، بس عمرها ما تسجّل حصة طفل من غير موافقة ولي أمره. ده في «تسجيلات الحصص»." });
        await menu(s, 'تسجيلات الحصص');

        await s.say("الصفحة بتشرح: إيه اللي بيتسجل، ومين يشوفه، وبيفضل قد إيه.", { voice: "الصفحة بتشرح كل حاجة بوضوح: إيه اللي بيتسجّل، ومين يقدر يشوفه، وبيفضل قد إيه قبل ما يتمسح لوحده، وإن ولي الأمر يقدر يرجع في كلامه في أي وقت." });
        await s.show(s.page.locator('.rc-card').first(), { hold: 2400 });

        await s.say("ولو موافق، يدوس «موافق».", { voice: "ولو موافق، يدوس «موافق». ومن هنا، لما حصة زياد تتسجّل، الكل بيشوف علامة «بيسجّل» طول ما التسجيل شغّال." });
        await s.scroll(s.page.getByRole('button', { name: 'موافق' }).first());
        await s.click(s.page.getByRole('button', { name: 'موافق' }).first(), { settle: 1600 });

        await s.say("ومن القايمة ← «بياناتي»: كل اللي الأكاديمية محتفظة بيه عنك وعن ولادك.", { voice: "وفي الآخر «بياناتي»: هنا كل اللي الأكاديمية محتفظة بيه عن ولي الأمر وعن ولاده." });
        await menu(s, 'بياناتي');

        await s.say("يصحّح الاسم على طول، وأي حاجة تانية غلط يكتبها للأكاديمية.", { voice: "يقدر يصحّح الاسم على طول، ولو فيه حاجة تانية غلط، زي تاريخ ميلاد أو اسم في فاتورة، يدوس «اطلب تصحيح» ويكتبها، والأكاديمية بترد في خلال تلاتين يوم." });
        await s.show(s.page.locator('.md-card').first().locator('.md-part').first(), { hold: 2000 });

        await s.say("«جهّز نسخة»: ملف واحد فيه كل بياناته، بيجهز في دقايق ويفضل 7 أيام.", { voice: "و«جهّز نسخة»: الأكاديمية بتجهّز له ملف واحد فيه كل بياناته، والملفات اللي اتسلّمت، في دقايق، ويفضل متاح أسبوع." });
        await s.scroll(s.page.getByRole('button', { name: 'جهّز نسخة' }).first());
        await s.click(s.page.getByRole('button', { name: 'جهّز نسخة' }).first(), { settle: 1800 });

        await s.say("و«امسح الحساب ده»: الأكاديمية بترد في 30 يوم، وبيوضّح قبلها إيه اللي بيتمسح.", { voice: "ولو حابب يمسح حساب، يدوس «امسح الحساب ده». الأكاديمية بترد في خلال تلاتين يوم، وقبل ما يطلب، بيشوف بالظبط إيه اللي هيتمسح، وإيه اللي بيفضل من غير اسم، زي الفواتير." });
        await s.point(s.page.getByRole('button', { name: 'امسح الحساب ده' }).first(), { hold: 2000 });
      },
    },
    {
      n: 7, slug: 'help', title: 'الرسايل والمساعدة والشكاوى', voice: VOICES.maged,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يكلّم مدرّس ابنه، ويوصل للأكاديمية بسرعة لو فيه مشكلة أو شكوى.',
      learn: ['الرسايل مع المدرّسين: انت في كل محادثة، والأرقام بتتخفي', 'تذكرة مساعدة: شكوى، أو فلوس، أو مشكلة تقنية', 'متابعة الرد', '«بلّغ عن قلق» لو فيه قلق على ابنك'],
      start: '/my',
      async steps(s) {
        await s.say("من القايمة ← «الرسايل»: محادثات ولادك مع مدرّسينهم، وانت موجود في كل واحدة.", { voice: "[warmly] من القايمة نفتح «الرسايل». هنا كل محادثة بين مدرّس وابنك، وولي الأمر موجود فيها دايمًا، والأكاديمية بتراجعها." });
        await menu(s, 'الرسايل');

        await s.say("بنختار ليلى، ونفتح محادثتها مع الأستاذة نور.", { voice: "نغيّر لليلى، ونفتح الشات بتاعها مع الأستاذة نور الهدى." });
        await s.click(s.page.locator('.pf-switch').getByText('ليلى عمرو'), { settle: 1400 });
        await s.click(s.page.locator('.ms-item').first(), { settle: 1400 });

        await s.say("يكتب رسالته ويبعتها. ولو كتب رقم تليفون أو لينك، بيتخفي لوحده.", { voice: "يكتب رسالته ويبعتها. ولاحظ: لو كتب رقم تليفون أو لينك، المنصّة بتخفيه لوحدها، عشان التواصل يفضل هنا، وده بيحمي الولاد." });
        await s.type(s.page.locator('.ms-compose textarea'), 'شكرًا يا أستاذة نور، ليلى مبسوطة جدًا. ولو حبيتي كلميني على 0100 123 4567');
        await s.click(s.page.locator('.ms-compose').getByRole('button').last(), { settle: 1800 });
        await s.show(s.page.locator('.ms-thread'), { hold: 2000 });

        await s.say("ولو فيه مشكلة: من القايمة ← «المساعدة» ← «تذكرة جديدة».", { voice: "ولو فيه مشكلة محتاجة الأكاديمية نفسها، يفتح من القايمة «المساعدة»، ويدوس «تَذْكَرة جديدة»." });
        await menu(s, 'المساعدة');
        await s.click(s.page.getByRole('button', { name: 'تذكرة جديدة' }).first(), { settle: 1000 });

        await s.say("يختار الموضوع: شكوى على مدرّس أو حصة، أو الدفع والفواتير، أو مشكلة تقنية.", { voice: "يختار الموضوع: شكوى على مدرّس أو حصّة، والأكاديمية بتحقّق فيها وتقوله عملت إيه. أو الدفع والفواتير. أو مشكلة تقنية. أو أي حاجة تانية." });
        await s.show(s.modal(), { hold: 1800 });
        await s.click(s.modal().locator('label', { hasText: 'الدفع والفواتير' }).first(), { settle: 500 });

        await s.say("يكتب عنوان، ويحكي حصل إيه، ويدوس «ابعت».", { voice: "ويكتب عنوان قصير، ويحكي حصل إيه، ولو معاه صورة يرفعها، ويدوس «ابعت»." });
        await s.type(s.modal().locator('input[type=text]').first(), 'دفعت الفاتورة ولسه مكتوب متأخرة');
        await s.type(s.modal().locator('textarea').first(), 'حوّلت المبلغ امبارح، ورفعت الإيصال، بس الفاتورة لسه مكتوب عليها متأخرة.');
        await s.click(s.modal().locator('.fi-modal-footer').getByRole('button', { name: 'ابعت' }), { settle: 1800 });

        await s.say("التذكرة اتفتحت، وحالتها ظاهرة، والرد بيوصله هنا وفي رسالة.", { voice: "التذكرة اتفتحت، وحالتها ظاهرة. ولما حد من الأكاديمية يرد، الرد بيوصله هنا، وكمان في رسالة، ويقدر يرد عليه، لحد ما المشكلة تتحل." });
        await s.wait(1800);

        await s.say("و«بلّغ عن قلق»: لو قلقان على ابنك من حاجة حصلت، المسؤولين عن حماية الطلاب بس اللي بيقروها.", { voice: "وفي القايمة كمان «بلّغ عن قلق». ده لحاجة مختلفة: لو ولي الأمر قلقان على ابنه من حاجة حصلت، يكتبها هنا، واللي بيقراها المسؤولين عن حماية الطلاب في الأكاديمية بس، وبيتابعوها على طول." });
        await menu(s, 'بلّغ عن قلق');
        await s.wait(2000);
      },
    },
    {
      n: 8, slug: 'settings-app', title: 'الإعدادات والإشعارات والتطبيق', voice: VOICES.hoda,
      who: 'لولي الأمر',
      goal: 'ولي الأمر يختار إزاي توصله الأخبار، ويظبط حسابه.',
      learn: ['اللغة والتوقيت', 'تغيير الإيميل أو الموبايل بكود', 'الإشعارات: القنوات والأوقات الهادية', 'التحقق بخطوتين', 'تطبيق الموبايل وبوابة الويب'],
      start: '/my',
      async steps(s) {
        await s.say("من الصورة اللي فوق ← «إعداداتي».", { voice: "[warmly] كل إعدادات الحساب في مكان واحد: ندوس على الصورة اللي فوق، ونختار «إعداداتي»." });
        await s.click(s.page.locator('.fi-topbar .fi-avatar').first(), { settle: 600 });
        await s.click(s.page.locator('.fi-dropdown-list-item').filter({ hasText: 'إعداداتي' }).first(), { settle: 1500 });

        await s.say("اللغة، والتوقيت: كل المواعيد بتظهر بتوقيت مدينته.", { voice: "أول حاجة اللغة، عربي أو إنجليزي. وبعدها التوقيت: كل مواعيد الحصص بتظهر له بتوقيت مدينته هو، حتى لو المدرّس في بلد تانية." });
        await s.show(s.page.locator('.fi-section').filter({ hasText: 'اللغة والتوقيت' }).first(), { hold: 2600 });

        await s.say("تغيير الإيميل أو الموبايل: بيوصل كود على الجديد الأول.", { voice: "ولو غيّر الإيميل أو رقم الموبايل، يدوس «غيّر الإيميل»، ويكتب الجديد. المنصّة بتبعت كود على العنوان الجديد، ومش بيتغيّر غير لما الكود يرجع، عشان نتأكد إنه بتاعه." });
        await s.click(s.page.getByRole('button', { name: 'غيّر الإيميل' }), { settle: 900 });
        await s.type(s.modal().locator('input:visible').first(), 'amr.salem@demo.test');
        await s.click(s.modal().getByRole('button', { name: 'ابعت كود' }), { settle: 1500 });
        await s.show(s.modal(), { hold: 2200 });
        await s.hush();
        await s.key('Escape');
        await s.wait(700);

        await s.say("الإشعارات: لكل نوع يختار يوصله إزاي.", { voice: "[cheerful] ودي أهم حتة: الإشعارات. لكل نوع، الحصص، والتقارير والواجبات، والفواتير، يختار يوصله إزاي: على كل القنوات، أو من غير واتساب، أو جوّه التطبيق بس." });
        await s.scroll(s.page.locator('.fi-section').filter({ hasText: 'الشاشات والإشعارات' }).first());
        await s.click(s.page.locator('label').filter({ hasText: 'تنبيه وإيميل، من غير واتساب' }).nth(2), { settle: 500 });

        await s.say("والأوقات الهادية: الرسايل بتستنى لحد الصبح.", { voice: "وكمان الأوقات الهادية: في الوقت ده الرسايل والتنبيهات بتستنى لحد الصبح، عشان محدّش يصحى على رسالة. بس تنبيه «ادخل دلوقتي» بتاع الحصّة بيوصل في أي وقت." });
        await s.click(s.page.locator('label').filter({ hasText: 'من 11 بالليل لـ 8 الصبح' }).first(), { settle: 500 });

        await s.say("ويدوس «حفظ التغييرات».", { voice: "وبعدين يدوس «حفظ التغييرات»، عشان يتسجّل اللي اختاره." });
        await s.click(s.page.getByRole('button', { name: 'حفظ التغييرات' }), { settle: 1500 });

        await s.say("والتحقق بخطوتين: كود من تطبيق على الموبايل مع كلمة السر.", { voice: "وتحت خالص فيه التحقق بخطوتين. لو شغّله، الدخول بيطلب كمان كود من تطبيق على موبايله، فحتى لو حد عرف كلمة السر، مش هيقدر يدخل." });
        await s.scroll(s.page.getByRole('button', { name: 'إعداد' }));
        await s.point(s.page.getByRole('button', { name: 'إعداد' }), { hold: 1800 });

        await s.say("ونفس الحساب على تطبيق الموبايل وبوابة الويب.", { voice: "[warmly] وآخر حاجة: نفس الحساب ده بيفتح على تطبيق الأكاديمية على الموبايل، وعلى بوابة الويب، بنفس الإعدادات، والإشعارات بتوصله على التطبيق على طول." });
        await s.wait(2000);
      },
    },
  ],
};
