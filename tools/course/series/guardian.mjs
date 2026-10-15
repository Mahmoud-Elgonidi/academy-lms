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
      n: 2, slug: 'lessons', title: 'حصص ابني',
      goal: 'ولي الأمر يتابع مواعيد ابنه ويغيّرها في حدود السياسة.',
      learn: ['المواعيد بتوقيتك', 'إلغاء حصة ومعرفة أثرها قبل ما تأكد', 'المدرس البديل والتقييم والاعتراض'],
    },
    {
      n: 3, slug: 'progress-reports', title: 'التقدم والتقارير والواجبات',
      goal: 'ولي الأمر يعرف ابنه وصل لفين وإيه المطلوب منه.',
      learn: ['تقرير كل حصة', 'خريطة التقدم في المنهج', 'الواجبات وحالتها'],
    },
    {
      n: 4, slug: 'billing', title: 'الفواتير والدفع',
      goal: 'ولي الأمر يدفع بالطريقة اللي تناسبه ويعرف رصيده.',
      learn: ['الفواتير والرصيد', 'الدفع أونلاين أو رفع إثبات تحويل', 'شراء حصص إضافية والكوبون'],
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
