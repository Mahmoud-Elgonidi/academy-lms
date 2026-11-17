/*
 * Fixture pages for building the portal before the API ships the page
 * (site-builder.md, 8.4): /{locale}/site-preview?t=fixture-<name> in
 * development only, and the unit tests. Pictures are stock photos by URL;
 * names are made up. Removed from the preview route before merge.
 */
import type { AcademySite } from '../server';
import type { BlockStyle, Media, PageBlock, PageSettings, SitePageData } from '../page';

type Lang = 'ar' | 'en';
type Pair = [ar: string, en: string];

const photo = (id: string, w: number, h: number, alt: Pair, lang: Lang): Media => ({
  url: `https://images.unsplash.com/photo-${id}?w=${w}&h=${h}&fit=crop&q=72&auto=format`,
  width: w,
  height: h,
  alt: lang === 'ar' ? alt[0] : alt[1],
  mime: 'image/jpeg',
});

const wordmark = (name: string, hue: number): Media => ({
  url: `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="240" height="72" viewBox="0 0 240 72"><circle cx="30" cy="36" r="16" fill="hsl(${hue} 60% 45%)"/><text x="56" y="44" font-family="Georgia, serif" font-size="22" fill="#1f2937">${name}</text></svg>`)}`,
  width: 240,
  height: 72,
  alt: name,
  mime: 'image/png',
});

const VIDEO: Media = { url: 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/360/Big_Buck_Bunny_360_10s_1MB.mp4', width: null, height: null, alt: '', mime: 'video/mp4' };

const style = (over: Partial<BlockStyle> = {}): BlockStyle => ({ tone: 'paper', align: 'start', space: 'normal', width: 'normal', eyebrow: false, ...over });
let seq = 0;
const id = () => `fx${(++seq).toString(36).padStart(6, '0')}`;

function block<T extends PageBlock['type']>(type: T, variant: Extract<PageBlock, { type: T }>['variant'], data: Extract<PageBlock, { type: T }>['data'], over: { style?: Partial<BlockStyle>; anchor?: string; nav?: string | null } = {}): PageBlock {
  const blockId = id();
  return { id: blockId, type, variant, anchor: over.anchor ?? `b-${blockId}`, nav: over.nav ?? null, style: style(over.style), data } as PageBlock;
}

const H = { eyebrow: null, title: null, lead: null };

function settings(over: Partial<PageSettings>, _lang: Lang): PageSettings {
  return {
    audience: 'adults',
    fonts: { heading: 'IBM Plex Sans Arabic', body: 'IBM Plex Sans Arabic', url: null },
    accent: 'primary',
    mood: 'light',
    corners: 'brand',
    motion: 'subtle',
    header: { style: 'solid', cta: { label: '', action: { kind: 'trial' } }, sign_in: true, language_switch: true },
    footer: { text: null, links: [], social: [] },
    whatsapp: '+966500000000',
    floating_whatsapp: true,
    seo: { title: null, description: null, image: null },
    ...over,
  };
}

/** The demo academy's page as it migrated: today's page, block for block. */
function legacy(l: (p: Pair) => string, lang: Lang): SitePageData {
  return {
    revision: 1,
    settings: settings({ audience: 'families', motion: 'full', header: { style: 'overlay', cta: { label: '', action: { kind: 'trial' } }, sign_in: true, language_switch: true } }, lang),
    blocks: [
      block('hero', 'phones', {
        kicker: null, title: l(['تعليم فردي مباشر يصنع الفرق', 'One-to-one live lessons that make the difference']),
        lead: l(['معلّم واحد لابنك، وخطة على مقاسه، وتقرير بعد كل حصة.', 'One teacher for your child, a plan that fits, and a report after every lesson.']),
        primary: { label: '', action: { kind: 'trial' } }, secondary: { label: '', action: { kind: 'sign_in' } }, trust: null,
        media: null, video: null, poster: null, media_side: 'end', media_shape: 'rounded', scrim: 'medium',
        show: { kicker: true, lead: true, buttons: true, trust: true, media: true },
      }, { style: { tone: 'night' } }),
      block('features', 'cards', { ...H, columns: 3, items: [
        { icon: 'users', title: l(['معلّم واحد', 'One teacher']), text: l(['يعرف ابنك ويتابعه حصة بعد حصة.', 'Who knows your child, lesson after lesson.']) },
        { icon: 'report', title: l(['تقرير بعد كل حصة', 'A report after every lesson']), text: l(['ما تعلّمه، وما يحتاجه بعدها.', 'What was learned, and what comes next.']) },
        { icon: 'calendar', title: l(['مواعيد تناسبكم', 'Times that suit you']), text: l(['تختارون الأيام والساعات.', 'You pick the days and hours.']) },
      ] }, { anchor: 'why', nav: l(['لماذا نحن', 'Why us']), style: { tone: 'white', eyebrow: true } }),
      block('subjects', 'cards', { ...H, subject_ids: [], show_levels: true, show_programs: true, show_categories: true, ask_label: null }, { anchor: 'subjects', nav: l(['المواد', 'Subjects']), style: { eyebrow: true } }),
      block('teachers', 'cards', { ...H, show_bio: true, show_languages: true, show_ages: true, show_badges: true }, { anchor: 'teachers', nav: l(['المعلّمون', 'Teachers']), style: { tone: 'white', eyebrow: true } }),
      block('steps', 'line', { ...H, items: null }, { anchor: 'start', style: { eyebrow: true } }),
      block('app', 'phones', { ...H, points: null, show_code: true, show_stores: true, media: null }, { anchor: 'app', style: { tone: 'night', eyebrow: true } }),
      block('pricing', 'cards', { ...H, highlight: null, highlight_label: null, per_lesson: true, note: null }, { anchor: 'pricing', nav: l(['الأسعار', 'Pricing']), style: { tone: 'white', eyebrow: true } }),
      block('testimonials', 'marquee', { ...H, items: testimonials(l, false) }, { anchor: 'testimonials', style: { eyebrow: true } }),
      block('faq', 'accordion', { ...H, items: faqs(l) }, { anchor: 'faq', nav: l(['أسئلة', 'FAQ']), style: { tone: 'white', eyebrow: true } }),
      block('form', 'split', { ...H, points: null, teach: true, whatsapp_button: true }, { anchor: 'join', style: { tone: 'night', eyebrow: true } }),
    ],
  };
}

function testimonials(l: (p: Pair) => string, photos: boolean, lang: Lang = 'ar') {
  return [
    { name: l(['سارة المطيري', 'Sarah Al-Mutairi']), role: l(['متعلّمة في مسار تحليل البيانات', 'Data analysis track']), quote: l(['خرجت من كل حصة بشيء أطبّقه في عملي في اليوم نفسه. المعلّم يعرف أين أتعثّر قبل أن أقول.', 'I left every lesson with something I used at work the same day. My teacher knew where I would stumble before I said it.']), photo: photos ? photo('1494790108377-be9c29b29330', 240, 240, ['', ''], lang) : null, rating: photos ? 5 : 0 },
    { name: l(['خالد الحربي', 'Khalid Al-Harbi']), role: l(['مدير مشاريع', 'Project manager']), quote: l(['الحصص الفردية وفّرت عليّ أشهرًا. خطة واضحة، وتقرير بعد كل حصة.', 'One-to-one lessons saved me months. A clear plan, and a report after each lesson.']), photo: photos ? photo('1507003211169-0a1dd7228f2d', 240, 240, ['', ''], lang) : null, rating: photos ? 5 : 0 },
    { name: l(['منى عبد الله', 'Mona Abdullah']), role: l(['ولية أمر', 'Parent']), quote: l(['ابنتي تنتظر الحصة كل أسبوع، وأنا أقرأ التقرير وأعرف أين وصلت.', 'My daughter looks forward to every lesson, and I read the report to see where she is.']), photo: photos ? photo('1438761681033-6461ffad8d80', 240, 240, ['', ''], lang) : null, rating: photos ? 4 : 0 },
  ];
}

function faqs(l: (p: Pair) => string) {
  return [
    { q: l(['كيف تبدأ الحصة التجريبية؟', 'How does the free trial work?']), a: l(['تملأ النموذج، ونتواصل معك لنحدد موعدًا مع معلّم مناسب.', 'Fill in the form and we will set a time with a suitable teacher.']) },
    { q: l(['هل أحتاج إلى برنامج خاص؟', 'Do I need special software?']), a: l(['لا. تدخل الحصة من المتصفح أو من التطبيق.', 'No. You join from your browser or the app.']) },
    { q: l(['هل أحصل على شهادة؟', 'Do I get a certificate?']), a: l(['نعم، عند إتمام كل مسار بنجاح.', 'Yes, when you complete each track.']) },
  ];
}

/** An online-courses academy: editorial, the academy's own photography, no phones. */
function courses(l: (p: Pair) => string, lang: Lang): SitePageData {
  return {
    revision: 7,
    settings: settings({}, lang),
    blocks: [
      block('hero', 'split', {
        kicker: l(['دورات مباشرة بإشراف خبراء', 'Live courses led by practitioners']),
        title: l(['تعلّم المهارة التي تنقلك إلى عملك القادم', 'Learn the skill that takes you to your next role']),
        lead: l(['دورات قصيرة مباشرة في البرمجة والبيانات والتصميم، بمجموعات صغيرة ومشروع حقيقي في نهاية كل دورة.', 'Short live courses in software, data and design, in small groups, with a real project at the end of each one.']),
        primary: { label: l(['احجز جلسة تعريفية مجانية', 'Book a free intro session']), action: { kind: 'trial' } },
        secondary: { label: l(['تصفّح الدورات', 'Browse the courses']), action: { kind: 'block', block: 'courses' } },
        trust: [l(['مجموعات حتى ٨ متعلّمين', 'Groups of up to 8']), l(['شهادة إتمام', 'Certificate of completion']), l(['استرداد خلال ٧ أيام', '7-day refund'])],
        media: photo('1531482615713-2afd69097998', 1600, 1900, ['متعلّمون يعملون معًا على حاسوب', 'Learners working together at a laptop'], lang),
        video: null, poster: null, media_side: 'end', media_shape: 'arch', scrim: 'medium',
        show: { kicker: true, lead: true, buttons: true, trust: true, media: true },
      }, { style: { space: 'roomy', width: 'wide' } }),
      block('stats', 'band', { ...H, items: [
        { value: '+1,200', label: l(['متعلّم أتمّوا دوراتنا', 'learners finished a course']) },
        { value: '94%', label: l(['أوصوا بنا لزملائهم', 'recommend us to a colleague']) },
        { value: '38', label: l(['دورة مباشرة هذا العام', 'live courses this year']) },
        { value: '4.8', label: l(['متوسط تقييم المعلّمين', 'average teacher rating']) },
      ] }, { style: { tone: 'tint', space: 'compact' } }),
      block('subjects', 'list', { ...H, title: l(['الدورات', 'Courses']), lead: l(['اختر المسار، وابدأ من المستوى الذي يناسبك.', 'Pick a track and start at your level.']), subject_ids: [], show_levels: true, show_programs: true, show_categories: true, ask_label: null }, { anchor: 'courses', nav: l(['الدورات', 'Courses']) }),
      block('features', 'icons', { ...H, title: l(['لماذا يختارنا المتعلّمون', 'Why learners choose us']), lead: null, columns: 3, items: [
        { icon: 'laptop', title: l(['مشروع حقيقي', 'A real project']), text: l(['تنهي كل دورة بعمل تضيفه إلى ملفك.', 'Every course ends with work for your portfolio.']) },
        { icon: 'users', title: l(['مجموعات صغيرة', 'Small groups']), text: l(['ثمانية متعلّمين على الأكثر، فيعرفك المعلّم باسمك.', 'Eight learners at most, so your teacher knows you by name.']) },
        { icon: 'award', title: l(['شهادة موثّقة', 'A verified certificate']), text: l(['برمز تحقق يراه صاحب العمل.', 'With a code an employer can check.']) },
        { icon: 'clock', title: l(['مواعيد مسائية', 'Evening times']), text: l(['تتعلّم بعد العمل دون أن تترك وظيفتك.', 'Learn after work without leaving your job.']) },
        { icon: 'chat', title: l(['مرشد لكل متعلّم', 'A mentor for each learner']), text: l(['تسأله بين الحصص ويجيبك في يومه.', 'Ask between lessons and hear back the same day.']) },
        { icon: 'globe', title: l(['بالعربية والإنجليزية', 'In Arabic and English']), text: l(['المصطلح بلغته، والشرح بلغتك.', 'Terms in their language, the explaining in yours.']) },
      ] }, { nav: l(['لماذا نحن', 'Why us']), style: { tone: 'white' } }),
      block('teachers', 'portraits', { ...H, title: l(['معلّمون يعملون في المجال', 'Teachers who work in the field']), show_bio: true, show_languages: true, show_ages: false, show_badges: false }, { nav: l(['المعلّمون', 'Teachers']) }),
      block('pricing', 'table', { ...H, highlight: 'plan-2', highlight_label: null, per_lesson: true, note: l(['الأسعار شاملة الضريبة. تستطيع الاسترداد خلال سبعة أيام من الدفع.', 'Prices include VAT. Refunds within seven days of payment.']) }, { nav: l(['الأسعار', 'Pricing']), style: { tone: 'white' } }),
      block('testimonials', 'grid', { ...H, title: l(['ماذا قال متعلّمونا', 'What our learners say']), items: testimonials(l, true, lang) }, {}),
      block('faq', 'two_columns', { ...H, items: faqs(l) }, { nav: l(['أسئلة', 'FAQ']), style: { tone: 'white' } }),
      block('cta', 'banner', { title: l(['ابدأ بجلسة تعريفية مجانية', 'Start with a free intro session']), lead: l(['نتعرّف على هدفك ونقترح الدورة المناسبة، دون أي التزام.', 'We learn your goal and suggest the right course, with no commitment.']), primary: { label: l(['احجز الجلسة', 'Book the session']), action: { kind: 'trial' } }, secondary: { label: '', action: { kind: 'whatsapp' } }, media: null }, { style: { tone: 'brand' } }),
      block('form', 'centered', { ...H, points: null, teach: false, whatsapp_button: false }, { style: { tone: 'paper' } }),
      block('contact', 'band', { ...H, email: 'hello@example.com', phone: '+966112345678', address: l(['الرياض، حي الملقا', 'Al Malqa, Riyadh']), hours: l(['من الأحد إلى الخميس، ٩ص – ٩م', 'Sun–Thu, 9am – 9pm']), whatsapp: true }, { style: { tone: 'white', space: 'compact' } }),
    ],
  };
}

/** A training company: a video opening, partners, words beside pictures. */
function training(l: (p: Pair) => string, lang: Lang): SitePageData {
  const page = courses(l, lang);
  return {
    revision: 3,
    settings: { ...page.settings, header: { ...page.settings.header, style: 'overlay' }, accent: 'info', footer: { text: l(['تدريب عملي للفرق والأفراد منذ ٢٠١٥.', 'Hands-on training for teams and individuals since 2015.']), links: [{ label: l(['سياسة الاسترداد', 'Refund policy']), url: '/ar/sign-in' }], social: [{ network: 'linkedin', url: 'https://www.linkedin.com/' }, { network: 'youtube', url: 'https://www.youtube.com/' }] } },
    blocks: [
      block('hero', 'video', {
        kicker: l(['تدريب وتطوير مهني', 'Training and professional development']),
        title: l(['فرق أقوى تبدأ بتدريب يناسب عملها', 'Stronger teams start with training that fits their work']),
        lead: l(['برامج مباشرة يصمّمها مدرّبون خبراء حول تحدياتكم الحقيقية.', 'Live programmes designed by expert trainers around your real challenges.']),
        primary: { label: l(['اطلب عرضًا لفريقك', 'Request a proposal']), action: { kind: 'trial' } }, secondary: { label: '', action: { kind: 'whatsapp' } }, trust: [],
        media: null, video: VIDEO, poster: photo('1427504494785-3a9ca7044f45', 1920, 1080, ['', ''], lang), media_side: 'end', media_shape: 'rounded', scrim: 'strong',
        show: { kicker: true, lead: true, buttons: true, trust: false, media: true },
      }, { style: { tone: 'night', space: 'roomy', align: 'center' } }),
      block('logos', 'row', { ...H, title: l(['تدرّب معنا فرق من', 'Teams who trained with us']), items: [wordmark('Nimbus', 200), wordmark('Qamar Labs', 30), wordmark('Atlas', 150), wordmark('Sahel Co', 280), wordmark('Oryx', 0)].map((m) => ({ media: m, name: m.alt, url: null })), mono: true }, { style: { tone: 'white', space: 'compact', align: 'center' } }),
      block('features', 'list', { ...H, title: l(['ما الذي يتغيّر بعد البرنامج', 'What changes after the programme']), columns: 2, items: [
        { icon: 'check', title: l(['قرارات أسرع', 'Faster decisions']), text: l(['أدوات تحليل يطبّقها الفريق في الأسبوع الأول.', 'Analysis tools the team uses in week one.']) },
        { icon: 'check', title: l(['اجتماعات أقصر', 'Shorter meetings']), text: l(['إطار واضح لكل اجتماع ونتيجة مكتوبة.', 'A clear frame for each meeting and a written outcome.']) },
        { icon: 'check', title: l(['قادة يتابعون', 'Leads who follow up']), text: l(['خطط متابعة شهرية لكل مدير.', 'Monthly follow-up plans for each manager.']) },
        { icon: 'check', title: l(['قياس الأثر', 'Measured impact']), text: l(['تقرير قبل البرنامج وبعده.', 'A report before and after.']) },
      ] }, {}),
      block('image_text', 'side', { ...H, title: l(['ورش عمل في مقرّكم أو عن بعد', 'Workshops at your office or online']), body: [
        { t: 'p', c: [{ text: l(['نصمّم البرنامج بعد جلسة استماع مع قادة الفريق، ثم ', 'We design the programme after a listening session with your leads, then ']) }, { text: l(['نقيس الأثر', 'measure the impact']), b: true }, { text: l([' بعد ثلاثة أشهر.', ' three months later.']) }] },
        { t: 'quote', c: [{ text: l(['«لم يكن تدريبًا عامًا؛ كان عن مشاكلنا نحن.»', '“It was not generic training; it was about our own problems.”']) }] },
      ], media: photo('1600880292203-757bb62b4baf', 1400, 1000, ['فريق يحتفل بإنجاز', 'A team celebrating a result'], lang), video: null, poster: null, media_side: 'start', bullets: [l(['من ٦ إلى ٢٠ مشاركًا', '6 to 20 participants']), l(['بالعربية أو الإنجليزية', 'In Arabic or English'])], link: { label: l(['تحدّث مع مستشار', 'Talk to an adviser']), action: { kind: 'whatsapp' } } }, { style: { tone: 'tint' } }),
      block('steps', 'cards', { ...H, title: l(['كيف نعمل', 'How we work']), items: [
        { title: l(['جلسة استماع', 'A listening session']), text: l(['نفهم أهدافكم وتحدياتكم.', 'We learn your goals and challenges.']) },
        { title: l(['برنامج مصمّم', 'A tailored programme']), text: l(['محتوى وتمارين من واقع عملكم.', 'Content and exercises from your work.']) },
        { title: l(['تدريب مباشر', 'Live training']), text: l(['ورش تفاعلية بمدرّبين خبراء.', 'Interactive workshops with expert trainers.']) },
        { title: l(['متابعة وقياس', 'Follow-up and measuring']), text: l(['تقرير أثر بعد ثلاثة أشهر.', 'An impact report after three months.']) },
      ] }, { style: { tone: 'white' } }),
      block('stats', 'cards', { ...H, title: l(['بالأرقام', 'In numbers']), items: [
        { value: '120+', label: l(['شركة', 'companies']) }, { value: '9,400', label: l(['متدرّب', 'trainees']) }, { value: '4.9/5', label: l(['رضا المشاركين', 'participant rating']) },
      ] }, { style: { tone: 'night' } }),
      block('testimonials', 'single', { ...H, title: l(['بكلماتهم', 'In their words']), items: testimonials(l, true, lang) }, { style: { align: 'center' } }),
      block('gallery', 'masonry', { ...H, title: l(['من ورشنا', 'From our workshops']), ratio: 'auto', items: [
        { media: photo('1524178232363-1fb2b075b655', 1200, 800, ['قاعة تدريب', 'A training room'], lang), caption: null },
        { media: photo('1543269865-cbf427effbad', 900, 1100, ['متدرّبون يتناقشون', 'Trainees in discussion'], lang), caption: l(['ورشة التفكير التصميمي', 'Design thinking workshop']) },
        { media: photo('1519389950473-47ba0277781c', 1200, 900, ['طاولة عمل', 'A worktable'], lang), caption: null },
        { media: photo('1523240795612-9a054b0db644', 1200, 800, ['فريق في مكتبة', 'A team in a library'], lang), caption: null },
      ] }, { style: { tone: 'white' } }),
      block('video', 'framed', { ...H, title: l(['شاهد يومًا من برنامج القيادة', 'A day in the leadership programme']), source: { kind: 'youtube', id: 'dQw4w9WgXcQ', poster: photo('1515378791036-0648a3ef77b2', 1600, 900, ['', ''], lang) }, caption: l(['ثلاث دقائق من الورشة الثانية.', 'Three minutes from the second workshop.']), ratio: '16:9', autoplay_muted: false }, {}),
      block('cta', 'split', { title: l(['جاهزون لبرنامج فريقكم؟', 'Ready for your team’s programme?']), lead: l(['أرسلوا لنا هدفكم، ونعود إليكم بمقترح خلال يومين.', 'Send us your goal; we reply with a proposal within two days.']), primary: { label: l(['اطلب عرضًا', 'Request a proposal']), action: { kind: 'trial' } }, secondary: null, media: photo('1573496359142-b8d87734a5a2', 1200, 900, ['مدرّبة تبتسم', 'A smiling trainer'], lang) }, { style: { tone: 'paper' } }),
      block('form', 'split', { ...H, title: l(['اطلب عرضًا لفريقك', 'Request a proposal for your team']), points: [l(['ردّ خلال يومين', 'A reply within two days']), l(['دون أي التزام', 'No commitment'])], teach: true, whatsapp_button: true }, { style: { tone: 'night' } }),
    ],
  };
}

/** Every block type and variant on one page (a long page for checking each). */
function everything(l: (p: Pair) => string, lang: Lang): SitePageData {
  const a = courses(l, lang).blocks;
  const b = training(l, lang).blocks;
  const kids = legacy(l, lang).blocks;
  return {
    revision: 9,
    settings: settings({ header: { style: 'overlay', cta: { label: '', action: { kind: 'trial' } }, sign_in: true, language_switch: true }, footer: training(l, lang).settings.footer }, lang),
    blocks: [
      block('hero', 'centered', { kicker: null, title: l(['أكاديمية تُعلّم كما تحب أن تتعلّم', 'An academy that teaches the way you like to learn']), lead: l(['حصص مباشرة، ومعلّمون يعرفونك، وتقدّم تراه.', 'Live lessons, teachers who know you, progress you can see.']), primary: { label: '', action: { kind: 'trial' } }, secondary: { label: '', action: { kind: 'sign_in' } }, trust: null, media: photo('1522202176988-66273c2fd55f', 1800, 1000, ['متعلّمون حول طاولة', 'Learners around a table'], lang), video: null, poster: null, media_side: 'end', media_shape: 'rounded', scrim: 'medium', show: { kicker: true, lead: true, buttons: true, trust: true, media: true } }, { style: { align: 'center' } }),
      ...a.slice(1, 3), a[3]!, kids[1]!,
      block('features', 'numbers', { ...H, title: l(['أربع خطوات نحو الإتقان', 'Four steps to mastery']), columns: 2, items: [
        { icon: 'star', title: l(['تشخيص', 'Diagnose']), text: l(['نقيس أين تقف.', 'We measure where you stand.']) },
        { icon: 'star', title: l(['خطة', 'Plan']), text: l(['أهداف أسبوعية واضحة.', 'Clear weekly goals.']) },
        { icon: 'star', title: l(['تطبيق', 'Practise']), text: l(['تمارين على مشاريع حقيقية.', 'Exercises on real projects.']) },
        { icon: 'star', title: l(['مراجعة', 'Review']), text: l(['تقرير بعد كل حصة.', 'A report after each lesson.']) },
      ] }, { style: { tone: 'white' } }),
      block('subjects', 'chips', { ...H, subject_ids: [], show_levels: false, show_programs: false, show_categories: false, ask_label: null }, { style: { align: 'center' } }),
      a[4]!, block('teachers', 'compact', { ...H, show_bio: false, show_languages: true, show_ages: true, show_badges: true }, { style: { tone: 'white' } }),
      kids[4]!, b[4]!, kids[5]!,
      block('app', 'plain', { ...H, points: null, show_code: true, show_stores: true, media: null }, { style: { tone: 'white' } }),
      block('app', 'image', { ...H, points: null, show_code: false, show_stores: true, media: photo('1588072432836-e10032774350', 1200, 1000, ['', ''], lang) }, { style: { tone: 'tint' } }),
      kids[6]!, a[5]!, kids[7]!, a[6]!, b[6]!, kids[8]!, a[7]!,
      b[5]!,
      block('rich_text', 'article', { ...H, title: l(['عن الأكاديمية', 'About the academy']), body: [
        { t: 'p', c: [{ text: l(['بدأنا عام ٢٠١٥ بمعلّمين اثنين وفكرة واحدة: ', 'We started in 2015 with two teachers and one idea: ']) }, { text: l(['كل متعلّم يستحق معلّمًا يعرفه', 'every learner deserves a teacher who knows them']), b: true }, { text: '.' }] },
        { t: 'h3', c: [{ text: l(['ما نؤمن به', 'What we believe']) }] },
        { t: 'ul', items: [[{ text: l(['التعلّم المباشر أسرع من المسجّل.', 'Live learning beats recorded.']) }], [{ text: l(['التقرير بعد كل حصة حقّ لك.', 'A report after every lesson is your right.']) }]] },
        { t: 'quote', c: [{ text: l(['التعليم الجيد يبدأ بالإصغاء.', 'Good teaching starts with listening.']), i: true }] },
        { t: 'p', c: [{ text: l(['اقرأ ', 'Read ']) }, { text: l(['شروطنا', 'our terms']), href: '/ar/sign-in' }, { text: '.' }] },
      ] }, { style: { width: 'narrow' } }),
      block('rich_text', 'two_columns', { ...H, title: l(['منهجنا', 'Our method']), body: [{ t: 'p', c: [{ text: l(['نقسم كل مهارة إلى خطوات صغيرة تقيسها بنفسك.', 'We split each skill into small steps you can measure yourself.']) }] }, { t: 'ol', items: [[{ text: l(['شرح', 'Explain']) }], [{ text: l(['تطبيق', 'Apply']) }], [{ text: l(['مراجعة', 'Review']) }]] }] }, { style: { tone: 'white' } }),
      b[3]!,
      block('image_text', 'overlap', { ...H, title: l(['مكتبة مفتوحة لكل متعلّم', 'A library open to every learner']), body: [{ t: 'p', c: [{ text: l(['مئات الكتب والمراجع لمتعلّمينا.', 'Hundreds of books and references for our learners.']) }] }], media: photo('1501504905252-473c47e087f8', 1400, 900, ['', ''], lang), video: null, poster: null, media_side: 'end', bullets: [], link: null }, {}),
      block('image_text', 'stacked', { ...H, title: l(['صف الأطفال', 'The children’s class']), body: [{ t: 'p', c: [{ text: l(['تعلّم بالألوان والقصص.', 'Learning with colour and stories.']) }] }], media: photo('1580582932707-520aed937b7b', 1600, 900, ['', ''], lang), video: null, poster: null, media_side: 'start', bullets: [], link: null }, { style: { tone: 'white' } }),
      block('gallery', 'grid', { ...H, title: l(['صور', 'Photos']), ratio: '4:3', items: [
        { media: photo('1517245386807-bb43f82c33c4', 1200, 900, ['', ''], lang), caption: null },
        { media: photo('1515378791036-0648a3ef77b2', 1200, 900, ['', ''], lang), caption: null },
        { media: photo('1544717305-2782549b5136', 1200, 900, ['', ''], lang), caption: null },
      ] }, {}),
      block('gallery', 'carousel', { ...H, title: l(['من الصفوف', 'From the classes']), ratio: 'auto', items: [
        { media: photo('1560785496-3c9d27877182', 1200, 800, ['', ''], lang), caption: l(['صف الرياضيات', 'Maths class']) },
        { media: photo('1588072432836-e10032774350', 1200, 800, ['', ''], lang), caption: null },
        { media: photo('1524178232363-1fb2b075b655', 1200, 800, ['', ''], lang), caption: null },
        { media: photo('1543269865-cbf427effbad', 1200, 800, ['', ''], lang), caption: null },
      ] }, { style: { tone: 'white' } }),
      b[7]!,
      block('video', 'wide', { ...H, title: l(['جولة في الأكاديمية', 'A tour of the academy']), source: { kind: 'upload', video: VIDEO, poster: photo('1427504494785-3a9ca7044f45', 1600, 900, ['', ''], lang) }, caption: null, ratio: '16:9', autoplay_muted: false }, { style: { tone: 'night', width: 'wide' } }),
      block('video', 'side', { ...H, title: l(['تعرّف على منهجنا', 'Meet our method']), lead: l(['دقيقتان تشرحان كيف تسير الحصة.', 'Two minutes on how a lesson goes.']), source: { kind: 'vimeo', id: '76979871', poster: null }, caption: null, ratio: '16:9', autoplay_muted: false }, {}),
      b[1]!,
      block('logos', 'marquee', { ...H, items: [wordmark('Nimbus', 200), wordmark('Qamar Labs', 30), wordmark('Atlas', 150), wordmark('Sahel Co', 280)].map((m) => ({ media: m, name: m.alt, url: 'https://example.com' })), mono: false }, { style: { space: 'compact' } }),
      block('logos', 'grid', { ...H, title: l(['اعتمادات', 'Accreditations']), items: [wordmark('Nimbus', 200), wordmark('Atlas', 150), wordmark('Oryx', 0)].map((m) => ({ media: m, name: m.alt, url: null })), mono: true }, { style: { tone: 'white', align: 'center' } }),
      a[8]!,
      block('cta', 'card', { title: l(['هل أنت معلّم؟', 'Are you a teacher?']), lead: l(['انضم إلى فريقنا.', 'Join our team.']), primary: { label: '', action: { kind: 'teach' } }, secondary: null, media: null }, {}),
      b[8]!,
      block('cta', 'banner', { title: l(['الحصة الأولى علينا', 'The first lesson is on us']), lead: null, primary: { label: '', action: { kind: 'trial' } }, secondary: null, media: photo('1523240795612-9a054b0db644', 1920, 900, ['', ''], lang) }, {}),
      block('spacer', 'divider', { size: 'md' }, {}),
      a[10]!,
      block('contact', 'cards', { ...H, email: 'hello@example.com', phone: '+966112345678', address: l(['الرياض', 'Riyadh']), hours: null, whatsapp: true }, {}),
      block('spacer', 'space', { size: 'sm' }, {}),
      block('form', 'centered', { ...H, points: null, teach: true, whatsapp_button: true }, { style: { tone: 'tint' } }),
    ],
  };
}

const PAGES: Record<string, (l: (p: Pair) => string, lang: Lang) => SitePageData> = { legacy, courses, training, all: everything };

/** The shared live data the fixture pages draw from (subjects, teachers, plans), as GET /v1/site writes them. */
export function fixtureSite(name: string, locale: string = 'ar'): AcademySite {
  seq = 0;
  const lang: Lang = locale === 'en' ? 'en' : 'ar';
  const l = (p: Pair) => (lang === 'ar' ? p[0] : p[1]);
  const page = (PAGES[name] ?? everything)(l, lang);
  return {
    academy: { name: l(['أكاديمية المدار', 'Madar Academy']), code: 'MADAR' },
    page,
    preview: { revision: page.revision, published_revision: page.revision - 1, enabled: true },
    content: { hero: { title: '', lead: '' }, features: [], testimonials: [], faqs: [], sections: [], whatsapp: null, seo: { title: '', description: '' } },
    subjects: [
      { id: 'sub-1', name: l(['تطوير الويب', 'Web development']), levels: [l(['مبتدئ', 'Beginner']), l(['متوسط', 'Intermediate']), l(['متقدم', 'Advanced'])], categories: [l(['برمجة', 'Software'])], programs: [{ name: l(['مسار المطوّر الشامل', 'Full-stack track']), description: l(['١٢ أسبوعًا', '12 weeks']) }] },
      { id: 'sub-2', name: l(['تحليل البيانات', 'Data analysis']), levels: [l(['أساسيات', 'Foundations']), l(['تطبيقي', 'Applied'])], categories: [l(['بيانات', 'Data'])], programs: [{ name: 'Python & SQL', description: null }] },
      { id: 'sub-3', name: l(['تصميم تجربة المستخدم', 'UX design']), levels: [l(['مبتدئ', 'Beginner']), l(['متقدم', 'Advanced'])], categories: [l(['تصميم', 'Design'])], programs: [] },
      { id: 'sub-4', name: l(['اللغة الإنجليزية للأعمال', 'Business English']), levels: ['B1', 'B2', 'C1'], categories: [l(['لغات', 'Languages'])], programs: [] },
    ],
    teachers: [
      { id: 't-1', name: l(['أ. ليلى حسن', 'Laila Hassan']), bio: l(['مهندسة برمجيات منذ عشر سنوات، تدرّس الويب بمشاريع حقيقية.', 'A software engineer for ten years who teaches the web through real projects.']), photo_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=600&h=750&fit=crop&q=72', gender: 'female', languages: ['ar', 'en'], age_groups: ['adults'], subjects: [{ name: l(['تطوير الويب', 'Web development']), level_from: null, level_to: null }], accepts_trials: true, accepts_groups: true },
      { id: 't-2', name: l(['أ. عمر السيد', 'Omar El-Sayed']), bio: l(['محلّل بيانات في قطاع البنوك.', 'A data analyst in banking.']), photo_url: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&h=750&fit=crop&q=72', gender: 'male', languages: ['ar'], age_groups: ['adults', 'teen'], subjects: [{ name: l(['تحليل البيانات', 'Data analysis']), level_from: l(['أساسيات', 'Foundations']), level_to: null }], accepts_trials: true, accepts_groups: false },
      { id: 't-3', name: l(['أ. نورة القحطاني', 'Noura Al-Qahtani']), bio: l(['مصممة منتجات رقمية.', 'A digital product designer.']), photo_url: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=600&h=750&fit=crop&q=72', gender: 'female', languages: ['ar', 'en'], age_groups: ['adults'], subjects: [{ name: l(['تصميم تجربة المستخدم', 'UX design']), level_from: null, level_to: null }], accepts_trials: false, accepts_groups: true },
      { id: 't-4', name: l(['أ. يوسف إبراهيم', 'Youssef Ibrahim']), bio: null, photo_url: null, gender: 'male', languages: ['en'], age_groups: ['adults'], subjects: [{ name: l(['اللغة الإنجليزية للأعمال', 'Business English']), level_from: 'B1', level_to: 'C1' }], accepts_trials: true, accepts_groups: false },
    ],
    plans: [
      { id: 'plan-1', name: l(['أساسية', 'Starter']), session_minutes: 60, sessions_per_week: 1, sessions_per_block: 4, validity_days: 30, shared: false, format: 'group', max_students: 8, prices: [{ region_id: 'r-1', price: lang === 'ar' ? '⁨600 ر.س.⁩' : 'SAR 600', amount_minor: 60000, currency: 'SAR' }] },
      { id: 'plan-2', name: l(['مكثّفة', 'Intensive']), session_minutes: 60, sessions_per_week: 2, sessions_per_block: 8, validity_days: 30, shared: false, format: 'group', max_students: 8, prices: [{ region_id: 'r-1', price: lang === 'ar' ? '⁨1,080 ر.س.⁩' : 'SAR 1,080', amount_minor: 108000, currency: 'SAR' }] },
      { id: 'plan-3', name: l(['فردية', 'One-to-one']), session_minutes: 45, sessions_per_week: 2, sessions_per_block: 8, validity_days: 45, shared: true, format: 'private', max_students: null, prices: [{ region_id: 'r-1', price: lang === 'ar' ? '⁨1,600 ر.س.⁩' : 'SAR 1,600', amount_minor: 160000, currency: 'SAR' }] },
    ],
    regions: [{ id: 'r-1', name: l(['السعودية', 'Saudi Arabia']), currency: 'SAR', default: true, countries: ['SA'] }],
    app: { android_url: 'https://play.google.com/store', ios_url: null },
    whatsapp: '+966500000000',
    trials: { booking: 'staff', response_hours: 24, notice_hours: 3, help_choose_subject: false, help_choose_subject_id: null },
    form_token: '',
  };
}
