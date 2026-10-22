// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appName => 'الأكاديمية';

  @override
  String get retry => 'جرّب تاني';

  @override
  String get offline => 'مفيش نت. اتأكد من الاتصال وجرّب تاني.';

  @override
  String get problem => 'حصلت مشكلة. جرّب تاني.';

  @override
  String get back => 'رجوع';

  @override
  String get close => 'قفل';

  @override
  String get errorOfflineTitle => 'مفيش نت';

  @override
  String get errorServerTitle => 'حصلت مشكلة عندنا';

  @override
  String get errorServerBody => 'بنصلّحها. جرّب كمان شوية.';

  @override
  String get errorForbiddenTitle => 'مش مسموح تشوف ده';

  @override
  String get errorForbiddenBody => 'لو شايف إن ده غلط، كلّم الأكاديمية.';

  @override
  String get reloadFailed => 'ماقدرناش نحدّث، واللي قدامك ممكن يكون قديم.';

  @override
  String get send => 'ابعت';

  @override
  String get optional => '(اختياري)';

  @override
  String get noticeSignedOut => 'خرجت من الحساب.';

  @override
  String get noticeExpired => 'الدخول انتهى على الحساب ده. ادخل تاني.';

  @override
  String minutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقايق', two: 'دقيقتين', one: 'دقيقة');
    return '$_temp0';
  }

  @override
  String get today => 'النهارده';

  @override
  String get tomorrow => 'بكرة';

  @override
  String inMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقايق', two: 'دقيقتين', one: 'دقيقة');
    return '$_temp0';
  }

  @override
  String inHours(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n ساعة', few: '$n ساعات', two: 'ساعتين', one: 'ساعة');
    return '$_temp0';
  }

  @override
  String lessonsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n حصة',
      few: '$n حصص',
      two: 'حصتين',
      one: 'حصة واحدة',
      zero: 'مفيش حصص',
    );
    return '$_temp0';
  }

  @override
  String get introSkip => 'تخطّي';

  @override
  String get introNext => 'التالي';

  @override
  String get introStart => 'يلا نبدأ';

  @override
  String introSlide(int n, int total) {
    return 'شريحة $n من $total';
  }

  @override
  String get introLive => 'لايف';

  @override
  String get intro1Title => 'حصص لايف مع مدرّسك';

  @override
  String get intro1Body => 'حصة واحد لواحد بالصوت والصورة، من البيت.';

  @override
  String get intro2Title => 'تابع التقدّم خطوة بخطوة';

  @override
  String get intro2Body => 'كل حصة بتقرّب من الهدف، والطريق كله قدامك.';

  @override
  String get intro3Title => 'المواعيد والواجبات والفلوس في مكان واحد';

  @override
  String get intro3Body => 'والتنبيه بيوصلك في وقته، فمفيش حاجة بتفوتك.';

  @override
  String get introLessonWhen => 'السبت · 7:00 م';

  @override
  String get introHomework => 'واجب جديد';

  @override
  String get introHomeworkWhen => 'قبل الثلاثاء';

  @override
  String get introPaid => 'الفاتورة اتدفعت';

  @override
  String get introThanks => 'شكرًا ليك';

  @override
  String get welcomeTitle => 'أهلًا بيك';

  @override
  String get welcomeLead => 'اكتب رقم موبايلك أو إيميلك، وهنبعتلك كود تدخل بيه.';

  @override
  String get contactLabel => 'رقم الموبايل أو الإيميل';

  @override
  String get contactPlaceholder => '01xxxxxxxxx أو name@mail.com';

  @override
  String get contactEmpty => 'اكتب رقم موبايلك أو إيميلك الأول.';

  @override
  String get contactShortPhone => 'الرقم ده ناقص، اكتبه كامل.';

  @override
  String get contactBadEmail => 'الإيميل ده ناقص حاجة، زي name@mail.com';

  @override
  String countryChange(String code) {
    return 'كود الدولة $code، دوس عشان تغيّره';
  }

  @override
  String get countrySearch => 'دوّر على دولة';

  @override
  String get countryNone => 'مفيش دولة بالاسم ده.';

  @override
  String get countrySa => 'السعودية';

  @override
  String get countryEg => 'مصر';

  @override
  String get countryAe => 'الإمارات';

  @override
  String get countryKw => 'الكويت';

  @override
  String get countryQa => 'قطر';

  @override
  String get countryBh => 'البحرين';

  @override
  String get countryOm => 'عُمان';

  @override
  String get countryJo => 'الأردن';

  @override
  String get countryMa => 'المغرب';

  @override
  String get countryUs => 'أمريكا وكندا';

  @override
  String get countryGb => 'بريطانيا';

  @override
  String get legalAgree => 'لما تكمّل، تبقى موافق على';

  @override
  String get legalAnd => 'و';

  @override
  String get countryCode => 'كود الدولة';

  @override
  String get phoneHint => 'الكود هيوصلك على واتساب.';

  @override
  String get emailHint => 'الكود هيوصلك على الإيميل ده.';

  @override
  String get sendCode => 'ابعتلي كود';

  @override
  String get havePassword => 'عندك كلمة سر؟ ادخل بيها';

  @override
  String get signInOr => 'أو';

  @override
  String get signInWithGoogle => 'ادخل بحساب جوجل';

  @override
  String get signInWithApple => 'ادخل بحساب أبل';

  @override
  String socialFailed(String provider) {
    return 'الدخول بحساب $provider ماكملش. جرّب تاني.';
  }

  @override
  String get providerGoogle => 'جوجل';

  @override
  String get providerApple => 'أبل';

  @override
  String get signInMethodsTitle => 'الدخول بجوجل وأبل';

  @override
  String get signInMethodsHint => 'اربط حسابك بجوجل أو أبل، وادخل بيهم من غير كود ولا كلمة سر.';

  @override
  String signInMethodLinkedAs(String email) {
    return 'مربوط: $email';
  }

  @override
  String get signInMethodLinked => 'مربوط';

  @override
  String get signInMethodNotLinked => 'مش مربوط';

  @override
  String get signInMethodLink => 'اربط';

  @override
  String get signInMethodUnlink => 'افصل';

  @override
  String signInMethodUnlinkConfirm(String provider) {
    return 'تفصل حساب $provider؟ مش هتقدر تدخل بيه لحد ما تربطه تاني.';
  }

  @override
  String signInMethodLinkedDone(String provider) {
    return 'حساب $provider اتربط.';
  }

  @override
  String signInMethodUnlinkedDone(String provider) {
    return 'حساب $provider اتفصل.';
  }

  @override
  String get codeTitle => 'اكتب الكود';

  @override
  String codeSentWhatsapp(String to) {
    return 'بعتنالك كود من 6 أرقام على واتساب، على الرقم $to';
  }

  @override
  String codeSentSms(String to) {
    return 'بعتنالك كود من 6 أرقام في رسالة SMS، على الرقم $to';
  }

  @override
  String codeSentEmail(String to) {
    return 'بعتنالك كود من 6 أرقام على الإيميل $to';
  }

  @override
  String get codeLabel => 'الكود';

  @override
  String get codeValid => 'الكود صالح 10 دقايق.';

  @override
  String get codePaste => 'الصق الكود';

  @override
  String get codeChecking => 'بنتأكد…';

  @override
  String get codePasteNone => 'مفيش كود من 6 أرقام منسوخ.';

  @override
  String get codeRight => 'تمام!';

  @override
  String get codeResent => 'بعتنالك كود جديد.';

  @override
  String get noCode => 'ماوصلكش؟';

  @override
  String resendIn(String time) {
    return 'ابعت كود جديد بعد $time';
  }

  @override
  String get resend => 'ابعت كود جديد';

  @override
  String get sendBySms => 'ابعته رسالة SMS بدل واتساب';

  @override
  String get confirm => 'تأكيد';

  @override
  String get otherContact => 'استخدم رقم أو إيميل تاني';

  @override
  String accountsFound(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'لقينا $n حساب ليك',
      few: 'لقينا $n حسابات ليك',
      two: 'لقينا حسابين ليك',
      one: 'لقينا حساب واحد ليك',
      zero: 'مالقيناش حسابات',
    );
    return '$_temp0';
  }

  @override
  String get accountsLead => 'اختار اللي عايزه على الموبايل ده. بعد كده بتتنقل بينهم من \"حسابي\" من غير ما تدخل تاني.';

  @override
  String get accountsLegal =>
      'قبل ما تضيف حسابك، اقرا سياسة الخصوصية وشروط الاستخدام بتاعة الأكاديمية: هي اللي بتقرر إيه اللي بيحصل لبياناتك عندها.';

  @override
  String get accountsNote =>
      'بنعرض بس الحسابات اللي على نفس الرقم، في الأكاديميات الشغالة. مش لاقي حساب؟ يمكن الأكاديمية مسجلاك برقم تاني: كلّمها.';

  @override
  String addAccounts(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'أضف $n حساب وادخل',
      few: 'أضف $n حسابات وادخل',
      two: 'أضف الحسابين وادخل',
      one: 'ادخل',
    );
    return '$_temp0';
  }

  @override
  String get foundYou => 'لقيناك!';

  @override
  String get foundYouLead => 'لقينا حساب واحد ليك.';

  @override
  String enterAcademy(String academy) {
    return 'ادخل $academy';
  }

  @override
  String get notYou => 'مش حسابك؟ استخدم رقم أو إيميل تاني';

  @override
  String get pickOne => 'اختار حساب واحد على الأقل.';

  @override
  String notAddedTitle(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'دخلت، بس فيه حسابات ماتضافتش:', one: 'دخلت، بس فيه حساب ماتضافش:');
    return '$_temp0';
  }

  @override
  String notAddedLine(String name, String academy, String reason) {
    return '$name في $academy: $reason';
  }

  @override
  String get noAccounts => 'مالقيناش حساب بالرقم أو الإيميل ده في أي أكاديمية. اتأكد منه، أو كلّم أكاديميتك.';

  @override
  String get noAccountTitle => 'مالقيناش حساب';

  @override
  String get noAccountByPhone => 'مالقيناش حساب بالرقم ده';

  @override
  String get noAccountByEmail => 'مالقيناش حساب بالإيميل ده';

  @override
  String get noAccountCheckPhone => 'اتأكد إن الرقم مكتوب صح. ويمكن الأكاديمية مسجلاك برقم أو إيميل تاني، فجرّبه أو كلّمها.';

  @override
  String get noAccountCheckEmail => 'اتأكد إن الإيميل مكتوب صح. ويمكن الأكاديمية مسجلاك برقم أو إيميل تاني، فجرّبه أو كلّمها.';

  @override
  String get noAccountTryEmail => 'جرّب بالإيميل';

  @override
  String get noAccountTryEmailBody => 'لو الأكاديمية عندها إيميلك، الكود هيوصلك عليه.';

  @override
  String get noAccountOther => 'جرّب رقم أو إيميل تاني';

  @override
  String get contactChange => 'غيّره';

  @override
  String get consentNeededTitle => 'محتاجين موافقة ولي أمرك';

  @override
  String get consentNeededLead => 'قبل ما تدخل حسابك، ولي أمرك لازم يوافق مرة واحدة بس. وبعدها تدخل على طول.';

  @override
  String get consentNeededStep1 => 'ولي أمرك يفتح التطبيق بحسابه.';

  @override
  String consentNeededStep2(String me, String privacy) {
    return 'من «$me» ثم «$privacy» يوافق على حسابك.';
  }

  @override
  String get consentNeededStep3 => 'ارجع هنا وادخل تاني.';

  @override
  String get signInAgain => 'جرّب تدخل تاني';

  @override
  String get twoFactorSetupTitle => 'فعّل الدخول بخطوتين الأول';

  @override
  String get twoFactorSetupLead =>
      'حسابك ده لازم يكون عليه الدخول بخطوتين. فعّله من موقع الأكاديمية على الكمبيوتر، وبعدها ادخل من هنا عادي.';

  @override
  String get tooManyTitle => 'جرّبت كتير';

  @override
  String get tooManyBody => 'علشان نحمي حسابك، وقّفنا المحاولات شوية. استنى لحد ما العدّاد يخلص، وبعدها جرّب تاني.';

  @override
  String retryIn(String time) {
    return 'جرّب تاني بعد $time';
  }

  @override
  String welcomeHello(String name) {
    return 'أهلًا يا $name';
  }

  @override
  String welcomeIn(String academy) {
    return 'إنت دلوقتي في $academy';
  }

  @override
  String get roleStudent => 'طالب';

  @override
  String get roleGuardian => 'ولي أمر';

  @override
  String get roleTeacher => 'مدرّس';

  @override
  String get roleStaff => 'إدارة';

  @override
  String get passwordTitle => 'ادخل بكلمة السر';

  @override
  String get academyCode => 'كود الأكاديمية';

  @override
  String get academyCodeHint => 'بتلاقيه في رسالة الترحيب، أو اسأل الأكاديمية.';

  @override
  String get loginLabel => 'الإيميل أو الموبايل أو اسم المستخدم';

  @override
  String get passwordLabel => 'كلمة السر';

  @override
  String get academyCodeMissing => 'اكتب كود الأكاديمية.';

  @override
  String get loginMissing => 'اكتب إيميلك أو موبايلك أو اسم المستخدم.';

  @override
  String get passwordMissing => 'اكتب كلمة السر.';

  @override
  String get passwordShow => 'إظهار كلمة السر';

  @override
  String get passwordHide => 'إخفاء كلمة السر';

  @override
  String get signIn => 'ادخل';

  @override
  String get useCodeInstead => 'ادخل بكود بدل كلمة السر';

  @override
  String get twoFactorTitle => 'الدخول بخطوتين';

  @override
  String get twoFactorLead => 'افتح تطبيق المصادقة واكتب الكود اللي فيه (6 أرقام). ضيّعت موبايلك؟ اكتب كود من أكواد الاسترجاع.';

  @override
  String twoFactorAccount(String name, String academy) {
    return '$name في $academy';
  }

  @override
  String get twoFactorCode => 'الكود';

  @override
  String get startAgain => 'ابدأ من الأول';

  @override
  String get webOnlyTitle => 'الحساب ده للإدارة';

  @override
  String get webOnlyBody => 'حسابات الإدارة بتشتغل من الموقع. افتح لوحة الأكاديمية من الكمبيوتر.';

  @override
  String get privacyTitle => 'خصوصية الأولاد';

  @override
  String privacyLead(int age) {
    String _temp0 = intl.Intl.pluralLogic(age, locale: localeName, other: '$age سنة', few: '$age سنين', two: 'سنتين', one: 'سنة');
    return 'الأولاد اللي تحت $_temp0 محتاجين موافقتك عشان يدخلوا حساباتهم ويحضروا بنفسهم.';
  }

  @override
  String get privacyNone => 'مفيش ولاد محتاجين موافقة.';

  @override
  String get consentWaiting => 'مستني موافقتك';

  @override
  String get consentGranted => 'موافق عليه';

  @override
  String get consentAgree => 'موافق';

  @override
  String consentAgreed(String name) {
    return 'تمام، $name يقدر يدخل دلوقتي.';
  }

  @override
  String get consentWithdraw => 'اسحب الموافقة';

  @override
  String consentWithdrawConfirm(String name) {
    return '$name هيخرج من حسابه على كل الأجهزة، ومش هيقدر يدخل لحد ما توافق تاني. متأكد؟';
  }

  @override
  String consentWithdrawn(String name) {
    return 'سحبت موافقتك، و$name خرج من حسابه.';
  }

  @override
  String consentNudge(int n, String names) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$names مستنيين موافقتك عشان يدخلوا حساباتهم.',
      one: '$names مستني موافقتك عشان يدخل حسابه.',
    );
    return '$_temp0';
  }

  @override
  String get consentNudgeAction => 'اقرا ووافق';

  @override
  String get myDataTitle => 'بياناتي';

  @override
  String get myDataLead => 'كل اللي الأكاديمية محتفظة بيه عنك وعن ولادك: صحّح بياناتك، أو نزّل نسخة، أو اطلب مسح حساب.';

  @override
  String get myDataLeadTeacher => 'كل اللي الأكاديمية محتفظة بيه عنك: صحّح اسمك، أو نزّل نسخة، أو اطلب مسح حسابك.';

  @override
  String get myDataYoung => 'ولي أمرك هو اللي بيطلب بخصوص بياناتك من حسابه.';

  @override
  String get myDataCopyTitle => 'نسخة من البيانات';

  @override
  String myDataCopyBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يوم', few: '$days أيام', two: 'يومين', one: 'يوم');
    return 'ملف ZIP واحد: البيانات في ملف يتقري، ومعاها الملفات اللي اتسلمت. بتجهز في دقايق وبتفضل $_temp0.';
  }

  @override
  String get myDataCopyAction => 'جهّز نسخة';

  @override
  String get myDataCopyPreparing => 'بتتجهز: هنقولك أول ما تخلص.';

  @override
  String get myDataCopyDownload => 'نزّل';

  @override
  String myDataCopyUntil(String when) {
    return 'النسخة جاهزة لحد $when';
  }

  @override
  String get myDataEraseTitle => 'مسح الحساب';

  @override
  String myDataEraseBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يوم', few: '$days أيام', two: 'يومين', one: 'يوم');
    return 'الأكاديمية بترد في خلال $_temp0، ولما توافق الحساب بيتمسح بعدها بيوم.';
  }

  @override
  String get myDataEraseAction => 'امسح الحساب ده';

  @override
  String myDataEraseConfirm(String name) {
    return 'تطلب مسح حساب $name؟';
  }

  @override
  String get myDataEraseCopyFirst => 'تقدر تنزّل نسخة الأول.';

  @override
  String get myDataEraseReason => 'ليه؟ (لو حابب)';

  @override
  String myDataEraseWaiting(String when) {
    return 'اتطلب: الأكاديمية هترد قبل $when.';
  }

  @override
  String myDataEraseApproved(String when) {
    return 'اتوافق: الحساب هيتمسح $when.';
  }

  @override
  String myDataEraseRefused(String answer) {
    return 'الأكاديمية رفضت: $answer';
  }

  @override
  String get myDataEraseCancel => 'ارجع في طلبك';

  @override
  String get myDataEraseAsked => 'الطلب وصل للأكاديمية.';

  @override
  String get myDataEraseCancelled => 'رجعت في الطلب: الحساب هيفضل.';

  @override
  String get myDataWhatGoes => 'إيه اللي بيتمسح وإيه اللي بيفضل';

  @override
  String get myDataWhatStays => 'إيه اللي بيفضل، من غير اسم';

  @override
  String get myDataHistory => 'الطلبات';

  @override
  String get myDataNone => 'مفيش طلبات لسه.';

  @override
  String myDataAbout(String name) {
    return 'عن $name';
  }

  @override
  String get myDataFixTitle => 'تصحيح البيانات';

  @override
  String get myDataFixEdit => 'صحّح';

  @override
  String myDataFixEditTitle(String name) {
    return 'تصحيح بيانات $name';
  }

  @override
  String get myDataFixSave => 'احفظ';

  @override
  String get myDataFixSaved => 'اتحفظ.';

  @override
  String get myDataFixUnchanged => 'مفيش حاجة اتغيرت.';

  @override
  String get myDataFixUnset => 'مش متسجل';

  @override
  String get myDataFixContact => 'الإيميل ورقم الموبايل بتغيّرهم من «حسابي».';

  @override
  String myDataFixBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يوم', few: '$days أيام', two: 'يومين', one: 'يوم');
    return 'حاجة تانية غلط (تاريخ الميلاد، اسم في فاتورة، كلام في تقرير)؟ اكتبها للأكاديمية، وهترد في خلال $_temp0.';
  }

  @override
  String get myDataFixAsk => 'اطلب تصحيح';

  @override
  String myDataFixAskTitle(String name) {
    return 'اطلب تصحيح بيانات $name';
  }

  @override
  String myDataFixAskBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يوم', few: '$days أيام', two: 'يومين', one: 'يوم');
    return 'اكتب إيه اللي غلط وإيه الصح. الأكاديمية بترد في خلال $_temp0، وبيوصلك ردها.';
  }

  @override
  String get myDataFixSays => 'إيه اللي محتاج يتصلح';

  @override
  String get myDataFixPlaceholder => 'مثلًا: تاريخ الميلاد المتسجل 2016 والصح 2017.';

  @override
  String myDataFixTooShort(int min) {
    String _temp0 = intl.Intl.pluralLogic(min, locale: localeName, other: '$min حرف', few: '$min حروف', two: 'حرفين', one: 'حرف');
    return 'اكتب $_temp0 على الأقل.';
  }

  @override
  String get myDataFixSend => 'ابعت الطلب';

  @override
  String get myDataFixAsked => 'الطلب وصل للأكاديمية.';

  @override
  String myDataFixWaiting(String when) {
    return 'اتطلب: الأكاديمية هترد قبل $when.';
  }

  @override
  String get myDataFixDone => 'اتصلح';

  @override
  String myDataFixDoneWith(String answer) {
    return 'اتصلح: $answer';
  }

  @override
  String myDataFixRefused(String answer) {
    return 'الأكاديمية ماصلحتش: $answer';
  }

  @override
  String get myDataFixCancel => 'الغي الطلب';

  @override
  String get myDataFixCancelled => 'الطلب اتلغى.';

  @override
  String get myDataPrivacy => 'سياسة الخصوصية';

  @override
  String get myDataTerms => 'شروط الاستخدام';

  @override
  String get myDataDeletion => 'مسح الحساب';

  @override
  String get navHome => 'الرئيسية';

  @override
  String get navLessons => 'الحصص';

  @override
  String get navJourneyTab => 'الرحلة';

  @override
  String get navMoney => 'الفلوس';

  @override
  String get moneyEyebrow => 'الفواتير والرصيد';

  @override
  String get navMe => 'حسابي';

  @override
  String get navJourney => 'رحلتي';

  @override
  String get navMyLessons => 'حصصي';

  @override
  String get navMyHomework => 'واجباتي';

  @override
  String get navHomework => 'الواجبات';

  @override
  String get navReports => 'التقارير';

  @override
  String get navToday => 'النهارده';

  @override
  String get navWeek => 'الأسبوع';

  @override
  String get navStudents => 'الطلاب';

  @override
  String get navEarnings => 'أرباحي';

  @override
  String get navMore => 'المزيد';

  @override
  String greetingMorning(String name) {
    return 'صباح الخير يا $name';
  }

  @override
  String greetingEvening(String name) {
    return 'مساء الخير يا $name';
  }

  @override
  String greetingHello(String name) {
    return 'أهلًا يا $name';
  }

  @override
  String get nextLesson => 'الحصة الجاية';

  @override
  String get noNextLesson => 'مفيش حصص جاية في الأربع أسابيع دول.';

  @override
  String roomOpensIn(String time) {
    return 'الفصل بيفتح بعد $time';
  }

  @override
  String get roomOpen => 'الفصل مفتوح';

  @override
  String get lessonOn => 'الحصة شغالة دلوقتي';

  @override
  String lessonWith(String subject, String teacher) {
    return '$subject مع $teacher';
  }

  @override
  String get joinLesson => 'ادخل الحصة';

  @override
  String joinOpensAt(String time) {
    return 'بيفتح $time';
  }

  @override
  String get allLessons => 'كل الحصص';

  @override
  String startsIn(String time) {
    return 'بعد $time';
  }

  @override
  String roomOpensAt(String time) {
    return 'الفصل بيفتح $time';
  }

  @override
  String balanceOf(String name) {
    return 'رصيد $name';
  }

  @override
  String get payShort => 'ادفع';

  @override
  String get openJourney => 'افتح الرحلة';

  @override
  String get allReports => 'كل التقارير';

  @override
  String get allHomework => 'كل الواجبات';

  @override
  String aboutLearner(String name) {
    return 'عن $name';
  }

  @override
  String get pickLearner => 'عن مين؟';

  @override
  String get pickedNow => 'دلوقتي';

  @override
  String get balance => 'الرصيد';

  @override
  String get owed => 'عليك';

  @override
  String get payNow => 'ادفع دلوقتي';

  @override
  String get nothingOwed => 'مفيش عليك حاجة';

  @override
  String get lastReport => 'آخر تقرير';

  @override
  String get readReport => 'اقراه';

  @override
  String homeworkLine(String title) {
    return 'واجب: $title';
  }

  @override
  String get unfunded => 'مستنية الدفع';

  @override
  String get notices => 'تنبيهات';

  @override
  String lessonsOf(String name) {
    return 'حصص $name';
  }

  @override
  String onClock(String zone) {
    return 'بتوقيت $zone';
  }

  @override
  String get upcoming => 'الجاية';

  @override
  String get past => 'اللي فاتت';

  @override
  String get noUpcoming => 'مفيش حصص جاية في الأربع أسابيع دول.';

  @override
  String get noPast => 'مفيش حصص في الأربع أسابيع اللي فاتوا.';

  @override
  String substituteFor(String name) {
    return 'مدرّس بديل: $name في إجازة';
  }

  @override
  String get refuseSubstitute => 'مش موافقين على البديل';

  @override
  String get refuseSubstituteTitle => 'مش موافقين على البديل؟';

  @override
  String get refuseSubstituteBody => 'الحصة هتتلغي على حساب الأكاديمية، ودقايقها هترجعلك حصة تعويض تحجزها بعدين.';

  @override
  String get refuseSubstituteYes => 'أيوه، ألغيها وعوّضوني';

  @override
  String substituteChip(String name) {
    return 'بديل بدل $name';
  }

  @override
  String minutesShort(int n) {
    return '$n د';
  }

  @override
  String get lessonTime => 'الوقت';

  @override
  String get lessonWho => 'مع';

  @override
  String get lessonState => 'الحالة';

  @override
  String timeRange(String from, String to, String clock) {
    return '$from – $to $clock';
  }

  @override
  String roomOpensOn(String when) {
    return 'الفصل بيفتح $when';
  }

  @override
  String get lessonDetails => 'تفاصيل الحصة';

  @override
  String refusedSubstitute(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يوم', few: '$days أيام', two: 'يومين', one: 'يوم');
    return 'اتلغت. عندك $_temp0 تحجز فيهم حصة التعويض.';
  }

  @override
  String get cancelLesson => 'إلغاء الحصة';

  @override
  String get disputeLesson => 'اعترض';

  @override
  String get disputeTitle => 'اعتراض على نتيجة الحصة';

  @override
  String disputeIntroFamily(String outcome) {
    return 'الحصة متسجلة \"$outcome\". لو ده مش اللي حصل، قولولنا خلال 48 ساعة من آخرها، والأكاديمية هتراجع حضور الفصل وترد عليكم.';
  }

  @override
  String disputeIntroTeacher(String outcome) {
    return 'الحصة متسجلة \"$outcome\". لو ده مش اللي حصل، قولنا خلال 48 ساعة من آخرها، والأكاديمية هتراجع حضور الفصل وترد عليك.';
  }

  @override
  String get disputeWhat => 'إيه اللي حصل؟';

  @override
  String get disputeNote => 'التفاصيل';

  @override
  String get disputeNoteHint => 'الأكاديمية بس اللي هتقرا ده.';

  @override
  String get disputeFillIn => 'اختار السبب واكتب اللي حصل.';

  @override
  String get disputeSend => 'ابعت الاعتراض';

  @override
  String get disputeSent => 'الاعتراض وصل، والحصة تحت المراجعة.';

  @override
  String disputeState(String status) {
    return 'الاعتراض: $status';
  }

  @override
  String disputeAnswer(String answer) {
    return 'رد الأكاديمية: $answer';
  }

  @override
  String cancelTitle(String day) {
    return 'تلغي حصة $day؟';
  }

  @override
  String get reason => 'السبب';

  @override
  String get noteForAcademy => 'ملاحظة للأكاديمية';

  @override
  String get keepIt => 'لأ، خليها';

  @override
  String get yesCancel => 'أيوه، ألغيها';

  @override
  String get cancelled => 'الحصة اتلغت.';

  @override
  String get pickReason => 'اختار سبب الأول.';

  @override
  String get rateLesson => 'قيّم الحصة';

  @override
  String get rateTitle => 'الحصة كانت إزاي؟';

  @override
  String get rateGood => 'إيه اللي عجبكم؟';

  @override
  String get rateBad => 'إيه اللي ماعجبكمش؟';

  @override
  String get rateComment => 'كلمة للأكاديمية';

  @override
  String get rateAnonymous => 'المدرّس بيشوف التقييم من غير اسمك.';

  @override
  String get sendRating => 'ابعت التقييم';

  @override
  String get notNow => 'مش دلوقتي';

  @override
  String get rated => 'شكرًا! وصل تقييمك.';

  @override
  String starsOf(int n) {
    return '$n من 5';
  }

  @override
  String get stars1 => 'وحشة';

  @override
  String get stars2 => 'مش أحسن حاجة';

  @override
  String get stars3 => 'كويسة';

  @override
  String get stars4 => 'حلوة';

  @override
  String get stars5 => 'ممتازة';

  @override
  String get yourRating => 'تقييمك';

  @override
  String percentDone(int p) {
    return '$p% من البرنامج';
  }

  @override
  String hereNow(String title) {
    return 'دلوقتي: $title';
  }

  @override
  String weeksLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'باقي حوالي $n أسبوع',
      few: 'باقي حوالي $n أسابيع',
      two: 'باقي حوالي أسبوعين',
      one: 'باقي حوالي أسبوع',
      zero: 'قرّب يخلص',
    );
    return '$_temp0';
  }

  @override
  String homeworkOpen(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجب مفتوح',
      few: '$n واجبات مفتوحة',
      two: 'واجبين مفتوحين',
      one: 'واجب مفتوح',
    );
    return '$_temp0';
  }

  @override
  String get reports => 'التقارير';

  @override
  String get noReports => 'مفيش تقارير لسه.';

  @override
  String get noCourses => 'مفيش اشتراكات شغالة.';

  @override
  String get noProgram => 'من غير منهج';

  @override
  String reportOf(String day) {
    return 'تقرير $day';
  }

  @override
  String get engagement => 'التركيز';

  @override
  String get overall => 'المستوى العام';

  @override
  String get homeworkTitle => 'الواجبات';

  @override
  String get homeworkWaiting => 'مستني';

  @override
  String get homeworkSent => 'اتسلّم';

  @override
  String get homeworkMarked => 'اتصحّح';

  @override
  String homeworkWaitingOnYou(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجب مستني منك',
      few: '$n واجبات مستنية منك',
      two: 'واجبين مستنيين منك',
      one: 'واجب مستني منك',
    );
    return '$_temp0';
  }

  @override
  String get homeworkNoneWaiting => 'مفيش واجبات مستنياك دلوقتي.';

  @override
  String get homeworkNoneSent => 'مفيش واجبات مستنية تصحيح.';

  @override
  String get homeworkNoneMarked => 'لسه مفيش واجبات اتصحّحت.';

  @override
  String get homeworkLastMarked => 'آخر تصحيح';

  @override
  String get homeworkHandIn => 'سلّم الواجب';

  @override
  String get homeworkStartRound => 'ابدأ الجولة';

  @override
  String get noHomework => 'مفيش واجبات دلوقتي.';

  @override
  String dueBy(String when) {
    return 'قبل $when';
  }

  @override
  String get handIn => 'سلّم';

  @override
  String get yourAnswer => 'إجابتك';

  @override
  String get handedIn => 'اتسلّم. المدرّس هيصححه.';

  @override
  String get teacherFeedback => 'رأي المدرّس';

  @override
  String grade(int g) {
    return 'الدرجة: $g من 5';
  }

  @override
  String get yourSubmission => 'اللي سلّمته';

  @override
  String get kidsHomeworkStar => 'كل واجب بتسلّمه بيجيبلك نجمة جديدة!';

  @override
  String get owedNow => 'عليك دلوقتي';

  @override
  String invoiceDue(String number, String when) {
    return 'فاتورة $number · قبل $when';
  }

  @override
  String payAmount(String amount) {
    return 'ادفع $amount';
  }

  @override
  String get payNote => 'الدفع بيتم في صفحة بوابة الأكاديمية. لما تخلّص ارجع هنا.';

  @override
  String get lessonsLeftTitle => 'الحصص الفاضلة';

  @override
  String get invoices => 'الفواتير';

  @override
  String get noInvoices => 'مفيش فواتير.';

  @override
  String get openInvoice => 'افتح الفاتورة';

  @override
  String get extraAction => 'حصص زيادة';

  @override
  String extraHint(String price, int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: '$minutes دقيقة',
      few: '$minutes دقايق',
      two: 'دقيقتين',
      one: 'دقيقة',
    );
    return 'الحصة الزيادة بـ $price ($_temp0).';
  }

  @override
  String extraTitle(String subject) {
    return 'حصص زيادة في $subject';
  }

  @override
  String get extraBody => 'اختار العدد، وهتطلعلك فاتورة تدفعها زي أي فاتورة. بعد ما تدفع، الأكاديمية بتحجز الحصص معاك.';

  @override
  String extraEach(int n, String price) {
    return '$n × $price';
  }

  @override
  String get extraSubmit => 'اعمل الفاتورة';

  @override
  String extraDone(String number, String total) {
    return 'الفاتورة $number جاهزة ($total).';
  }

  @override
  String extraWaiting(String number) {
    return 'فاتورة الحصص الزيادة $number مستنية الدفع.';
  }

  @override
  String get invoiceNote => 'افتح أي فاتورة تشوفها وتطبعها، وفيها رمز QR بتاع الفاتورة الضريبية.';

  @override
  String get cantPayOnline => 'الدفع أونلاين مش متاح للفاتورة دي. كلّم الأكاديمية.';

  @override
  String get pickGateway => 'تدفع بإيه؟';

  @override
  String get paidByTransfer => 'دفعت بالتحويل';

  @override
  String get proofTitle => 'ابعت إثبات الدفع';

  @override
  String get proofHint => 'ادفع الأول، وبعدين ابعت الإيصال هنا. الأكاديمية هتتأكد إن الفلوس وصلت وتأكّد الدفعة.';

  @override
  String get proofHowTo => 'إزاي تدفع بالتحويل';

  @override
  String get proofMethod => 'دفعت إزاي';

  @override
  String get proofAmount => 'المبلغ اللي دفعته';

  @override
  String get proofPaidOn => 'يوم الدفع';

  @override
  String get proofReference => 'رقم التحويل (اختياري)';

  @override
  String get proofNote => 'ملاحظة (اختياري)';

  @override
  String get proofReceipt => 'الإيصال';

  @override
  String get proofPickReceipt => 'اختار صورة أو PDF';

  @override
  String get proofReceiptHint => 'صورة أو سكرين شوت أو PDF البنك، لحد 10 ميجا.';

  @override
  String get proofSend => 'ابعت الإثبات';

  @override
  String get proofSent => 'الإثبات اتبعت. هنقولك أول ما الأكاديمية تأكّده.';

  @override
  String get proofNeedsReceipt => 'اختار الإيصال الأول.';

  @override
  String get proofFileTooBig => 'الملف أكبر من 10 ميجا.';

  @override
  String get transferTitle => 'بيانات التحويل';

  @override
  String get transferCopy => 'انسخ';

  @override
  String get transferCopied => 'بيانات التحويل اتنسخت.';

  @override
  String get joining => 'بندخّلك الحصة…';

  @override
  String get linkNotOpened => 'مفيش برنامج على الموبايل يفتح اللينك ده.';

  @override
  String get openedOutside => 'الحصة اتفتحت في تطبيق تاني.';

  @override
  String get leave => 'اخرج من الحصة';

  @override
  String get micOff => 'اقفل المايك';

  @override
  String get micOn => 'افتح المايك';

  @override
  String get camOff => 'اقفل الكاميرا';

  @override
  String get camOn => 'افتح الكاميرا';

  @override
  String get audioOnly => 'الصوت بس';

  @override
  String get rejoinHint => 'لو خرجت بالغلط، ادخل تاني من نفس الزرار لحد ما الحصة تخلص.';

  @override
  String get weakNetwork => 'النت ضعيف. جرّب \"الصوت بس\" لو الصورة بتقطّع.';

  @override
  String get waitingOther => 'مستنيين الطرف التاني يدخل…';

  @override
  String get you => 'انت';

  @override
  String minutesLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقايق', two: 'دقيقتين', one: 'دقيقة');
    return 'فاضل $_temp0';
  }

  @override
  String sharedPackage(String subjects) {
    return '$subjects · باقة مشتركة';
  }

  @override
  String get familyInvoice => 'فاتورة العيلة';

  @override
  String coursePaused(String day) {
    return 'موقوف لحد $day';
  }

  @override
  String coursePausedKids(String day) {
    return 'في إجازة لحد $day';
  }

  @override
  String coursePauseAhead(String from, String until) {
    return 'هيتوقف من $from لحد $until';
  }

  @override
  String courseEnding(String day) {
    return 'بينتهي من $day';
  }

  @override
  String get courseEnded => 'انتهى، والدقايق اللي فاضلة لسه في الرصيد';

  @override
  String lessonUntil(String time) {
    return 'الحصة لحد $time';
  }

  @override
  String get meTitle => 'حسابي';

  @override
  String get moreChildren => 'الأولاد';

  @override
  String get moreTalk => 'التواصل';

  @override
  String get moreMine => 'حسابي';

  @override
  String get moreAccounts => 'الحسابات';

  @override
  String get moreLanguage => 'اللغة والتوقيت';

  @override
  String get moreSecurity => 'الدخول والأمان';

  @override
  String get moreTeaching => 'التدريس';

  @override
  String get moreWork => 'شغلي';

  @override
  String get moreStudy => 'الدراسة';

  @override
  String get moreHelp => 'مساعدة';

  @override
  String get yourAccount => 'حسابك وإعداداتك';

  @override
  String get moreAccountSettings => 'الحساب والإعدادات';

  @override
  String get meetingLinkMissing => 'مش متحط';

  @override
  String moreAcademies(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n أكاديمية',
      few: '$n أكاديميات',
      two: 'أكاديميتين',
      one: 'أكاديمية واحدة',
    );
    return '$_temp0';
  }

  @override
  String guardianOf(String names) {
    return 'ولي أمر $names';
  }

  @override
  String appVersion(String version) {
    return 'النسخة $version';
  }

  @override
  String get contactTitle => 'الإيميل والموبايل';

  @override
  String get contactHint => 'دول طرق دخولك، والأكواد والرسايل بتوصلك عليهم. لما تغيّر واحد، بنبعت كود على الجديد عشان نتأكد إنه بتاعك.';

  @override
  String get contactEmail => 'الإيميل';

  @override
  String get contactPhone => 'رقم الموبايل';

  @override
  String get contactNone => 'مش متسجل';

  @override
  String get contactChangeEmail => 'غيّر الإيميل';

  @override
  String get contactChangePhone => 'غيّر الرقم';

  @override
  String get contactAddEmail => 'ضيف إيميل';

  @override
  String get contactAddPhone => 'ضيف رقم';

  @override
  String get contactNewEmail => 'الإيميل الجديد';

  @override
  String get contactNewPhone => 'الرقم الجديد';

  @override
  String get contactPhoneHint => 'بكود الدولة، أو رقم محلي من نفس بلدك.';

  @override
  String get contactSend => 'ابعت كود';

  @override
  String get contactCode => 'الكود اللي وصلك';

  @override
  String contactCodeHint(String contact, String until) {
    return 'بعتنا كود على $contact. صالح لحد $until.';
  }

  @override
  String get contactConfirm => 'أكّد';

  @override
  String get contactResend => 'ابعت كود تاني';

  @override
  String get contactDoneEmail => 'الإيميل اتغيّر. من دلوقتي بتدخل وبتوصلك الرسايل عليه.';

  @override
  String get contactDonePhone => 'الرقم اتغيّر. من دلوقتي بتدخل وبتوصلك الرسايل عليه.';

  @override
  String get contactManaged => 'الأكاديمية أو ولي أمرك هم اللي بيغيّروا الإيميل والرقم بتوعك.';

  @override
  String get openNow => 'داخل فيه';

  @override
  String get switchTo => 'انتقل';

  @override
  String get addAccount => 'ضيف حساب تاني';

  @override
  String clockPrompt(String device, String profile) {
    return 'موبايلك على توقيت $device، وحسابك على $profile. نعرض المواعيد بتوقيت $device؟';
  }

  @override
  String clockYes(String zone) {
    return 'أيوه، $zone';
  }

  @override
  String clockKeep(String zone) {
    return 'خليه $zone';
  }

  @override
  String get language => 'اللغة';

  @override
  String get timezone => 'التوقيت';

  @override
  String get timeFormat => 'شكل الساعة';

  @override
  String timeFormatExample(String time) {
    return 'المواعيد بتظهر كده: $time';
  }

  @override
  String timeFormatAcademy(String hours) {
    return 'زي الأكاديمية ($hours)';
  }

  @override
  String get timeFormat12 => '12 ساعة';

  @override
  String get timeFormat24 => '24 ساعة';

  @override
  String get myDevices => 'أجهزتي';

  @override
  String get thisDevice => 'الجهاز ده';

  @override
  String lastUsed(String when) {
    return 'آخر مرة $when';
  }

  @override
  String get signOutDevice => 'خرّجه';

  @override
  String get deviceSignedOut => 'الجهاز خرج من الحساب.';

  @override
  String get signOut => 'اخرج من الحساب ده';

  @override
  String signOutConfirm(String academy) {
    return 'تخرج من $academy على الموبايل ده؟';
  }

  @override
  String todayHeader(String date, String zone) {
    return '$date · بتوقيت $zone';
  }

  @override
  String todayCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'النهارده: $n حصة',
      few: 'النهارده: $n حصص',
      two: 'النهارده: حصتين',
      one: 'النهارده: حصة واحدة',
      zero: 'النهارده مفيش حصص',
    );
    return '$_temp0';
  }

  @override
  String hours(String h) {
    return '$h ساعة';
  }

  @override
  String nextIn(String time) {
    return 'الجاية بعد $time';
  }

  @override
  String reportsWaiting(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n تقرير مستنيك',
      few: '$n تقارير مستنياك',
      two: 'تقريرين مستنيينك',
      one: 'تقرير مستنيك',
    );
    return '$_temp0';
  }

  @override
  String homeworkToMark(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجب تصحّحه',
      few: '$n واجبات تصحّحها',
      two: 'واجبين تصحّحهم',
      one: 'واجب تصحّحه',
    );
    return '$_temp0';
  }

  @override
  String reportDueBy(String when) {
    return 'قبل $when';
  }

  @override
  String get prevWeek => 'الأسبوع اللي فات';

  @override
  String get nextWeek => 'الأسبوع الجاي';

  @override
  String get noLessonsDay => 'مفيش حصص';

  @override
  String get noLessonsWeek => 'مفيش حصص الأسبوع ده.';

  @override
  String get thisWeek => 'الأسبوع ده';

  @override
  String get weekdaysShort => 'سبت،حد،اتنين،تلات،أربع،خميس،جمعة';

  @override
  String dayToday(String day) {
    return '$day · النهارده';
  }

  @override
  String get noLessonsLater => 'مفيش حصص من اليوم ده لآخر الأسبوع.';

  @override
  String get laterToday => 'باقي النهارده';

  @override
  String get doneToday => 'خلصت النهارده';

  @override
  String get restDay => 'النهارده مفيش حصص. حصصك الجاية في الأسبوع.';

  @override
  String get openWeek => 'افتح الأسبوع';

  @override
  String get writeTheReport => 'اكتب التقرير';

  @override
  String get markingTile => 'واجبات للتصحيح';

  @override
  String get allWritten => 'كلها اتكتبت';

  @override
  String get nothingToMark => 'مفيش حاجة';

  @override
  String oldestFor(String name) {
    return 'أقدمها: $name';
  }

  @override
  String get studentsTitle => 'طلابي';

  @override
  String get paused => 'متوقف مؤقتًا';

  @override
  String toMark(int n) {
    return '$n للتصحيح';
  }

  @override
  String lastReportAt(String when) {
    return 'آخر تقرير: $when';
  }

  @override
  String get noStudents => 'مفيش طلاب لسه.';

  @override
  String studentsIn(String students, String subjects) {
    return '$students في $subjects';
  }

  @override
  String studentsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n طالب',
      few: '$n طلاب',
      two: 'طالبين',
      one: 'طالب واحد',
      zero: 'مفيش طلاب',
    );
    return '$_temp0';
  }

  @override
  String subjectsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n مادة',
      few: '$n مواد',
      two: 'مادتين',
      one: 'مادة واحدة',
      zero: 'مفيش مواد',
    );
    return '$_temp0';
  }

  @override
  String get searchByName => 'دوّر بالاسم';

  @override
  String get allSubjects => 'الكل';

  @override
  String get noStudentsFound => 'مفيش طالب بالاسم ده.';

  @override
  String get reportWaitingChip => 'تقرير مستنيك';

  @override
  String get messageStudent => 'ابعتله رسالة';

  @override
  String get markHomework => 'صحّح الواجبات';

  @override
  String ofCourse(int n) {
    return '$n% من البرنامج';
  }

  @override
  String get prevMonth => 'الشهر اللي فات';

  @override
  String get nextMonth => 'الشهر الجاي';

  @override
  String get soFar => 'لحد النهارده';

  @override
  String get monthOpen => 'الشهر لسه مفتوح';

  @override
  String get monthClosed => 'الشهر اتقفل';

  @override
  String lessonsHours(String lessons, String hours) {
    return '$lessons · $hours';
  }

  @override
  String get lessonPay => 'الحصص';

  @override
  String get bonuses => 'مكافآت';

  @override
  String get deductions => 'خصومات';

  @override
  String unpricedNote(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n حصة لسه مالهاش سعر',
      few: '$n حصص لسه مالهاش سعر',
      two: 'حصتين لسه مالهمش سعر',
      one: 'حصة لسه مالهاش سعر',
    );
    return '$_temp0. الأكاديمية هتحدده، وهتتحسب في الشهر ده.';
  }

  @override
  String get noEarnings => 'مفيش حصص في الشهر ده.';

  @override
  String get adjustments => 'تعديلات';

  @override
  String get corrected => 'اتصحّح';

  @override
  String get meetingLinkTitle => 'لينك الحصة بتاعك';

  @override
  String get meetingLinkNeeded => 'الأكاديمية شغالة على لينكات خارجية، ومفيش حصة هتفتح من غير لينكك.';

  @override
  String get meetingLinkSave => 'احفظ اللينك';

  @override
  String get meetingLinkSaved => 'اللينك اتحفظ.';

  @override
  String get meetingLinkRemoved => 'اللينك اتشال.';

  @override
  String get timeOff => 'الإجازات';

  @override
  String get requestTimeOff => 'اطلب إجازة';

  @override
  String timeOffLead(String zone) {
    return 'بتوقيتك: $zone · الأكاديمية بترد عليك';
  }

  @override
  String get wholeDays => 'أيام كاملة';

  @override
  String get partOfDay => 'جزء من يوم';

  @override
  String get fromDay => 'من';

  @override
  String get toDay => 'لحد';

  @override
  String get dayLabel => 'اليوم';

  @override
  String get fromTime => 'من الساعة';

  @override
  String get toTime => 'لحد الساعة';

  @override
  String get reasonSick => 'مرض';

  @override
  String get reasonVacation => 'إجازة سنوية';

  @override
  String get reasonPersonal => 'ظرف شخصي';

  @override
  String get reasonFamily => 'ظرف عائلي';

  @override
  String get reasonEmergency => 'طارئ';

  @override
  String get reasonOther => 'حاجة تانية';

  @override
  String get sendRequest => 'ابعت الطلب';

  @override
  String get yourRequests => 'طلباتك';

  @override
  String get withdraw => 'اسحبه';

  @override
  String get requestSent => 'الطلب اتبعت. هيوصلك الرد.';

  @override
  String get withdrawn => 'الطلب اتسحب.';

  @override
  String get academyHolidays => 'إجازات الأكاديمية';

  @override
  String coverState(int covered, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$total حصة',
      few: '$total حصص',
      two: 'حصتين',
      one: 'حصة واحدة',
    );
    return '$covered من $_temp0 ليها بديل';
  }

  @override
  String get fillTimeOff => 'اختار الأيام والسبب الأول.';

  @override
  String get noTimeOff => 'مفيش طلبات.';

  @override
  String get youAreHere => 'انت هنا!';

  @override
  String get topRank => 'وصلت لأعلى رتبة!';

  @override
  String streak(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n حصة ورا بعض',
      few: '$n حصص ورا بعض',
      two: 'حصتين ورا بعض',
      one: 'حصة واحدة',
      zero: 'لسه مفيش حصص ورا بعض',
    );
    return '$_temp0';
  }

  @override
  String badgesCount(int earned, int total) {
    return '$earned من $total';
  }

  @override
  String get finishOn => 'هتخلّص على كده';

  @override
  String get programDone => 'خلّصت البرنامج!';

  @override
  String weeksLeftLevel(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'باقي $n أسبوع في المستوى ده',
      few: 'باقي $n أسابيع في المستوى ده',
      two: 'باقي أسبوعين في المستوى ده',
      one: 'باقي أسبوع في المستوى ده',
      zero: 'المستوى ده قرّب يخلص',
    );
    return '$_temp0';
  }

  @override
  String get fromLastMonth => 'من الشهر اللي فات';

  @override
  String get doneMark => 'تم!';

  @override
  String get chestReady => 'الصندوق جاهز! دوس عليه';

  @override
  String get chestOpened => 'فتحت صندوق الأسبوع ده';

  @override
  String get album => 'ألبوم الستيكرز';

  @override
  String get albumLead => 'كل صندوق بتفتحه بيجيبلك ستيكر جديد.';

  @override
  String get newSticker => 'ستيكر جديد في ألبومك!';

  @override
  String get rewardKids => 'برافو عليك!';

  @override
  String get rewardTitle => 'أحسنت!';

  @override
  String rankUp(String rank) {
    return 'رتبة جديدة: $rank';
  }

  @override
  String get awesome => 'تمام!';

  @override
  String get cheerFromFamily => 'تشجيع من أهلك!';

  @override
  String get cheerThanks => 'شكرًا!';

  @override
  String get cheerClap => 'تصفيقة';

  @override
  String get cheerStar => 'نجمة';

  @override
  String get cheerHeart => 'قلب';

  @override
  String get cheerFire => 'شعلة حماس';

  @override
  String cheerOn(String name) {
    return 'شجّع $name';
  }

  @override
  String cheerSent(String name) {
    return 'التشجيع وصل لـ $name!';
  }

  @override
  String cheersLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'فاضل $n تشجيع النهارده',
      few: 'فاضل $n تشجيعات النهارده',
      two: 'فاضل تشجيعين النهارده',
      one: 'فاضل تشجيع واحد النهارده',
      zero: 'خلّصت تشجيعات النهارده',
    );
    return '$_temp0';
  }

  @override
  String get sounds => 'الأصوات';

  @override
  String get soundsHint => 'أصوات خفيفة لما تدوس أو تستلم جايزة';

  @override
  String get reportsOpen => 'تقارير مستنياك';

  @override
  String get reportsSent => 'اتبعتت';

  @override
  String get writeReport => 'اكتب';

  @override
  String waitingFor(String what) {
    return 'مستنيك: $what';
  }

  @override
  String get waitingNothing => 'مفيش حاجة مستنياك';

  @override
  String bothWaiting(String a, String b) {
    return '$a و$b';
  }

  @override
  String reportsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n تقرير',
      few: '$n تقارير',
      two: 'تقريرين',
      one: 'تقرير واحد',
      zero: 'مفيش تقارير',
    );
    return '$_temp0';
  }

  @override
  String homeworkCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجب',
      few: '$n واجبات',
      two: 'واجبين',
      one: 'واجب واحد',
      zero: 'مفيش واجبات',
    );
    return '$_temp0';
  }

  @override
  String get markingRow => 'واجبات مستنية تصحيح';

  @override
  String get markingNone => 'مفيش واجبات مستنية دلوقتي';

  @override
  String get noReportsWaiting => 'مفيش تقارير مستنياك. برافو!';

  @override
  String get reportLesson => 'تقرير حصة';

  @override
  String get reportAbsence => 'ملاحظة غياب';

  @override
  String get todaysPlan => 'خطة النهارده';

  @override
  String get planHint => 'اختار اللي حصل في كل درس.';

  @override
  String get reportTitle => 'تقرير الحصة';

  @override
  String get workedOn => 'اشتغلنا على إيه';

  @override
  String get howItWent => 'الحصة كانت إزاي';

  @override
  String get talkSection => 'الكلام';

  @override
  String get keepDraft => 'مسودة';

  @override
  String get reportDraftSaved => 'اتحفظت مسودة على الموبايل ده.';

  @override
  String draftFrom(String ago) {
    return 'كمّلت من مسودة اتحفظت من $ago.';
  }

  @override
  String get discardDraft => 'ابدأ من الأول';

  @override
  String get voiceTitle => 'مسودة من رسالة صوتية';

  @override
  String get voiceHint => 'قول عملتوا إيه، والطالب كان عامل إزاي، والواجب. المسودة بتملا الخانات الفاضية بس، وإنت بتراجعها قبل ما تبعت.';

  @override
  String get voiceRecord => 'سجّل رسالة';

  @override
  String get voiceWorking => 'بنسمع ونكتب المسودة…';

  @override
  String voiceDone(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'المسودة ملت $n خانة. راجعهم قبل ما تبعت.',
      few: 'المسودة ملت $n خانات. راجعهم قبل ما تبعت.',
      two: 'المسودة ملت خانتين. راجعهم قبل ما تبعت.',
      one: 'المسودة ملت خانة واحدة. راجعها قبل ما تبعت.',
      zero: 'الرسالة مازوّدتش حاجة جديدة: الفورم كان فيه كل ده.',
    );
    return '$_temp0';
  }

  @override
  String get voiceHeard => 'اللي اتسمع';

  @override
  String get voiceUnsure => 'راجع دول: المسودة مكانتش متأكدة';

  @override
  String voiceLeft(String fields) {
    return 'لسه محتاج: $fields';
  }

  @override
  String get voiceLimit => 'لحد دقيقتين';

  @override
  String get voiceKept => 'التسجيل بيتمسح أول ما يتسمع، وعمره ما بيتحفظ.';

  @override
  String get suggested => 'مقترح';

  @override
  String get checkThis => 'راجعه';

  @override
  String get reciteTitle => 'تصحيح التسميع';

  @override
  String get reciteToggle => 'تصحيح التسميع آلي';

  @override
  String get reciteHint => 'لما الطالب يسلّم تسجيل، بنعلّم لك على الكلمات اللي ناقصة أو غلط. ليك إنت بس، والأسرة مابتشوفوش.';

  @override
  String reciteSuggestion(String title) {
    return 'من نص الدرس: $title';
  }

  @override
  String reciteFromMistakes(String title) {
    return 'من أخطاء الحصة: $title';
  }

  @override
  String get reciteSurah => 'السورة';

  @override
  String get reciteChooseSurah => 'اختار السورة';

  @override
  String get reciteSearch => 'دوّر على سورة';

  @override
  String get reciteFrom => 'من آية';

  @override
  String get reciteTo => 'لحد آية';

  @override
  String reciteWhat(String title) {
    return 'التسميع: $title';
  }

  @override
  String get reciteMissing => 'ناقصة';

  @override
  String get reciteWrong => 'غلط';

  @override
  String get reciteExtra => 'زيادة';

  @override
  String reciteSaid(String word) {
    return 'اتقال: $word';
  }

  @override
  String get reciteHeard => 'اللي اتسمع';

  @override
  String get drafts => 'مسودات';

  @override
  String savedAgo(String ago) {
    return 'اتحفظت من $ago';
  }

  @override
  String get continueDraft => 'كمّل';

  @override
  String get reasonNext => 'الدرس الجاي';

  @override
  String get reasonReview => 'مراجعة';

  @override
  String get outcomeIntroduced => 'اتشرح';

  @override
  String get outcomePracticed => 'اتدرّب عليه';

  @override
  String get outcomeMastered => 'اتقن';

  @override
  String get outcomeNeedsReview => 'محتاج مراجعة';

  @override
  String get itemScore => 'الدرجة';

  @override
  String get itemNote => 'ملاحظة على الدرس';

  @override
  String get addNote => 'ضيف ملاحظة';

  @override
  String get addLesson => 'ضيف درس';

  @override
  String get pickLesson => 'اختار درس';

  @override
  String get searchLessons => 'دوّر على درس';

  @override
  String get remove => 'شيل';

  @override
  String get notesForFamily => 'ملاحظات للطالب وولي الأمر';

  @override
  String get notesInternal => 'ملاحظات داخلية';

  @override
  String get notesInternalHint => 'للأكاديمية بس، الأسرة مابتشوفهاش.';

  @override
  String get questionsTitle => 'أسئلة الأكاديمية';

  @override
  String get questionsHint => 'أسئلة الأكاديمية بتسألها في كل تقرير للمادة دي.';

  @override
  String answerRequired(String question) {
    return 'جاوب على \"$question\".';
  }

  @override
  String get answerYes => 'أيوه';

  @override
  String get answerNo => 'لأ';

  @override
  String get academyOnly => 'للأكاديمية بس';

  @override
  String get lessonMaterials => 'مواد الدرس';

  @override
  String get teachersOnly => 'للمدرّس بس';

  @override
  String get recordingsTitle => 'تسجيلات الحصص';

  @override
  String get recordingsMine => 'تسجيلات حصصي';

  @override
  String get recordingsOff => 'الأكاديمية مش بتسجل الحصص دلوقتي.';

  @override
  String recordingsOf(String name) {
    return 'تسجيلات حصص $name';
  }

  @override
  String get recordingsTeacherList => 'تسجيلات حصصك';

  @override
  String get recordingsEmpty => 'مفيش تسجيلات لسه.';

  @override
  String get recordingsNotShared => 'الأكاديمية مش بتعرض التسجيلات هنا.';

  @override
  String get recordingWatch => 'شوف';

  @override
  String get recordingAgreed => 'موافق';

  @override
  String get recordingNotAgreed => 'مش موافق';

  @override
  String get recordingAgree => 'موافق';

  @override
  String get recordingAgreedDone => 'شكرًا. الحصص ممكن تتسجل من دلوقتي.';

  @override
  String get recordingWithdraw => 'اسحب الموافقة';

  @override
  String get recordingWithdrawConfirm => 'متأكد؟ أي تسجيل شغال هيقف، ومش هيتسجل حاجة تاني.';

  @override
  String get recordingWithdrawn => 'الموافقة اتسحبت، ومش هيتسجل حاجة تاني.';

  @override
  String recordingUntil(String date) {
    return 'متاح لحد $date';
  }

  @override
  String get recordingVideo => 'فيديو';

  @override
  String get recordingAudio => 'صوت';

  @override
  String get recordingLogged => 'فتحك للتسجيل بيتسجل باسمك.';

  @override
  String get recordingOn => 'بيسجل';

  @override
  String get recordStart => 'سجّل الحصة';

  @override
  String get recordStop => 'وقّف التسجيل';

  @override
  String recordingMissing(String names) {
    return 'مينفعش نسجل لحد ما يوافقوا على التسجيل: $names.';
  }

  @override
  String get absenceNote => 'ملاحظة الغياب';

  @override
  String get absenceHint => 'الطالب ماحضرش. اكتب اللي حصل وأي حاجة الأسرة محتاجة تعرفها.';

  @override
  String get giveHomework => 'ادّي واجب';

  @override
  String get homeworkField => 'الواجب';

  @override
  String get homeworkDetails => 'التفاصيل';

  @override
  String get acceptsLabel => 'بيتسلّم';

  @override
  String get acceptsAny => 'أي حاجة';

  @override
  String get acceptsText => 'كتابة';

  @override
  String get acceptsAudio => 'تسجيل صوت';

  @override
  String get acceptsFile => 'ملف';

  @override
  String dueIn(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'بعد $n يوم', few: 'بعد $n أيام', two: 'بعد يومين', one: 'بعد يوم');
    return '$_temp0';
  }

  @override
  String get homeworkLocked => 'الطالب سلّم الواجب ده، فمش هيتغير.';

  @override
  String get sendReport => 'ابعت التقرير';

  @override
  String get updateReport => 'حدّث التقرير';

  @override
  String get reportSent => 'التقرير اتبعت.';

  @override
  String get reportLocked => 'الأكاديمية راجعت التقرير ده، فمش هيتغير.';

  @override
  String get pickRatings => 'اختار التركيز والمستوى العام.';

  @override
  String get pickOutcomes => 'اختار اللي حصل في كل درس.';

  @override
  String get markTitle => 'تصحيح الواجبات';

  @override
  String get markWaiting => 'مستني التصحيح';

  @override
  String get markDone => 'اتصحّح';

  @override
  String get noHomeworkToMark => 'مفيش واجبات مستنية تصحيح.';

  @override
  String get gradeLabel => 'الدرجة';

  @override
  String get feedbackLabel => 'كلمتك للطالب';

  @override
  String get saveMark => 'سجّل التصحيح';

  @override
  String get marked => 'اتسجّل التصحيح.';

  @override
  String handedInAt(String when) {
    return 'اتسلّم $when';
  }

  @override
  String get fileOnWeb => 'الملف ده بيتفتح من الموقع.';

  @override
  String get showPicture => 'اعرض الصورة';

  @override
  String get playRecording => 'شغّل التسجيل';

  @override
  String get pause => 'وقّف';

  @override
  String get cannotPlay => 'مش قادرين نشغّل التسجيل ده هنا.';

  @override
  String get waitingTap => 'دوس عشان تبدأ';

  @override
  String get recordVoice => 'سجّل صوتك';

  @override
  String get recordKids => 'سجّل تسميعك';

  @override
  String recordingNow(String time) {
    return 'بيسجّل… $time';
  }

  @override
  String get stopRecording => 'وقّف التسجيل';

  @override
  String get recordAgain => 'سجّل تاني';

  @override
  String get attachFile => 'ضيف ملف';

  @override
  String get attachPicture => 'ضيف صورة أو PDF';

  @override
  String get micDenied => 'محتاجين إذن المايك عشان تسجّل. فعّله من إعدادات الموبايل.';

  @override
  String recordingLimit(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقايق', two: 'دقيقتين', one: 'دقيقة');
    return 'التسجيل وقف عند $_temp0.';
  }

  @override
  String get fileTooBig => 'الملف أكبر من 25 ميجا.';

  @override
  String get answerOptional => 'اكتب حاجة كمان (اختياري)';

  @override
  String get myHours => 'مواعيدي';

  @override
  String hoursOnClock(String zone) {
    return 'على توقيت $zone';
  }

  @override
  String weeklyHours(String h) {
    return '$h في الأسبوع';
  }

  @override
  String get currentWeek => 'المواعيد المعتمدة';

  @override
  String get noHoursYet => 'لسه مفيش مواعيد معتمدة.';

  @override
  String get proposeHours => 'اقترح مواعيد جديدة';

  @override
  String get yourProposal => 'مقترحك';

  @override
  String get waitingOnAcademy => 'مستني رد الأكاديمية';

  @override
  String get waitingOnYou => 'مستني ردك';

  @override
  String startsOn(String date) {
    return 'يبدأ $date';
  }

  @override
  String get academyCounter => 'الأكاديمية عدّلت مقترحك. ده اللي اتغير:';

  @override
  String get timeAdded => 'اتضاف';

  @override
  String get timeRemoved => 'اتشال';

  @override
  String get acceptCounter => 'موافق على التعديل';

  @override
  String get editProposal => 'عدّل المقترح';

  @override
  String get withdrawConfirm => 'تسحب المقترح؟ المواعيد المعتمدة هتفضل زي ما هي.';

  @override
  String get proposalAccepted => 'مواعيدك اتعتمدت.';

  @override
  String get proposalSent => 'المقترح اتبعت للأكاديمية.';

  @override
  String get draftSaved => 'المسودة اتحفظت.';

  @override
  String get conversation => 'المحادثة مع الأكاديمية';

  @override
  String get writeMessage => 'اكتب رسالة…';

  @override
  String get editHours => 'عدّل مواعيدك';

  @override
  String get addTime => 'ضيف وقت';

  @override
  String get dayOff => 'مفيش';

  @override
  String get effectiveFrom => 'يبدأ من';

  @override
  String reduceNotice(String date) {
    return 'لو هتقلل ساعات، أقرب بداية $date.';
  }

  @override
  String get minHours => 'أقل ساعات في الأسبوع';

  @override
  String get maxHours => 'أكتر ساعات في الأسبوع';

  @override
  String get proposalNote => 'ملاحظة للأكاديمية';

  @override
  String get saveDraft => 'احفظ مسودة';

  @override
  String get sendProposal => 'ابعت للأكاديمية';

  @override
  String get endAfterStart => 'وقت النهاية لازم يكون بعد البداية.';

  @override
  String get untilMidnight => 'لحد نص الليل';

  @override
  String get inboxTitle => 'الإشعارات';

  @override
  String get markAllRead => 'علّم الكل مقروء';

  @override
  String get noNotifications => 'مفيش إشعارات.';

  @override
  String get loadMore => 'اعرض أكتر';

  @override
  String get notificationChoices => 'اختيارات الإشعارات';

  @override
  String get saved => 'اتحفظ.';

  @override
  String get openIt => 'افتح';

  @override
  String unreadCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n إشعار جديد',
      few: '$n إشعارات جديدة',
      two: 'إشعارين جداد',
      one: 'إشعار جديد',
      zero: 'مفيش جديد',
    );
    return '$_temp0';
  }

  @override
  String get updateTitle => 'فيه نسخة جديدة من التطبيق';

  @override
  String get updateBody => 'النسخة اللي عندك قديمة ومش هتشتغل مع الأكاديمية. حدّثها عشان تكمل.';

  @override
  String get updateNow => 'حدّث التطبيق';

  @override
  String get updateFromStore => 'حدّثه من متجر التطبيقات على موبايلك.';

  @override
  String get helpTitle => 'المساعدة';

  @override
  String get supportTitle => 'المساعدة';

  @override
  String get helpLead => 'اكتب للأكاديمية عن حصة، أو دفع، أو مشكلة تقنية، أو أي حاجة تانية. الرد هيوصلك هنا وفي رسالة.';

  @override
  String get supportLead => 'اكتب للأكاديمية عن مشكلة تقنية، أو مرتبك، أو أي حاجة تانية. الرد هيوصلك هنا وفي رسالة.';

  @override
  String get ticketNew => 'تذكرة جديدة';

  @override
  String get ticketNewTitle => 'اكتب للأكاديمية';

  @override
  String get ticketKind => 'عن إيه؟';

  @override
  String get ticketHintComplaint => 'على مدرّس أو حصة. الأكاديمية بتدوّر فيها وبتقولك عملت إيه.';

  @override
  String get ticketHintBilling => 'فاتورة، أو دفعة، أو فلوس راجعة، أو باقتك.';

  @override
  String get ticketHintPay => 'مرتبك أو كشف حسابك.';

  @override
  String get ticketHintTechnical => 'الفصل، أو التطبيق، أو الدخول مش شغال.';

  @override
  String get ticketHintOther => 'سؤال أو طلب.';

  @override
  String get ticketLesson => 'أنهي حصة؟ (لو فيه)';

  @override
  String get ticketNoLesson => 'مش حصة معينة';

  @override
  String get ticketSubject => 'العنوان';

  @override
  String get ticketSubjectHint => 'في كلمتين';

  @override
  String get ticketBody => 'احكيلنا حصل إيه';

  @override
  String get ticketAttach => 'ارفق صورة أو PDF';

  @override
  String get ticketTooBig => 'الملف أكبر من 10 ميجا.';

  @override
  String get ticketSent => 'اتبعتت. الأكاديمية هترد هنا.';

  @override
  String get ticketsEmpty => 'مفيش تذاكر لسه. لو فيه أي حاجة مش مظبوطة، اكتبلنا.';

  @override
  String get ticketsMine => 'تذاكري';

  @override
  String ticketAbout(String name) {
    return 'عن $name';
  }

  @override
  String get ticketYourTurn => 'الأكاديمية ردت';

  @override
  String get ticketYou => 'انت';

  @override
  String get ticketAcademy => 'الأكاديمية';

  @override
  String get ticketReplyHint => 'اكتب للأكاديمية…';

  @override
  String get ticketReopen => 'لسه ماتحلّتش';

  @override
  String ticketReopenHint(String date) {
    return 'لسه ماتحلّتش؟ تقدر تفتحها تاني لحد $date.';
  }

  @override
  String get ticketClose => 'اقفل التذكرة';

  @override
  String get ticketCloseHint => 'اقفلها لما تتحل أو لو مابقيتش محتاجها. مش هتاخد رسايل تاني.';

  @override
  String get ticketClosed => 'التذكرة دي اتقفلت. افتح تذكرة جديدة لو محتاج حاجة.';

  @override
  String get tellUsTitle => 'بلّغ عن قلق';

  @override
  String get tellUsTitleStudent => 'فيه حاجة مش مظبوطة؟ قولّنا';

  @override
  String get tellUsTitleKids => 'فيه حاجة زعّلتك؟';

  @override
  String get tellUsNavKids => 'قول لحد كبير';

  @override
  String get tellUsNavMiddle => 'قولّنا لو فيه حاجة مضايقاك';

  @override
  String get tellUsLead =>
      'لو أي حاجة في حصة، أو من مدرّس، أو من أي حد على المنصة قلقاك، قولّنا هنا. اللي بيقراها بس المسؤولين عن حماية الأطفال في الأكاديمية.';

  @override
  String get tellUsLeadStudent =>
      'لو حد خلّاك تحس إنك مش مرتاح، أو خايف، أو مش في أمان، قولّنا. اللي بيقراها بس المسؤولين عن حماية الطلاب في الأكاديمية، ومش هتتعاقب.';

  @override
  String get tellUsLeadKids => 'لو حد زعّلك أو خوّفك، دوس على زرار. حد كبير في الأكاديمية هيساعدك.';

  @override
  String get concernTeacherNav => 'بلّغ عن قلق';

  @override
  String get concernTeacherTitle => 'بلّغ عن قلق على أمان طالب';

  @override
  String get concernTeacherLead =>
      'لو حاجة في أمان طالب أو حالته قلقاك، أو تصرّف حد كبير تاني، قول لمسؤولين الحماية في الأكاديمية هنا. هما بس اللي بيقروه. ماتحققش فيه ولا تكلّم الأسرة عنه بنفسك.';

  @override
  String concernAbout(String name) {
    return 'عن $name';
  }

  @override
  String get concernWhat => 'الموضوع عن إيه؟';

  @override
  String get concernWhatKids => 'إيه اللي حصل؟';

  @override
  String get concernPick => 'اختار الموضوع عن إيه.';

  @override
  String get concernText => 'إيه اللي حصل؟';

  @override
  String get concernTextKids => 'عايز تقول أكتر؟ مش لازم.';

  @override
  String get concernTextHint => 'حصل إمتى، ومين كان موجود، وإيه اللي اتقال.';

  @override
  String get concernLesson => 'في أنهي حصة (لو فيه)؟';

  @override
  String get concernNoLesson => 'مش عن حصة';

  @override
  String get concernStudent => 'أنهي طالب؟';

  @override
  String get concernNoStudent => 'مش عن طالب معيّن';

  @override
  String get concernUrgent => 'ممكن حد يكون في خطر دلوقتي';

  @override
  String get concernSend => 'ابعت';

  @override
  String get concernSendTeacher => 'ابعت لمسؤولين الحماية';

  @override
  String get concernAgain => 'قولّنا حاجة تانية';

  @override
  String get concernAgainTeacher => 'بلّغ عن حاجة تانية';

  @override
  String concernNumber(String number) {
    return 'رقم $number';
  }

  @override
  String get concernsMine => 'اللي قولتهولنا';

  @override
  String get concernsNone => 'لسه مفيش.';

  @override
  String get documentsTitle => 'مستنداتي';

  @override
  String get documentsLead => 'الأكاديمية بتراجع المستندات دي على الأصل؛ وفيش الصحيفة الجنائية هو اللي مطلوب عشان تدرّس للي تحت 18.';

  @override
  String get documentSend => 'ابعت مستند';

  @override
  String get documentSendHint => 'صورة واضحة أو الـ PDF من الجهة. خلّي الأصل معاك: الأكاديمية ممكن تطلب تشوفه.';

  @override
  String get documentKind => 'المستند';

  @override
  String get documentReference => 'رقم الشهادة (اختياري)';

  @override
  String get documentExpires => 'صالح لحد (اختياري)';

  @override
  String documentUntil(String day) {
    return 'صالح لحد $day';
  }

  @override
  String get documentNoEnd => 'مالوش تاريخ انتهاء';

  @override
  String get documentPick => 'اختار صورة أو PDF';

  @override
  String get documentPickFirst => 'اختار الصورة أو الـ PDF الأول.';

  @override
  String get documentTooBig => 'الملف أكبر من 10 ميجا.';

  @override
  String get documentSent => 'اتبعت: الأكاديمية هتراجعه.';

  @override
  String get documentsList => 'اللي بعته';

  @override
  String get documentsNone => 'لسه ما بعتش أي مستندات.';

  @override
  String get messagesTitle => 'الرسايل';

  @override
  String get messagesFamilyTitle => 'الرسايل مع المدرّسين';

  @override
  String get messagesStudentTitle => 'الرسايل مع مدرّسينك';

  @override
  String get messagesKidsTitle => 'كلّم مدرسك';

  @override
  String get messagesEmptyTeacher => 'لما يبقى عندك طلاب، هتلاقيهم هنا وتقدر تكلّمهم وتكلّم أهلهم.';

  @override
  String get messagesEmptyFamily => 'لما يبقى للطالب مدرّس، هتلاقيه هنا.';

  @override
  String get messagesStart => 'لسه ماحدش كتب';

  @override
  String get messagesYou => 'إنت';

  @override
  String messagesAlsoHere(String names) {
    return 'معاكم: $names';
  }

  @override
  String get messagesFirst => 'ابدأ المحادثة: الكلام هنا بيشوفه أولياء الأمور والأكاديمية.';

  @override
  String get messagesWrite => 'اكتب رسالة…';

  @override
  String gamesStars(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n نجمة',
      few: '$n نجوم',
      two: 'نجمتين',
      one: 'نجمة واحدة',
      zero: 'من غير نجوم',
    );
    return '$_temp0';
  }

  @override
  String get gamesSoundOn => 'شغّل المزيكا والأصوات';

  @override
  String get gamesSoundOff => 'اقفل المزيكا والأصوات';

  @override
  String gamesOf(int found, int total) {
    return '$found من $total';
  }

  @override
  String get gamesHomeworkPin => 'واجب';

  @override
  String get gamesToMap => 'الخريطة';

  @override
  String get gamesIslandGames => 'ألعاب الجزيرة';

  @override
  String get gamesBadgeHomework => 'واجب';

  @override
  String gamesCreaturesCard(int found, int total) {
    return 'كائنات الجزيرة · $found من $total';
  }

  @override
  String gamesEggCardKids(int have, int need) {
    return 'البيضة الجاية: $have من $need ⭐';
  }

  @override
  String get gamesIslandDone => 'لمّيت كل كائنات الجزيرة!';

  @override
  String get gamesClose => 'اقفل اللعبة وارجع';

  @override
  String get gamesToIsland => 'ارجع للجزيرة';

  @override
  String get gamesAlbum => 'كائناتي';

  @override
  String gamesItems(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n سؤال', few: '$n أسئلة', two: 'سؤالين', one: 'سؤال واحد');
    return '$_temp0';
  }

  @override
  String gamesPractising(String what) {
    return 'بتتمرن على: $what';
  }

  @override
  String get gamesResult0 => 'كمّل وحاول تاني!';

  @override
  String get gamesResult1 => 'بداية حلوة!';

  @override
  String get gamesResult2 => 'شاطر!';

  @override
  String get gamesResult3 => 'رائع! كله صح تقريبًا';

  @override
  String gamesScore(int correct, int total) {
    return '$correct من $total صح من أول مرة';
  }

  @override
  String gamesHatched(String name) {
    return 'فقس $name!';
  }

  @override
  String gamesHomeworkDone(String title) {
    return 'الواجب «$title» اتسلّم بالتدريب ده.';
  }

  @override
  String gamesEgg(int have, int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'البيضة الجاية: $have من $_temp0';
  }

  @override
  String get gamesAgain => 'العب تاني';

  @override
  String get gamesPlay => 'العب';

  @override
  String get gamesLoading => 'بنجهّز الجولة…';

  @override
  String get gamesSending => 'بنحسب النجوم…';

  @override
  String get gamesPageProblem => 'اللعبة ما اشتغلتش. جرّب تاني.';

  @override
  String get gamesHatchReady => 'البيضة جاهزة!';

  @override
  String gamesHatchReadySub(int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'جمعت $_temp0 في رحلتك';
  }

  @override
  String get gamesHatchDone => 'فقست!';

  @override
  String gamesHatchDoneSub(int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'كل ما تجمع $_temp0، تفقسلك بيضة جديدة';
  }

  @override
  String get gamesHatchTap0 => 'دوس على البيضة 3 مرات';

  @override
  String get gamesHatchTap1 => 'كمان مرتين!';

  @override
  String get gamesHatchTap2 => 'مرة كمان!';

  @override
  String get gamesHatchTap3 => 'بتفقس...';

  @override
  String gamesHatchEgg(int left) {
    return 'البيضة، دوس عليها. فاضل $left';
  }

  @override
  String gamesHatchNew(int found, int total) {
    return 'كائن جديد في ألبومك · $found من $total';
  }

  @override
  String get gamesHatchAsk => 'تحب تسميه إيه؟';

  @override
  String get gamesHatchKeep => 'حطّه في الألبوم';

  @override
  String get gamesAlbumTitle => 'ألبوم كائناتي';

  @override
  String gamesAlbumHint(int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'كل $_temp0 في رحلتك بتفقسلك بيضة، وكل درس تتقنه بيفقسلك كائن.';
  }

  @override
  String gamesAlbumGuardian(String name) {
    return 'ده ألبوم $name. هو بس اللي يختار صاحبه ويسمّي كائناته.';
  }

  @override
  String get gamesAlbumAll => 'الكل';

  @override
  String get gamesRarityCommon => 'عادي';

  @override
  String get gamesRarityRare => 'نادر';

  @override
  String get gamesRarityLegendary => 'أسطوري';

  @override
  String get gamesAllIslands => 'كل الجزر';

  @override
  String get gamesNotYet => 'لسه';

  @override
  String gamesAriaFound(String name, String island) {
    return '$name، من $island';
  }

  @override
  String gamesAriaShadow(String island) {
    return 'كائن لسه ما فقسش، من $island';
  }

  @override
  String get gamesVoice => 'اسمع صوته';

  @override
  String get gamesMakeBuddy => 'خليه صاحبك';

  @override
  String get gamesIsBuddyKids => 'ده صاحبك 💛';

  @override
  String get gamesIsBuddy => 'ده صاحبك';

  @override
  String gamesBuddyDone(String name) {
    return '$name هيطير معاك في الألعاب من دلوقتي';
  }

  @override
  String get gamesShadowStory => 'لسه ما فقسش.';

  @override
  String gamesHowCommon(String island, int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'إزاي تفقسه: اجمع نجوم وانت على $island: كل $_temp0 بيضة.';
  }

  @override
  String gamesHowRare(String island) {
    return 'إزاي تفقسه: كائن نادر في $island: بيفقس بعد الكائنات العادية بتاعتها.';
  }

  @override
  String gamesHowLegendary(String island) {
    return 'إزاي تفقسه: أسطوري! آخر كائن في $island. اجمع اللي قبله كلهم.';
  }

  @override
  String get gamesNameIt => 'سمّيه:';

  @override
  String gamesNamed(String name) {
    return 'اتسمّى $name';
  }

  @override
  String get gamesFromStars => 'فقس من نجوم رحلتك';

  @override
  String get gamesFromMastery => 'فقس لما اتقنت درس';

  @override
  String get gamesRace => 'سباق مع نفسك';

  @override
  String gamesBest(String s) {
    return 'أحسن وقت: $s';
  }

  @override
  String gamesSeconds(int s) {
    return '$sث';
  }

  @override
  String get gamesBeat => 'كسرت رقمك!';

  @override
  String get gamesSoClose => 'قربت!';

  @override
  String get gamesFirst => 'أول رقم ليك!';

  @override
  String gamesOver(int correct, int total, String s) {
    return '$correct من $total صح في $s';
  }

  @override
  String get gamesReviewAgain => 'راجع تاني';

  @override
  String gamesGuardianTitle(String name) {
    return 'ألعاب $name';
  }

  @override
  String get gamesPlayTime => 'وقت اللعب النهارده';

  @override
  String gamesTimeText(int used, int limit, int left) {
    String _temp0 = intl.Intl.pluralLogic(
      limit,
      locale: localeName,
      other: '$limit دقيقة',
      few: '$limit دقايق',
      two: 'دقيقتين',
      one: 'دقيقة',
    );
    String _temp1 = intl.Intl.pluralLogic(left, locale: localeName, other: '$left دقيقة', few: '$left دقايق', two: 'دقيقتين', one: 'دقيقة');
    return '$used من $_temp0 لعب النهارده. فاضل $_temp1.';
  }

  @override
  String get gamesRefusedOff => 'الألعاب مقفولة في الأكاديمية دلوقتي.';

  @override
  String get gamesRefusedPaused => 'ولي الأمر قفل الألعاب دلوقتي.';

  @override
  String get gamesRefusedNight => 'الطيور نامت. الألعاب بترجع الساعة 7 الصبح.';

  @override
  String get gamesRefusedLimit => 'خلصت دقايق اللعب النهارده. نكمل بكرة!';

  @override
  String gamesTimeOpen(int used) {
    String _temp0 = intl.Intl.pluralLogic(used, locale: localeName, other: '$used دقيقة', few: '$used دقايق', two: 'دقيقتين', one: 'دقيقة');
    return '$_temp0 لعب النهارده، من غير حد.';
  }

  @override
  String get gamesMinutesShort => 'دقيقة';

  @override
  String get gamesMaxTime => 'أقصى وقت في اليوم';

  @override
  String gamesChoice(int n) {
    return '$n د';
  }

  @override
  String get gamesAcademyChoice => 'الأكاديمية';

  @override
  String get gamesNightLock => 'قفل اللعب من 9 بالليل لـ 7 الصبح';

  @override
  String get gamesEnabled => 'الألعاب شغالة';

  @override
  String get gamesLearned => 'اتراجع إيه في اللعب';

  @override
  String gamesRoundsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n مرة', few: '$n مرات', two: 'مرتين', one: 'مرة واحدة');
    return '$_temp0';
  }

  @override
  String gamesGamesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n لعبة', few: '$n ألعاب', two: 'لعبتين', one: 'لعبة واحدة');
    return '$_temp0';
  }

  @override
  String gamesPractised(String title, String rounds, String games) {
    return 'اتراجع «$title» $rounds في $games';
  }

  @override
  String gamesHardest(String text) {
    return 'أصعب سؤال: «$text». ممكن تراجعوه سوا.';
  }

  @override
  String gamesBestRound(int correct, int total, String game) {
    return 'أحسن جولة: $correct من $total صح من أول مرة في «$game»';
  }

  @override
  String get gamesQuiet => 'مفيش لعب الأسبوع ده. الألعاب بتتمرن على دروسه بس، والمدرّس بيحط التدريبات على الدرس.';

  @override
  String gamesHatchedToday(String name) {
    return 'فقس «$name» النهارده';
  }

  @override
  String gamesHatchedStars(int found) {
    return 'من نجوم الرحلة · $found في الألبوم';
  }

  @override
  String gamesHatchedMastery(int found) {
    return 'من درس اتقنه · $found في الألبوم';
  }

  @override
  String get gamesCheer => 'هنّيه';

  @override
  String get gamesCheered => 'وصلته تهنئتك';

  @override
  String get gamesSafety => 'مفيش شراء جوه الألعاب ولا إعلانات ولا كلام مع حد غريب. كل كائن وكل نجمة بتتكسب بالمذاكرة.';

  @override
  String get gamesTogether => 'العبوا سوا';

  @override
  String get gamesSaved => 'اتحفظ.';

  @override
  String get gamesCardKids => 'الجزر بتستناك 🏝️';

  @override
  String gamesCardLead(String what) {
    return 'اتمرن على دروسك بألعاب: $what';
  }

  @override
  String journeyTitle(String name) {
    return 'رحلة $name';
  }

  @override
  String journeyYours(String name) {
    return 'رحلتك يا $name';
  }

  @override
  String journeyLevel(int n) {
    return 'المستوى $n';
  }

  @override
  String journeyLevelOf(int i, int n) {
    return 'المستوى $i من $n';
  }

  @override
  String journeyStationOf(int i, int n) {
    return 'المحطة $i من $n';
  }

  @override
  String journeyHow(String name) {
    return 'إزاي $name ماشي';
  }

  @override
  String journeyNextStation(String title) {
    return 'الجاية: $title';
  }

  @override
  String get journeyLevels => 'المستويات';

  @override
  String journeyLessonsDone(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$total درس',
      few: '$total دروس',
      two: 'درسين',
      one: 'درس واحد',
    );
    return '$done من $_temp0';
  }

  @override
  String journeyAheadLocked(String title) {
    return 'بتتفتح لما تخلّص $title';
  }

  @override
  String get journeyAheadFirst => 'بتتفتح لما توصلها';

  @override
  String journeyAriaDone(String title) {
    return '$title، خلّصتها';
  }

  @override
  String journeyAriaHere(String title) {
    return '$title، انت هنا';
  }

  @override
  String journeyAriaAhead(String title) {
    return '$title، لسه مقفولة';
  }

  @override
  String get journeyStationDone => 'خلّصته';

  @override
  String get journeyStationReview => 'محتاج مراجعة';

  @override
  String get journeyStationHere => 'انت هنا';

  @override
  String get journeyStationTodo => 'لسه جاي';

  @override
  String get journeyStationTodoNote => 'الدرس ده لسه جاي. هتتدرّب عليه لما توصله.';

  @override
  String get journeyStationReviewNote => 'وقت تراجع الدرس ده: العب لعبة عليه.';

  @override
  String journeyPractised(String rounds, int p) {
    return 'اتدرّبت عليه $rounds · $p% صح';
  }

  @override
  String get journeyPlayStation => 'العب على الدرس ده';

  @override
  String get journeyNoGames => 'مفيش لعبة على تدريبات الدرس ده لسه.';

  @override
  String journeyRaceLast(String s) {
    return 'آخر مرة: $s';
  }

  @override
  String get journeyMissions => 'مهماتك';

  @override
  String journeyMissionsOf(String name) {
    return 'مهمات $name';
  }

  @override
  String journeyMissionsOn(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n مهمة', few: '$n مهمات', two: 'مهمتين', one: 'مهمة');
    return '$_temp0';
  }

  @override
  String journeyOpenMissions(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n مهمة مستنية',
      few: '$n مهمات مستنية',
      two: 'مهمتين مستنيين',
      one: 'مهمة مستنية',
    );
    return '$_temp0';
  }

  @override
  String get journeyNoMissions => 'مفيش مهمات دلوقتي. استمتع بيومك!';

  @override
  String get journeyReview => 'راجع';

  @override
  String reviewsWaiting(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n مراجعة مستنياك',
      few: '$n مراجعات مستنياك',
      two: 'مراجعتين مستنينك',
      one: 'مراجعة مستنياك',
    );
    return '$_temp0';
  }

  @override
  String get reviewsHint => 'كل مراجعة لعبة قصيرة';

  @override
  String hereAndNext(String here, String next) {
    return 'انت هنا: $here · بعدها: $next';
  }

  @override
  String hereOnly(String here) {
    return 'انت هنا: $here';
  }

  @override
  String levelNamed(int n, String title) {
    return 'المستوى $n: $title';
  }

  @override
  String get allLevels => 'كل المستويات';

  @override
  String get goalTitle => 'هدفك: تخلّص البرنامج';

  @override
  String get goalDone => 'خلّصتهم من البرنامج';

  @override
  String goalPace(String day) {
    return 'على السرعة دي هتخلّص يوم $day';
  }

  @override
  String get nowTitle => 'دلوقتي';

  @override
  String nextIs(String title) {
    return 'الجاية: $title';
  }

  @override
  String get weekPlan => 'خطة الأسبوع';

  @override
  String get notHandedIn => 'لسه ماتسلّمش';

  @override
  String get planNoDay => 'من غير ميعاد';

  @override
  String lessonsToReview(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n درس محتاجين مراجعة',
      few: '$n دروس محتاجة مراجعة',
      two: 'درسين محتاجين مراجعة',
      one: 'درس محتاج مراجعة',
    );
    return '$_temp0';
  }

  @override
  String get reviewEachRound => 'جولة قصيرة لكل درس';

  @override
  String get wholeCurriculum => 'المنهج كله';

  @override
  String levelsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n مستوى', few: '$n مستويات', two: 'مستويين', one: 'مستوى واحد');
    return '$_temp0';
  }

  @override
  String curriculumLessons(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n درس', few: '$n دروس', two: 'درسين', one: 'درس واحد');
    return '$_temp0';
  }

  @override
  String get journeyLate => 'متأخر';

  @override
  String get journeyChestTitle => 'صندوق الأسبوع';

  @override
  String journeyChestProgress(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$total مهمة',
      few: '$total مهمات',
      two: 'مهمتين',
      one: 'مهمة واحدة',
    );
    return '$done من $_temp0 الأسبوع';
  }

  @override
  String journeyChestHint(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n نجمة', few: '$n نجوم', two: 'نجمتين', one: 'نجمة');
    return 'احضر كل حصص الأسبوع وسلّم واجباته، والصندوق يتفتح بـ $_temp0';
  }

  @override
  String get journeyChestMissed => 'الصندوق ده فاتك. فيه صندوق جديد الأسبوع ده!';

  @override
  String journeyChestWaiting(String name) {
    return 'الصندوق جاهز يتفتح من حساب $name';
  }

  @override
  String journeyChestGot(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '+$n نجمة في رحلتك!',
      few: '+$n نجوم في رحلتك!',
      two: '+نجمتين في رحلتك!',
      one: '+نجمة في رحلتك!',
    );
    return '$_temp0';
  }

  @override
  String get journeyYourStars => 'نجومك';

  @override
  String journeyStarsTo(int n, String rank) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'باقيلك $n نجمة لـ $rank',
      few: 'باقيلك $n نجوم لـ $rank',
      two: 'باقيلك نجمتين لـ $rank',
      one: 'باقيلك نجمة لـ $rank',
    );
    return '$_temp0';
  }

  @override
  String journeyStarsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n نجمة',
      few: '$n نجوم',
      two: 'نجمتين',
      one: 'نجمة واحدة',
      zero: 'مفيش نجوم لسه',
    );
    return '$_temp0';
  }

  @override
  String journeyStarsOf(String name) {
    return 'نجوم $name';
  }

  @override
  String get journeyLastLesson => 'آخر حصة';

  @override
  String journeyLastLessonOf(String name) {
    return 'آخر حصة لـ $name';
  }

  @override
  String journeyLessonStars(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'خدت $n نجمة في آخر حصة!',
      few: 'خدت $n نجوم في آخر حصة!',
      two: 'خدت نجمتين في آخر حصة!',
      one: 'خدت نجمة في آخر حصة',
      zero: 'ماخدتش نجوم في آخر حصة',
    );
    return '$_temp0';
  }

  @override
  String journeyLessonCanBring(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'تجيبلك لحد $n نجمة',
      few: 'تجيبلك لحد $n نجوم',
      two: 'تجيبلك نجمتين',
      one: 'تجيبلك نجمة',
    );
    return '$_temp0';
  }

  @override
  String get journeyEggReady => 'بيضتك جاهزة!';

  @override
  String get journeyEggHint => 'افقسها وشوف مين هيطلع';

  @override
  String get journeyEggHatch => 'افقسها';

  @override
  String get journeyEggNone => 'مفيش بيضة جاهزة دلوقتي. كمّل جمع نجوم!';

  @override
  String get journeyNoCourses => 'مفيش كورسات شغالة لسه.';

  @override
  String get journeyNoCurriculum => 'الكورس ده لسه مالوش منهج. مهماته بتظهر هنا.';

  @override
  String get journeyCourses => 'مواد الدروس والتقارير';

  @override
  String get homeworkPracticeTitle => 'التدريب بالألعاب';

  @override
  String get homeworkPracticeNotYet => 'لسه ما اتلعبتش جولته.';

  @override
  String get homeworkByPlaying => 'الواجب ده بيتسلّم لما تلعب جولته.';

  @override
  String homeworkPracticeResult(int correct, int total, String stars, String game) {
    return 'التدريب: $correct من $total صح · $stars ($game)';
  }

  @override
  String get homeworkNoGame => 'مفيش لعبة للواجب ده دلوقتي.';

  @override
  String get hifzTitle => 'خريطة الحفظ';

  @override
  String get hifzTitleKids => 'خريطة حفظي';

  @override
  String hifzTitleOf(String name) {
    return 'خريطة حفظ $name';
  }

  @override
  String get hifzNoText => 'نص القرآن لسه مش موجود، والخريطة هتظهر أول ما يوصل.';

  @override
  String hifzMemorized(int n, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n صفحة محفوظة من $total',
      few: '$n صفحات محفوظة من $total',
      two: 'صفحتين محفوظين من $total',
      one: 'صفحة واحدة محفوظة من $total',
      zero: 'لسه مفيش صفحات محفوظة من $total',
    );
    return '$_temp0';
  }

  @override
  String hifzMemorizedKids(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'حفظت $n صفحة! ⭐',
      few: 'حفظت $n صفحات! ⭐',
      two: 'حفظت صفحتين! ⭐',
      one: 'حفظت صفحة واحدة! ⭐',
      zero: 'لسه ماحفظتش صفحات. يلا نبدأ!',
    );
    return '$_temp0';
  }

  @override
  String hifzJuz(int n) {
    return 'الجزء $n';
  }

  @override
  String hifzJuzSummary(int n, String summary) {
    return 'الجزء $n: $summary';
  }

  @override
  String hifzPageNumber(int page) {
    return 'صفحة $page';
  }

  @override
  String hifzPageOf(int page, int juz) {
    return 'صفحة $page · الجزء $juz';
  }

  @override
  String get hifzToday => 'ورد النهارده';

  @override
  String hifzTodayProgress(int done, int daily) {
    String _temp0 = intl.Intl.pluralLogic(
      daily,
      locale: localeName,
      other: '$daily صفحة',
      few: '$daily صفحات',
      two: 'صفحتين',
      one: 'صفحة واحدة',
    );
    return 'خلص $done من $_temp0';
  }

  @override
  String get hifzTodayDone => 'ورد النهارده خلص. برافو!';

  @override
  String get hifzTodayNone => 'مفيش صفحات عليها مراجعة النهارده.';

  @override
  String get hifzWirdDone => 'ورد النهارده خلص!';

  @override
  String get hifzWirdDoneKids => 'خلّصت وردك! 🎉';

  @override
  String hifzOverdue(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'متأخرة $n يوم',
      few: 'متأخرة $n أيام',
      two: 'متأخرة يومين',
      one: 'متأخرة يوم',
    );
    return '$_temp0';
  }

  @override
  String get hifzReveal => 'اكشف الصفحة';

  @override
  String get hifzRevealHint => 'سمّع الصفحة من حفظك الأول، وبعدين اكشفها واتأكد.';

  @override
  String get hifzHowWent => 'الحفظ كان عامل إزاي؟';

  @override
  String get hifzGrade1 => 'نسيتها';

  @override
  String get hifzGrade2 => 'بصعوبة';

  @override
  String get hifzGrade3 => 'كويس';

  @override
  String get hifzGrade4 => 'سهلة';

  @override
  String hifzNextReview(String day) {
    return 'اتسجّلت. الصفحة دي هترجعلك $day.';
  }

  @override
  String get hifzRecite => 'سمّع الصفحة';

  @override
  String get hifzReciteKids => 'سمّع صفحة';

  @override
  String get hifzReciteHint => 'سجّل الصفحة من حفظك، وهنصحّحها كلمة كلمة.';

  @override
  String get hifzSendRecitation => 'ابعت التسميع';

  @override
  String get hifzListening => 'بنسمع التسميع…';

  @override
  String get hifzListeningHint => 'النتيجة هتظهر هنا لوحدها.';

  @override
  String get hifzSelfGrade => 'قيّم الصفحة بنفسك';

  @override
  String get hifzLatest => 'آخر تسميع';

  @override
  String get hifzWird => 'ورد الحفظ';

  @override
  String hifzCardLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'فاضل $n صفحة النهارده',
      few: 'فاضل $n صفحات النهارده',
      two: 'فاضل صفحتين النهارده',
      one: 'فاضل صفحة واحدة النهارده',
    );
    return '$_temp0';
  }

  @override
  String get hifzCardDone => 'خلص النهارده';

  @override
  String get hifzCardNone => 'مفيش صفحات النهارده';

  @override
  String hifzCardKids(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'فاضلك $n صفحة تسمّعها',
      few: 'فاضلك $n صفحات تسمّعها',
      two: 'فاضلك صفحتين تسمّعهم',
      one: 'فاضلك صفحة واحدة تسمّعها',
      zero: 'خلّصت وردك النهارده! ⭐',
    );
    return '$_temp0';
  }

  @override
  String get hifzTell => 'قول اللي حافظه';

  @override
  String get hifzClear => 'شيل صفحات';

  @override
  String get hifzClearHint => 'الصفحات اللي هتشيلها هترجع جديدة على الخريطة، ومراجعاتها القديمة هتفضل.';

  @override
  String get hifzClearConfirm => 'تشيل الصفحات دي من الخريطة؟';

  @override
  String get hifzParts => 'الأجزاء';

  @override
  String get hifzSurahs => 'السور';

  @override
  String get hifzSurahHint => 'اكتب اسم السورة أو رقمها';

  @override
  String get hifzHowWell => 'حافظها إزاي؟';

  @override
  String hifzChosen(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n صفحة',
      few: '$n صفحات',
      two: 'صفحتين',
      one: 'صفحة واحدة',
      zero: 'مااخترتش صفحات لسه',
    );
    return '$_temp0';
  }

  @override
  String get hifzSave => 'سجّل';

  @override
  String hifzMarked(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'اتسجّلت $n صفحة',
      few: 'اتسجّلت $n صفحات',
      two: 'اتسجّلت صفحتين',
      one: 'اتسجّلت صفحة واحدة',
      zero: 'مااتسجّلش صفحات',
    );
    return '$_temp0';
  }

  @override
  String hifzCleared(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'اتشالت $n صفحة',
      few: 'اتشالت $n صفحات',
      two: 'اتشالت صفحتين',
      one: 'اتشالت صفحة واحدة',
      zero: 'مفيش صفحات اتشالت',
    );
    return '$_temp0';
  }

  @override
  String get hifzRecent => 'آخر المراجعات';

  @override
  String get hifzRecentNone => 'مفيش مراجعات في آخر أسبوعين.';

  @override
  String get mushafTitle => 'المصحف';

  @override
  String get mushafOpen => 'افتح المصحف';

  @override
  String get mushafNone => 'الحصة دي مفيهاش مصحف.';

  @override
  String get mushafPrevious => 'اللي قبلها';

  @override
  String get mushafNext => 'اللي بعدها';

  @override
  String get mushafPageField => 'صفحة';

  @override
  String mushafPageOfTotal(int total) {
    return 'من $total';
  }

  @override
  String get mushafFollowing => 'الصفحة بتتقلّب مع المدرّس.';

  @override
  String get mushafBackToTeacher => 'ارجع لصفحة المدرّس';

  @override
  String mushafTeacherOn(int page) {
    return 'المدرّس على صفحة $page';
  }

  @override
  String get mushafHintTeacher => 'دوس على أي كلمة عشان تعلّمها، والطالب بيشوف الصفحة اللي إنت فاتحها.';

  @override
  String get mushafMarkingClosed => 'تعليم الكلمات بيفتح مع أوضة الحصة، وبيقفل لما تسلّم التقرير.';

  @override
  String get mushafMarkWord => 'علّم الكلمة';

  @override
  String get mushafClearMark => 'امسح العلامة';

  @override
  String get mushafEarlier => 'اتعلّمت في حصة قبل كده';

  @override
  String mushafWordMarked(String word, String kind) {
    return '$word: $kind';
  }

  @override
  String mushafWordEarlier(String word, String kind, String times) {
    return '$word: $kind، اتعلّمت في حصة قبل كده $times';
  }

  @override
  String mushafVerseEnd(int n) {
    return 'آخر الآية $n';
  }

  @override
  String mushafSurah(String name) {
    return 'سورة $name';
  }

  @override
  String get mushafShow => 'المصحف';

  @override
  String get mushafShowVideo => 'الفيديو';

  @override
  String get mushafBackToVideo => 'ارجع للفيديو';

  @override
  String get mushafFailed => 'ماقدرناش نحفظ. جرّب تاني.';

  @override
  String get mistakesTitle => 'دفتر الأخطاء';

  @override
  String get mistakesTitleKids => 'كلمات بنراجعها';

  @override
  String get mistakesLead => 'الكلمات اللي اتعلّم عليها في الحصص في آخر ست شهور، عشان تراجعها.';

  @override
  String get mistakesLeadTeacher => 'الكلمات اللي اتعلّم عليها في حصص الطالب في آخر ست شهور.';

  @override
  String get mistakesLeadKids => 'دي كلمات هنقولها تاني مع بعض لحد ما تبقى سهلة.';

  @override
  String get mistakesEmpty => 'مفيش كلمات متعلّمة في آخر ست شهور.';

  @override
  String get mistakesEmptyKids => 'مفيش كلمات نراجعها دلوقتي. شاطر! ⭐';

  @override
  String mistakesWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n كلمة',
      few: '$n كلمات',
      two: 'كلمتين',
      one: 'كلمة واحدة',
      zero: 'مفيش كلمات',
    );
    return '$_temp0';
  }

  @override
  String mistakesTimes(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n مرة', few: '$n مرات', two: 'مرتين', one: 'مرة');
    return '$_temp0';
  }

  @override
  String mistakesWord(String word, String kinds, String times, String when) {
    return '$word: $kinds، $times، آخر مرة $when';
  }

  @override
  String mistakesWordKids(String word) {
    return '$word: كلمة بنراجعها';
  }
}
