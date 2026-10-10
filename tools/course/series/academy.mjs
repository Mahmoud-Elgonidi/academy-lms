// The academy's back office (/admin): owners, admins, schedulers, sales and the accountant.
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

export default {
  key: 'academy',
  title: 'سلسلة الأكاديمية',
  audience: 'لصاحب الأكاديمية وفريق الإدارة',
  color: '#14b8a6',
  device: 'desktop',
  user: 'owner',
  lessons: [
    {
      n: 1, slug: 'first-sign-in', title: 'أول دخول وجولة في لوحة الإدارة',
      goal: 'تدخل لوحة الأكاديمية بأمان وتعرف كل حاجة فين.',
      learn: ['الدخول بالإيميل أو الموبايل والتحقق بخطوتين', 'القائمة الجانبية ومجموعاتها والعدّادات', 'البحث والإشعارات و«إعداداتي»'],
    },
    {
      n: 2, slug: 'branding-and-plan', title: 'هوية الأكاديمية وباقتها',
      goal: 'تخلّي المنصة شبه أكاديميتك: الاسم والشعار والألوان، وتعرف حدود باقتك.',
      learn: ['الاسم والشعار وقوالب الألوان', 'شكل المنصة عند المدرسين والأسر والتطبيق', 'صفحة «باقة الأكاديمية» والحدود والاستهلاك'],
    },
    {
      n: 3, slug: 'subjects-and-curriculum', title: 'المواد والمستويات والمناهج',
      goal: 'تبني كتالوج المواد ومنهج كل مادة بالدروس والمواد التعليمية.',
      learn: ['مادة بمستويات مرتبة وتصنيفات (مسارات ومناهج)', 'منشئ المنهج: برنامج ← مستوى ← وحدة ← درس', 'ملفات ولينكات الدرس ومين يشوفها، والنشر'],
    },
    {
      n: 4, slug: 'teachers', title: 'المدرسين: الإضافة والملف والفحص',
      goal: 'تضيف مدرس وتظبط ملفه عشان يظهر صح في البحث.',
      learn: ['إضافة مدرس ورسالة الترحيب', 'المواد والمستويات والأعمار والحد اليومي', 'فحص المدرسين: المستندات والاعتماد والانتهاء'],
    },
    {
      n: 5, slug: 'shifts-and-time-off', title: 'الشفتات والإجازات والمدرس البديل',
      goal: 'تعتمد أوقات المدرسين وتدير إجازاتهم من غير ما حصة تضيع.',
      learn: ['مراجعة مقترح شفتات: المقارنة وتحليل الأثر والاعتماد', 'طلبات الإجازة وتعيين مدرس بديل', 'إجازات الأكاديمية والعطلات الرسمية'],
    },
    {
      n: 6, slug: 'students-and-guardians', title: 'الطلاب وأولياء الأمور',
      goal: 'تضيف طالب وولي أمره وتظبط مرحلته وموافقته.',
      learn: ['طالب صغير تحت ولي أمر، وطالب كبير بحسابه', 'المرحلة: أطفال، إعدادي، ثانوي، كبار', 'موافقات أولياء الأمور وسن الموافقة'],
    },
    {
      n: 7, slug: 'find-teacher-enroll', title: 'ابحث عن مدرس وسجّل طالب',
      goal: 'تلاقي المدرس المناسب في الأوقات اللي تناسب الطالب، وتسجّله في اشتراك أسبوعي بفاتورته.',
      learn: ['تختار الطالب والمادة والشروط', 'تحدد الأوقات المناسبة وتقرا النتايج', 'تحجز مبدئيًا وتسجّل وتصدر الفاتورة'],
      start: '/admin',
      async steps(s) {
        await s.say('من القائمة الجانبية افتح «ابحث عن مدرس». من الصفحة دي بتسجّل أي طالب في مادة جديدة.');
        await s.click(s.nav('ابحث عن مدرس'), { settle: 1200 });

        await s.say('فوق بتختار نوع الحجز: «اشتراك أسبوعي» لحصص ثابتة كل أسبوع، أو «حصة تجريبية» لحصة واحدة.');
        await s.point(s.page.locator('.fi-fo-toggle-buttons').first(), { hold: 1600 });

        await s.say(`اختار الطالب. ابدأ اكتب اسمه، وتوقيته وسنّه بيتحطوا لوحدهم من ملفه.`);
        await choose(s, 'student_id', STUDENT, 'رنا');
        await s.show(field(s, 'timezone'), { hold: 1200 });

        await s.say('اختار المادة. لو المادة ليها مستويات أو تصنيفات، حددها هنا عشان توصل للمدرس المناسب بالظبط.');
        await choose(s, 'subject_id', 'قرآن');
        await s.show(field(s, 'level_id'), { hold: 1100 });

        await s.say('حدد جنس المدرس، وعدد الحصص في الأسبوع، ومدة الحصة، وتاريخ البداية.');
        await s.point(labelled(s, 'جنس المدرس'), { hold: 900 });
        await s.point(field(s, 'per_week'), { hold: 700 });
        await s.point(labelled(s, 'مدة الحصة'), { hold: 700 });

        await s.say('«الحد الأدنى للتوافر»: الميعاد لازم يكون فاضي 8 أسابيع ورا بعض، أو تقبل أسبوع واحد ناقص.');
        await s.point(labelled(s, 'الحد الأدنى للتوافر'), { hold: 1400 });

        await s.say('في «الأوقات المناسبة للطالب» دوس على الساعات اللي تناسبه، بتوقيته هو.');
        await s.scroll(s.page.locator('.ft-grid'));
        for (const day of [6, 1, 3]) {
          for (const hour of [17, 18, 19]) await s.click(s.page.locator(`button.ft-cell[wire\\:click="toggleCell(${day}, ${hour})"]`), { settle: 120, ringFirst: false });
        }

        await s.say('ودوس «ابحث».');
        await s.click(s.page.locator('.ft-grid-tools').getByRole('button', { name: 'ابحث' }), { settle: 1200 });
        await s.scroll(-2000);

        await s.say('النتيجة: المدرسين مترتبين حسب «الملاءمة» (قد إيه مواعيدهم فاضية في الأوقات دي)، و«الإشغال» يعني قد إيه جدوله مليان.');
        await s.show(s.page.locator('.ft-teacher').first().locator('.ft-teacher-head'), { hold: 2200 });

        await s.say('كل ميعاد مكتوب بتوقيت الطالب بالخط الكبير، وتحته بتوقيت المدرس، فمفيش لخبطة في فرق التوقيت.');
        await s.point(s.page.locator('.ft-teacher').first().locator('.ft-chip').first(), { hold: 1800 });

        await s.say('النظام بيقترح «نمط» جاهز على قد عدد الحصص. دوس «اختاره»، أو اختار المواعيد بنفسك من تحت.');
        await s.click(s.page.locator('.ft-teacher').first().getByRole('button', { name: 'اختاره' }));

        await s.say('دوس «احجز مبدئيًا»: المواعيد دي بتتحجز للطالب 10 دقايق، محدش تاني ياخدها لحد ما تخلّص.');
        await s.click(s.page.locator('.ft-select').getByRole('button').first(), { settle: 900 });
        await s.show(s.page.locator('.ft-select'), { hold: 1500 });

        await s.say('دوس «سجّل الطالب». الرقم اللي جنبه هو عدد الحصص اللي هتتعمل في الفترة.');
        await s.click(s.page.locator('.ft-select').getByRole('button', { name: /سجّل الطالب/ }), { settle: 1200 });

        await s.say('اختار المنطقة السعرية والباقة. الأسعار بتظهر بعملة المنطقة، والباقات اللي مش مناسبة بتبقى مقفولة.');
        await s.click(s.modal().getByText('8 حصص', { exact: false }).first(), { settle: 900 });

        await s.say('اختار مين اللي هيدفع: ولي الأمر أو الطالب نفسه. ولو فيه كوبون خصم اكتبه هنا، والإجمالي بيتحسب قدامك.');
        await s.point(s.modal().getByText('ولي أمر', { exact: false }).first(), { hold: 900 });
        await s.point(s.modal().locator('input[type=text]').last(), { hold: 1200 });

        await s.say('دوس «سجّل وأصدر الفاتورة».');
        await s.click(s.modal().getByRole('button', { name: 'سجّل وأصدر الفاتورة' }), { settle: 1800 });

        await s.say('خلاص: الطالب اتسجّل والحصص اتعملت، والفاتورة راحت لولي الأمر. الحصص بتتأكد أول ما الفاتورة تتدفع.');
        await s.show(s.page.locator('.fi-no-notification').first(), { hold: 2600 });

        await s.say('ومن نفس الرسالة تفتح الفاتورة أو حساب الطالب على طول.');
        await s.click(s.page.locator('.fi-no-notification').first().getByRole('link', { name: /الفاتورة/ }).first(), { settle: 1500 });
        await s.wait(2500);
      },
    },
    {
      n: 8, slug: 'leads-and-trials', title: 'العملاء المحتملين والحصة التجريبية',
      goal: 'تتابع أي استفسار لحد ما يبقى طالب مشترك.',
      learn: ['لوحة العملاء ومراحلهم', 'حجز حصة تجريبية من «ابحث عن مدرس»', 'تقييم المدرس للحصة والتحويل لاشتراك'],
    },
    {
      n: 9, slug: 'plans-prices-coupons', title: 'الباقات والأسعار والكوبونات',
      goal: 'تسعّر خدمتك بمرونة لكل منطقة وتعمل عروض.',
      learn: ['باقة بعدد حصص وأسعار لكل منطقة وعملة', 'الباقة المشتركة بين أكتر من مادة', 'الكوبونات وخصم الإخوات'],
    },
    {
      n: 10, slug: 'invoices-and-payments', title: 'الفواتير والمدفوعات',
      goal: 'تحصّل فلوسك أونلاين أو بالتحويل وتطلع فاتورة ضريبية سليمة.',
      learn: ['الدفع الإلكتروني وبوابات الدفع', 'مراجعة إثبات التحويل واعتماده أو رفضه', 'الفاتورة الضريبية وكود QR والإلغاء بإشعار دائن'],
    },
    {
      n: 11, slug: 'live-board-calendar', title: 'العمليات اللحظية والتقويم',
      goal: 'تتابع كل حصة لحظة بلحظة وتتدخل في الوقت المناسب.',
      learn: ['مين دخل ومين اتأخر والتنبيهات', 'التقويم: يوم وأسبوع وشهر وفلترة', 'تصدير المواعيد'],
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
