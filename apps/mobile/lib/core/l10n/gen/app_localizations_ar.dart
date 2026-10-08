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
  String get send => 'ابعت';

  @override
  String get optional => '(اختياري)';

  @override
  String get noticeSignedOut => 'خرجت من الحساب.';

  @override
  String get noticeExpired => 'الدخول انتهى على الحساب ده. ادخل تاني.';

  @override
  String minutes(int n) {
    return '$n دقيقة';
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
  String get welcomeTitle => 'أهلًا بيك';

  @override
  String get welcomeLead => 'اكتب رقم موبايلك أو إيميلك، وهنبعتلك كود. بعدها هتلاقي حساباتك في كل الأكاديميات اللي انت فيها.';

  @override
  String get byPhone => 'رقم الموبايل';

  @override
  String get byEmail => 'الإيميل';

  @override
  String get countryCode => 'كود الدولة';

  @override
  String get phoneHint => 'الكود هيوصلك على واتساب. لو ماعندكش واتساب، هتقدر تطلبه رسالة SMS.';

  @override
  String get emailHint => 'الكود هيوصلك على الإيميل ده.';

  @override
  String get sendCode => 'ابعتلي كود';

  @override
  String get havePassword => 'عندك كلمة سر؟ ادخل بيها';

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
  String get accountsTitle => 'حساباتك في كل الأكاديميات';

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
  String get noAccounts => 'مالقيناش حساب بالرقم أو الإيميل ده في أي أكاديمية. اتأكد منه، أو كلّم أكاديميتك.';

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
    return 'الأولاد اللي تحت $age سنة محتاجين موافقتك عشان يدخلوا حساباتهم ويحضروا بنفسهم.';
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
  String get navHome => 'الرئيسية';

  @override
  String get navLessons => 'الحصص';

  @override
  String get navProgress => 'التقدم';

  @override
  String get navMoney => 'الفلوس';

  @override
  String get navMe => 'حسابي';

  @override
  String get navJourney => 'رحلتي';

  @override
  String get navMyLessons => 'حصصي';

  @override
  String get navMyHomework => 'واجباتي';

  @override
  String get navBadges => 'إنجازاتي';

  @override
  String get navPlan => 'الخطة';

  @override
  String get navHomework => 'الواجبات';

  @override
  String get navReports => 'التقارير';

  @override
  String get navToday => 'النهارده';

  @override
  String get navWeek => 'أسبوعي';

  @override
  String get navStudents => 'طلابي';

  @override
  String get navEarnings => 'أرباحي';

  @override
  String get navMore => 'المزيد';

  @override
  String get navMissions => 'مهماتي';

  @override
  String get navQuests => 'تحدياتي';

  @override
  String get navGoals => 'أهدافي';

  @override
  String greetingMorning(String name) {
    return 'صباح الخير يا $name';
  }

  @override
  String greetingEvening(String name) {
    return 'مساء الخير يا $name';
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
  String refusedSubstitute(int days) {
    return 'اتلغت. عندك $days يوم تحجز فيهم حصة التعويض.';
  }

  @override
  String get cancelLesson => 'إلغاء الحصة';

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
  String get yourRating => 'تقييمك';

  @override
  String progressOf(String name) {
    return 'تقدّم $name';
  }

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
  String get joining => 'بندخّلك الحصة…';

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
    return 'فاضل $n دقيقة';
  }

  @override
  String lessonUntil(String time) {
    return 'الحصة لحد $time';
  }

  @override
  String get meTitle => 'حسابي';

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
    return '$covered من $total حصص ليها بديل';
  }

  @override
  String get fillTimeOff => 'اختار الأيام والسبب الأول.';

  @override
  String get noTimeOff => 'مفيش طلبات.';

  @override
  String get kidsHello => 'أهلًا يا بطل';

  @override
  String kidsJourney(String program) {
    return 'رحلتي في $program';
  }

  @override
  String kidsStations(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'خلّصت $n محطة!',
      few: 'خلّصت $n محطات!',
      two: 'خلّصت محطتين!',
      one: 'خلّصت محطة!',
      zero: 'أول محطة مستنياك!',
    );
    return '$_temp0';
  }

  @override
  String kidsNext(String title) {
    return 'الجاية: $title';
  }

  @override
  String get youAreHere => 'انت هنا!';

  @override
  String levelRank(int index, String rank) {
    return 'المستوى $index · $rank';
  }

  @override
  String pointsTo(String rank) {
    return 'نقاطك لـ $rank';
  }

  @override
  String pointsLeft(int n) {
    return 'باقيلك $n نقطة.';
  }

  @override
  String get topRank => 'وصلت لأعلى رتبة!';

  @override
  String worldStage(int index, String title) {
    return 'العالم $index · $title';
  }

  @override
  String stageOf(int i, int n) {
    return 'المرحلة $i من $n';
  }

  @override
  String streak(int n) {
    return '$n حصص ورا بعض';
  }

  @override
  String badgesCount(int earned, int total) {
    return '$earned من $total';
  }

  @override
  String planFor(String name) {
    return 'خطتك يا $name';
  }

  @override
  String get goal => 'هدفك';

  @override
  String get finishOn => 'هتخلّص على كده';

  @override
  String get pace => 'سرعتك';

  @override
  String perWeek(int n) {
    return '$n درس في الأسبوع';
  }

  @override
  String get milestones => 'المحطات';

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
  String get stars => 'نجوم';

  @override
  String get points => 'نقطة';

  @override
  String get missionsTitle => 'مهمات الأسبوع';

  @override
  String get questsTitle => 'تحديات الأسبوع';

  @override
  String get goalsTitle => 'أهداف الأسبوع';

  @override
  String get monthGoal => 'هدف الشهر';

  @override
  String get bossFights => 'تحدي الزعيم';

  @override
  String get waitingRewards => 'جوايز مستنياك';

  @override
  String get fromLastWeek => 'من الأسبوع اللي فات';

  @override
  String get fromLastMonth => 'من الشهر اللي فات';

  @override
  String weekEnds(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'باقي $n يوم على آخر الأسبوع',
      few: 'باقي $n أيام على آخر الأسبوع',
      two: 'باقي يومين على آخر الأسبوع',
      one: 'باقي يوم على آخر الأسبوع',
      zero: 'آخر يوم في الأسبوع!',
    );
    return '$_temp0';
  }

  @override
  String get doneMark => 'تم!';

  @override
  String get collect => 'استلم';

  @override
  String missionsOf(int done, int total) {
    return '$done من $total مهمات';
  }

  @override
  String challengesDone(int done, int total) {
    return '$done من $total خلصوا';
  }

  @override
  String get chestReady => 'الصندوق جاهز! دوس عليه';

  @override
  String get chestSurprise => 'جواه ستيكر مفاجأة';

  @override
  String get chestOpened => 'فتحت صندوق الأسبوع ده';

  @override
  String get noChallenges => 'مفيش تحديات دلوقتي. لما يكون عندك حصص الأسبوع ده، هتظهر هنا.';

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
  String get yourPoints => 'نقطك';

  @override
  String get yourGems => 'جواهرك';

  @override
  String get pointsWord => 'النقط';

  @override
  String get thisWeek => 'الأسبوع ده';

  @override
  String readyCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n جايزة مستنياك',
      few: '$n جوايز مستنياك',
      two: 'جايزتين مستنيينك',
      one: 'جايزة مستنياك',
    );
    return '$_temp0';
  }

  @override
  String cheerFrom(String name) {
    return 'تشجيع من $name!';
  }

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
  String childChallenges(String name) {
    return 'تحديات $name';
  }

  @override
  String get myChallenges => 'تحدياتي';

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
  String get seeAll => 'شوف الكل';

  @override
  String get allBadges => 'كل الإنجازات';

  @override
  String get sounds => 'الأصوات';

  @override
  String get soundsHint => 'أصوات خفيفة لما تدوس أو تستلم جايزة';

  @override
  String get reportsOpen => 'مستنية منك';

  @override
  String get reportsSent => 'اتبعتت';

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
    return 'التسجيل وقف عند $n دقايق.';
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
  String get supportTitle => 'الدعم';

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
  String get ticketHintComplaint => 'على مدرس أو حصة. الأكاديمية بتدوّر فيها وبتقولك عملت إيه.';

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
}
