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
  String get retry => 'حاول مرة أخرى';

  @override
  String get offline => 'لا يوجد اتصال بالإنترنت. تحقّق من الاتصال وحاول مرة أخرى.';

  @override
  String get problem => 'حدثت مشكلة. حاول مرة أخرى.';

  @override
  String get back => 'رجوع';

  @override
  String get close => 'إغلاق';

  @override
  String get errorOfflineTitle => 'لا يوجد اتصال بالإنترنت';

  @override
  String get errorServerTitle => 'حدثت مشكلة لدينا';

  @override
  String get errorServerBody => 'نعمل على إصلاحها. حاول مرة أخرى بعد قليل.';

  @override
  String get errorForbiddenTitle => 'لا يُسمح لك بعرض هذا';

  @override
  String get errorForbiddenBody => 'إن رأيت أن هذا خطأ، فتواصل مع الأكاديمية.';

  @override
  String get reloadFailed => 'تعذّر التحديث، وقد يكون ما تراه قديمًا.';

  @override
  String get send => 'أرسل';

  @override
  String get optional => '(اختياري)';

  @override
  String get noticeSignedOut => 'خرجت من الحساب.';

  @override
  String get noticeExpired => 'انتهى الدخول إلى هذا الحساب. ادخل مرة أخرى.';

  @override
  String minutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقائق', two: 'دقيقتان', one: 'دقيقة');
    return '$_temp0';
  }

  @override
  String get today => 'اليوم';

  @override
  String get tomorrow => 'غدًا';

  @override
  String inMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقائق', two: 'دقيقتين', one: 'دقيقة');
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
      two: 'حصتان',
      one: 'حصة واحدة',
      zero: 'لا توجد حصص',
    );
    return '$_temp0';
  }

  @override
  String get introSkip => 'تخطَّ';

  @override
  String get introNext => 'التالي';

  @override
  String get introStart => 'لنبدأ';

  @override
  String introSlide(int n, int total) {
    return 'شريحة $n من $total';
  }

  @override
  String get introLive => 'مباشر';

  @override
  String get intro1Title => 'حصص مباشرة مع معلّمك';

  @override
  String get intro1Body => 'حصة فردية بالصوت والصورة، من البيت.';

  @override
  String get intro2Title => 'تابع التقدّم خطوة بخطوة';

  @override
  String get intro2Body => 'كل حصة تقرّبك من الهدف، والطريق كله أمامك.';

  @override
  String get intro3Title => 'المواعيد والواجبات والمدفوعات في مكان واحد';

  @override
  String get intro3Body => 'والتنبيه يصلك في وقته، فلا يفوتك شيء.';

  @override
  String get introLessonWhen => 'السبت · 7:00 م';

  @override
  String get introHomework => 'واجب جديد';

  @override
  String get introHomeworkWhen => 'قبل الثلاثاء';

  @override
  String get introPaid => 'دُفعت الفاتورة';

  @override
  String get introThanks => 'شكرًا لك';

  @override
  String get welcomeTitle => 'أهلًا بك';

  @override
  String get welcomeLead => 'اكتب رقم هاتفك أو بريدك الإلكتروني، وسنرسل إليك رمزًا تدخل به.';

  @override
  String get contactLabel => 'رقم الهاتف أو البريد الإلكتروني';

  @override
  String get contactPlaceholder => '01xxxxxxxxx أو name@mail.com';

  @override
  String get contactEmpty => 'اكتب رقم هاتفك أو بريدك الإلكتروني أولًا.';

  @override
  String get contactShortPhone => 'هذا الرقم ناقص، اكتبه كاملًا.';

  @override
  String get contactBadEmail => 'ينقص هذا البريد الإلكتروني شيء، مثل name@mail.com';

  @override
  String countryChange(String code) {
    return 'رمز الدولة $code، انقر لتغييره';
  }

  @override
  String get countrySearch => 'ابحث عن دولة';

  @override
  String get countryNone => 'لا توجد دولة بهذا الاسم.';

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
  String get countryUs => 'الولايات المتحدة وكندا';

  @override
  String get countryGb => 'المملكة المتحدة';

  @override
  String get legalAgree => 'بالمتابعة، أنت توافق على';

  @override
  String get legalAnd => 'و';

  @override
  String get countryCode => 'رمز الدولة';

  @override
  String get phoneHint => 'سيصلك الرمز على واتساب.';

  @override
  String get emailHint => 'سيصلك الرمز على هذا البريد الإلكتروني.';

  @override
  String get sendCode => 'أرسل لي رمزًا';

  @override
  String get havePassword => 'لديك كلمة مرور؟ ادخل بها';

  @override
  String get signInOr => 'أو';

  @override
  String get signInWithGoogle => 'ادخل بحساب جوجل';

  @override
  String get signInWithApple => 'ادخل بحساب أبل';

  @override
  String socialFailed(String provider) {
    return 'لم يكتمل الدخول بحساب $provider. حاول مرة أخرى.';
  }

  @override
  String get providerGoogle => 'جوجل';

  @override
  String get providerApple => 'أبل';

  @override
  String get signInMethodsTitle => 'الدخول بجوجل وأبل';

  @override
  String get signInMethodsHint => 'اربط حسابك بحساب جوجل أو أبل، وادخل بهما دون رمز أو كلمة مرور.';

  @override
  String signInMethodLinkedAs(String email) {
    return 'مربوط: $email';
  }

  @override
  String get signInMethodLinked => 'مربوط';

  @override
  String get signInMethodNotLinked => 'غير مربوط';

  @override
  String get signInMethodLink => 'اربط';

  @override
  String get signInMethodUnlink => 'افصل';

  @override
  String signInMethodUnlinkConfirm(String provider) {
    return 'أتفصل حساب $provider؟ لن تستطيع الدخول به حتى تربطه مرة أخرى.';
  }

  @override
  String signInMethodLinkedDone(String provider) {
    return 'رُبط حساب $provider.';
  }

  @override
  String signInMethodUnlinkedDone(String provider) {
    return 'فُصل حساب $provider.';
  }

  @override
  String get codeTitle => 'اكتب الرمز';

  @override
  String codeSentWhatsapp(String to) {
    return 'أرسلنا إليك رمزًا من 6 أرقام على واتساب، إلى الرقم $to';
  }

  @override
  String codeSentSms(String to) {
    return 'أرسلنا إليك رمزًا من 6 أرقام في رسالة نصية، إلى الرقم $to';
  }

  @override
  String codeSentEmail(String to) {
    return 'أرسلنا إليك رمزًا من 6 أرقام إلى البريد الإلكتروني $to';
  }

  @override
  String get codeLabel => 'الرمز';

  @override
  String get codeValid => 'الرمز صالح لمدة 10 دقائق.';

  @override
  String get codePaste => 'الصق الرمز';

  @override
  String get codeChecking => 'نتحقّق…';

  @override
  String get codePasteNone => 'لا يوجد رمز منسوخ من 6 أرقام.';

  @override
  String get codeRight => 'تم!';

  @override
  String get codeResent => 'أرسلنا إليك رمزًا جديدًا.';

  @override
  String get noCode => 'لم يصلك؟';

  @override
  String resendIn(String time) {
    return 'أرسل رمزًا جديدًا بعد $time';
  }

  @override
  String get resend => 'أرسل رمزًا جديدًا';

  @override
  String get sendBySms => 'أرسله في رسالة نصية بدلًا من واتساب';

  @override
  String get confirm => 'تأكيد';

  @override
  String get otherContact => 'استخدم رقمًا أو بريدًا إلكترونيًا آخر';

  @override
  String accountsFound(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'وجدنا لك $n حسابًا',
      few: 'وجدنا لك $n حسابات',
      two: 'وجدنا لك حسابين',
      one: 'وجدنا لك حسابًا واحدًا',
      zero: 'لم نجد حسابات',
    );
    return '$_temp0';
  }

  @override
  String get accountsLead => 'اختر ما تريده على هذا الهاتف. بعد ذلك تنتقل بينها من \"حسابي\" دون أن تدخل مرة أخرى.';

  @override
  String get accountsLegal =>
      'قبل أن تضيف حسابك، اقرأ سياسة الخصوصية وشروط الاستخدام الخاصة بالأكاديمية: فهي التي تحدّد ما يحدث لبياناتك لديها.';

  @override
  String get accountsNote =>
      'نعرض فقط الحسابات المسجّلة بالرقم نفسه، في الأكاديميات العاملة. لم تجد حسابًا؟ ربما سجّلتك الأكاديمية برقم آخر: تواصل معها.';

  @override
  String addAccounts(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'أضف $n حسابًا وادخل',
      few: 'أضف $n حسابات وادخل',
      two: 'أضف الحسابين وادخل',
      one: 'ادخل',
    );
    return '$_temp0';
  }

  @override
  String get foundYou => 'وجدناك!';

  @override
  String get foundYouLead => 'وجدنا لك حسابًا واحدًا.';

  @override
  String enterAcademy(String academy) {
    return 'ادخل $academy';
  }

  @override
  String get notYou => 'ليس حسابك؟ استخدم رقمًا أو بريدًا إلكترونيًا آخر';

  @override
  String get pickOne => 'اختر حسابًا واحدًا على الأقل.';

  @override
  String notAddedTitle(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'دخلت، لكن هناك حسابات لم تُضَف:',
      one: 'دخلت، لكن هناك حسابًا لم يُضَف:',
    );
    return '$_temp0';
  }

  @override
  String notAddedLine(String name, String academy, String reason) {
    return '$name في $academy: $reason';
  }

  @override
  String get noAccounts => 'لم نجد حسابًا بهذا الرقم أو البريد الإلكتروني في أي أكاديمية. تحقّق منه، أو تواصل مع أكاديميتك.';

  @override
  String get noAccountTitle => 'لم نجد حسابًا';

  @override
  String get noAccountByPhone => 'لم نجد حسابًا بهذا الرقم';

  @override
  String get noAccountByEmail => 'لم نجد حسابًا بهذا البريد الإلكتروني';

  @override
  String get noAccountCheckPhone => 'تأكّد أن الرقم مكتوب صحيحًا. وربما سجّلتك الأكاديمية برقم أو بريد إلكتروني آخر، فجرّبه أو تواصل معها.';

  @override
  String get noAccountCheckEmail =>
      'تأكّد أن البريد الإلكتروني مكتوب صحيحًا. وربما سجّلتك الأكاديمية برقم أو بريد إلكتروني آخر، فجرّبه أو تواصل معها.';

  @override
  String get noAccountTryEmail => 'جرّب البريد الإلكتروني';

  @override
  String get noAccountTryEmailBody => 'إن كان بريدك الإلكتروني لدى الأكاديمية، فسيصلك الرمز عليه.';

  @override
  String get noAccountOther => 'جرّب رقمًا أو بريدًا إلكترونيًا آخر';

  @override
  String get contactChange => 'غيّره';

  @override
  String get consentNeededTitle => 'نحتاج إلى موافقة ولي أمرك';

  @override
  String get consentNeededLead => 'قبل أن تدخل حسابك، يجب أن يوافق ولي أمرك مرة واحدة فقط. وبعدها تدخل مباشرة.';

  @override
  String get consentNeededStep1 => 'يفتح ولي أمرك التطبيق بحسابه.';

  @override
  String consentNeededStep2(String me, String privacy) {
    return 'من «$me» ثم «$privacy» يوافق على حسابك.';
  }

  @override
  String get consentNeededStep3 => 'ارجع إلى هنا وادخل مرة أخرى.';

  @override
  String get signInAgain => 'حاول الدخول مرة أخرى';

  @override
  String get twoFactorSetupTitle => 'فعّل الدخول بخطوتين أولًا';

  @override
  String get twoFactorSetupLead =>
      'يجب أن يكون الدخول بخطوتين مفعّلًا على هذا الحساب. فعّله من موقع الأكاديمية على الحاسوب، ثم ادخل من هنا كالمعتاد.';

  @override
  String get tooManyTitle => 'محاولات كثيرة';

  @override
  String get tooManyBody => 'لحماية حسابك، أوقفنا المحاولات قليلًا. انتظر حتى ينتهي العدّاد، ثم حاول مرة أخرى.';

  @override
  String retryIn(String time) {
    return 'حاول مرة أخرى بعد $time';
  }

  @override
  String welcomeHello(String name) {
    return 'أهلًا يا $name';
  }

  @override
  String welcomeIn(String academy) {
    return 'أنت الآن في $academy';
  }

  @override
  String get roleStudent => 'طالب';

  @override
  String get roleGuardian => 'ولي أمر';

  @override
  String get roleTeacher => 'معلّم';

  @override
  String get roleStaff => 'إدارة';

  @override
  String get passwordTitle => 'ادخل بكلمة المرور';

  @override
  String get academyCode => 'رمز الأكاديمية';

  @override
  String get academyCodeHint => 'تجده في رسالة الترحيب، أو اسأل الأكاديمية.';

  @override
  String get loginLabel => 'البريد الإلكتروني أو الهاتف أو اسم المستخدم';

  @override
  String get passwordLabel => 'كلمة المرور';

  @override
  String get academyCodeMissing => 'اكتب رمز الأكاديمية.';

  @override
  String get loginMissing => 'اكتب بريدك الإلكتروني أو هاتفك أو اسم المستخدم.';

  @override
  String get passwordMissing => 'اكتب كلمة المرور.';

  @override
  String get passwordShow => 'إظهار كلمة المرور';

  @override
  String get passwordHide => 'إخفاء كلمة المرور';

  @override
  String get signIn => 'ادخل';

  @override
  String get useCodeInstead => 'ادخل برمز بدلًا من كلمة المرور';

  @override
  String get twoFactorTitle => 'الدخول بخطوتين';

  @override
  String get twoFactorLead => 'افتح تطبيق المصادقة واكتب الرمز الذي فيه (6 أرقام). فقدت هاتفك؟ اكتب رمزًا من رموز الاسترداد.';

  @override
  String twoFactorAccount(String name, String academy) {
    return '$name في $academy';
  }

  @override
  String get twoFactorCode => 'الرمز';

  @override
  String get startAgain => 'ابدأ من جديد';

  @override
  String get webOnlyTitle => 'هذا الحساب للإدارة';

  @override
  String get webOnlyBody => 'حسابات الإدارة تعمل من الموقع. افتح لوحة الأكاديمية من الحاسوب.';

  @override
  String get privacyTitle => 'خصوصية الأبناء';

  @override
  String privacyLead(int age) {
    String _temp0 = intl.Intl.pluralLogic(age, locale: localeName, other: '$age سنة', few: '$age سنوات', two: 'سنتين', one: 'سنة');
    return 'الأبناء دون $_temp0 يحتاجون إلى موافقتك ليدخلوا حساباتهم ويحضروا بأنفسهم.';
  }

  @override
  String get privacyNone => 'لا يوجد أبناء يحتاجون إلى موافقة.';

  @override
  String get consentWaiting => 'بانتظار موافقتك';

  @override
  String get consentGranted => 'تمت الموافقة';

  @override
  String get consentAgree => 'موافق';

  @override
  String consentAgreed(String name) {
    return 'تم، يستطيع $name الدخول الآن.';
  }

  @override
  String get consentWithdraw => 'اسحب الموافقة';

  @override
  String consentWithdrawConfirm(String name) {
    return 'سيخرج $name من حسابه على كل الأجهزة، ولن يستطيع الدخول حتى توافق مرة أخرى. هل أنت متأكد؟';
  }

  @override
  String consentWithdrawn(String name) {
    return 'سحبت موافقتك، وخرج $name من حسابه.';
  }

  @override
  String consentNudge(int n, String names) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$names بانتظار موافقتك ليدخلوا حساباتهم.',
      one: '$names بانتظار موافقتك ليدخل حسابه.',
    );
    return '$_temp0';
  }

  @override
  String get consentNudgeAction => 'اقرأ ووافق';

  @override
  String get myDataTitle => 'بياناتي';

  @override
  String get myDataLead => 'كل ما تحتفظ به الأكاديمية عنك وعن أبنائك: صحّح بياناتك، أو نزّل نسخة، أو اطلب حذف حساب.';

  @override
  String get myDataLeadTeacher => 'كل ما تحتفظ به الأكاديمية عنك: صحّح اسمك، أو نزّل نسخة، أو اطلب حذف حسابك.';

  @override
  String get myDataYoung => 'ولي أمرك هو من يقدّم الطلبات الخاصة ببياناتك من حسابه.';

  @override
  String get myDataCopyTitle => 'نسخة من البيانات';

  @override
  String myDataCopyBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days يومًا',
      few: '$days أيام',
      two: 'يومين',
      one: 'يومًا واحدًا',
    );
    return 'ملف ZIP واحد: البيانات في ملف يمكن قراءته، ومعها الملفات التي سُلّمت. تجهز في دقائق وتبقى $_temp0.';
  }

  @override
  String get myDataCopyAction => 'جهّز نسخة';

  @override
  String get myDataCopyPreparing => 'قيد التجهيز: سنخبرك فور اكتمالها.';

  @override
  String get myDataCopyDownload => 'نزّل';

  @override
  String myDataCopyUntil(String when) {
    return 'النسخة جاهزة حتى $when';
  }

  @override
  String get myDataEraseTitle => 'حذف الحساب';

  @override
  String myDataEraseBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يومًا', few: '$days أيام', two: 'يومين', one: 'يوم واحد');
    return 'ترد الأكاديمية خلال $_temp0، وعندما توافق يُحذف الحساب بعد ذلك بيوم.';
  }

  @override
  String get myDataEraseAction => 'احذف هذا الحساب';

  @override
  String myDataEraseConfirm(String name) {
    return 'أتطلب حذف حساب $name؟';
  }

  @override
  String get myDataEraseCopyFirst => 'يمكنك تنزيل نسخة أولًا.';

  @override
  String get myDataEraseReason => 'لماذا؟ (إن أردت)';

  @override
  String myDataEraseWaiting(String when) {
    return 'طُلب: سترد الأكاديمية قبل $when.';
  }

  @override
  String myDataEraseApproved(String when) {
    return 'تمت الموافقة: سيُحذف الحساب $when.';
  }

  @override
  String myDataEraseRefused(String answer) {
    return 'الأكاديمية رفضت: $answer';
  }

  @override
  String get myDataEraseCancel => 'تراجع عن طلبك';

  @override
  String get myDataEraseAsked => 'وصل الطلب إلى الأكاديمية.';

  @override
  String get myDataEraseCancelled => 'تراجعت عن الطلب: سيبقى الحساب.';

  @override
  String get myDataWhatGoes => 'ما الذي يُحذف وما الذي يبقى';

  @override
  String get myDataWhatStays => 'ما الذي يبقى، دون الاسم';

  @override
  String get myDataHistory => 'الطلبات';

  @override
  String get myDataNone => 'لا توجد طلبات بعد.';

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
  String get myDataFixSaved => 'حُفظ.';

  @override
  String get myDataFixUnchanged => 'لم يتغيّر شيء.';

  @override
  String get myDataFixUnset => 'غير مسجّل';

  @override
  String get myDataFixContact => 'تغيّر البريد الإلكتروني ورقم الهاتف من «حسابي».';

  @override
  String myDataFixBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يومًا', few: '$days أيام', two: 'يومين', one: 'يوم واحد');
    return 'هل هناك خطأ آخر (تاريخ الميلاد، اسم في فاتورة، كلام في تقرير)؟ اكتبه للأكاديمية، وسترد خلال $_temp0.';
  }

  @override
  String get myDataFixAsk => 'اطلب تصحيحًا';

  @override
  String myDataFixAskTitle(String name) {
    return 'اطلب تصحيح بيانات $name';
  }

  @override
  String myDataFixAskBody(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يومًا', few: '$days أيام', two: 'يومين', one: 'يوم واحد');
    return 'اكتب ما الخطأ وما الصواب. ترد الأكاديمية خلال $_temp0، ويصلك ردها.';
  }

  @override
  String get myDataFixSays => 'ما الذي يحتاج إلى تصحيح';

  @override
  String get myDataFixPlaceholder => 'مثلًا: تاريخ الميلاد المسجّل 2016 والصحيح 2017.';

  @override
  String myDataFixTooShort(int min) {
    String _temp0 = intl.Intl.pluralLogic(
      min,
      locale: localeName,
      other: '$min حرفًا',
      few: '$min أحرف',
      two: 'حرفين',
      one: 'حرفًا واحدًا',
    );
    return 'اكتب $_temp0 على الأقل.';
  }

  @override
  String get myDataFixSend => 'أرسل الطلب';

  @override
  String get myDataFixAsked => 'وصل الطلب إلى الأكاديمية.';

  @override
  String myDataFixWaiting(String when) {
    return 'طُلب: سترد الأكاديمية قبل $when.';
  }

  @override
  String get myDataFixDone => 'صُحّح';

  @override
  String myDataFixDoneWith(String answer) {
    return 'صُحّح: $answer';
  }

  @override
  String myDataFixRefused(String answer) {
    return 'لم تصحّحه الأكاديمية: $answer';
  }

  @override
  String get myDataFixCancel => 'ألغِ الطلب';

  @override
  String get myDataFixCancelled => 'أُلغي الطلب.';

  @override
  String get myDataPrivacy => 'سياسة الخصوصية';

  @override
  String get myDataTerms => 'شروط الاستخدام';

  @override
  String get myDataDeletion => 'حذف الحساب';

  @override
  String get navHome => 'الرئيسية';

  @override
  String get navLessons => 'الحصص';

  @override
  String get navJourneyTab => 'الرحلة';

  @override
  String get navMoney => 'المدفوعات';

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
  String get navToday => 'اليوم';

  @override
  String get navWeek => 'أسبوعي';

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
  String get nextLesson => 'الحصة القادمة';

  @override
  String get noNextLesson => 'لا توجد حصص قادمة في الأسابيع الأربعة المقبلة.';

  @override
  String roomOpensIn(String time) {
    return 'يُفتح الفصل بعد $time';
  }

  @override
  String get roomOpen => 'الفصل مفتوح';

  @override
  String get lessonOn => 'الحصة جارية الآن';

  @override
  String lessonWith(String subject, String teacher) {
    return '$subject مع $teacher';
  }

  @override
  String get joinLesson => 'ادخل الحصة';

  @override
  String joinOpensAt(String time) {
    return 'يُفتح $time';
  }

  @override
  String get allLessons => 'كل الحصص';

  @override
  String startsIn(String time) {
    return 'بعد $time';
  }

  @override
  String roomOpensAt(String time) {
    return 'يُفتح الفصل $time';
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
  String get pickLearner => 'عن مَن؟';

  @override
  String get pickedNow => 'الآن';

  @override
  String get balance => 'الرصيد';

  @override
  String get owed => 'المستحق عليك';

  @override
  String get payNow => 'ادفع الآن';

  @override
  String get nothingOwed => 'لا شيء مستحق عليك';

  @override
  String get lastReport => 'آخر تقرير';

  @override
  String get readReport => 'اقرأه';

  @override
  String homeworkLine(String title) {
    return 'واجب: $title';
  }

  @override
  String get unfunded => 'بانتظار الدفع';

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
  String get upcoming => 'القادمة';

  @override
  String get past => 'السابقة';

  @override
  String get noUpcoming => 'لا توجد حصص قادمة في الأسابيع الأربعة المقبلة.';

  @override
  String get noPast => 'لا توجد حصص في الأسابيع الأربعة الماضية.';

  @override
  String substituteFor(String name) {
    return 'معلّم بديل: $name في إجازة';
  }

  @override
  String get refuseSubstitute => 'لا نوافق على البديل';

  @override
  String get refuseSubstituteTitle => 'ألا توافقون على البديل؟';

  @override
  String get refuseSubstituteBody => 'ستُلغى الحصة على حساب الأكاديمية، وتعود دقائقها إليك حصة تعويضية تحجزها لاحقًا.';

  @override
  String get refuseSubstituteYes => 'نعم، ألغِها وعوّضونا';

  @override
  String substituteChip(String name) {
    return 'بديل عن $name';
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
    return 'يُفتح الفصل $when';
  }

  @override
  String get lessonDetails => 'تفاصيل الحصة';

  @override
  String refusedSubstitute(int days) {
    String _temp0 = intl.Intl.pluralLogic(days, locale: localeName, other: '$days يومًا', few: '$days أيام', two: 'يومان', one: 'يوم واحد');
    return 'أُلغيت. لديك $_temp0 لتحجز فيها حصة التعويض.';
  }

  @override
  String get cancelLesson => 'إلغاء الحصة';

  @override
  String get disputeLesson => 'اعترض';

  @override
  String get disputeTitle => 'اعتراض على نتيجة الحصة';

  @override
  String disputeIntroFamily(String outcome) {
    return 'الحصة مسجّلة \"$outcome\". إن لم يكن هذا ما حدث، فأخبرونا خلال 48 ساعة من انتهائها، وستراجع الأكاديمية حضور الفصل وترد عليكم.';
  }

  @override
  String disputeIntroTeacher(String outcome) {
    return 'الحصة مسجّلة \"$outcome\". إن لم يكن هذا ما حدث، فأخبرنا خلال 48 ساعة من انتهائها، وستراجع الأكاديمية حضور الفصل وترد عليك.';
  }

  @override
  String get disputeWhat => 'ماذا حدث؟';

  @override
  String get disputeNote => 'التفاصيل';

  @override
  String get disputeNoteHint => 'لن يقرأ هذا إلا الأكاديمية.';

  @override
  String get disputeFillIn => 'اختر السبب واكتب ما حدث.';

  @override
  String get disputeSend => 'أرسل الاعتراض';

  @override
  String get disputeSent => 'وصل الاعتراض، والحصة قيد المراجعة.';

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
    return 'أتلغي حصة $day؟';
  }

  @override
  String get reason => 'السبب';

  @override
  String get noteForAcademy => 'ملاحظة للأكاديمية';

  @override
  String get keepIt => 'لا، أبقِها';

  @override
  String get yesCancel => 'نعم، ألغِها';

  @override
  String get cancelled => 'أُلغيت الحصة.';

  @override
  String get pickReason => 'اختر سببًا أولًا.';

  @override
  String get formatGroup => 'جماعية';

  @override
  String get formatPrivate => 'فردية';

  @override
  String groupSeats(int taken, int capacity) {
    return '$taken/$capacity';
  }

  @override
  String groupSeatsSpoken(int taken, int capacity) {
    return 'حصة جماعية، المقاعد المشغولة $taken من $capacity';
  }

  @override
  String groupWithNames(String names) {
    return 'مع $names';
  }

  @override
  String groupWithCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'مع $n زميلًا',
      few: 'مع $n زملاء',
      two: 'مع زميلين',
      one: 'مع زميل واحد',
      zero: 'لا زملاء معك في هذه الحصة',
    );
    return '$_temp0';
  }

  @override
  String groupStudentsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n طالبًا',
      few: '$n طلاب',
      two: 'طالبان',
      one: 'طالب واحد',
      zero: 'لا طلاب بعد',
    );
    return '$_temp0';
  }

  @override
  String get groupClassmates => 'الزملاء';

  @override
  String get groupStudents => 'الطلاب';

  @override
  String get groupFormat => 'النوع';

  @override
  String get skipLesson => 'لن أحضر هذه الحصة';

  @override
  String skipTitle(String day) {
    return 'الاعتذار عن حصة $day الجماعية';
  }

  @override
  String get skipGoesOn => 'الحصة جماعية فلا تُلغى: تستمر لبقية المجموعة، ويُعفى مقعدك وحدك.';

  @override
  String get skipYes => 'نعم، لن أحضر';

  @override
  String get skipDone => 'سجّلنا الاعتذار، وتستمر الحصة للمجموعة.';

  @override
  String groupYourSeat(int seat, int capacity) {
    return 'مقعدك $seat من $capacity';
  }

  @override
  String get groupCourse => 'مجموعة';

  @override
  String groupCourseSpoken(int taken, int capacity) {
    return 'مجموعة، المقاعد المشغولة $taken من $capacity';
  }

  @override
  String get groupForming => 'المجموعة قيد التكوين، وتبدأ حصصها حين يكتمل عددها.';

  @override
  String get groupPlan => 'باقة جماعية';

  @override
  String groupPlanSpoken(int taken, int capacity) {
    return 'باقة جماعية، المقاعد المشغولة $taken من $capacity';
  }

  @override
  String get rateLesson => 'قيّم الحصة';

  @override
  String get rateTitle => 'كيف كانت الحصة؟';

  @override
  String get rateGood => 'ما الذي أعجبكم؟';

  @override
  String get rateBad => 'ما الذي لم يعجبكم؟';

  @override
  String get rateComment => 'كلمة للأكاديمية';

  @override
  String get rateAnonymous => 'يرى المعلّم التقييم دون اسمك.';

  @override
  String get sendRating => 'أرسل التقييم';

  @override
  String get notNow => 'ليس الآن';

  @override
  String get rated => 'شكرًا! وصل تقييمك.';

  @override
  String starsOf(int n) {
    return '$n من 5';
  }

  @override
  String get stars1 => 'سيئة';

  @override
  String get stars2 => 'ليست جيدة تمامًا';

  @override
  String get stars3 => 'جيدة';

  @override
  String get stars4 => 'جيدة جدًا';

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
    return 'الآن: $title';
  }

  @override
  String weeksLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بقي نحو $n أسبوعًا',
      few: 'بقي نحو $n أسابيع',
      two: 'بقي نحو أسبوعين',
      one: 'بقي نحو أسبوع',
      zero: 'أوشك على الانتهاء',
    );
    return '$_temp0';
  }

  @override
  String homeworkOpen(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجبًا مفتوحًا',
      few: '$n واجبات مفتوحة',
      two: 'واجبان مفتوحان',
      one: 'واجب مفتوح',
    );
    return '$_temp0';
  }

  @override
  String get reports => 'التقارير';

  @override
  String get noReports => 'لا توجد تقارير بعد.';

  @override
  String get noCourses => 'لا توجد اشتراكات جارية.';

  @override
  String get noProgram => 'دون منهج';

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
  String get homeworkWaiting => 'بالانتظار';

  @override
  String get homeworkSent => 'سُلّم';

  @override
  String get homeworkMarked => 'صُحّح';

  @override
  String homeworkWaitingOnYou(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجبًا ينتظرك',
      few: '$n واجبات تنتظرك',
      two: 'واجبان ينتظرانك',
      one: 'واجب ينتظرك',
    );
    return '$_temp0';
  }

  @override
  String get homeworkNoneWaiting => 'لا توجد واجبات تنتظرك الآن.';

  @override
  String get homeworkNoneSent => 'لا توجد واجبات تنتظر التصحيح.';

  @override
  String get homeworkNoneMarked => 'لم يُصحَّح أي واجب بعد.';

  @override
  String get homeworkLastMarked => 'آخر تصحيح';

  @override
  String get homeworkHandIn => 'سلّم الواجب';

  @override
  String get homeworkStartRound => 'ابدأ الجولة';

  @override
  String get noHomework => 'لا توجد واجبات الآن.';

  @override
  String dueBy(String when) {
    return 'قبل $when';
  }

  @override
  String get handIn => 'سلّم';

  @override
  String get yourAnswer => 'إجابتك';

  @override
  String get handedIn => 'سُلّم. سيصحّحه المعلّم.';

  @override
  String get teacherFeedback => 'رأي المعلّم';

  @override
  String grade(int g) {
    return 'الدرجة: $g من 5';
  }

  @override
  String get yourSubmission => 'ما سلّمته';

  @override
  String get kidsHomeworkStar => 'كل واجب تسلّمه يعطيك نجمة جديدة!';

  @override
  String get owedNow => 'المستحق الآن';

  @override
  String invoiceDue(String number, String when) {
    return 'فاتورة $number · قبل $when';
  }

  @override
  String payAmount(String amount) {
    return 'ادفع $amount';
  }

  @override
  String get payNote => 'يتم الدفع في صفحة الدفع الخاصة بالأكاديمية. عندما تنتهي، ارجع إلى هنا.';

  @override
  String get lessonsLeftTitle => 'الحصص المتبقية';

  @override
  String get invoices => 'الفواتير';

  @override
  String get noInvoices => 'لا توجد فواتير.';

  @override
  String get openInvoice => 'افتح الفاتورة';

  @override
  String get extraAction => 'حصص إضافية';

  @override
  String extraHint(String price, int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: '$minutes دقيقة',
      few: '$minutes دقائق',
      two: 'دقيقتان',
      one: 'دقيقة',
    );
    return 'سعر الحصة الإضافية $price ($_temp0).';
  }

  @override
  String extraTitle(String subject) {
    return 'حصص إضافية في $subject';
  }

  @override
  String get extraBody => 'اختر العدد، وستصدر لك فاتورة تدفعها مثل أي فاتورة. بعد أن تدفع، تحجز الأكاديمية الحصص معك.';

  @override
  String extraEach(int n, String price) {
    return '$n × $price';
  }

  @override
  String get extraSubmit => 'أصدر الفاتورة';

  @override
  String extraDone(String number, String total) {
    return 'الفاتورة $number جاهزة ($total).';
  }

  @override
  String extraWaiting(String number) {
    return 'فاتورة الحصص الإضافية $number بانتظار الدفع.';
  }

  @override
  String get invoiceNote => 'افتح أي فاتورة لترى تفاصيلها وتدفعها، وزر المستند يعرض نسختها للطباعة مع رمز QR الخاص بالفاتورة الضريبية.';

  @override
  String get cantPayOnline => 'الدفع الإلكتروني غير متاح لهذه الفاتورة. تواصل مع الأكاديمية.';

  @override
  String get pickGateway => 'بماذا تدفع؟';

  @override
  String get paidByTransfer => 'دفعت بالتحويل';

  @override
  String get proofTitle => 'أرسل إثبات الدفع';

  @override
  String get proofHint => 'ادفع أولًا، ثم أرسل الإيصال هنا. ستتحقّق الأكاديمية من وصول المبلغ وتؤكّد الدفعة.';

  @override
  String get proofHowTo => 'كيف تدفع بالتحويل';

  @override
  String get proofMethod => 'طريقة الدفع';

  @override
  String get proofAmount => 'المبلغ الذي دفعته';

  @override
  String get proofPaidOn => 'يوم الدفع';

  @override
  String get proofReference => 'رقم التحويل (اختياري)';

  @override
  String get proofNote => 'ملاحظة (اختياري)';

  @override
  String get proofReceipt => 'الإيصال';

  @override
  String get proofPickReceipt => 'اختر صورة أو ملف PDF';

  @override
  String get proofReceiptHint => 'صورة أو لقطة شاشة أو ملف PDF من البنك، حتى 10 ميغابايت.';

  @override
  String get proofSend => 'أرسل الإثبات';

  @override
  String get proofSent => 'أُرسل الإثبات. سنخبرك فور تأكيد الأكاديمية له.';

  @override
  String get proofNeedsReceipt => 'اختر الإيصال أولًا.';

  @override
  String get proofFileTooBig => 'الملف أكبر من 10 ميغابايت.';

  @override
  String get transferTitle => 'بيانات التحويل';

  @override
  String get transferCopy => 'انسخ';

  @override
  String get transferCopied => 'نُسخت بيانات التحويل.';

  @override
  String get joining => 'ندخلك إلى الحصة…';

  @override
  String get linkNotOpened => 'لا يوجد تطبيق على هذا الهاتف يفتح هذا الرابط.';

  @override
  String get openedOutside => 'فُتحت الحصة في تطبيق آخر.';

  @override
  String get leave => 'اخرج من الحصة';

  @override
  String get micOff => 'أغلق الميكروفون';

  @override
  String get micOn => 'افتح الميكروفون';

  @override
  String get camOff => 'أغلق الكاميرا';

  @override
  String get camOn => 'افتح الكاميرا';

  @override
  String get audioOnly => 'الصوت فقط';

  @override
  String get rejoinHint => 'إن خرجت بالخطأ، فادخل مرة أخرى من الزر نفسه حتى تنتهي الحصة.';

  @override
  String get weakNetwork => 'الاتصال ضعيف. جرّب \"الصوت فقط\" إن كانت الصورة تتقطّع.';

  @override
  String get waitingOther => 'ننتظر دخول الطرف الآخر…';

  @override
  String get you => 'أنت';

  @override
  String roomScreenOf(String name) {
    return 'شاشة $name';
  }

  @override
  String get roomFlipCamera => 'بدّل الكاميرا';

  @override
  String get roomLayout => 'طريقة العرض';

  @override
  String get roomLayoutAuto => 'تلقائي';

  @override
  String get roomLayoutGrid => 'شبكة';

  @override
  String get roomLayoutSpotlight => 'عرض مكبّر';

  @override
  String get roomMoreOptions => 'خيارات أخرى';

  @override
  String get roomPin => 'ثبّت في الواجهة';

  @override
  String get roomUnpin => 'ألغِ التثبيت';

  @override
  String get roomMicMuted => 'الميكروفون مغلق';

  @override
  String get roomQualityGood => 'الاتصال جيد';

  @override
  String get roomQualityFair => 'الاتصال متوسط';

  @override
  String get roomQualityPoor => 'الاتصال ضعيف';

  @override
  String get roomZoomHint => 'باعد بإصبعين للتكبير، وانقر مرتين للعودة.';

  @override
  String get roomShowBars => 'أظهر أزرار الحصة';

  @override
  String minutesLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقائق', two: 'دقيقتان', one: 'دقيقة');
    return 'بقي $_temp0';
  }

  @override
  String sharedPackage(String subjects) {
    return '$subjects · باقة مشتركة';
  }

  @override
  String get familyInvoice => 'فاتورة العائلة';

  @override
  String coursePaused(String day) {
    return 'متوقف حتى $day';
  }

  @override
  String coursePausedKids(String day) {
    return 'في إجازة حتى $day';
  }

  @override
  String coursePauseAhead(String from, String until) {
    return 'سيتوقف من $from حتى $until';
  }

  @override
  String courseEnding(String day) {
    return 'ينتهي من $day';
  }

  @override
  String get courseEnded => 'انتهى، والدقائق المتبقية ما زالت في الرصيد';

  @override
  String lessonUntil(String time) {
    return 'الحصة حتى $time';
  }

  @override
  String get meTitle => 'حسابي';

  @override
  String get moreChildren => 'الأبناء';

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
  String get moreWork => 'عملي';

  @override
  String get moreStudy => 'الدراسة';

  @override
  String get moreHelp => 'المساعدة';

  @override
  String get yourAccount => 'حسابك وإعداداتك';

  @override
  String get moreAccountSettings => 'الحساب والإعدادات';

  @override
  String get meetingLinkMissing => 'غير محدد';

  @override
  String moreAcademies(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n أكاديمية',
      few: '$n أكاديميات',
      two: 'أكاديميتان',
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
    return 'الإصدار $version';
  }

  @override
  String get contactTitle => 'البريد الإلكتروني والهاتف';

  @override
  String get contactHint => 'هذه طرق دخولك، وتصلك عليها الرموز والرسائل. عندما تغيّر أحدها، نرسل رمزًا إلى الجديد لنتأكد أنه لك.';

  @override
  String get contactEmail => 'البريد الإلكتروني';

  @override
  String get contactPhone => 'رقم الهاتف';

  @override
  String get contactNone => 'غير مسجّل';

  @override
  String get contactChangeEmail => 'غيّر البريد';

  @override
  String get contactChangePhone => 'غيّر الرقم';

  @override
  String get contactAddEmail => 'أضف بريدًا';

  @override
  String get contactAddPhone => 'أضف رقمًا';

  @override
  String get contactNewEmail => 'البريد الإلكتروني الجديد';

  @override
  String get contactNewPhone => 'الرقم الجديد';

  @override
  String get contactPhoneHint => 'مع رمز الدولة، أو رقمًا محليًا من بلدك.';

  @override
  String get contactSend => 'أرسل رمزًا';

  @override
  String get contactCode => 'الرمز الذي وصلك';

  @override
  String contactCodeHint(String contact, String until) {
    return 'أرسلنا رمزًا إلى $contact. صالح حتى $until.';
  }

  @override
  String get contactConfirm => 'أكّد';

  @override
  String get contactResend => 'أرسل رمزًا آخر';

  @override
  String get contactDoneEmail => 'تغيّر البريد الإلكتروني. من الآن تدخل به وتصلك الرسائل عليه.';

  @override
  String get contactDonePhone => 'تغيّر الرقم. من الآن تدخل به وتصلك الرسائل عليه.';

  @override
  String get contactManaged => 'الأكاديمية أو ولي أمرك هما من يغيّران بريدك الإلكتروني ورقمك.';

  @override
  String get openNow => 'مفتوح الآن';

  @override
  String get switchTo => 'انتقل';

  @override
  String get addAccount => 'أضف حسابًا آخر';

  @override
  String clockPrompt(String device, String profile) {
    return 'هاتفك على توقيت $device، وحسابك على $profile. هل نعرض المواعيد بتوقيت $device؟';
  }

  @override
  String clockYes(String zone) {
    return 'نعم، $zone';
  }

  @override
  String clockKeep(String zone) {
    return 'أبقِه $zone';
  }

  @override
  String get language => 'اللغة';

  @override
  String get timezone => 'التوقيت';

  @override
  String zoneChip(String city, String time) {
    return 'توقيتك $city، والساعة الآن $time';
  }

  @override
  String get zoneChipMismatch => 'توقيت هاتفك يختلف عن توقيت حسابك';

  @override
  String zoneShownIn(String zone) {
    return 'المواعيد في التطبيق بتوقيت $zone.';
  }

  @override
  String get zoneYours => 'توقيت حسابك';

  @override
  String get zoneAcademy => 'توقيت الأكاديمية';

  @override
  String get zoneDevice => 'توقيت هاتفك';

  @override
  String get zoneUseDevice => 'استخدم توقيت هاتفي';

  @override
  String get zoneChange => 'غيّر توقيتي';

  @override
  String get zonePick => 'اختر توقيتك';

  @override
  String get zoneSearch => 'ابحث عن مدينة أو منطقة';

  @override
  String get zoneNone => 'لا توجد مدينة بهذا الاسم.';

  @override
  String zoneSaved(String zone) {
    return 'صارت مواعيدك بتوقيت $zone.';
  }

  @override
  String get zoneHint => 'تظهر مواعيد حصصك وتذكيراتها بهذا التوقيت، واضغط لتغييره.';

  @override
  String get zoneCurrent => 'الحالي';

  @override
  String get zoneHide => 'أخفِ المؤشّر';

  @override
  String get zoneHidden => 'أخفيت مؤشّر التوقيت. يمكنك إظهاره من الحساب والإعدادات.';

  @override
  String get zoneChipShow => 'إظهار مؤشّر التوقيت';

  @override
  String get zoneChipShowHint => 'مدينتك وساعتها أعلى الشاشات، ومنه تغيّر توقيتك. يسري اختيارك هنا وعلى الويب.';

  @override
  String get timeFormat => 'شكل الساعة';

  @override
  String timeFormatExample(String time) {
    return 'تظهر المواعيد هكذا: $time';
  }

  @override
  String timeFormatAcademy(String hours) {
    return 'مثل الأكاديمية ($hours)';
  }

  @override
  String get timeFormat12 => '12 ساعة';

  @override
  String get timeFormat24 => '24 ساعة';

  @override
  String get myDevices => 'أجهزتي';

  @override
  String get thisDevice => 'هذا الجهاز';

  @override
  String lastUsed(String when) {
    return 'آخر مرة $when';
  }

  @override
  String get signOutDevice => 'أخرِجه';

  @override
  String get deviceSignedOut => 'خرج الجهاز من الحساب.';

  @override
  String get signOut => 'اخرج من هذا الحساب';

  @override
  String signOutConfirm(String academy) {
    return 'أتخرج من $academy على هذا الهاتف؟';
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
      other: 'اليوم: $n حصة',
      few: 'اليوم: $n حصص',
      two: 'اليوم: حصتان',
      one: 'اليوم: حصة واحدة',
      zero: 'لا توجد حصص اليوم',
    );
    return '$_temp0';
  }

  @override
  String hours(String h) {
    return '$h ساعة';
  }

  @override
  String nextIn(String time) {
    return 'القادمة بعد $time';
  }

  @override
  String reportsWaiting(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n تقريرًا ينتظرك',
      few: '$n تقارير تنتظرك',
      two: 'تقريران ينتظرانك',
      one: 'تقرير ينتظرك',
    );
    return '$_temp0';
  }

  @override
  String homeworkToMark(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجبًا تصحّحها',
      few: '$n واجبات تصحّحها',
      two: 'واجبان تصحّحهما',
      one: 'واجب تصحّحه',
    );
    return '$_temp0';
  }

  @override
  String reportDueBy(String when) {
    return 'قبل $when';
  }

  @override
  String get prevWeek => 'الأسبوع الماضي';

  @override
  String get nextWeek => 'الأسبوع القادم';

  @override
  String get noLessonsDay => 'لا توجد حصص';

  @override
  String get noLessonsWeek => 'لا توجد حصص هذا الأسبوع.';

  @override
  String get thisWeek => 'هذا الأسبوع';

  @override
  String get weekdaysShort => 'سبت،أحد،اثنين،ثلاثاء،أربعاء،خميس،جمعة';

  @override
  String dayToday(String day) {
    return '$day · اليوم';
  }

  @override
  String get noLessonsLater => 'لا توجد حصص من هذا اليوم حتى آخر الأسبوع.';

  @override
  String get laterToday => 'بقية اليوم';

  @override
  String get doneToday => 'انتهت اليوم';

  @override
  String get restDay => 'لا توجد حصص اليوم. حصصك القادمة خلال الأسبوع.';

  @override
  String get openWeek => 'افتح الأسبوع';

  @override
  String get writeTheReport => 'اكتب التقرير';

  @override
  String get markingTile => 'واجبات للتصحيح';

  @override
  String get allWritten => 'كُتبت كلها';

  @override
  String get nothingToMark => 'لا شيء';

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
  String get noStudents => 'لا يوجد طلاب بعد.';

  @override
  String studentsIn(String students, String subjects) {
    return '$students في $subjects';
  }

  @override
  String studentsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n طالبًا',
      few: '$n طلاب',
      two: 'طالبان',
      one: 'طالب واحد',
      zero: 'لا يوجد طلاب',
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
      zero: 'لا توجد مواد',
    );
    return '$_temp0';
  }

  @override
  String get searchByName => 'ابحث بالاسم';

  @override
  String get allSubjects => 'الكل';

  @override
  String get noStudentsFound => 'لا يوجد طالب بهذا الاسم.';

  @override
  String get reportWaitingChip => 'تقرير ينتظرك';

  @override
  String get messageStudent => 'أرسل إليه رسالة';

  @override
  String get markHomework => 'صحّح الواجبات';

  @override
  String ofCourse(int n) {
    return '$n% من البرنامج';
  }

  @override
  String get prevMonth => 'الشهر الماضي';

  @override
  String get nextMonth => 'الشهر القادم';

  @override
  String get soFar => 'حتى اليوم';

  @override
  String get monthOpen => 'الشهر ما زال مفتوحًا';

  @override
  String get monthClosed => 'أُغلق الشهر';

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
      other: '$n حصة لم يُحدَّد سعرها بعد',
      few: '$n حصص لم يُحدَّد سعرها بعد',
      two: 'حصتان لم يُحدَّد سعرهما بعد',
      one: 'حصة لم يُحدَّد سعرها بعد',
    );
    return '$_temp0. ستحدّد الأكاديمية السعر، وتُحسب في هذا الشهر.';
  }

  @override
  String get noEarnings => 'لا توجد حصص في هذا الشهر.';

  @override
  String get adjustments => 'تعديلات';

  @override
  String get corrected => 'صُحّح';

  @override
  String get meetingLinkTitle => 'رابط حصتك';

  @override
  String get meetingLinkNeeded => 'تعمل الأكاديمية بروابط خارجية، ولن تُفتح أي حصة دون رابطك.';

  @override
  String get meetingLinkSave => 'احفظ الرابط';

  @override
  String get meetingLinkSaved => 'حُفظ الرابط.';

  @override
  String get meetingLinkRemoved => 'أُزيل الرابط.';

  @override
  String get teacherCardTitle => 'بطاقتي في صفحة الأكاديمية';

  @override
  String get teacherCardLead =>
      'البطاقة التي تراها الأسر عنك في صفحة الأكاديمية. أنت من يكتبها، وأنت من يقرّر أن تظهر أو لا. رقمك وبريدك الإلكتروني لا يظهران أبدًا.';

  @override
  String get teacherCardShown => 'أظهرني في صفحة الأكاديمية';

  @override
  String get teacherCardShownHint => 'ما دام مغلقًا، فلن يرى أحد بطاقتك.';

  @override
  String get teacherCardName => 'الاسم الذي يظهر';

  @override
  String get teacherCardNameHint => 'مثل «أ. سارة» أو اسمك الأول فقط.';

  @override
  String get teacherCardBio => 'كلمات قليلة عنك';

  @override
  String get teacherCardBioHint => 'خبرتك وما تحب تدريسه، حتى 280 حرفًا. دون أرقام أو روابط.';

  @override
  String get teacherCardPhoto => 'صورتك (اختياري)';

  @override
  String get teacherCardPhotoHint => 'PNG أو JPEG أو WebP حتى 1 ميغابايت. يراها كل من يفتح الصفحة.';

  @override
  String get teacherCardPickPhoto => 'اختر صورة';

  @override
  String get teacherCardChangePhoto => 'غيّر الصورة';

  @override
  String get teacherCardRemovePhoto => 'أزل الصورة';

  @override
  String get teacherCardTooBig => 'الصورة أكبر من 1 ميغابايت.';

  @override
  String get teacherCardPreview => 'هكذا سترى الأسر بطاقتك';

  @override
  String get teacherCardSubjectsFrom => 'المواد من ملفك في الأكاديمية';

  @override
  String get teacherCardSave => 'احفظ';

  @override
  String get teacherCardSaved => 'حُفظت بطاقتك.';

  @override
  String teacherCardLevels(String from, String to) {
    return '$from ← $to';
  }

  @override
  String get teacherCardFromStart => 'من البداية';

  @override
  String get teacherCardToEnd => 'حتى النهاية';

  @override
  String get performanceTitle => 'أدائي';

  @override
  String performanceLead(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'آخر $days يومًا',
      few: 'آخر $days أيام',
      two: 'آخر يومين',
      one: 'آخر يوم',
    );
    return '$_temp0 · الحصص التي كان سببها المنصة لا تُحسب';
  }

  @override
  String get performanceGroupCommitment => 'الالتزام';

  @override
  String get performanceGroupQuality => 'الجودة';

  @override
  String get performanceGroupProfessionalism => 'الاحترافية';

  @override
  String get performanceGroupBusiness => 'النمو';

  @override
  String get performanceGood => 'جيد';

  @override
  String get performanceBad => 'يحتاج إلى تحسين';

  @override
  String get performanceNone => 'ليس بعد';

  @override
  String get performanceCounted => 'ما حُسب';

  @override
  String get performanceNoEvents => 'لم يُحسب عليك شيء. استمر!';

  @override
  String get performanceExcluded => 'غير محسوبة';

  @override
  String get performanceRatings => 'تقييمات الأسر';

  @override
  String performanceAverage(String avg, int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n تقييمًا', few: '$n تقييمات', two: 'تقييمان', one: 'تقييم واحد');
    return '$avg من 5 · $_temp0';
  }

  @override
  String get performanceNoRatings => 'لا توجد تقييمات بعد.';

  @override
  String performanceStars(int n) {
    return '$n من 5';
  }

  @override
  String get timeOff => 'الإجازات';

  @override
  String get requestTimeOff => 'اطلب إجازة';

  @override
  String timeOffLead(String zone) {
    return 'بتوقيتك: $zone · الأكاديمية ترد عليك';
  }

  @override
  String get wholeDays => 'أيام كاملة';

  @override
  String get partOfDay => 'جزء من يوم';

  @override
  String get fromDay => 'من';

  @override
  String get toDay => 'حتى';

  @override
  String get dayLabel => 'اليوم';

  @override
  String get fromTime => 'من الساعة';

  @override
  String get toTime => 'حتى الساعة';

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
  String get reasonOther => 'سبب آخر';

  @override
  String get sendRequest => 'أرسل الطلب';

  @override
  String get yourRequests => 'طلباتك';

  @override
  String get withdraw => 'اسحبه';

  @override
  String get requestSent => 'أُرسل الطلب. سيصلك الرد.';

  @override
  String get withdrawn => 'سُحب الطلب.';

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
    return '$covered من $_temp0 لها بديل';
  }

  @override
  String get fillTimeOff => 'اختر الأيام والسبب أولًا.';

  @override
  String get noTimeOff => 'لا توجد طلبات.';

  @override
  String get youAreHere => 'أنت هنا!';

  @override
  String get topRank => 'وصلت إلى أعلى رتبة!';

  @override
  String streak(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n حصة متتالية',
      few: '$n حصص متتالية',
      two: 'حصتان متتاليتان',
      one: 'حصة واحدة',
      zero: 'لا توجد حصص متتالية بعد',
    );
    return '$_temp0';
  }

  @override
  String badgesCount(int earned, int total) {
    return '$earned من $total';
  }

  @override
  String get finishOn => 'بهذه الوتيرة تنتهي';

  @override
  String get programDone => 'أنهيت البرنامج!';

  @override
  String weeksLeftLevel(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بقي $n أسبوعًا في هذا المستوى',
      few: 'بقيت $n أسابيع في هذا المستوى',
      two: 'بقي أسبوعان في هذا المستوى',
      one: 'بقي أسبوع في هذا المستوى',
      zero: 'أوشك هذا المستوى على الانتهاء',
    );
    return '$_temp0';
  }

  @override
  String get fromLastMonth => 'مقارنة بالشهر الماضي';

  @override
  String get doneMark => 'تم!';

  @override
  String get chestReady => 'الصندوق جاهز! انقر عليه';

  @override
  String get chestOpened => 'فتحت صندوق هذا الأسبوع';

  @override
  String get album => 'ألبوم الملصقات';

  @override
  String get albumLead => 'كل صندوق تفتحه يعطيك ملصقًا جديدًا.';

  @override
  String get newSticker => 'ملصق جديد في ألبومك!';

  @override
  String get rewardKids => 'أحسنت!';

  @override
  String get rewardTitle => 'أحسنت!';

  @override
  String rankUp(String rank) {
    return 'رتبة جديدة: $rank';
  }

  @override
  String get awesome => 'رائع!';

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
    return 'وصل التشجيع إلى $name!';
  }

  @override
  String cheersLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بقي $n تشجيعًا اليوم',
      few: 'بقيت $n تشجيعات اليوم',
      two: 'بقي تشجيعان اليوم',
      one: 'بقي تشجيع واحد اليوم',
      zero: 'انتهت تشجيعات اليوم',
    );
    return '$_temp0';
  }

  @override
  String get sounds => 'الأصوات';

  @override
  String get soundsHint => 'أصوات خفيفة عند النقر أو عند استلام جائزة';

  @override
  String get reportsOpen => 'تقارير تنتظرك';

  @override
  String get reportsSent => 'أُرسلت';

  @override
  String get writeReport => 'اكتب';

  @override
  String waitingFor(String what) {
    return 'ينتظرك: $what';
  }

  @override
  String get waitingNothing => 'لا شيء ينتظرك';

  @override
  String bothWaiting(String a, String b) {
    return '$a و$b';
  }

  @override
  String reportsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n تقريرًا',
      few: '$n تقارير',
      two: 'تقريران',
      one: 'تقرير واحد',
      zero: 'لا توجد تقارير',
    );
    return '$_temp0';
  }

  @override
  String homeworkCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n واجبًا',
      few: '$n واجبات',
      two: 'واجبان',
      one: 'واجب واحد',
      zero: 'لا توجد واجبات',
    );
    return '$_temp0';
  }

  @override
  String get markingRow => 'واجبات تنتظر التصحيح';

  @override
  String get markingNone => 'لا توجد واجبات تنتظر الآن';

  @override
  String get noReportsWaiting => 'لا توجد تقارير تنتظرك. أحسنت!';

  @override
  String get reportLesson => 'تقرير حصة';

  @override
  String get reportAbsence => 'ملاحظة غياب';

  @override
  String get todaysPlan => 'خطة اليوم';

  @override
  String get planHint => 'اختر ما حدث في كل درس.';

  @override
  String get reportTitle => 'تقرير الحصة';

  @override
  String get workedOn => 'على ماذا عملنا';

  @override
  String get howItWent => 'كيف كانت الحصة';

  @override
  String get talkSection => 'الملاحظات';

  @override
  String get keepDraft => 'مسودة';

  @override
  String get reportDraftSaved => 'حُفظت مسودة على هذا الهاتف.';

  @override
  String draftFrom(String ago) {
    return 'أكملت من مسودة حُفظت قبل $ago.';
  }

  @override
  String get discardDraft => 'ابدأ من جديد';

  @override
  String get voiceTitle => 'مسودة من رسالة صوتية';

  @override
  String get voiceHint => 'قل ماذا فعلتم، وكيف كان أداء الطالب، والواجب. المسودة تملأ الخانات الفارغة فقط، وأنت تراجعها قبل أن ترسل.';

  @override
  String get voiceRecord => 'سجّل رسالة';

  @override
  String get voiceWorking => 'نستمع ونكتب المسودة…';

  @override
  String voiceDone(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'ملأت المسودة $n خانة. راجعها قبل أن ترسل.',
      few: 'ملأت المسودة $n خانات. راجعها قبل أن ترسل.',
      two: 'ملأت المسودة خانتين. راجعهما قبل أن ترسل.',
      one: 'ملأت المسودة خانة واحدة. راجعها قبل أن ترسل.',
      zero: 'لم تُضف الرسالة شيئًا جديدًا: كان كل ذلك في النموذج.',
    );
    return '$_temp0';
  }

  @override
  String get voiceHeard => 'ما سُمع';

  @override
  String get voiceUnsure => 'راجع هذه: لم تكن المسودة متأكدة منها';

  @override
  String voiceLeft(String fields) {
    return 'ما زال ينقص: $fields';
  }

  @override
  String get voiceLimit => 'حتى دقيقتين';

  @override
  String get voiceKept => 'يُحذف التسجيل فور سماعه، ولا يُحفظ أبدًا.';

  @override
  String get suggested => 'مقترح';

  @override
  String get checkThis => 'راجعه';

  @override
  String get reciteTitle => 'تصحيح التسميع';

  @override
  String get reciteToggle => 'تصحيح التسميع آليًا';

  @override
  String get reciteHint => 'عندما يسلّم الطالب تسجيلًا، نعلّم لك الكلمات الناقصة أو الخاطئة. هذا لك وحدك، والأسرة لا تراه.';

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
  String get reciteChooseSurah => 'اختر السورة';

  @override
  String get reciteSearch => 'ابحث عن سورة';

  @override
  String get reciteFrom => 'من آية';

  @override
  String get reciteTo => 'إلى آية';

  @override
  String reciteWhat(String title) {
    return 'التسميع: $title';
  }

  @override
  String get reciteMissing => 'ناقصة';

  @override
  String get reciteWrong => 'خطأ';

  @override
  String get reciteExtra => 'زيادة';

  @override
  String reciteSaid(String word) {
    return 'قيل: $word';
  }

  @override
  String get reciteHeard => 'ما سُمع';

  @override
  String get drafts => 'مسودات';

  @override
  String savedAgo(String ago) {
    return 'حُفظت قبل $ago';
  }

  @override
  String get continueDraft => 'أكمل';

  @override
  String get reasonNext => 'الدرس القادم';

  @override
  String get reasonReview => 'مراجعة';

  @override
  String get outcomeIntroduced => 'شُرح';

  @override
  String get outcomePracticed => 'تدرّب عليه';

  @override
  String get outcomeMastered => 'أتقنه';

  @override
  String get outcomeNeedsReview => 'يحتاج إلى مراجعة';

  @override
  String get itemScore => 'الدرجة';

  @override
  String get itemNote => 'ملاحظة على الدرس';

  @override
  String get addNote => 'أضف ملاحظة';

  @override
  String get addLesson => 'أضف درسًا';

  @override
  String get pickLesson => 'اختر درسًا';

  @override
  String get searchLessons => 'ابحث عن درس';

  @override
  String get remove => 'أزل';

  @override
  String get notesForFamily => 'ملاحظات للطالب وولي الأمر';

  @override
  String get notesInternal => 'ملاحظات داخلية';

  @override
  String get notesInternalHint => 'للأكاديمية فقط، ولا تراها الأسرة.';

  @override
  String get questionsTitle => 'أسئلة الأكاديمية';

  @override
  String get questionsHint => 'أسئلة تطرحها الأكاديمية في كل تقرير لهذه المادة.';

  @override
  String answerRequired(String question) {
    return 'أجب عن \"$question\".';
  }

  @override
  String get answerYes => 'نعم';

  @override
  String get answerNo => 'لا';

  @override
  String get academyOnly => 'للأكاديمية فقط';

  @override
  String get lessonMaterials => 'مواد الدرس';

  @override
  String get teachersOnly => 'للمعلّم فقط';

  @override
  String get recordingsTitle => 'تسجيلات الحصص';

  @override
  String get recordingsMine => 'تسجيلات حصصي';

  @override
  String get recordingsOff => 'لا تسجّل الأكاديمية الحصص الآن.';

  @override
  String recordingsOf(String name) {
    return 'تسجيلات حصص $name';
  }

  @override
  String get recordingsTeacherList => 'تسجيلات حصصك';

  @override
  String get recordingsEmpty => 'لا توجد تسجيلات بعد.';

  @override
  String get recordingsNotShared => 'لا تعرض الأكاديمية التسجيلات هنا.';

  @override
  String get recordingWatch => 'شاهد';

  @override
  String get recordingAgreed => 'موافق';

  @override
  String get recordingNotAgreed => 'غير موافق';

  @override
  String get recordingAgree => 'موافق';

  @override
  String get recordingAgreedDone => 'شكرًا. يمكن تسجيل الحصص من الآن.';

  @override
  String get recordingWithdraw => 'اسحب الموافقة';

  @override
  String get recordingWithdrawConfirm => 'هل أنت متأكد؟ سيتوقف أي تسجيل جارٍ، ولن يُسجَّل شيء بعد ذلك.';

  @override
  String get recordingWithdrawn => 'سُحبت الموافقة، ولن يُسجَّل شيء بعد ذلك.';

  @override
  String recordingUntil(String date) {
    return 'متاح حتى $date';
  }

  @override
  String get recordingVideo => 'فيديو';

  @override
  String get recordingAudio => 'صوت';

  @override
  String get recordingLogged => 'يُسجَّل فتحك للتسجيل باسمك.';

  @override
  String get recordingOn => 'يسجّل';

  @override
  String get recordStart => 'سجّل الحصة';

  @override
  String get recordStop => 'أوقف التسجيل';

  @override
  String recordingMissing(String names) {
    return 'لا يمكن التسجيل حتى يوافقوا على التسجيل: $names.';
  }

  @override
  String get absenceNote => 'ملاحظة الغياب';

  @override
  String get absenceHint => 'لم يحضر الطالب. اكتب ما حدث وأي شيء تحتاج الأسرة إلى معرفته.';

  @override
  String get giveHomework => 'أعطِ واجبًا';

  @override
  String get homeworkField => 'الواجب';

  @override
  String get homeworkDetails => 'التفاصيل';

  @override
  String get acceptsLabel => 'يُسلَّم';

  @override
  String get acceptsAny => 'أي شيء';

  @override
  String get acceptsText => 'كتابة';

  @override
  String get acceptsAudio => 'تسجيل صوت';

  @override
  String get acceptsFile => 'ملف';

  @override
  String dueIn(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بعد $n يومًا',
      few: 'بعد $n أيام',
      two: 'بعد يومين',
      one: 'بعد يوم',
    );
    return '$_temp0';
  }

  @override
  String get homeworkLocked => 'سلّم الطالب هذا الواجب، فلن يتغيّر.';

  @override
  String get sendReport => 'أرسل التقرير';

  @override
  String get updateReport => 'حدّث التقرير';

  @override
  String get reportSent => 'أُرسل التقرير.';

  @override
  String get reportLocked => 'راجعت الأكاديمية هذا التقرير، فلن يتغيّر.';

  @override
  String get pickRatings => 'اختر التركيز والمستوى العام.';

  @override
  String get groupReportTitle => 'تقرير الحصة الجماعية';

  @override
  String get groupReportShared => 'ما دُرس والأسئلة والواجب هنا للمجموعة كلها، ويصل الواجب إلى كل طالب.';

  @override
  String get groupReportRowsHint => 'لكل طالب سطر: التركيز والمستوى العام وملاحظة له، أو ملاحظة غيابه.';

  @override
  String get groupAttended => 'حضر';

  @override
  String get groupAbsent => 'غاب';

  @override
  String get groupRowNote => 'ملاحظة له ولأسرته';

  @override
  String get groupRowAbsence => 'ملاحظة الغياب لأسرته';

  @override
  String groupRowRate(String name) {
    return 'قيّم $name: التركيز والمستوى العام.';
  }

  @override
  String get groupRowsMissing => 'أكمل سطر كل طالب حضر.';

  @override
  String get groupVoiceShared => 'يملأ التسجيل الجزء المشترك فقط، وسطر كل طالب تكتبه بنفسك.';

  @override
  String get groupLessonRow => 'حصة جماعية';

  @override
  String get disputeWhose => 'عن أي طالب الاعتراض؟';

  @override
  String get disputeWhoseHint => 'يكون الاعتراض في الحصة الجماعية على مقعد طالب واحد.';

  @override
  String disputeAbout(String name) {
    return 'عن $name';
  }

  @override
  String get pickOutcomes => 'اختر ما حدث في كل درس.';

  @override
  String get markTitle => 'تصحيح الواجبات';

  @override
  String get markWaiting => 'بانتظار التصحيح';

  @override
  String get markDone => 'صُحّح';

  @override
  String get noHomeworkToMark => 'لا توجد واجبات تنتظر التصحيح.';

  @override
  String get gradeLabel => 'الدرجة';

  @override
  String get feedbackLabel => 'كلمتك للطالب';

  @override
  String get saveMark => 'سجّل التصحيح';

  @override
  String get marked => 'سُجّل التصحيح.';

  @override
  String handedInAt(String when) {
    return 'سُلّم $when';
  }

  @override
  String get fileOnWeb => 'يُفتح هذا الملف من الموقع.';

  @override
  String get showPicture => 'اعرض الصورة';

  @override
  String get playRecording => 'شغّل التسجيل';

  @override
  String get pause => 'أوقف';

  @override
  String get cannotPlay => 'لا نستطيع تشغيل هذا التسجيل هنا.';

  @override
  String get waitingTap => 'انقر لتبدأ';

  @override
  String get recordVoice => 'سجّل صوتك';

  @override
  String get recordKids => 'سجّل تسميعك';

  @override
  String recordingNow(String time) {
    return 'يسجّل… $time';
  }

  @override
  String get stopRecording => 'أوقف التسجيل';

  @override
  String get recordAgain => 'سجّل مرة أخرى';

  @override
  String get attachFile => 'أضف ملفًا';

  @override
  String get attachPicture => 'أضف صورة أو ملف PDF';

  @override
  String get micDenied => 'نحتاج إلى إذن الميكروفون لتسجّل. فعّله من إعدادات الهاتف.';

  @override
  String recordingLimit(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n دقيقة', few: '$n دقائق', two: 'دقيقتين', one: 'دقيقة');
    return 'توقف التسجيل عند $_temp0.';
  }

  @override
  String get fileTooBig => 'الملف أكبر من 25 ميغابايت.';

  @override
  String get answerOptional => 'اكتب شيئًا آخر (اختياري)';

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
  String get currentWeek => 'جدولك الحالي';

  @override
  String get noHoursYet => 'لا توجد مواعيد معتمدة بعد.';

  @override
  String get proposeHours => 'اقترح مواعيد جديدة';

  @override
  String startsOn(String date) {
    return 'يبدأ $date';
  }

  @override
  String get academyCounter => 'عدّلت الأكاديمية مقترحك. هذا ما تغيّر:';

  @override
  String get timeAdded => 'أُضيف';

  @override
  String get timeRemoved => 'أُزيل';

  @override
  String get acceptCounter => 'موافق على التعديل';

  @override
  String get withdrawConfirm => 'أتسحب المقترح؟ ستبقى المواعيد المعتمدة كما هي.';

  @override
  String get proposalAccepted => 'اعتُمدت مواعيدك.';

  @override
  String get proposalSent => 'أُرسل المقترح إلى الأكاديمية.';

  @override
  String get draftSaved => 'حُفظت المسودة.';

  @override
  String get conversation => 'المحادثة مع الأكاديمية';

  @override
  String get writeMessage => 'اكتب رسالة…';

  @override
  String get editHours => 'عدّل مواعيدك';

  @override
  String get addTime => 'أضف وقتًا';

  @override
  String get dayOff => 'لا يوجد';

  @override
  String get effectiveFrom => 'يبدأ من';

  @override
  String reduceNotice(String date) {
    return 'إن كنت ستقلّل الساعات، فأقرب بداية $date.';
  }

  @override
  String get minHours => 'أقل عدد من الساعات في الأسبوع';

  @override
  String get maxHours => 'أكثر عدد من الساعات في الأسبوع';

  @override
  String get proposalNote => 'ملاحظة للأكاديمية';

  @override
  String get saveDraft => 'احفظ مسودة';

  @override
  String get sendProposal => 'أرسل إلى الأكاديمية';

  @override
  String get endAfterStart => 'يجب أن يكون وقت النهاية بعد البداية.';

  @override
  String get untilMidnight => 'حتى منتصف الليل';

  @override
  String get upcomingNote => 'اعتمدته الأكاديمية، ويبقى جدولك الحالي حتى ذلك اليوم.';

  @override
  String hoursSame(String h) {
    return '$h في الأسبوع كما هي، بأوقات أخرى';
  }

  @override
  String get stepDraft => 'مسودة';

  @override
  String get stepSent => 'أُرسل';

  @override
  String get stepReview => 'قيد المراجعة';

  @override
  String get stepDecision => 'القرار';

  @override
  String get stepCountered => 'عرض مضاد';

  @override
  String get stepChanges => 'طُلب تعديل';

  @override
  String get stepApproved => 'معتمد';

  @override
  String get stepRejected => 'مرفوض';

  @override
  String get stageDraft => 'المسودة محفوظة على حسابك ولم تُرسل بعد.';

  @override
  String get stageReview => 'وصل مقترحك إلى الأكاديمية، وسنخبرك حين ترد.';

  @override
  String get stageChanges => 'طلبت الأكاديمية تعديلًا. اقرأ المحادثة، ثم عدّل المقترح وأرسله.';

  @override
  String get stageRejected => 'لم تعتمد الأكاديمية مقترحك، ويبقى جدولك كما هو.';

  @override
  String reasonLine(String reason) {
    return 'السبب: $reason';
  }

  @override
  String get editAndSend => 'عدّل وأرسل';

  @override
  String get withdrawProposal => 'اسحب المقترح';

  @override
  String get newProposalHint => 'تريد تغيير أوقاتك؟ اقترح جدولًا، وتراجعه الأكاديمية.';

  @override
  String conversationCount(int n) {
    return 'المحادثة ($n)';
  }

  @override
  String get conversationStart => 'اكتب للأكاديمية عن مقترحك.';

  @override
  String get conversationEmpty => 'لا رسائل بعد. اكتب للأكاديمية، وسيصلك ردها هنا.';

  @override
  String get conversationClosed => 'لا يوجد مقترح مفتوح الآن، فلا يمكن الكتابة هنا.';

  @override
  String get proposalSentEvent => 'أُرسل المقترح';

  @override
  String askedToStart(String date) {
    return 'طُلب أن يبدأ $date';
  }

  @override
  String get heroDraftTitle => 'مسودة لم تُرسل بعد';

  @override
  String get heroReviewTitle => 'في انتظار الأكاديمية';

  @override
  String get heroChangesTitle => 'طلبت الأكاديمية تعديلًا';

  @override
  String get heroCounterTitle => 'عرض من الأكاديمية';

  @override
  String get continueAndSend => 'أكمل وأرسل';

  @override
  String get proposalDays => 'ما يتغيّر في مقترحك';

  @override
  String upcomingHeroTitle(String date) {
    return 'جدولك الجديد يبدأ $date';
  }

  @override
  String startsInDays(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بعد $n يومًا',
      few: 'بعد $n أيام',
      two: 'بعد يومين',
      one: 'غدًا',
      zero: 'اليوم',
    );
    return '$_temp0';
  }

  @override
  String get upcomingApproved => 'اعتمدته الأكاديمية.';

  @override
  String get rejectedHeroTitle => 'لم تُعتمد مواعيدك';

  @override
  String get weekNow => 'الآن';

  @override
  String weekFrom(String date) {
    return 'من $date';
  }

  @override
  String noHoursBefore(String date) {
    return 'لا مواعيد قبل $date';
  }

  @override
  String get proposeChange => 'اقترح تغييرًا';

  @override
  String builtOnUpcoming(String date) {
    return 'سيُبنى على جدولك من $date';
  }

  @override
  String historyCount(int n) {
    return 'السجل ($n)';
  }

  @override
  String get supersededLabel => 'حلّ محلّه مقترح أحدث';

  @override
  String get dayNew => 'جديد';

  @override
  String get dayChanged => 'تغيّر';

  @override
  String get inboxTitle => 'الإشعارات';

  @override
  String get markAllRead => 'علّم الكل كمقروء';

  @override
  String get noNotifications => 'لا توجد إشعارات.';

  @override
  String get loadMore => 'اعرض المزيد';

  @override
  String get notificationChoices => 'اختيارات الإشعارات';

  @override
  String get saved => 'حُفظ.';

  @override
  String get openIt => 'افتح';

  @override
  String unreadCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n إشعارًا جديدًا',
      few: '$n إشعارات جديدة',
      two: 'إشعاران جديدان',
      one: 'إشعار جديد',
      zero: 'لا جديد',
    );
    return '$_temp0';
  }

  @override
  String get updateTitle => 'يوجد إصدار جديد من التطبيق';

  @override
  String get updateBody => 'الإصدار الذي لديك قديم ولن يعمل مع الأكاديمية. حدّثه لتتابع.';

  @override
  String get updateNow => 'حدّث التطبيق';

  @override
  String get updateFromStore => 'حدّثه من متجر التطبيقات على هاتفك.';

  @override
  String get helpTitle => 'المساعدة';

  @override
  String get supportTitle => 'المساعدة';

  @override
  String get helpLead => 'اكتب للأكاديمية عن حصة، أو دفعة، أو مشكلة تقنية، أو أي شيء آخر. يصلك الرد هنا وفي رسالة.';

  @override
  String get supportLead => 'اكتب للأكاديمية عن مشكلة تقنية، أو راتبك، أو أي شيء آخر. يصلك الرد هنا وفي رسالة.';

  @override
  String get ticketNew => 'تذكرة جديدة';

  @override
  String get ticketNewTitle => 'اكتب للأكاديمية';

  @override
  String get ticketKind => 'عمّ تكتب؟';

  @override
  String get ticketHintComplaint => 'عن معلّم أو حصة. تبحث الأكاديمية في الأمر وتخبرك بما فعلت.';

  @override
  String get ticketHintBilling => 'فاتورة، أو دفعة، أو مبلغ مسترد، أو باقتك.';

  @override
  String get ticketHintPay => 'راتبك أو كشف حسابك.';

  @override
  String get ticketHintTechnical => 'الفصل أو التطبيق أو الدخول لا يعمل.';

  @override
  String get ticketHintOther => 'سؤال أو طلب.';

  @override
  String get ticketLesson => 'أي حصة؟ (إن وُجدت)';

  @override
  String get ticketNoLesson => 'ليست حصة معيّنة';

  @override
  String get ticketSubject => 'العنوان';

  @override
  String get ticketSubjectHint => 'في كلمات قليلة';

  @override
  String get ticketBody => 'أخبرنا بما حدث';

  @override
  String get ticketAttach => 'أرفق صورة أو ملف PDF';

  @override
  String get ticketTooBig => 'الملف أكبر من 10 ميغابايت.';

  @override
  String get ticketSent => 'أُرسلت. سترد الأكاديمية هنا.';

  @override
  String get ticketsEmpty => 'لا توجد تذاكر بعد. إن كان هناك أي شيء غير سليم، فاكتب إلينا.';

  @override
  String get ticketsMine => 'تذاكري';

  @override
  String ticketAbout(String name) {
    return 'عن $name';
  }

  @override
  String get ticketYourTurn => 'ردّت الأكاديمية';

  @override
  String get ticketYou => 'أنت';

  @override
  String get ticketAcademy => 'الأكاديمية';

  @override
  String get ticketReplyHint => 'اكتب للأكاديمية…';

  @override
  String get ticketReopen => 'لم تُحَل بعد';

  @override
  String ticketReopenHint(String date) {
    return 'لم تُحَل بعد؟ يمكنك إعادة فتحها حتى $date.';
  }

  @override
  String get ticketClose => 'أغلق التذكرة';

  @override
  String get ticketCloseHint => 'أغلقها عندما تُحَل أو إن لم تعد تحتاج إليها. لن تستقبل رسائل بعد ذلك.';

  @override
  String get ticketClosed => 'أُغلقت هذه التذكرة. افتح تذكرة جديدة إن احتجت إلى شيء.';

  @override
  String get tellUsTitle => 'بلّغ عن قلق';

  @override
  String get tellUsTitleStudent => 'هل هناك شيء غير سليم؟ أخبرنا';

  @override
  String get tellUsTitleKids => 'هل أحزنك شيء؟';

  @override
  String get tellUsNavKids => 'أخبر شخصًا كبيرًا';

  @override
  String get tellUsNavMiddle => 'أخبرنا إن كان هناك ما يزعجك';

  @override
  String get tellUsLead =>
      'إن أقلقك أي شيء في حصة، أو من معلّم، أو من أي شخص على المنصة، فأخبرنا هنا. لا يقرؤه إلا المسؤولون عن حماية الأطفال في الأكاديمية.';

  @override
  String get tellUsLeadStudent =>
      'إن جعلك أحد تشعر بعدم الارتياح، أو بالخوف، أو بأنك لست في أمان، فأخبرنا. لا يقرؤه إلا المسؤولون عن حماية الطلاب في الأكاديمية، ولن تُعاقَب.';

  @override
  String get tellUsLeadKids => 'إن أحزنك أحد أو أخافك، فاضغط على زر. سيساعدك شخص كبير في الأكاديمية.';

  @override
  String get concernTeacherNav => 'بلّغ عن قلق';

  @override
  String get concernTeacherTitle => 'بلّغ عن قلق على سلامة طالب';

  @override
  String get concernTeacherLead =>
      'إن أقلقك شيء في سلامة طالب أو حاله، أو تصرّف شخص بالغ آخر، فأخبر مسؤولي الحماية في الأكاديمية هنا. هم وحدهم من يقرؤونه. لا تحقّق فيه ولا تتواصل مع الأسرة بشأنه بنفسك.';

  @override
  String concernAbout(String name) {
    return 'عن $name';
  }

  @override
  String get concernWhat => 'عمّ يتعلّق الأمر؟';

  @override
  String get concernWhatKids => 'ماذا حدث؟';

  @override
  String get concernPick => 'اختر موضوع الأمر.';

  @override
  String get concernText => 'ماذا حدث؟';

  @override
  String get concernTextKids => 'هل تريد أن تقول المزيد؟ ليس ضروريًا.';

  @override
  String get concernTextHint => 'متى حدث، ومن كان موجودًا، وماذا قيل.';

  @override
  String get concernLesson => 'في أي حصة (إن وُجدت)؟';

  @override
  String get concernNoLesson => 'ليس عن حصة';

  @override
  String get concernStudent => 'أي طالب؟';

  @override
  String get concernNoStudent => 'ليس عن طالب معيّن';

  @override
  String get concernUrgent => 'قد يكون أحد في خطر الآن';

  @override
  String get concernSend => 'أرسل';

  @override
  String get concernSendTeacher => 'أرسل إلى مسؤولي الحماية';

  @override
  String get concernAgain => 'أخبرنا بشيء آخر';

  @override
  String get concernAgainTeacher => 'بلّغ عن أمر آخر';

  @override
  String concernNumber(String number) {
    return 'رقم $number';
  }

  @override
  String get concernsMine => 'ما أخبرتنا به';

  @override
  String get concernsNone => 'لا شيء بعد.';

  @override
  String get documentsTitle => 'مستنداتي';

  @override
  String get documentsLead =>
      'تراجع الأكاديمية هذه المستندات مقارنة بالأصل؛ وشهادة خلو السوابق الجنائية هي المطلوبة لتدرّس من هم دون 18 عامًا.';

  @override
  String get documentSend => 'أرسل مستندًا';

  @override
  String get documentSendHint => 'صورة واضحة أو ملف PDF من الجهة المختصة. احتفظ بالأصل: فقد تطلب الأكاديمية رؤيته.';

  @override
  String get documentKind => 'المستند';

  @override
  String get documentReference => 'رقم الشهادة (اختياري)';

  @override
  String get documentExpires => 'صالح حتى (اختياري)';

  @override
  String documentUntil(String day) {
    return 'صالح حتى $day';
  }

  @override
  String get documentNoEnd => 'دون تاريخ انتهاء';

  @override
  String get documentPick => 'اختر صورة أو ملف PDF';

  @override
  String get documentPickFirst => 'اختر الصورة أو ملف PDF أولًا.';

  @override
  String get documentTooBig => 'الملف أكبر من 10 ميغابايت.';

  @override
  String get documentSent => 'أُرسل: ستراجعه الأكاديمية.';

  @override
  String get documentsList => 'ما أرسلته';

  @override
  String get documentsNone => 'لم ترسل أي مستندات بعد.';

  @override
  String get messagesTitle => 'الرسائل';

  @override
  String get messagesFamilyTitle => 'الرسائل مع المعلّمين';

  @override
  String get messagesStudentTitle => 'الرسائل مع معلّميك';

  @override
  String get messagesKidsTitle => 'تحدّث مع معلّمك';

  @override
  String get messagesEmptyTeacher => 'عندما يصبح لديك طلاب، ستجدهم هنا وتستطيع التحدث معهم ومع أسرهم.';

  @override
  String get messagesEmptyFamily => 'عندما يصبح للطالب معلّم، ستجده هنا.';

  @override
  String get messagesStart => 'لم يكتب أحد بعد';

  @override
  String get messagesYou => 'أنت';

  @override
  String messagesAlsoHere(String names) {
    return 'معكم: $names';
  }

  @override
  String get messagesFirst => 'ابدأ المحادثة: يقرأ أولياء الأمور والأكاديمية ما يُكتب هنا.';

  @override
  String get messagesWrite => 'اكتب رسالة…';

  @override
  String gamesStars(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n نجمة',
      few: '$n نجوم',
      two: 'نجمتان',
      one: 'نجمة واحدة',
      zero: 'دون نجوم',
    );
    return '$_temp0';
  }

  @override
  String get gamesSoundOn => 'شغّل الموسيقى والأصوات';

  @override
  String get gamesSoundOff => 'أوقف الموسيقى والأصوات';

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
    return 'البيضة القادمة: $have من $need ⭐';
  }

  @override
  String get gamesIslandDone => 'جمعت كل كائنات الجزيرة!';

  @override
  String get gamesClose => 'أغلق اللعبة وارجع';

  @override
  String get gamesToIsland => 'ارجع إلى الجزيرة';

  @override
  String get gamesAlbum => 'كائناتي';

  @override
  String gamesItems(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n سؤالًا', few: '$n أسئلة', two: 'سؤالان', one: 'سؤال واحد');
    return '$_temp0';
  }

  @override
  String gamesPractising(String what) {
    return 'تتدرّب على: $what';
  }

  @override
  String get gamesResult0 => 'استمر وحاول مرة أخرى!';

  @override
  String get gamesResult1 => 'بداية جميلة!';

  @override
  String get gamesResult2 => 'أحسنت!';

  @override
  String get gamesResult3 => 'رائع! كله صحيح تقريبًا';

  @override
  String gamesScore(int correct, int total) {
    return '$correct من $total صحيحة من أول مرة';
  }

  @override
  String gamesHatched(String name) {
    return 'فقس $name!';
  }

  @override
  String gamesHomeworkDone(String title) {
    return 'سُلّم الواجب «$title» بهذا التدريب.';
  }

  @override
  String gamesEgg(int have, int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'البيضة القادمة: $have من $_temp0';
  }

  @override
  String get gamesAgain => 'العب مرة أخرى';

  @override
  String get gamesPlay => 'العب';

  @override
  String get gamesLoading => 'نجهّز الجولة…';

  @override
  String get gamesSending => 'نحسب النجوم…';

  @override
  String get gamesPageProblem => 'لم تعمل اللعبة. حاول مرة أخرى.';

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
    return 'كلما جمعت $_temp0، فقست لك بيضة جديدة';
  }

  @override
  String get gamesHatchTap0 => 'انقر على البيضة 3 مرات';

  @override
  String get gamesHatchTap1 => 'مرتان أخريان!';

  @override
  String get gamesHatchTap2 => 'مرة أخرى!';

  @override
  String get gamesHatchTap3 => 'تفقس...';

  @override
  String gamesHatchEgg(int left) {
    return 'البيضة، انقر عليها. بقي $left';
  }

  @override
  String gamesHatchNew(int found, int total) {
    return 'كائن جديد في ألبومك · $found من $total';
  }

  @override
  String get gamesHatchAsk => 'ماذا تحب أن تسمّيه؟';

  @override
  String get gamesHatchKeep => 'ضعه في الألبوم';

  @override
  String get gamesAlbumTitle => 'ألبوم كائناتي';

  @override
  String gamesAlbumHint(int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'كل $_temp0 في رحلتك تفقس لك بيضة، وكل درس تتقنه يفقس لك كائنًا.';
  }

  @override
  String gamesAlbumGuardian(String name) {
    return 'هذا ألبوم $name. هو وحده من يختار صديقه ويسمّي كائناته.';
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
  String get gamesNotYet => 'ليس بعد';

  @override
  String gamesAriaFound(String name, String island) {
    return '$name، من $island';
  }

  @override
  String gamesAriaShadow(String island) {
    return 'كائن لم يفقس بعد، من $island';
  }

  @override
  String get gamesVoice => 'اسمع صوته';

  @override
  String get gamesMakeBuddy => 'اجعله صديقك';

  @override
  String get gamesIsBuddyKids => 'هذا صديقك 💛';

  @override
  String get gamesIsBuddy => 'هذا صديقك';

  @override
  String gamesBuddyDone(String name) {
    return 'سيطير $name معك في الألعاب من الآن';
  }

  @override
  String get gamesShadowStory => 'لم يفقس بعد.';

  @override
  String gamesHowCommon(String island, int need) {
    String _temp0 = intl.Intl.pluralLogic(need, locale: localeName, other: '$need نجمة', few: '$need نجوم', two: 'نجمتين', one: 'نجمة');
    return 'كيف تفقسه: اجمع النجوم وأنت في $island: كل $_temp0 بيضة.';
  }

  @override
  String gamesHowRare(String island) {
    return 'كيف تفقسه: كائن نادر في $island: يفقس بعد كائناتها العادية.';
  }

  @override
  String gamesHowLegendary(String island) {
    return 'كيف تفقسه: أسطوري! آخر كائن في $island. اجمع كل ما قبله.';
  }

  @override
  String get gamesNameIt => 'سمِّه:';

  @override
  String gamesNamed(String name) {
    return 'اسمه الآن $name';
  }

  @override
  String get gamesFromStars => 'فقس من نجوم رحلتك';

  @override
  String get gamesFromMastery => 'فقس عندما أتقنت درسًا';

  @override
  String get gamesRace => 'سباق مع نفسك';

  @override
  String gamesBest(String s) {
    return 'أفضل وقت: $s';
  }

  @override
  String gamesSeconds(int s) {
    return '$sث';
  }

  @override
  String get gamesBeat => 'كسرت رقمك!';

  @override
  String get gamesSoClose => 'اقتربت!';

  @override
  String get gamesFirst => 'أول رقم لك!';

  @override
  String gamesOver(int correct, int total, String s) {
    return '$correct من $total صحيحة في $s';
  }

  @override
  String get gamesReviewAgain => 'راجع مرة أخرى';

  @override
  String gamesGuardianTitle(String name) {
    return 'ألعاب $name';
  }

  @override
  String get gamesPlayTime => 'وقت اللعب اليوم';

  @override
  String gamesTimeText(int used, int limit, int left) {
    String _temp0 = intl.Intl.pluralLogic(
      limit,
      locale: localeName,
      other: '$limit دقيقة',
      few: '$limit دقائق',
      two: 'دقيقتين',
      one: 'دقيقة',
    );
    String _temp1 = intl.Intl.pluralLogic(left, locale: localeName, other: '$left دقيقة', few: '$left دقائق', two: 'دقيقتان', one: 'دقيقة');
    return '$used من $_temp0 لعب اليوم. بقي $_temp1.';
  }

  @override
  String get gamesRefusedOff => 'الألعاب مغلقة في الأكاديمية الآن.';

  @override
  String get gamesRefusedPaused => 'أوقف ولي الأمر الألعاب الآن.';

  @override
  String get gamesRefusedNight => 'نامت الطيور. تعود الألعاب في الساعة 7 صباحًا.';

  @override
  String get gamesRefusedLimit => 'انتهت دقائق اللعب اليوم. نكمل غدًا!';

  @override
  String gamesTimeOpen(int used) {
    String _temp0 = intl.Intl.pluralLogic(used, locale: localeName, other: '$used دقيقة', few: '$used دقائق', two: 'دقيقتان', one: 'دقيقة');
    return '$_temp0 لعب اليوم، دون حد.';
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
  String get gamesNightLock => 'أغلق اللعب من 9 مساءً إلى 7 صباحًا';

  @override
  String get gamesEnabled => 'الألعاب مفعّلة';

  @override
  String get gamesLearned => 'ما رُوجع في اللعب';

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
    return 'رُوجع «$title» $rounds في $games';
  }

  @override
  String gamesHardest(String text) {
    return 'أصعب سؤال: «$text». يمكنكما مراجعته معًا.';
  }

  @override
  String gamesBestRound(int correct, int total, String game) {
    return 'أفضل جولة: $correct من $total صحيحة من أول مرة في «$game»';
  }

  @override
  String get gamesQuiet => 'لم يلعب هذا الأسبوع. الألعاب تدرّب على دروسه فقط، والمعلّم هو من يضع التدريبات على الدرس.';

  @override
  String gamesHatchedToday(String name) {
    return 'فقس «$name» اليوم';
  }

  @override
  String gamesHatchedStars(int found) {
    return 'من نجوم الرحلة · $found في الألبوم';
  }

  @override
  String gamesHatchedMastery(int found) {
    return 'من درس أتقنه · $found في الألبوم';
  }

  @override
  String get gamesCheer => 'هنّئه';

  @override
  String get gamesCheered => 'وصلته تهنئتك';

  @override
  String get gamesSafety => 'لا مشتريات داخل الألعاب، ولا إعلانات، ولا محادثة مع غرباء. كل كائن وكل نجمة تُكسب بالمذاكرة.';

  @override
  String get gamesTogether => 'العبوا معًا';

  @override
  String get gamesSaved => 'حُفظ.';

  @override
  String get gamesCardKids => 'الجزر تنتظرك 🏝️';

  @override
  String gamesCardLead(String what) {
    return 'تدرّب على دروسك بالألعاب: $what';
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
    return 'كيف يتقدّم $name';
  }

  @override
  String journeyNextStation(String title) {
    return 'القادمة: $title';
  }

  @override
  String get journeyLevels => 'المستويات';

  @override
  String journeyLessonsDone(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$total درسًا',
      few: '$total دروس',
      two: 'درسين',
      one: 'درس واحد',
    );
    return '$done من $_temp0';
  }

  @override
  String journeyAheadLocked(String title) {
    return 'تُفتح عندما تنهي $title';
  }

  @override
  String get journeyAheadFirst => 'تُفتح عندما تصل إليها';

  @override
  String journeyAriaDone(String title) {
    return '$title، أنهيتها';
  }

  @override
  String journeyAriaHere(String title) {
    return '$title، أنت هنا';
  }

  @override
  String journeyAriaAhead(String title) {
    return '$title، ما زالت مغلقة';
  }

  @override
  String get journeyStationDone => 'أنهيته';

  @override
  String get journeyStationReview => 'يحتاج إلى مراجعة';

  @override
  String get journeyStationHere => 'أنت هنا';

  @override
  String get journeyStationTodo => 'لم يأتِ بعد';

  @override
  String get journeyStationTodoNote => 'هذا الدرس لم يأتِ بعد. ستتدرّب عليه عندما تصل إليه.';

  @override
  String get journeyStationReviewNote => 'حان وقت مراجعة هذا الدرس: العب لعبة عليه.';

  @override
  String journeyPractised(String rounds, int p) {
    return 'تدرّبت عليه $rounds · $p% صحيح';
  }

  @override
  String get journeyPlayStation => 'العب على هذا الدرس';

  @override
  String get journeyNoGames => 'لا توجد لعبة على تدريبات هذا الدرس بعد.';

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
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n مهمة', few: '$n مهمات', two: 'مهمتان', one: 'مهمة');
    return '$_temp0';
  }

  @override
  String journeyOpenMissions(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n مهمة تنتظرك',
      few: '$n مهمات تنتظرك',
      two: 'مهمتان تنتظرانك',
      one: 'مهمة تنتظرك',
    );
    return '$_temp0';
  }

  @override
  String get journeyNoMissions => 'لا توجد مهمات الآن. استمتع بيومك!';

  @override
  String get journeyReview => 'راجع';

  @override
  String reviewsWaiting(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n مراجعة تنتظرك',
      few: '$n مراجعات تنتظرك',
      two: 'مراجعتان تنتظرانك',
      one: 'مراجعة تنتظرك',
    );
    return '$_temp0';
  }

  @override
  String get reviewsHint => 'كل مراجعة لعبة قصيرة';

  @override
  String hereAndNext(String here, String next) {
    return 'أنت هنا: $here · بعدها: $next';
  }

  @override
  String hereOnly(String here) {
    return 'أنت هنا: $here';
  }

  @override
  String levelNamed(int n, String title) {
    return 'المستوى $n: $title';
  }

  @override
  String get allLevels => 'كل المستويات';

  @override
  String get goalTitle => 'هدفك: أن تنهي البرنامج';

  @override
  String get goalDone => 'أنهيتها من البرنامج';

  @override
  String goalPace(String day) {
    return 'بهذه السرعة تنهيه يوم $day';
  }

  @override
  String get nowTitle => 'الآن';

  @override
  String nextIs(String title) {
    return 'القادمة: $title';
  }

  @override
  String get weekPlan => 'خطة الأسبوع';

  @override
  String get notHandedIn => 'لم يُسلَّم بعد';

  @override
  String get planNoDay => 'دون موعد';

  @override
  String lessonsToReview(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n درسًا يحتاج إلى مراجعة',
      few: '$n دروس تحتاج إلى مراجعة',
      two: 'درسان يحتاجان إلى مراجعة',
      one: 'درس يحتاج إلى مراجعة',
    );
    return '$_temp0';
  }

  @override
  String get reviewEachRound => 'جولة قصيرة لكل درس';

  @override
  String get wholeCurriculum => 'المنهج كله';

  @override
  String levelsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n مستوى', few: '$n مستويات', two: 'مستويان', one: 'مستوى واحد');
    return '$_temp0';
  }

  @override
  String curriculumLessons(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n درسًا', few: '$n دروس', two: 'درسان', one: 'درس واحد');
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
    return '$done من $_temp0 هذا الأسبوع';
  }

  @override
  String journeyChestHint(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n نجمة', few: '$n نجوم', two: 'نجمتين', one: 'نجمة');
    return 'احضر كل حصص الأسبوع وسلّم واجباته، وسيُفتح الصندوق بـ $_temp0';
  }

  @override
  String get journeyChestMissed => 'فاتك هذا الصندوق. هناك صندوق جديد هذا الأسبوع!';

  @override
  String journeyChestWaiting(String name) {
    return 'الصندوق جاهز ليُفتح من حساب $name';
  }

  @override
  String journeyChestGot(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '+$n نجمة في رحلتك!',
      few: '+$n نجوم في رحلتك!',
      two: '+نجمتان في رحلتك!',
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
      other: 'بقيت لك $n نجمة حتى $rank',
      few: 'بقيت لك $n نجوم حتى $rank',
      two: 'بقيت لك نجمتان حتى $rank',
      one: 'بقيت لك نجمة حتى $rank',
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
      two: 'نجمتان',
      one: 'نجمة واحدة',
      zero: 'لا توجد نجوم بعد',
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
      other: 'أخذت $n نجمة في آخر حصة!',
      few: 'أخذت $n نجوم في آخر حصة!',
      two: 'أخذت نجمتين في آخر حصة!',
      one: 'أخذت نجمة في آخر حصة',
      zero: 'لم تأخذ نجومًا في آخر حصة',
    );
    return '$_temp0';
  }

  @override
  String journeyLessonCanBring(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'تمنحك حتى $n نجمة',
      few: 'تمنحك حتى $n نجوم',
      two: 'تمنحك نجمتين',
      one: 'تمنحك نجمة',
    );
    return '$_temp0';
  }

  @override
  String get journeyEggReady => 'بيضتك جاهزة!';

  @override
  String get journeyEggHint => 'افقسها وانظر من سيخرج';

  @override
  String get journeyEggHatch => 'افقسها';

  @override
  String get journeyEggNone => 'لا توجد بيضة جاهزة الآن. استمر في جمع النجوم!';

  @override
  String get journeyNoCourses => 'لا توجد دورات جارية بعد.';

  @override
  String get journeyNoCurriculum => 'ليس لهذه الدورة منهج بعد. تظهر مهماتها هنا.';

  @override
  String get journeyCourses => 'مواد الدروس والتقارير';

  @override
  String get homeworkPracticeTitle => 'التدريب بالألعاب';

  @override
  String get homeworkPracticeNotYet => 'لم تُلعب جولته بعد.';

  @override
  String get homeworkByPlaying => 'يُسلَّم هذا الواجب عندما تلعب جولته.';

  @override
  String homeworkPracticeResult(int correct, int total, String stars, String game) {
    return 'التدريب: $correct من $total صحيحة · $stars ($game)';
  }

  @override
  String get homeworkNoGame => 'لا توجد لعبة لهذا الواجب الآن.';

  @override
  String get hifzTitle => 'خريطة الحفظ';

  @override
  String get hifzTitleKids => 'خريطة حفظي';

  @override
  String hifzTitleOf(String name) {
    return 'خريطة حفظ $name';
  }

  @override
  String get hifzNoText => 'نص القرآن غير موجود بعد، وستظهر الخريطة فور وصوله.';

  @override
  String hifzMemorized(int n, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n صفحة محفوظة من $total',
      few: '$n صفحات محفوظة من $total',
      two: 'صفحتان محفوظتان من $total',
      one: 'صفحة واحدة محفوظة من $total',
      zero: 'لا توجد صفحات محفوظة بعد من $total',
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
      zero: 'لم تحفظ صفحات بعد. هيا نبدأ!',
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
  String get hifzToday => 'ورد اليوم';

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
    return 'أُنجز $done من $_temp0';
  }

  @override
  String get hifzTodayDone => 'انتهى ورد اليوم. أحسنت!';

  @override
  String get hifzTodayNone => 'لا توجد صفحات عليها مراجعة اليوم.';

  @override
  String get hifzWirdDone => 'انتهى ورد اليوم!';

  @override
  String get hifzWirdDoneKids => 'أنهيت وردك! 🎉';

  @override
  String hifzOverdue(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'متأخرة $n يومًا',
      few: 'متأخرة $n أيام',
      two: 'متأخرة يومين',
      one: 'متأخرة يومًا',
    );
    return '$_temp0';
  }

  @override
  String get hifzReveal => 'اكشف الصفحة';

  @override
  String get hifzRevealHint => 'سمّع الصفحة من حفظك أولًا، ثم اكشفها وتأكّد.';

  @override
  String get hifzHowWent => 'كيف كان الحفظ؟';

  @override
  String get hifzGrade1 => 'نسيتها';

  @override
  String get hifzGrade2 => 'بصعوبة';

  @override
  String get hifzGrade3 => 'جيد';

  @override
  String get hifzGrade4 => 'سهلة';

  @override
  String hifzNextReview(String day) {
    return 'سُجّلت. ستعود إليك هذه الصفحة $day.';
  }

  @override
  String get hifzRecite => 'سمّع الصفحة';

  @override
  String get hifzReciteKids => 'سمّع صفحة';

  @override
  String get hifzReciteHint => 'سجّل الصفحة من حفظك، وسنصحّحها كلمة كلمة.';

  @override
  String get hifzSendRecitation => 'أرسل التسميع';

  @override
  String get hifzListening => 'نستمع إلى التسميع…';

  @override
  String get hifzListeningHint => 'ستظهر النتيجة هنا تلقائيًا.';

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
      other: 'بقيت $n صفحة اليوم',
      few: 'بقيت $n صفحات اليوم',
      two: 'بقيت صفحتان اليوم',
      one: 'بقيت صفحة واحدة اليوم',
    );
    return '$_temp0';
  }

  @override
  String get hifzCardDone => 'انتهى اليوم';

  @override
  String get hifzCardNone => 'لا توجد صفحات اليوم';

  @override
  String hifzCardKids(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بقيت لك $n صفحة تسمّعها',
      few: 'بقيت لك $n صفحات تسمّعها',
      two: 'بقيت لك صفحتان تسمّعهما',
      one: 'بقيت لك صفحة واحدة تسمّعها',
      zero: 'أنهيت وردك اليوم! ⭐',
    );
    return '$_temp0';
  }

  @override
  String get hifzTell => 'سجّل ما يحفظه';

  @override
  String get hifzClear => 'أزل صفحات';

  @override
  String get hifzClearHint => 'الصفحات التي تزيلها تعود جديدة على الخريطة، وتبقى مراجعاتها السابقة.';

  @override
  String get hifzClearConfirm => 'أتزيل هذه الصفحات من الخريطة؟';

  @override
  String get hifzParts => 'الأجزاء';

  @override
  String get hifzSurahs => 'السور';

  @override
  String get hifzSurahHint => 'اكتب اسم السورة أو رقمها';

  @override
  String get hifzHowWell => 'ما درجة حفظها؟';

  @override
  String hifzChosen(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n صفحة',
      few: '$n صفحات',
      two: 'صفحتان',
      one: 'صفحة واحدة',
      zero: 'لم تختر صفحات بعد',
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
      other: 'سُجّلت $n صفحة',
      few: 'سُجّلت $n صفحات',
      two: 'سُجّلت صفحتان',
      one: 'سُجّلت صفحة واحدة',
      zero: 'لم تُسجَّل صفحات',
    );
    return '$_temp0';
  }

  @override
  String hifzCleared(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'أُزيلت $n صفحة',
      few: 'أُزيلت $n صفحات',
      two: 'أُزيلت صفحتان',
      one: 'أُزيلت صفحة واحدة',
      zero: 'لم تُزَل صفحات',
    );
    return '$_temp0';
  }

  @override
  String get hifzRecent => 'آخر المراجعات';

  @override
  String get hifzRecentNone => 'لا توجد مراجعات في آخر أسبوعين.';

  @override
  String get mushafTitle => 'المصحف';

  @override
  String get mushafOpen => 'افتح المصحف';

  @override
  String get mushafNone => 'لا يوجد مصحف في هذه الحصة.';

  @override
  String get mushafPrevious => 'الصفحة السابقة';

  @override
  String get mushafNext => 'الصفحة التالية';

  @override
  String get mushafPageField => 'صفحة';

  @override
  String mushafPageOfTotal(int total) {
    return 'من $total';
  }

  @override
  String get mushafFollowing => 'تتقلّب الصفحة مع المعلّم.';

  @override
  String get mushafBackToTeacher => 'ارجع إلى صفحة المعلّم';

  @override
  String mushafTeacherOn(int page) {
    return 'المعلّم على صفحة $page';
  }

  @override
  String get mushafHintTeacher => 'انقر على أي كلمة لتعلّمها، ويرى الطالب الصفحة التي تفتحها.';

  @override
  String get mushafMarkingClosed => 'يُفتح تعليم الكلمات مع غرفة الحصة، ويُغلق عندما تسلّم التقرير.';

  @override
  String get mushafMarkWord => 'علّم الكلمة';

  @override
  String get mushafClearMark => 'امسح العلامة';

  @override
  String get mushafEarlier => 'عُلّمت في حصة سابقة';

  @override
  String mushafWordMarked(String word, String kind) {
    return '$word: $kind';
  }

  @override
  String mushafWordEarlier(String word, String kind, String times) {
    return '$word: $kind، عُلّمت في حصص سابقة $times';
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
  String get mushafBackToVideo => 'ارجع إلى الفيديو';

  @override
  String get mushafFailed => 'تعذّر الحفظ. حاول مرة أخرى.';

  @override
  String get mistakesTitle => 'دفتر الأخطاء';

  @override
  String get mistakesTitleKids => 'كلمات نراجعها';

  @override
  String get mistakesLead => 'الكلمات التي عُلّمت في الحصص خلال الأشهر الستة الأخيرة، لتراجعها.';

  @override
  String get mistakesLeadTeacher => 'الكلمات التي عُلّمت في حصص الطالب خلال الأشهر الستة الأخيرة.';

  @override
  String get mistakesLeadKids => 'هذه كلمات سنقولها معًا مرة أخرى حتى تصير سهلة.';

  @override
  String get mistakesEmpty => 'لا توجد كلمات معلَّمة في الأشهر الستة الأخيرة.';

  @override
  String get mistakesEmptyKids => 'لا توجد كلمات نراجعها الآن. أحسنت! ⭐';

  @override
  String mistakesWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n كلمة',
      few: '$n كلمات',
      two: 'كلمتان',
      one: 'كلمة واحدة',
      zero: 'لا توجد كلمات',
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
    return '$word: كلمة نراجعها';
  }

  @override
  String get quranTitle => 'القرآن';

  @override
  String get quranTitleKids => 'قرآني';

  @override
  String quranTitleOf(String name) {
    return 'القرآن مع $name';
  }

  @override
  String get quranCardLead => 'الخطة والورد والاختبارات والشهادات';

  @override
  String get quranCardKids => 'رحلتي مع القرآن ⭐';

  @override
  String get quranNone => 'لا توجد خطة قرآن بعد.';

  @override
  String get quranParts => 'الأجزاء الثلاثون';

  @override
  String get quranPartsKids => 'كل جزء تحفظه يضيء هنا ⭐';

  @override
  String quranJuzState(int n, String state) {
    return 'الجزء $n: $state';
  }

  @override
  String get quranJuzDone => 'محفوظ';

  @override
  String get quranJuzSome => 'بدأ حفظه';

  @override
  String get quranJuzNone => 'لم يبدأ';

  @override
  String get quranOpenHifz => 'خريطة الحفظ ودفتر الأخطاء';

  @override
  String get quranOpenHifzKids => 'خريطة حفظي وكلماتي';

  @override
  String get quranWirdKids => 'وردك ينتظرك اليوم 📖';

  @override
  String get quranPlanHeader => 'الخطة';

  @override
  String get quranPlanHeaderKids => 'طريقي';

  @override
  String quranAt(String title) {
    return 'الموضع الآن: $title';
  }

  @override
  String quranAtKids(String title) {
    return 'وصلت إلى $title ⭐';
  }

  @override
  String quranProgress(int percent) {
    return 'أُنجز $percent% من الخطة';
  }

  @override
  String get quranFinished => 'اكتملت الخطة';

  @override
  String get quranFinishedKids => 'أكملت الخطة! 🎉';

  @override
  String get quranWeek => 'ورد هذا الأسبوع';

  @override
  String get quranWeekKids => 'ما سأقرؤه هذا الأسبوع';

  @override
  String quranPages(String pages) {
    return 'الصفحات $pages';
  }

  @override
  String quranStops(String stops) {
    return 'يصل به إلى اختبار: $stops';
  }

  @override
  String get quranRecent => 'آخر الحصص';

  @override
  String get quranRecentNone => 'لم تُسجَّل نتائج بعد.';

  @override
  String get quranAdvanced => 'انتقل إلى الورد التالي';

  @override
  String get quranStays => 'يعيد الورد';

  @override
  String get quranExams => 'الاختبارات';

  @override
  String get quranExamsKids => 'اختباراتي';

  @override
  String get quranExamsNone => 'لا توجد اختبارات بعد.';

  @override
  String quranExamWith(String name) {
    return 'مع $name';
  }

  @override
  String quranExamRetakeOn(String day) {
    return 'الإعادة يوم $day';
  }

  @override
  String quranExamNotBefore(String day) {
    return 'لا يُحجز قبل $day';
  }

  @override
  String get quranCertificates => 'الشهادات';

  @override
  String get quranCertificatesKids => 'شهاداتي';

  @override
  String quranCertificateCode(String code) {
    return 'رمز التحقق: $code';
  }

  @override
  String get quranCertificateRevoked => 'أُلغيت';

  @override
  String get quranCertificateOpen => 'افتح الشهادة';

  @override
  String get quranLessonTitle => 'حصة القرآن';

  @override
  String get quranLessonOpen => 'ورد الحصة';

  @override
  String get quranLessonNoPlan => 'لا توجد خطة قرآن لهذه الحصة.';

  @override
  String get quranPartRecited => 'سمّعه الطالب';

  @override
  String get quranMistakes => 'الأخطاء';

  @override
  String get quranMistakesHint => 'إن لم تعدّ الأخطاء هنا، فتُحسب الكلمات المعلَّمة في المصحف.';

  @override
  String quranMistakeLess(String kind) {
    return 'أنقص $kind';
  }

  @override
  String quranMistakeMore(String kind) {
    return 'زد $kind';
  }

  @override
  String get quranGrade => 'التقدير';

  @override
  String get quranGradeAuto => 'من الأخطاء';

  @override
  String get quranNote => 'ملاحظة';

  @override
  String get quranNoteHint => 'لماذا اخترت تقديرًا غير تقدير الأخطاء؟';

  @override
  String get quranNoteNeeded => 'اكتب ملاحظة تشرح التقدير الذي اخترته.';

  @override
  String get quranChooseParts => 'اختر ما سمّعه الطالب.';

  @override
  String get quranSubmit => 'سجّل النتيجة';

  @override
  String get quranRecordedAdvanced => 'سُجّلت النتيجة، وينتقل الطالب إلى الورد التالي.';

  @override
  String get quranRecordedStays => 'سُجّلت النتيجة، ويعيد الطالب الورد نفسه.';

  @override
  String get quranAlreadyRecorded => 'سُجّلت نتيجة هذا الطالب في هذه الحصة.';

  @override
  String get quranStudent => 'الطالب';

  @override
  String get quranPace => 'الوتيرة';

  @override
  String quranPaceLine(String amount, String unit, String near, String far) {
    return 'الجديد $amount $unit · المراجعة القريبة $near · البعيدة $far';
  }

  @override
  String quranPaceNew(String unit) {
    return 'الجديد في كل حصة ($unit)';
  }

  @override
  String get quranPaceNear => 'المراجعة القريبة (عدد الأوراد)';

  @override
  String get quranPaceFar => 'المراجعة البعيدة (عدد الصفحات)';

  @override
  String get quranPaceSave => 'احفظ الوتيرة';

  @override
  String get quranPaceSaved => 'حُفظت الوتيرة.';

  @override
  String get quranPaceProgram => 'عُد إلى وتيرة البرنامج';

  @override
  String get quranExaminerTitle => 'اختبارات القرآن';

  @override
  String get quranExaminerBooked => 'المحجوزة';

  @override
  String get quranExaminerNone => 'لا توجد اختبارات محجوزة معك.';

  @override
  String get quranExaminerRecent => 'آخر النتائج';

  @override
  String get quranExamRecord => 'سجّل نتيجة الاختبار';

  @override
  String get quranExamFailHint => 'إن لم ينجح الطالب، فاكتب ما يراجعه واختر يوم الإعادة.';

  @override
  String get quranExamNote => 'كلمة للطالب';

  @override
  String get quranExamRetake => 'يوم الإعادة';

  @override
  String get quranExamRetakePick => 'اختر اليوم';

  @override
  String quranExamSaved(String status) {
    return 'سُجّلت النتيجة: $status';
  }

  @override
  String get phonePlaceholder => '05xxxxxxxx';

  @override
  String get trialEntryTitle => 'جديد معنا؟ احجز حصة تجريبية مجانية';

  @override
  String get trialEntryBody => 'اختر الموعد الذي يناسبك، دون حساب ودون دفع.';

  @override
  String get trialTitle => 'حصة تجريبية مجانية';

  @override
  String trialStepOf(int n, int total) {
    return 'الخطوة $n من $total';
  }

  @override
  String get trialNext => 'التالي';

  @override
  String get trialEdit => 'تعديل';

  @override
  String get trialAcademyTitle => 'في أي أكاديمية تريد الحصة؟';

  @override
  String get trialAcademyLead => 'اكتب رمز الأكاديمية لنعرض لك موادها ومواعيدها.';

  @override
  String get trialAcademyHint => 'تجده على صفحة الأكاديمية أو في رسالتها إليك.';

  @override
  String get trialAcademyOpening => 'نفتح صفحة الأكاديمية…';

  @override
  String get trialAcademyNotFound =>
      'لم نجد أكاديمية تستقبل طلبات الحصص التجريبية بهذا الرمز. تأكّد من الرمز مع الأكاديمية، أو اكتبه كما يصلك منها.';

  @override
  String get trialAcademyChange => 'أكاديمية أخرى';

  @override
  String get trialWhoTitle => 'لمن الحصة؟';

  @override
  String get trialWhoMe => 'لي';

  @override
  String get trialWhoMeHint => 'أنا من سيتعلّم.';

  @override
  String get trialWhoChild => 'لطفلي';

  @override
  String get trialWhoChildHint => 'أنا وليّ الأمر، وطفلي من سيتعلّم.';

  @override
  String get trialWhoMissing => 'اختر لمن الحصة.';

  @override
  String get trialYourName => 'اسمك';

  @override
  String get trialYourNameParent => 'اسمك أنت';

  @override
  String get trialYourNameHint => 'نخاطبك به في رسائلنا.';

  @override
  String get trialChildName => 'اسم طفلك';

  @override
  String get trialChildAge => 'كم عمر طفلك؟';

  @override
  String trialAgeYears(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n سنة', few: '$n سنوات', two: 'سنتان', one: 'سنة واحدة');
    return '$_temp0';
  }

  @override
  String get trialNameMissing => 'اكتب الاسم.';

  @override
  String get trialNameShort => 'اكتب الاسم كاملًا.';

  @override
  String get trialAgeMissing => 'اختر عمر طفلك.';

  @override
  String get trialSubjectTitle => 'اختر المادة';

  @override
  String get trialSubjectLead => 'ما الذي تريد أن تبدأ به؟ يمكنك أيضًا أن تطلب مساعدتنا في الاختيار.';

  @override
  String trialSubjectLevels(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n مستوى',
      few: '$n مستويات',
      two: 'مستويان',
      one: 'مستوى واحد',
      zero: '',
    );
    return '$_temp0';
  }

  @override
  String get trialHelpChoose => 'ساعدوني في الاختيار';

  @override
  String get trialHelpChooseHint => 'نتواصل معك لنختار معًا المادة والمستوى المناسبين.';

  @override
  String trialHelpChooseWith(String subject) {
    return 'نبدأ بحصة في $subject، ونختار معًا ما يناسب بعدها.';
  }

  @override
  String get trialNoSubjects => 'لم تعرض الأكاديمية موادها بعد. اطلب المساعدة في الاختيار، وسنتواصل معك.';

  @override
  String get trialWishesTitle => 'ما الأوقات التي تناسبكم؟';

  @override
  String get trialWishesLead => 'كلها اختيارية، وتساعدنا في اختيار المعلّم والموعد.';

  @override
  String get trialDays => 'الأيام المناسبة';

  @override
  String get trialDaysAny => 'أي يوم';

  @override
  String get trialDayPart => 'الوقت من اليوم';

  @override
  String get trialPartAny => 'أي وقت';

  @override
  String get trialPartMorning => 'صباحًا';

  @override
  String get trialPartAfternoon => 'بعد الظهر';

  @override
  String get trialPartEvening => 'مساءً';

  @override
  String get trialTeacher => 'المعلّم';

  @override
  String get trialTeacherAny => 'لا يهم';

  @override
  String get trialTeacherFemale => 'معلّمة';

  @override
  String get trialTeacherMale => 'معلّم';

  @override
  String get trialTimeTitle => 'اختر موعد الحصة';

  @override
  String trialTimeLead(String zone) {
    return 'المواعيد بتوقيت $zone، والأقرب أولًا.';
  }

  @override
  String trialTimesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n موعدًا', few: '$n مواعيد', two: 'موعدان', one: 'موعد واحد');
    return '$_temp0';
  }

  @override
  String get trialOnlySuiting => 'ما يناسبكم فقط';

  @override
  String get trialNoneSuit => 'لا مواعيد في الأيام والساعات التي اخترتها، فهذه كل المواعيد المتاحة.';

  @override
  String get trialLoadingTimes => 'نبحث عن المواعيد المتاحة…';

  @override
  String get trialNoTimesTitle => 'لا توجد مواعيد متاحة الآن';

  @override
  String trialNoTimesBody(String hours) {
    return 'لا يوجد معلّم متاح لهذه المادة خلال الأسبوعين القادمين. أرسل طلبك، وسنتفق معك على موعد خلال $hours.';
  }

  @override
  String get trialNoTimesGender => 'لا توجد مواعيد مع من اخترت الآن، وقد تجد موعدًا مع معلّم آخر.';

  @override
  String get trialAnyTeacher => 'أظهر مواعيد كل المعلّمين';

  @override
  String get trialSendInstead => 'أرسل الطلب دون موعد';

  @override
  String get trialNoTimeSuits => 'لا يناسبني أي موعد';

  @override
  String trialChosen(String label) {
    return 'اخترت: $label';
  }

  @override
  String get trialPickFirst => 'اختر موعدًا من القائمة.';

  @override
  String get trialBookThis => 'احجز هذا الموعد';

  @override
  String get trialContactTitle => 'رقم هاتفك';

  @override
  String get trialContactTitleRequest => 'كيف نتواصل معك؟';

  @override
  String get trialContactLeadCode => 'نرسل رمزًا من 6 أرقام على واتساب لنتأكّد من الرقم، ثم نحجز الموعد.';

  @override
  String trialContactLeadRequest(String hours) {
    return 'نرسل إليك تأكيد الطلب على واتساب، ونتواصل معك خلال $hours.';
  }

  @override
  String get trialPhoneLabel => 'رقم الهاتف (واتساب)';

  @override
  String get trialPhoneHint => 'يصلك عليه الرمز وتفاصيل الحصة.';

  @override
  String get trialPhoneHintRequest => 'يصلك عليه تأكيد الطلب، ونتواصل معك منه.';

  @override
  String get trialPhoneMissing => 'اكتب رقم هاتفك.';

  @override
  String get trialPhoneNotEmail => 'اكتب رقم هاتف، وضع البريد الإلكتروني في خانته تحته.';

  @override
  String get trialEmailLabel => 'البريد الإلكتروني';

  @override
  String get trialEmailHint => 'نرسل إليه نسخة من التأكيد.';

  @override
  String get trialSummary => 'طلبك';

  @override
  String trialSummaryFor(String name) {
    return 'الحصة لـ$name';
  }

  @override
  String get trialSummaryHelp => 'نختار المادة معًا';

  @override
  String get trialSummaryNoTime => 'نتفق على الموعد معك';

  @override
  String get trialSendRequest => 'أرسل الطلب';

  @override
  String get trialPickTimeAfterAll => 'أفضّل أن أختار الموعد بنفسي';

  @override
  String get trialCodeLead => 'نحجز الموعد فور كتابة الرمز.';

  @override
  String get trialBooking => 'نحجز موعدك…';

  @override
  String get trialCodeLocked => 'الرمز مقفل';

  @override
  String get trialCodeExpired => 'انتهى الرمز';

  @override
  String get trialChangeNumber => 'غيّر الرقم';

  @override
  String get trialRefusedTitle => 'لا يمكن حجز هذه الحصة من هنا';

  @override
  String get trialRefusedSend => 'أرسل الطلب وسنتواصل معك';

  @override
  String get trialWhatsapp => 'راسل الأكاديمية على واتساب';

  @override
  String get trialSignIn => 'ادخل بهذا الرقم';

  @override
  String get trialBookedTitle => 'حُجزت الحصة التجريبية';

  @override
  String trialBookedWith(String subject, String academy) {
    return '$subject مع $academy';
  }

  @override
  String trialBookedTeacher(String teacher) {
    return 'مع $teacher';
  }

  @override
  String get trialNextTitle => 'ما الذي يحدث الآن';

  @override
  String get trialBookedNext1 => 'تصلك رسالة على واتساب فيها الموعد ورابط دخول الحصة.';

  @override
  String get trialBookedNext2 => 'في الرسالة نفسها رابط لتغيير الموعد، ورابط إن لم تستطيعوا الحضور.';

  @override
  String get trialBookedNext3 => 'نذكّرك بالحصة قبلها بيوم.';

  @override
  String get trialBookedNext4 => 'ادخل إلى التطبيق بهذا الرقم متى شئت لترى الحصة وتدخلها.';

  @override
  String get trialAnotherChild => 'احجز لطفل آخر';

  @override
  String get trialAnotherTrial => 'احجز حصة تجريبية أخرى';

  @override
  String get trialLater => 'سأدخل لاحقًا';

  @override
  String get trialRequestedTitle => 'وصل طلبك';

  @override
  String trialRequestedBody(String hours) {
    return 'نتواصل معك على واتساب خلال $hours لنتفق على موعد الحصة.';
  }

  @override
  String trialRequestedHelp(String hours) {
    return 'نتواصل معك على واتساب خلال $hours لنختار معًا المادة والموعد.';
  }

  @override
  String get trialRequestedNext1 => 'تصلك الآن رسالة تأكيد على واتساب.';

  @override
  String get trialRequestedNext1Pick => 'تصلك الآن رسالة على واتساب فيها رابط تختار منه الموعد بنفسك متى شئت.';

  @override
  String trialRequestedNext2(String hours) {
    return 'يتواصل معك فريق الأكاديمية خلال $hours.';
  }

  @override
  String get trialRequestedNext3 => 'بعد الاتفاق يصلك الموعد ورابط دخول الحصة.';

  @override
  String get trialTooMany =>
      'طُلبت رموز كثيرة من هذا الجهاز أو لهذا الرقم، فأوقفنا الطلب قليلًا لحمايته. انتظر حتى ينتهي العدّاد، ثم حاول مرة أخرى.';

  @override
  String get trialDone => 'تم';

  @override
  String get trialsTitle => 'الحصص التجريبية';

  @override
  String get trialsKidsTitle => 'حصصي التجريبية';

  @override
  String get trialsRowHint => 'تقارير المعلّمين وما يقترحونه';

  @override
  String trialsLead(String name) {
    return 'حصص $name التجريبية المجانية وتقارير المعلّمين عنها.';
  }

  @override
  String get trialsLeadSelf => 'حصصك التجريبية المجانية وتقارير المعلّمين عنها.';

  @override
  String get trialsEmptyTitle => 'لا توجد حصص تجريبية';

  @override
  String get trialsEmptyBody => 'بعد كل حصة تجريبية يكتب المعلّم تقريرًا عمّا وجده وما يقترحه، وتجده هنا.';

  @override
  String get trialStateBooked => 'قادمة';

  @override
  String get trialStateReady => 'التقرير جاهز';

  @override
  String get trialStateWriting => 'التقرير قيد الكتابة';

  @override
  String get trialStateStudentAbsent => 'لم يحضرها الطالب';

  @override
  String get trialStateTeacherAbsent => 'تغيّب المعلّم';

  @override
  String get trialStateCancelledFamily => 'ألغيتموها';

  @override
  String get trialStateCancelledAcademy => 'ألغتها الأكاديمية';

  @override
  String get trialStateMissed => 'لم تتم';

  @override
  String get trialKindSecond => 'الحصة الثانية مع معلّم آخر';

  @override
  String get trialKindRebook => 'موعد جديد';

  @override
  String get trialNotCounted => 'لا تُحسب من حصتكم التجريبية المجانية.';

  @override
  String get trialWritingNote => 'يكتب المعلّم التقرير الآن، ويصلكم إشعار حين يجهز.';

  @override
  String get trialBookedNote => 'تجدونها في «الحصص» وتدخلونها من هناك في موعدها.';

  @override
  String get trialReadReport => 'اقرأ التقرير';

  @override
  String get trialBookAgain => 'احجز موعدًا آخر';

  @override
  String get trialTryAnother => 'جرّب معلّمًا آخر مجانًا';

  @override
  String get trialReportTitle => 'تقرير الحصة التجريبية';

  @override
  String get trialReportKidsTitle => 'ماذا قال معلّمك؟ ⭐';

  @override
  String get trialReportSecond => 'الحصة التجريبية الثانية';

  @override
  String trialReportFor(String subject, String name) {
    return '$subject · $name';
  }

  @override
  String get trialReportTeacher => 'المعلّم';

  @override
  String get trialReportLevel => 'المستوى الذي وجده المعلّم';

  @override
  String get trialReportLevelKids => 'مستواك الآن 🌱';

  @override
  String get trialReportStrengths => 'نقاط القوة';

  @override
  String get trialReportStrengthsKids => 'ما تُحسنه 💪';

  @override
  String get trialReportToWorkOn => 'ما نعمل عليه';

  @override
  String get trialReportToWorkOnKids => 'ما سنتعلّمه معًا 🎯';

  @override
  String get trialReportNote => 'كلمة المعلّم';

  @override
  String get trialReportNoteKids => 'رسالة من معلّمك 💌';

  @override
  String get trialReportNotReadyTitle => 'التقرير لم يجهز بعد';

  @override
  String get trialReportNotReadyBody =>
      'يكتب المعلّم تقرير الحصة التجريبية، ويصلكم إشعار حين يجهز. تجدون حصصكم التجريبية كلها في «الحصص التجريبية».';

  @override
  String get trialReportAllTrials => 'كل الحصص التجريبية';

  @override
  String get trialPlanTitle => 'ما يقترحه المعلّم';

  @override
  String trialPlanPerWeek(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n حصة', few: '$n حصص', two: 'حصتان', one: 'حصة واحدة');
    return '$_temp0 في الأسبوع';
  }

  @override
  String trialPlanLength(String minutes) {
    return 'الحصة $minutes';
  }

  @override
  String get trialPlanPrivate => 'حصص فردية';

  @override
  String get trialPlanGroup => 'حصص جماعية';

  @override
  String trialPlanBlock(String lessons, String days) {
    return '$lessons خلال $days';
  }

  @override
  String trialDaysCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n يومًا', few: '$n أيام', two: 'يومين', one: 'يوم واحد');
    return '$_temp0';
  }

  @override
  String trialPlanOnlyPerWeek(String perWeek) {
    return 'يقترح المعلّم $perWeek.';
  }

  @override
  String get trialPlanWithdrawn => 'لم تعد الأكاديمية تقدّم الباقة التي اقترحها المعلّم، وسيساعدكم فريقها في اختيار باقة مناسبة.';

  @override
  String get trialPrice => 'سعر الباقة';

  @override
  String trialPriceFor(String lessons) {
    return 'لكل $lessons';
  }

  @override
  String trialPriceRegion(String region) {
    return 'بأسعار $region';
  }

  @override
  String get trialNoPrice => 'يخبركم فريق الأكاديمية بالسعر حين تتواصلون معه.';

  @override
  String trialOfferTitle(String percent) {
    return 'خصم $percent٪ لكم';
  }

  @override
  String trialOfferLead(String label) {
    return 'على أول دفعة من أي باقة، حتى $label.';
  }

  @override
  String get trialOfferLeft => 'يبقى على انتهاء العرض';

  @override
  String get trialOfferDays => 'أيام';

  @override
  String get trialOfferHours => 'ساعات';

  @override
  String get trialOfferMinutes => 'دقائق';

  @override
  String get trialOfferSeconds => 'ثوانٍ';

  @override
  String trialOfferLeftSpoken(String days, String hours, String minutes) {
    return 'يبقى $days و$hours و$minutes';
  }

  @override
  String get trialOfferWith => 'بعد الخصم';

  @override
  String trialOfferInstead(String price) {
    return 'بدلًا من $price';
  }

  @override
  String trialOfferSaving(String amount) {
    return 'توفّرون $amount';
  }

  @override
  String get trialOfferNoPrice => 'يُطبَّق الخصم على أول فاتورة من أي باقة تختارونها.';

  @override
  String get trialOfferFamilyBigger => 'خصم الإخوة لديكم أكبر من هذا العرض، فيُطبَّق هو بدلًا منه، فالخصمان لا يجتمعان.';

  @override
  String get trialOfferEnded => 'انتهى وقت العرض، وهذا سعر الباقة اليوم.';

  @override
  String get trialOfferEndedNoPrice => 'انتهى وقت العرض.';

  @override
  String get trialOfferUsed => 'استخدمتم العرض على أول فاتورة.';

  @override
  String trialStarted(String subject) {
    return 'بدأت الدراسة في $subject. نتمنّى لكم التوفيق.';
  }

  @override
  String trialStartedKids(String subject) {
    return 'بدأت دروسك في $subject! 🎉';
  }

  @override
  String get trialCourseWaiting => 'الدورة بانتظار الدفع، وتجدون الفاتورة في «المدفوعات».';

  @override
  String get trialCourseActive => 'الدورة جارية، وتجدون حصصها في «الحصص».';

  @override
  String get trialCoursePaused => 'الدورة متوقفة مؤقتًا.';

  @override
  String get trialOpenMoney => 'افتح المدفوعات';

  @override
  String get trialStartPlan => 'ابدأ بهذه الباقة';

  @override
  String get trialStartAsk => 'اختاروا باقة وابدؤوا';

  @override
  String get trialSecondTitle => 'لم يناسبكم المعلّم؟';

  @override
  String trialSecondBody(String label) {
    return 'جرّبوا حصة تجريبية ثانية مجانية مع معلّم آخر، حتى $label.';
  }

  @override
  String get trialSecondBodyNoDate => 'جرّبوا حصة تجريبية ثانية مجانية مع معلّم آخر.';

  @override
  String get trialSecondPick => 'اختر موعدًا مع معلّم آخر';

  @override
  String get trialRebookTitle => 'لم تتم الحصة؟ احجزوا موعدًا آخر';

  @override
  String get trialRebookBody => 'اختاروا موعدًا جديدًا للحصة التجريبية المجانية.';

  @override
  String get trialNextPickSecond => 'اختر موعد الحصة الثانية';

  @override
  String get trialNextPickRebook => 'اختر موعدًا جديدًا';

  @override
  String trialNextLeadSecond(String teacher, String zone) {
    return 'مع معلّم آخر غير $teacher. المواعيد بتوقيت $zone، والأقرب أولًا.';
  }

  @override
  String get trialNextNoTimes => 'لا توجد مواعيد متاحة خلال الأسبوعين القادمين. اكتبوا إلينا في «المساعدة» وسنرتّب لكم موعدًا.';

  @override
  String get trialNextOpenHelp => 'اكتب إلى الأكاديمية';

  @override
  String get trialNextBookedTitle => 'حُجزت الحصة';

  @override
  String get trialNextBookedSecond => 'حُجزت الحصة الثانية مع معلّم آخر';

  @override
  String get trialNextBooked1 => 'يصلكم إشعار ورسالة فيهما الموعد ورابط دخول الحصة.';

  @override
  String get trialNextBooked2 => 'تجدون الحصة في «الحصص»، وتدخلونها من هناك في موعدها.';

  @override
  String get trialNextBooked3 => 'بعد الحصة يكتب المعلّم تقريرًا جديدًا، وتجدونه هنا.';

  @override
  String get trialNextTimeTaken => 'حُجز هذا الموعد للتو. اختر موعدًا آخر من المواعيد المتاحة الآن.';

  @override
  String get trialOpenLessons => 'افتح الحصص';

  @override
  String get trialHomeReady => 'تقرير الحصة التجريبية جاهز';

  @override
  String trialHomeReadyBody(String subject, String teacher) {
    return '$subject مع $teacher: ما وجده المعلّم وما يقترحه.';
  }

  @override
  String trialHomeRebook(String subject) {
    return 'لم تتم الحصة التجريبية في $subject';
  }

  @override
  String get trialHomeRebookBody => 'احجزوا موعدًا آخر متى شئتم.';

  @override
  String trialHomeSecond(String subject) {
    return 'جرّبوا معلّمًا آخر في $subject';
  }

  @override
  String get startNewCourse => 'ابدأ دورة جديدة';

  @override
  String get startNewCourseHint => 'اختاروا الباقة والمعلّم والمواعيد، وادفعوا من التطبيق.';

  @override
  String get startTitle => 'بدء دورة';

  @override
  String startStepOf(int n, int total) {
    return 'الخطوة $n من $total';
  }

  @override
  String get startContinue => 'متابعة';

  @override
  String get startChange => 'تغيير';

  @override
  String get startOffTitle => 'تسجّل الأكاديمية الدورات بنفسها';

  @override
  String get startOffHelp => 'راسلوا الأكاديمية';

  @override
  String get startWhoTitle => 'لمن الدورة؟';

  @override
  String get startWhoLead => 'اختاروا من سيدرس؛ لكل متعلّم دورته وفاتورته.';

  @override
  String get startWhoSelf => 'لي أنا';

  @override
  String get startWhoConsent => 'سنطلب موافقتكم على حسابه قبل البدء';

  @override
  String startSubjectTitle(String name) {
    return 'ماذا سيدرس $name؟';
  }

  @override
  String get startSubjectTitleSelf => 'ماذا تريد أن تدرس؟';

  @override
  String get startSubjectLead => 'المواد التي فيها معلّمون يستقبلون طلابًا جددًا الآن.';

  @override
  String get startSubjectNone => 'لا توجد مادة متاحة للبدء الآن. راسلوا الأكاديمية لتساعدكم.';

  @override
  String get startCourseWaiting => 'بانتظار الدفع';

  @override
  String get startCourseActive => 'جارية';

  @override
  String get startCoursePaused => 'متوقفة مؤقتًا';

  @override
  String get startOpenLessons => 'افتح الحصص';

  @override
  String get startCancelPending => 'ألغِ هذه الدورة';

  @override
  String get startPlanTitle => 'اختاروا الباقة';

  @override
  String startPlanLead(String region) {
    return 'الأسعار بعملة منطقتكم ($region).';
  }

  @override
  String get startPlanLeadAny => 'اختاروا ما يناسب وقتكم وميزانيتكم.';

  @override
  String get startPlanNone => 'لا توجد باقة بسعر في منطقتكم بعد. راسلوا الأكاديمية.';

  @override
  String get startPlanPrivateLine => 'حصص فردية، بمواعيد تختارونها';

  @override
  String get startPlanGroupLine => 'مقعد في مجموعة صغيرة، بمواعيد ثابتة';

  @override
  String get startPlanShared => 'باقة مشتركة: رصيد واحد لكل الدورات عليها.';

  @override
  String get startRecommended => 'اقترحها المعلّم';

  @override
  String startPerLesson(String price) {
    return '$price للحصة';
  }

  @override
  String get startBlockPrice => 'سعر الباقة';

  @override
  String get startFirstInvoice => 'أول فاتورة';

  @override
  String startOfferApplied(String percent) {
    return 'عرض الحصة التجريبية ($percent٪)';
  }

  @override
  String startOfferUntil(String percent, String when) {
    return 'يُطبَّق عرض الحصة التجريبية ($percent٪) على أول فاتورة حتى $when.';
  }

  @override
  String startRenews(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'حين تبقى $n حصة',
      few: 'حين تبقى $n حصص',
      two: 'حين تبقى حصتان',
      one: 'حين تبقى حصة واحدة',
    );
    return 'تصل فاتورة الباقة التالية $_temp0، ويمكنكم التوقف متى شئتم.';
  }

  @override
  String get startTeacherTitle => 'اختاروا المعلّم';

  @override
  String startTeacherLead(String subject) {
    return 'يستقبل كل هؤلاء طلابًا جددًا في $subject.';
  }

  @override
  String get startTrialTeacher => 'معلّم الحصة التجريبية';

  @override
  String get startTrialTeacherGone => 'لم يعد معلّم الحصة التجريبية يستقبل طلابًا جددًا، فاختاروا معلّمًا آخر.';

  @override
  String get startTeacherNone => 'لا يوجد معلّم يستقبل طلابًا جددًا في هذه المادة الآن. جرّبوا باقة جماعية، أو راسلوا الأكاديمية.';

  @override
  String get startGroupTitle => 'اختاروا المجموعة';

  @override
  String startGroupLead(String name) {
    return 'مجموعات مفتوحة فيها مقعد لـ$name، بمواعيدها الثابتة.';
  }

  @override
  String get startGroupNone => 'لا توجد مجموعة مفتوحة لهذه الباقة الآن. اختاروا باقة فردية، أو راسلوا الأكاديمية.';

  @override
  String startGroupWith(String name) {
    return 'مع $name';
  }

  @override
  String get startGroupForming => 'قيد التكوين';

  @override
  String get startGroupRunning => 'بدأت حصصها';

  @override
  String startGroupToStart(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'تبدأ حين ينضم $n طالبًا آخر',
      few: 'تبدأ حين ينضم $n طلاب آخرين',
      two: 'تبدأ حين ينضم طالبان آخران',
      one: 'تبدأ حين ينضم طالب واحد آخر',
      zero: 'تبدأ بانضمامكم',
    );
    return '$_temp0';
  }

  @override
  String startGroupFree(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n مقعدًا متاحًا',
      few: '$n مقاعد متاحة',
      two: 'مقعدان متاحان',
      one: 'مقعد واحد متاح',
    );
    return '$_temp0';
  }

  @override
  String startGroupNext(String when) {
    return 'الحصة القادمة: $when';
  }

  @override
  String startGroupHeld(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'عُقدت $n حصة قبل انضمامكم',
      few: 'عُقدت $n حصص قبل انضمامكم',
      two: 'عُقدت حصتان قبل انضمامكم',
      one: 'عُقدت حصة واحدة قبل انضمامكم',
    );
    return '$_temp0، وتعوّض الأكاديمية ما فات بالطريقة التي تختارها.';
  }

  @override
  String startTimesTitle(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'اختاروا $n موعدًا أسبوعيًا',
      few: 'اختاروا $n مواعيد أسبوعية',
      two: 'اختاروا موعدين أسبوعيين',
      one: 'اختاروا موعدًا أسبوعيًا',
    );
    return '$_temp0';
  }

  @override
  String get startTimesTitleShared => 'اختاروا مواعيدكم الأسبوعية';

  @override
  String get startTimesLead => 'كل موعد في يوم مختلف، ويتكرر كل أسبوع. المواعيد بتوقيتكم، ولكل موعد أول حصة فيه.';

  @override
  String get startTimesSuggested => 'اخترنا لكم مواعيد مقترحة، ويمكنكم تغييرها.';

  @override
  String startTimesCount(int picked, int total) {
    return 'اخترتم $picked من $total';
  }

  @override
  String get startTimesDayFull => 'اخترتم كل المواعيد. ألغوا موعدًا أولًا لتختاروا يومًا آخر.';

  @override
  String startTimesFirst(String day) {
    return 'أول حصة $day';
  }

  @override
  String get startSkippedTitle => 'أيام بلا حصة';

  @override
  String get startSkippedNote => 'لا تُحجز حصة في هذه الأيام، وتستمر المواعيد بعدها كالمعتاد.';

  @override
  String startTimesFrom(String day) {
    return 'أقرب بداية $day، ليبقى لكم وقت للدفع.';
  }

  @override
  String get startTimesNone => 'لا مواعيد متاحة مع هذا المعلّم في الأسابيع الأربعة القادمة.';

  @override
  String get startOtherTeacher => 'اختاروا معلّمًا آخر';

  @override
  String get startTimesTooFew =>
      'لا تكفي الأيام المتاحة لدى هذا المعلّم لهذه الباقة، فلكل حصة في الأسبوع يوم مختلف. اختاروا معلّمًا آخر، أو باقة بحصص أقل في الأسبوع.';

  @override
  String get startOtherPlan => 'اختاروا باقة أخرى';

  @override
  String get startReviewTitle => 'راجعوا قبل التأكيد';

  @override
  String get startReviewLead => 'لن يُسحب أي مبلغ الآن: تصدر الفاتورة، وتدفعونها في الخطوة التالية.';

  @override
  String get startReviewLearner => 'المتعلّم';

  @override
  String get startReviewSubject => 'المادة';

  @override
  String get startReviewPlan => 'الباقة';

  @override
  String get startReviewTeacher => 'المعلّم';

  @override
  String get startReviewGroup => 'المجموعة';

  @override
  String get startReviewTimes => 'المواعيد الأسبوعية';

  @override
  String get startReviewFirst => 'أول حصة';

  @override
  String get startReviewMoney => 'ما ستدفعونه';

  @override
  String get startReviewTotal => 'الإجمالي قبل الضريبة';

  @override
  String get startReviewVat => 'تظهر ضريبة القيمة المضافة، إن وُجدت، في الفاتورة قبل الدفع.';

  @override
  String startReviewPayBy(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'بـ$n ساعة', few: 'بـ$n ساعات', two: 'بساعتين', one: 'بساعة');
    return 'ادفعوا قبل أول حصة $_temp0 على الأقل؛ فالحصة التي لم تُدفع قبل موعدها بهذه المدة تُلغى.';
  }

  @override
  String get startReviewPayAny => 'ادفعوا قبل أول حصة لتثبيت الحصص.';

  @override
  String get startReviewCancel => 'ويمكنكم إلغاء الدورة قبل الدفع دون أي رسوم.';

  @override
  String get startConfirm => 'أكّدوا وانتقلوا إلى الدفع';

  @override
  String get startConfirmAgain => 'أكّدوا بالسعر الجديد';

  @override
  String startConsentTitle(String name) {
    return 'موافقتكم على حساب $name';
  }

  @override
  String get startConsentAgree => 'أوافق، وتابعوا البدء';

  @override
  String get startBlockedTitle => 'لا يمكن البدء الآن';

  @override
  String get startBlockedOver => 'ابدؤوا من جديد';

  @override
  String get startCancelTitle => 'أتلغون هذه الدورة؟';

  @override
  String get startCancelBody => 'تُلغى حصصها وفاتورتها دون أي رسوم، ويمكنكم البدء من جديد متى شئتم.';

  @override
  String get startCancelYes => 'نعم، ألغوها';

  @override
  String get startCancelKeep => 'أبقوها';

  @override
  String get payTitle => 'دفع الفاتورة';

  @override
  String get payCreatedTitle => 'جُهّزت الدورة، وبقيت خطوة واحدة';

  @override
  String payCreatedLead(String subject, String name) {
    return 'ادفعوا الفاتورة لتثبيت حصص $subject لـ$name.';
  }

  @override
  String payInvoiceNumber(String number) {
    return 'فاتورة $number';
  }

  @override
  String get paySubtotal => 'المجموع قبل الضريبة';

  @override
  String payVat(String rate) {
    return 'ضريبة القيمة المضافة $rate٪';
  }

  @override
  String payVatIncluded(String rate) {
    return 'منها ضريبة القيمة المضافة $rate٪';
  }

  @override
  String get payTotal => 'الإجمالي';

  @override
  String get payPaidSoFar => 'المدفوع';

  @override
  String get payLeft => 'المتبقي';

  @override
  String get payCouponTitle => 'لديكم كوبون خصم؟';

  @override
  String get payCouponField => 'رمز الكوبون';

  @override
  String get payCouponApply => 'طبّق';

  @override
  String get payCouponRemove => 'أزل';

  @override
  String payCouponOn(String code) {
    return 'الكوبون $code مطبّق';
  }

  @override
  String get payOfferStays => 'عرض الحصة التجريبية مطبّق، ويبقى على الفاتورة.';

  @override
  String get payNoGateway => 'لا تقبل الأكاديمية الدفع الإلكتروني لهذه الفاتورة. حوّلوا المبلغ وأرسلوا الإيصال من «المدفوعات».';

  @override
  String get payTransferInstead => 'دفعتم بالتحويل؟ أرسلوا الإيصال';

  @override
  String get payWaitingTitle => 'نتأكد من الدفع مع البوابة';

  @override
  String get payCheckNow => 'تحقّق الآن';

  @override
  String get payTryAgain => 'حاولوا الدفع مرة أخرى';

  @override
  String get payReadInvoice => 'اقرؤوا الفاتورة';

  @override
  String get payVoid => 'أُلغيت هذه الفاتورة، فلا شيء عليكم لدفعه.';

  @override
  String get payCancelCourse => 'ألغوا الدورة قبل الدفع';

  @override
  String get paidTitle => 'تم الدفع، وبدأت الدورة!';

  @override
  String paidLead(String name, String subject) {
    return 'أهلًا بـ$name في $subject. هذا ما ينتظركم:';
  }

  @override
  String get paidLeadPlain => 'وصل الدفع، وتجدون الحصص في «الحصص».';

  @override
  String get paidTeacher => 'المعلّم';

  @override
  String get paidTimes => 'المواعيد الأسبوعية';

  @override
  String get paidFirst => 'أول حصة';

  @override
  String get paidFirstLater => 'حين تكتمل المجموعة، ونرسل إليكم موعدها ورابطها.';

  @override
  String get paidJoin => 'كيف تنضمون';

  @override
  String get paidJoinHow => 'افتحوا الحصة من «الحصص» قبل موعدها ببضع دقائق، واضغطوا «انضم».';

  @override
  String get packageTitle => 'انضمت الدورة إلى باقتكم المشتركة';

  @override
  String get paidPlainTitle => 'دُفعت هذه الفاتورة';

  @override
  String get paidJustTitle => 'وصل الدفع، شكرًا لكم!';

  @override
  String get paidPlainLead => 'لا شيء عليكم فيها. تجدون نسختها للطباعة أدناه، والحصص في «الحصص».';

  @override
  String payDueOn(String day) {
    return 'ادفعوا قبل $day';
  }

  @override
  String get payOpenPayments => 'المدفوعات';

  @override
  String get balanceTitle => 'رصيد الدورة';

  @override
  String get balanceNone => 'لم تبقَ حصص';

  @override
  String balanceLeftOf(String total) {
    return 'متبقية من باقة $total';
  }

  @override
  String get balanceLeftPlain => 'متبقية في الرصيد';

  @override
  String balanceMakeup(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'و$n حصة تعويضية أيضًا',
      few: 'و$n حصص تعويضية أيضًا',
      two: 'وحصتان تعويضيتان أيضًا',
      one: 'وحصة تعويضية واحدة أيضًا',
    );
    return '$_temp0';
  }

  @override
  String balancePerWeek(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n حصة في الأسبوع',
      few: '$n حصص في الأسبوع',
      two: 'حصتان في الأسبوع',
      one: 'حصة واحدة في الأسبوع',
    );
    return '$_temp0';
  }

  @override
  String balancePerLesson(String minutes) {
    return 'الحصة $minutes';
  }

  @override
  String get balanceGroup => 'حصص جماعية';

  @override
  String get balanceShared => 'باقة مشتركة بين المواد';

  @override
  String get balanceMarkHint => 'العلامة على الشريط: هنا تصدر الفاتورة التالية';

  @override
  String balanceBarLabel(String left, String total) {
    return '$left من $total، والفاتورة التالية عند العلامة';
  }

  @override
  String get balanceWaitingTitle => 'فاتورة الباقة التالية تنتظر الدفع';

  @override
  String balanceWaitingLine(String number, String amount) {
    return 'فاتورة $number · المتبقي $amount';
  }

  @override
  String get balanceNotPayer => 'تصل هذه الفاتورة إلى من يدفع للدورة في أسرتكم، ويدفعها من حسابه.';

  @override
  String get balanceNextTitle => 'الفاتورة التالية';

  @override
  String balanceNextAbout(String amount) {
    return 'نحو $amount';
  }

  @override
  String balanceNextWhen(int n, String day) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'تصدر عندما تبقى $n حصة، في $day تقريبًا.',
      few: 'تصدر عندما تبقى $n حصص، في $day تقريبًا.',
      two: 'تصدر عندما تبقى حصتان، في $day تقريبًا.',
      one: 'تصدر عندما تبقى حصة واحدة، في $day تقريبًا.',
      zero: 'تصدر عندما تنتهي الحصص المدفوعة، في $day تقريبًا.',
    );
    return '$_temp0';
  }

  @override
  String balanceNextAfter(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بعد $n حصة',
      few: 'بعد $n حصص',
      two: 'بعد حصتين',
      one: 'بعد حصة واحدة',
    );
    return '$_temp0';
  }

  @override
  String get balanceNextNow => 'بلغ الرصيد حدّ التجديد، وتصدر فاتورة الباقة التالية قريبًا.';

  @override
  String get balanceEstimate => 'تقدير بحسب مواعيدكم؛ إلغاء حصة أو نقلها يغيّره.';

  @override
  String get balanceOpen => 'التفاصيل';

  @override
  String get balanceHowTitle => 'كيف يعمل الرصيد؟';

  @override
  String get balanceHowTaken => 'تُخصم كل حصة من الرصيد حين تتم، والحصص التعويضية تُحسب وحدها.';

  @override
  String balanceHowMark(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'عندما تبقى $n حصة تصدر فاتورة الباقة التالية، لتستمر الحصص دون انقطاع.',
      few: 'عندما تبقى $n حصص تصدر فاتورة الباقة التالية، لتستمر الحصص دون انقطاع.',
      two: 'عندما تبقى حصتان تصدر فاتورة الباقة التالية، لتستمر الحصص دون انقطاع.',
      one: 'عندما تبقى حصة واحدة تصدر فاتورة الباقة التالية، لتستمر الحصص دون انقطاع.',
      zero: 'تصدر فاتورة الباقة التالية حين تنتهي الحصص المدفوعة، لتستمر الحصص دون انقطاع.',
    );
    return '$_temp0';
  }

  @override
  String get balanceHowExpiry => 'للحصص غير المحجوزة صلاحية تنتهي في موعدها، أما الحصص المحجوزة فلا تضيع.';

  @override
  String get balanceKidsTitle => 'حصصي الباقية';

  @override
  String balanceKidsLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'بقيت لك $n حصة',
      few: 'بقيت لك $n حصص',
      two: 'بقيت لك حصتان',
      one: 'بقيت لك حصة واحدة',
      zero: 'لا توجد حصص الآن',
    );
    return '$_temp0';
  }

  @override
  String get balanceFailed => 'تعذّر تحميل رصيد هذه الدورة الآن.';

  @override
  String get addChildTitle => 'إضافة طفل';

  @override
  String get addChildHint => 'أضيفوا ابنًا أو ابنة إلى حسابكم، ثم اختاروا الدورة.';

  @override
  String get addChildAboutTitle => 'عن طفلكم';

  @override
  String get addChildAboutLead => 'نحتاج إلى الاسم والعمر لنعرض الدورات والمعلّمين المناسبين لسنّه.';

  @override
  String get addChildName => 'اسم الطفل';

  @override
  String get addChildNameHint => 'الاسم الأول واسم العائلة';

  @override
  String get addChildNameShort => 'اكتبوا الاسم بحرفين على الأقل.';

  @override
  String get addChildBirth => 'تاريخ الميلاد';

  @override
  String get addChildBirthExact => 'التاريخ';

  @override
  String get addChildBirthYearOnly => 'السنة فقط';

  @override
  String get addChildPickDate => 'اختاروا تاريخ الميلاد';

  @override
  String get addChildPickYear => 'اختاروا سنة الميلاد';

  @override
  String get addChildBirthNeeded => 'اختاروا تاريخ الميلاد أو سنته.';

  @override
  String get addChildYearNote => 'إن اخترتم السنة وحدها، نعدّ الميلاد في آخر يوم منها، حرصًا على حماية الصغار.';

  @override
  String get addChildGender => 'النوع';

  @override
  String get addChildGirl => 'بنت';

  @override
  String get addChildBoy => 'ولد';

  @override
  String get addChildNext => 'التالي: الموافقة';

  @override
  String get addChildConsentTitle => 'موافقتكم بصفتكم وليّ الأمر';

  @override
  String get addChildConsentLead => 'اقرؤوا ما نحتفظ به ومن يطّلع عليه، ثم ضعوا علامة الموافقة.';

  @override
  String get addChildAgreeNeeded => 'ضعوا علامة الموافقة أولًا.';

  @override
  String addChildSubmit(String name) {
    return 'أضيفوا $name وتابعوا';
  }

  @override
  String get addChildSomeone => 'طفلكم';

  @override
  String get addChildEdit => 'عدّلوا البيانات';

  @override
  String get startAddChild => 'طفل آخر؟ أضيفوه';

  @override
  String get startAddChildHint => 'يُضاف إلى حسابكم، ثم تختارون له الدورة مباشرة.';
}
