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
  String get webOnlyTitle => 'الحساب ده للإدارة';

  @override
  String get webOnlyBody => 'حسابات الإدارة بتشتغل من الموقع. افتح لوحة الأكاديمية من الكمبيوتر.';

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
  String get handInOnWeb => 'الواجب ده تسجيل صوت أو ملف. التسليم بيه جاي قريب في التطبيق، وسلّمه من الموقع لحد ما يوصل.';

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
  String get writeOnWeb => 'التقارير والتصحيح من لوحة المدرّس على الموقع لحد ما يوصلوا هنا.';

  @override
  String reportDueBy(String when) {
    return 'قبل $when';
  }

  @override
  String weekSummary(int n, String h) {
    return '$n حصة · $h ساعة';
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
  String lessonsHours(int n, String h) {
    return '$n حصة · $h ساعة';
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
}
