import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'gen/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale) : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates = <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('ar'), Locale('en')];

  /// No description provided for @appName.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية'**
  String get appName;

  /// No description provided for @retry.
  ///
  /// In ar, this message translates to:
  /// **'جرّب تاني'**
  String get retry;

  /// No description provided for @offline.
  ///
  /// In ar, this message translates to:
  /// **'مفيش نت. اتأكد من الاتصال وجرّب تاني.'**
  String get offline;

  /// No description provided for @problem.
  ///
  /// In ar, this message translates to:
  /// **'حصلت مشكلة. جرّب تاني.'**
  String get problem;

  /// No description provided for @back.
  ///
  /// In ar, this message translates to:
  /// **'رجوع'**
  String get back;

  /// No description provided for @close.
  ///
  /// In ar, this message translates to:
  /// **'قفل'**
  String get close;

  /// No description provided for @send.
  ///
  /// In ar, this message translates to:
  /// **'ابعت'**
  String get send;

  /// No description provided for @optional.
  ///
  /// In ar, this message translates to:
  /// **'(اختياري)'**
  String get optional;

  /// No description provided for @noticeSignedOut.
  ///
  /// In ar, this message translates to:
  /// **'خرجت من الحساب.'**
  String get noticeSignedOut;

  /// No description provided for @noticeExpired.
  ///
  /// In ar, this message translates to:
  /// **'الدخول انتهى على الحساب ده. ادخل تاني.'**
  String get noticeExpired;

  /// No description provided for @minutes.
  ///
  /// In ar, this message translates to:
  /// **'{n} دقيقة'**
  String minutes(int n);

  /// No description provided for @today.
  ///
  /// In ar, this message translates to:
  /// **'النهارده'**
  String get today;

  /// No description provided for @tomorrow.
  ///
  /// In ar, this message translates to:
  /// **'بكرة'**
  String get tomorrow;

  /// No description provided for @inMinutes.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{دقيقة} =2{دقيقتين} few{{n} دقايق} other{{n} دقيقة}}'**
  String inMinutes(int n);

  /// No description provided for @inHours.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{ساعة} =2{ساعتين} few{{n} ساعات} other{{n} ساعة}}'**
  String inHours(int n);

  /// No description provided for @lessonsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش حصص} =1{حصة واحدة} =2{حصتين} few{{n} حصص} other{{n} حصة}}'**
  String lessonsCount(int n);

  /// No description provided for @welcomeTitle.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا بيك'**
  String get welcomeTitle;

  /// No description provided for @welcomeLead.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رقم موبايلك أو إيميلك، وهنبعتلك كود. بعدها هتلاقي حساباتك في كل الأكاديميات اللي انت فيها.'**
  String get welcomeLead;

  /// No description provided for @byPhone.
  ///
  /// In ar, this message translates to:
  /// **'رقم الموبايل'**
  String get byPhone;

  /// No description provided for @byEmail.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل'**
  String get byEmail;

  /// No description provided for @countryCode.
  ///
  /// In ar, this message translates to:
  /// **'كود الدولة'**
  String get countryCode;

  /// No description provided for @phoneHint.
  ///
  /// In ar, this message translates to:
  /// **'الكود هيوصلك على واتساب. لو ماعندكش واتساب، هتقدر تطلبه رسالة SMS.'**
  String get phoneHint;

  /// No description provided for @emailHint.
  ///
  /// In ar, this message translates to:
  /// **'الكود هيوصلك على الإيميل ده.'**
  String get emailHint;

  /// No description provided for @sendCode.
  ///
  /// In ar, this message translates to:
  /// **'ابعتلي كود'**
  String get sendCode;

  /// No description provided for @havePassword.
  ///
  /// In ar, this message translates to:
  /// **'عندك كلمة سر؟ ادخل بيها'**
  String get havePassword;

  /// No description provided for @codeTitle.
  ///
  /// In ar, this message translates to:
  /// **'اكتب الكود'**
  String get codeTitle;

  /// No description provided for @codeSentWhatsapp.
  ///
  /// In ar, this message translates to:
  /// **'بعتنالك كود من 6 أرقام على واتساب، على الرقم {to}'**
  String codeSentWhatsapp(String to);

  /// No description provided for @codeSentSms.
  ///
  /// In ar, this message translates to:
  /// **'بعتنالك كود من 6 أرقام في رسالة SMS، على الرقم {to}'**
  String codeSentSms(String to);

  /// No description provided for @codeSentEmail.
  ///
  /// In ar, this message translates to:
  /// **'بعتنالك كود من 6 أرقام على الإيميل {to}'**
  String codeSentEmail(String to);

  /// No description provided for @codeLabel.
  ///
  /// In ar, this message translates to:
  /// **'الكود'**
  String get codeLabel;

  /// No description provided for @codeValid.
  ///
  /// In ar, this message translates to:
  /// **'الكود صالح 10 دقايق.'**
  String get codeValid;

  /// No description provided for @noCode.
  ///
  /// In ar, this message translates to:
  /// **'ماوصلكش؟'**
  String get noCode;

  /// No description provided for @resendIn.
  ///
  /// In ar, this message translates to:
  /// **'ابعت كود جديد بعد {time}'**
  String resendIn(String time);

  /// No description provided for @resend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت كود جديد'**
  String get resend;

  /// No description provided for @sendBySms.
  ///
  /// In ar, this message translates to:
  /// **'ابعته رسالة SMS بدل واتساب'**
  String get sendBySms;

  /// No description provided for @confirm.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد'**
  String get confirm;

  /// No description provided for @otherContact.
  ///
  /// In ar, this message translates to:
  /// **'استخدم رقم أو إيميل تاني'**
  String get otherContact;

  /// No description provided for @accountsTitle.
  ///
  /// In ar, this message translates to:
  /// **'حساباتك في كل الأكاديميات'**
  String get accountsTitle;

  /// No description provided for @accountsFound.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مالقيناش حسابات} =1{لقينا حساب واحد ليك} =2{لقينا حسابين ليك} few{لقينا {n} حسابات ليك} other{لقينا {n} حساب ليك}}'**
  String accountsFound(int n);

  /// No description provided for @accountsLead.
  ///
  /// In ar, this message translates to:
  /// **'اختار اللي عايزه على الموبايل ده. بعد كده بتتنقل بينهم من \"حسابي\" من غير ما تدخل تاني.'**
  String get accountsLead;

  /// No description provided for @accountsNote.
  ///
  /// In ar, this message translates to:
  /// **'بنعرض بس الحسابات اللي على نفس الرقم، في الأكاديميات الشغالة. مش لاقي حساب؟ يمكن الأكاديمية مسجلاك برقم تاني: كلّمها.'**
  String get accountsNote;

  /// No description provided for @addAccounts.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{ادخل} =2{أضف الحسابين وادخل} few{أضف {n} حسابات وادخل} other{أضف {n} حساب وادخل}}'**
  String addAccounts(int n);

  /// No description provided for @noAccounts.
  ///
  /// In ar, this message translates to:
  /// **'مالقيناش حساب بالرقم أو الإيميل ده في أي أكاديمية. اتأكد منه، أو كلّم أكاديميتك.'**
  String get noAccounts;

  /// No description provided for @roleStudent.
  ///
  /// In ar, this message translates to:
  /// **'طالب'**
  String get roleStudent;

  /// No description provided for @roleGuardian.
  ///
  /// In ar, this message translates to:
  /// **'ولي أمر'**
  String get roleGuardian;

  /// No description provided for @roleTeacher.
  ///
  /// In ar, this message translates to:
  /// **'مدرّس'**
  String get roleTeacher;

  /// No description provided for @roleStaff.
  ///
  /// In ar, this message translates to:
  /// **'إدارة'**
  String get roleStaff;

  /// No description provided for @passwordTitle.
  ///
  /// In ar, this message translates to:
  /// **'ادخل بكلمة السر'**
  String get passwordTitle;

  /// No description provided for @academyCode.
  ///
  /// In ar, this message translates to:
  /// **'كود الأكاديمية'**
  String get academyCode;

  /// No description provided for @academyCodeHint.
  ///
  /// In ar, this message translates to:
  /// **'بتلاقيه في رسالة الترحيب، أو اسأل الأكاديمية.'**
  String get academyCodeHint;

  /// No description provided for @loginLabel.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل أو الموبايل أو اسم المستخدم'**
  String get loginLabel;

  /// No description provided for @passwordLabel.
  ///
  /// In ar, this message translates to:
  /// **'كلمة السر'**
  String get passwordLabel;

  /// No description provided for @signIn.
  ///
  /// In ar, this message translates to:
  /// **'ادخل'**
  String get signIn;

  /// No description provided for @useCodeInstead.
  ///
  /// In ar, this message translates to:
  /// **'ادخل بكود بدل كلمة السر'**
  String get useCodeInstead;

  /// No description provided for @twoFactorTitle.
  ///
  /// In ar, this message translates to:
  /// **'الدخول بخطوتين'**
  String get twoFactorTitle;

  /// No description provided for @twoFactorLead.
  ///
  /// In ar, this message translates to:
  /// **'افتح تطبيق المصادقة واكتب الكود اللي فيه (6 أرقام). ضيّعت موبايلك؟ اكتب كود من أكواد الاسترجاع.'**
  String get twoFactorLead;

  /// No description provided for @twoFactorAccount.
  ///
  /// In ar, this message translates to:
  /// **'{name} في {academy}'**
  String twoFactorAccount(String name, String academy);

  /// No description provided for @twoFactorCode.
  ///
  /// In ar, this message translates to:
  /// **'الكود'**
  String get twoFactorCode;

  /// No description provided for @startAgain.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ من الأول'**
  String get startAgain;

  /// No description provided for @webOnlyTitle.
  ///
  /// In ar, this message translates to:
  /// **'الحساب ده للإدارة'**
  String get webOnlyTitle;

  /// No description provided for @webOnlyBody.
  ///
  /// In ar, this message translates to:
  /// **'حسابات الإدارة بتشتغل من الموقع. افتح لوحة الأكاديمية من الكمبيوتر.'**
  String get webOnlyBody;

  /// No description provided for @navHome.
  ///
  /// In ar, this message translates to:
  /// **'الرئيسية'**
  String get navHome;

  /// No description provided for @navLessons.
  ///
  /// In ar, this message translates to:
  /// **'الحصص'**
  String get navLessons;

  /// No description provided for @navProgress.
  ///
  /// In ar, this message translates to:
  /// **'التقدم'**
  String get navProgress;

  /// No description provided for @navMoney.
  ///
  /// In ar, this message translates to:
  /// **'الفلوس'**
  String get navMoney;

  /// No description provided for @navMe.
  ///
  /// In ar, this message translates to:
  /// **'حسابي'**
  String get navMe;

  /// No description provided for @navJourney.
  ///
  /// In ar, this message translates to:
  /// **'رحلتي'**
  String get navJourney;

  /// No description provided for @navMyLessons.
  ///
  /// In ar, this message translates to:
  /// **'حصصي'**
  String get navMyLessons;

  /// No description provided for @navMyHomework.
  ///
  /// In ar, this message translates to:
  /// **'واجباتي'**
  String get navMyHomework;

  /// No description provided for @navBadges.
  ///
  /// In ar, this message translates to:
  /// **'إنجازاتي'**
  String get navBadges;

  /// No description provided for @navPlan.
  ///
  /// In ar, this message translates to:
  /// **'الخطة'**
  String get navPlan;

  /// No description provided for @navHomework.
  ///
  /// In ar, this message translates to:
  /// **'الواجبات'**
  String get navHomework;

  /// No description provided for @navReports.
  ///
  /// In ar, this message translates to:
  /// **'التقارير'**
  String get navReports;

  /// No description provided for @navToday.
  ///
  /// In ar, this message translates to:
  /// **'النهارده'**
  String get navToday;

  /// No description provided for @navWeek.
  ///
  /// In ar, this message translates to:
  /// **'أسبوعي'**
  String get navWeek;

  /// No description provided for @navStudents.
  ///
  /// In ar, this message translates to:
  /// **'طلابي'**
  String get navStudents;

  /// No description provided for @navEarnings.
  ///
  /// In ar, this message translates to:
  /// **'أرباحي'**
  String get navEarnings;

  /// No description provided for @navMore.
  ///
  /// In ar, this message translates to:
  /// **'المزيد'**
  String get navMore;

  /// No description provided for @navMissions.
  ///
  /// In ar, this message translates to:
  /// **'مهماتي'**
  String get navMissions;

  /// No description provided for @navQuests.
  ///
  /// In ar, this message translates to:
  /// **'تحدياتي'**
  String get navQuests;

  /// No description provided for @navGoals.
  ///
  /// In ar, this message translates to:
  /// **'أهدافي'**
  String get navGoals;

  /// No description provided for @greetingMorning.
  ///
  /// In ar, this message translates to:
  /// **'صباح الخير يا {name}'**
  String greetingMorning(String name);

  /// No description provided for @greetingEvening.
  ///
  /// In ar, this message translates to:
  /// **'مساء الخير يا {name}'**
  String greetingEvening(String name);

  /// No description provided for @nextLesson.
  ///
  /// In ar, this message translates to:
  /// **'الحصة الجاية'**
  String get nextLesson;

  /// No description provided for @noNextLesson.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حصص جاية في الأربع أسابيع دول.'**
  String get noNextLesson;

  /// No description provided for @roomOpensIn.
  ///
  /// In ar, this message translates to:
  /// **'الفصل بيفتح بعد {time}'**
  String roomOpensIn(String time);

  /// No description provided for @roomOpen.
  ///
  /// In ar, this message translates to:
  /// **'الفصل مفتوح'**
  String get roomOpen;

  /// No description provided for @lessonOn.
  ///
  /// In ar, this message translates to:
  /// **'الحصة شغالة دلوقتي'**
  String get lessonOn;

  /// No description provided for @lessonWith.
  ///
  /// In ar, this message translates to:
  /// **'{subject} مع {teacher}'**
  String lessonWith(String subject, String teacher);

  /// No description provided for @joinLesson.
  ///
  /// In ar, this message translates to:
  /// **'ادخل الحصة'**
  String get joinLesson;

  /// No description provided for @joinOpensAt.
  ///
  /// In ar, this message translates to:
  /// **'بيفتح {time}'**
  String joinOpensAt(String time);

  /// No description provided for @allLessons.
  ///
  /// In ar, this message translates to:
  /// **'كل الحصص'**
  String get allLessons;

  /// No description provided for @balance.
  ///
  /// In ar, this message translates to:
  /// **'الرصيد'**
  String get balance;

  /// No description provided for @owed.
  ///
  /// In ar, this message translates to:
  /// **'عليك'**
  String get owed;

  /// No description provided for @payNow.
  ///
  /// In ar, this message translates to:
  /// **'ادفع دلوقتي'**
  String get payNow;

  /// No description provided for @nothingOwed.
  ///
  /// In ar, this message translates to:
  /// **'مفيش عليك حاجة'**
  String get nothingOwed;

  /// No description provided for @lastReport.
  ///
  /// In ar, this message translates to:
  /// **'آخر تقرير'**
  String get lastReport;

  /// No description provided for @readReport.
  ///
  /// In ar, this message translates to:
  /// **'اقراه'**
  String get readReport;

  /// No description provided for @homeworkLine.
  ///
  /// In ar, this message translates to:
  /// **'واجب: {title}'**
  String homeworkLine(String title);

  /// No description provided for @unfunded.
  ///
  /// In ar, this message translates to:
  /// **'مستنية الدفع'**
  String get unfunded;

  /// No description provided for @notices.
  ///
  /// In ar, this message translates to:
  /// **'تنبيهات'**
  String get notices;

  /// No description provided for @lessonsOf.
  ///
  /// In ar, this message translates to:
  /// **'حصص {name}'**
  String lessonsOf(String name);

  /// No description provided for @onClock.
  ///
  /// In ar, this message translates to:
  /// **'بتوقيت {zone}'**
  String onClock(String zone);

  /// No description provided for @upcoming.
  ///
  /// In ar, this message translates to:
  /// **'الجاية'**
  String get upcoming;

  /// No description provided for @past.
  ///
  /// In ar, this message translates to:
  /// **'اللي فاتت'**
  String get past;

  /// No description provided for @noUpcoming.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حصص جاية في الأربع أسابيع دول.'**
  String get noUpcoming;

  /// No description provided for @noPast.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حصص في الأربع أسابيع اللي فاتوا.'**
  String get noPast;

  /// No description provided for @substituteFor.
  ///
  /// In ar, this message translates to:
  /// **'مدرّس بديل: {name} في إجازة'**
  String substituteFor(String name);

  /// No description provided for @refuseSubstitute.
  ///
  /// In ar, this message translates to:
  /// **'مش موافقين على البديل'**
  String get refuseSubstitute;

  /// No description provided for @refuseSubstituteTitle.
  ///
  /// In ar, this message translates to:
  /// **'مش موافقين على البديل؟'**
  String get refuseSubstituteTitle;

  /// No description provided for @refuseSubstituteBody.
  ///
  /// In ar, this message translates to:
  /// **'الحصة هتتلغي على حساب الأكاديمية، ودقايقها هترجعلك حصة تعويض تحجزها بعدين.'**
  String get refuseSubstituteBody;

  /// No description provided for @refuseSubstituteYes.
  ///
  /// In ar, this message translates to:
  /// **'أيوه، ألغيها وعوّضوني'**
  String get refuseSubstituteYes;

  /// No description provided for @refusedSubstitute.
  ///
  /// In ar, this message translates to:
  /// **'اتلغت. عندك {days} يوم تحجز فيهم حصة التعويض.'**
  String refusedSubstitute(int days);

  /// No description provided for @cancelLesson.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الحصة'**
  String get cancelLesson;

  /// No description provided for @cancelTitle.
  ///
  /// In ar, this message translates to:
  /// **'تلغي حصة {day}؟'**
  String cancelTitle(String day);

  /// No description provided for @reason.
  ///
  /// In ar, this message translates to:
  /// **'السبب'**
  String get reason;

  /// No description provided for @noteForAcademy.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة للأكاديمية'**
  String get noteForAcademy;

  /// No description provided for @keepIt.
  ///
  /// In ar, this message translates to:
  /// **'لأ، خليها'**
  String get keepIt;

  /// No description provided for @yesCancel.
  ///
  /// In ar, this message translates to:
  /// **'أيوه، ألغيها'**
  String get yesCancel;

  /// No description provided for @cancelled.
  ///
  /// In ar, this message translates to:
  /// **'الحصة اتلغت.'**
  String get cancelled;

  /// No description provided for @pickReason.
  ///
  /// In ar, this message translates to:
  /// **'اختار سبب الأول.'**
  String get pickReason;

  /// No description provided for @rateLesson.
  ///
  /// In ar, this message translates to:
  /// **'قيّم الحصة'**
  String get rateLesson;

  /// No description provided for @rateTitle.
  ///
  /// In ar, this message translates to:
  /// **'الحصة كانت إزاي؟'**
  String get rateTitle;

  /// No description provided for @rateGood.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي عجبكم؟'**
  String get rateGood;

  /// No description provided for @rateBad.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي ماعجبكمش؟'**
  String get rateBad;

  /// No description provided for @rateComment.
  ///
  /// In ar, this message translates to:
  /// **'كلمة للأكاديمية'**
  String get rateComment;

  /// No description provided for @rateAnonymous.
  ///
  /// In ar, this message translates to:
  /// **'المدرّس بيشوف التقييم من غير اسمك.'**
  String get rateAnonymous;

  /// No description provided for @sendRating.
  ///
  /// In ar, this message translates to:
  /// **'ابعت التقييم'**
  String get sendRating;

  /// No description provided for @notNow.
  ///
  /// In ar, this message translates to:
  /// **'مش دلوقتي'**
  String get notNow;

  /// No description provided for @rated.
  ///
  /// In ar, this message translates to:
  /// **'شكرًا! وصل تقييمك.'**
  String get rated;

  /// No description provided for @starsOf.
  ///
  /// In ar, this message translates to:
  /// **'{n} من 5'**
  String starsOf(int n);

  /// No description provided for @yourRating.
  ///
  /// In ar, this message translates to:
  /// **'تقييمك'**
  String get yourRating;

  /// No description provided for @progressOf.
  ///
  /// In ar, this message translates to:
  /// **'تقدّم {name}'**
  String progressOf(String name);

  /// No description provided for @percentDone.
  ///
  /// In ar, this message translates to:
  /// **'{p}% من البرنامج'**
  String percentDone(int p);

  /// No description provided for @hereNow.
  ///
  /// In ar, this message translates to:
  /// **'دلوقتي: {title}'**
  String hereNow(String title);

  /// No description provided for @weeksLeft.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{قرّب يخلص} =1{باقي حوالي أسبوع} =2{باقي حوالي أسبوعين} few{باقي حوالي {n} أسابيع} other{باقي حوالي {n} أسبوع}}'**
  String weeksLeft(int n);

  /// No description provided for @homeworkOpen.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{واجب مفتوح} =2{واجبين مفتوحين} few{{n} واجبات مفتوحة} other{{n} واجب مفتوح}}'**
  String homeworkOpen(int n);

  /// No description provided for @reports.
  ///
  /// In ar, this message translates to:
  /// **'التقارير'**
  String get reports;

  /// No description provided for @noReports.
  ///
  /// In ar, this message translates to:
  /// **'مفيش تقارير لسه.'**
  String get noReports;

  /// No description provided for @noCourses.
  ///
  /// In ar, this message translates to:
  /// **'مفيش اشتراكات شغالة.'**
  String get noCourses;

  /// No description provided for @noProgram.
  ///
  /// In ar, this message translates to:
  /// **'من غير منهج'**
  String get noProgram;

  /// No description provided for @reportOf.
  ///
  /// In ar, this message translates to:
  /// **'تقرير {day}'**
  String reportOf(String day);

  /// No description provided for @engagement.
  ///
  /// In ar, this message translates to:
  /// **'التركيز'**
  String get engagement;

  /// No description provided for @overall.
  ///
  /// In ar, this message translates to:
  /// **'المستوى العام'**
  String get overall;

  /// No description provided for @homeworkTitle.
  ///
  /// In ar, this message translates to:
  /// **'الواجبات'**
  String get homeworkTitle;

  /// No description provided for @noHomework.
  ///
  /// In ar, this message translates to:
  /// **'مفيش واجبات دلوقتي.'**
  String get noHomework;

  /// No description provided for @dueBy.
  ///
  /// In ar, this message translates to:
  /// **'قبل {when}'**
  String dueBy(String when);

  /// No description provided for @handIn.
  ///
  /// In ar, this message translates to:
  /// **'سلّم'**
  String get handIn;

  /// No description provided for @yourAnswer.
  ///
  /// In ar, this message translates to:
  /// **'إجابتك'**
  String get yourAnswer;

  /// No description provided for @handedIn.
  ///
  /// In ar, this message translates to:
  /// **'اتسلّم. المدرّس هيصححه.'**
  String get handedIn;

  /// No description provided for @teacherFeedback.
  ///
  /// In ar, this message translates to:
  /// **'رأي المدرّس'**
  String get teacherFeedback;

  /// No description provided for @grade.
  ///
  /// In ar, this message translates to:
  /// **'الدرجة: {g} من 5'**
  String grade(int g);

  /// No description provided for @yourSubmission.
  ///
  /// In ar, this message translates to:
  /// **'اللي سلّمته'**
  String get yourSubmission;

  /// No description provided for @kidsHomeworkStar.
  ///
  /// In ar, this message translates to:
  /// **'كل واجب بتسلّمه بيجيبلك نجمة جديدة!'**
  String get kidsHomeworkStar;

  /// No description provided for @owedNow.
  ///
  /// In ar, this message translates to:
  /// **'عليك دلوقتي'**
  String get owedNow;

  /// No description provided for @invoiceDue.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة {number} · قبل {when}'**
  String invoiceDue(String number, String when);

  /// No description provided for @payAmount.
  ///
  /// In ar, this message translates to:
  /// **'ادفع {amount}'**
  String payAmount(String amount);

  /// No description provided for @payNote.
  ///
  /// In ar, this message translates to:
  /// **'الدفع بيتم في صفحة بوابة الأكاديمية. لما تخلّص ارجع هنا.'**
  String get payNote;

  /// No description provided for @lessonsLeftTitle.
  ///
  /// In ar, this message translates to:
  /// **'الحصص الفاضلة'**
  String get lessonsLeftTitle;

  /// No description provided for @invoices.
  ///
  /// In ar, this message translates to:
  /// **'الفواتير'**
  String get invoices;

  /// No description provided for @noInvoices.
  ///
  /// In ar, this message translates to:
  /// **'مفيش فواتير.'**
  String get noInvoices;

  /// No description provided for @openInvoice.
  ///
  /// In ar, this message translates to:
  /// **'افتح الفاتورة'**
  String get openInvoice;

  /// No description provided for @invoiceNote.
  ///
  /// In ar, this message translates to:
  /// **'افتح أي فاتورة تشوفها وتطبعها، وفيها رمز QR بتاع الفاتورة الضريبية.'**
  String get invoiceNote;

  /// No description provided for @cantPayOnline.
  ///
  /// In ar, this message translates to:
  /// **'الدفع أونلاين مش متاح للفاتورة دي. كلّم الأكاديمية.'**
  String get cantPayOnline;

  /// No description provided for @pickGateway.
  ///
  /// In ar, this message translates to:
  /// **'تدفع بإيه؟'**
  String get pickGateway;

  /// No description provided for @paidByTransfer.
  ///
  /// In ar, this message translates to:
  /// **'دفعت بالتحويل'**
  String get paidByTransfer;

  /// No description provided for @proofTitle.
  ///
  /// In ar, this message translates to:
  /// **'ابعت إثبات الدفع'**
  String get proofTitle;

  /// No description provided for @proofHint.
  ///
  /// In ar, this message translates to:
  /// **'ادفع الأول، وبعدين ابعت الإيصال هنا. الأكاديمية هتتأكد إن الفلوس وصلت وتأكّد الدفعة.'**
  String get proofHint;

  /// No description provided for @proofHowTo.
  ///
  /// In ar, this message translates to:
  /// **'إزاي تدفع بالتحويل'**
  String get proofHowTo;

  /// No description provided for @proofMethod.
  ///
  /// In ar, this message translates to:
  /// **'دفعت إزاي'**
  String get proofMethod;

  /// No description provided for @proofAmount.
  ///
  /// In ar, this message translates to:
  /// **'المبلغ اللي دفعته'**
  String get proofAmount;

  /// No description provided for @proofPaidOn.
  ///
  /// In ar, this message translates to:
  /// **'يوم الدفع'**
  String get proofPaidOn;

  /// No description provided for @proofReference.
  ///
  /// In ar, this message translates to:
  /// **'رقم التحويل (اختياري)'**
  String get proofReference;

  /// No description provided for @proofNote.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة (اختياري)'**
  String get proofNote;

  /// No description provided for @proofReceipt.
  ///
  /// In ar, this message translates to:
  /// **'الإيصال'**
  String get proofReceipt;

  /// No description provided for @proofPickReceipt.
  ///
  /// In ar, this message translates to:
  /// **'اختار صورة أو PDF'**
  String get proofPickReceipt;

  /// No description provided for @proofReceiptHint.
  ///
  /// In ar, this message translates to:
  /// **'صورة أو سكرين شوت أو PDF البنك، لحد 10 ميجا.'**
  String get proofReceiptHint;

  /// No description provided for @proofSend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت الإثبات'**
  String get proofSend;

  /// No description provided for @proofSent.
  ///
  /// In ar, this message translates to:
  /// **'الإثبات اتبعت. هنقولك أول ما الأكاديمية تأكّده.'**
  String get proofSent;

  /// No description provided for @proofNeedsReceipt.
  ///
  /// In ar, this message translates to:
  /// **'اختار الإيصال الأول.'**
  String get proofNeedsReceipt;

  /// No description provided for @proofFileTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 10 ميجا.'**
  String get proofFileTooBig;

  /// No description provided for @joining.
  ///
  /// In ar, this message translates to:
  /// **'بندخّلك الحصة…'**
  String get joining;

  /// No description provided for @openedOutside.
  ///
  /// In ar, this message translates to:
  /// **'الحصة اتفتحت في تطبيق تاني.'**
  String get openedOutside;

  /// No description provided for @leave.
  ///
  /// In ar, this message translates to:
  /// **'اخرج من الحصة'**
  String get leave;

  /// No description provided for @micOff.
  ///
  /// In ar, this message translates to:
  /// **'اقفل المايك'**
  String get micOff;

  /// No description provided for @micOn.
  ///
  /// In ar, this message translates to:
  /// **'افتح المايك'**
  String get micOn;

  /// No description provided for @camOff.
  ///
  /// In ar, this message translates to:
  /// **'اقفل الكاميرا'**
  String get camOff;

  /// No description provided for @camOn.
  ///
  /// In ar, this message translates to:
  /// **'افتح الكاميرا'**
  String get camOn;

  /// No description provided for @audioOnly.
  ///
  /// In ar, this message translates to:
  /// **'الصوت بس'**
  String get audioOnly;

  /// No description provided for @rejoinHint.
  ///
  /// In ar, this message translates to:
  /// **'لو خرجت بالغلط، ادخل تاني من نفس الزرار لحد ما الحصة تخلص.'**
  String get rejoinHint;

  /// No description provided for @weakNetwork.
  ///
  /// In ar, this message translates to:
  /// **'النت ضعيف. جرّب \"الصوت بس\" لو الصورة بتقطّع.'**
  String get weakNetwork;

  /// No description provided for @waitingOther.
  ///
  /// In ar, this message translates to:
  /// **'مستنيين الطرف التاني يدخل…'**
  String get waitingOther;

  /// No description provided for @you.
  ///
  /// In ar, this message translates to:
  /// **'انت'**
  String get you;

  /// No description provided for @minutesLeft.
  ///
  /// In ar, this message translates to:
  /// **'فاضل {n} دقيقة'**
  String minutesLeft(int n);

  /// No description provided for @lessonUntil.
  ///
  /// In ar, this message translates to:
  /// **'الحصة لحد {time}'**
  String lessonUntil(String time);

  /// No description provided for @meTitle.
  ///
  /// In ar, this message translates to:
  /// **'حسابي'**
  String get meTitle;

  /// No description provided for @openNow.
  ///
  /// In ar, this message translates to:
  /// **'داخل فيه'**
  String get openNow;

  /// No description provided for @switchTo.
  ///
  /// In ar, this message translates to:
  /// **'انتقل'**
  String get switchTo;

  /// No description provided for @addAccount.
  ///
  /// In ar, this message translates to:
  /// **'ضيف حساب تاني'**
  String get addAccount;

  /// No description provided for @clockPrompt.
  ///
  /// In ar, this message translates to:
  /// **'موبايلك على توقيت {device}، وحسابك على {profile}. نعرض المواعيد بتوقيت {device}؟'**
  String clockPrompt(String device, String profile);

  /// No description provided for @clockYes.
  ///
  /// In ar, this message translates to:
  /// **'أيوه، {zone}'**
  String clockYes(String zone);

  /// No description provided for @clockKeep.
  ///
  /// In ar, this message translates to:
  /// **'خليه {zone}'**
  String clockKeep(String zone);

  /// No description provided for @language.
  ///
  /// In ar, this message translates to:
  /// **'اللغة'**
  String get language;

  /// No description provided for @timezone.
  ///
  /// In ar, this message translates to:
  /// **'التوقيت'**
  String get timezone;

  /// No description provided for @myDevices.
  ///
  /// In ar, this message translates to:
  /// **'أجهزتي'**
  String get myDevices;

  /// No description provided for @thisDevice.
  ///
  /// In ar, this message translates to:
  /// **'الجهاز ده'**
  String get thisDevice;

  /// No description provided for @lastUsed.
  ///
  /// In ar, this message translates to:
  /// **'آخر مرة {when}'**
  String lastUsed(String when);

  /// No description provided for @signOutDevice.
  ///
  /// In ar, this message translates to:
  /// **'خرّجه'**
  String get signOutDevice;

  /// No description provided for @deviceSignedOut.
  ///
  /// In ar, this message translates to:
  /// **'الجهاز خرج من الحساب.'**
  String get deviceSignedOut;

  /// No description provided for @signOut.
  ///
  /// In ar, this message translates to:
  /// **'اخرج من الحساب ده'**
  String get signOut;

  /// No description provided for @signOutConfirm.
  ///
  /// In ar, this message translates to:
  /// **'تخرج من {academy} على الموبايل ده؟'**
  String signOutConfirm(String academy);

  /// No description provided for @todayHeader.
  ///
  /// In ar, this message translates to:
  /// **'{date} · بتوقيت {zone}'**
  String todayHeader(String date, String zone);

  /// No description provided for @todayCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{النهارده مفيش حصص} =1{النهارده: حصة واحدة} =2{النهارده: حصتين} few{النهارده: {n} حصص} other{النهارده: {n} حصة}}'**
  String todayCount(int n);

  /// No description provided for @hours.
  ///
  /// In ar, this message translates to:
  /// **'{h} ساعة'**
  String hours(String h);

  /// No description provided for @nextIn.
  ///
  /// In ar, this message translates to:
  /// **'الجاية بعد {time}'**
  String nextIn(String time);

  /// No description provided for @reportsWaiting.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{تقرير مستنيك} =2{تقريرين مستنيينك} few{{n} تقارير مستنياك} other{{n} تقرير مستنيك}}'**
  String reportsWaiting(int n);

  /// No description provided for @homeworkToMark.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{واجب تصحّحه} =2{واجبين تصحّحهم} few{{n} واجبات تصحّحها} other{{n} واجب تصحّحه}}'**
  String homeworkToMark(int n);

  /// No description provided for @reportDueBy.
  ///
  /// In ar, this message translates to:
  /// **'قبل {when}'**
  String reportDueBy(String when);

  /// No description provided for @prevWeek.
  ///
  /// In ar, this message translates to:
  /// **'الأسبوع اللي فات'**
  String get prevWeek;

  /// No description provided for @nextWeek.
  ///
  /// In ar, this message translates to:
  /// **'الأسبوع الجاي'**
  String get nextWeek;

  /// No description provided for @noLessonsDay.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حصص'**
  String get noLessonsDay;

  /// No description provided for @noLessonsWeek.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حصص الأسبوع ده.'**
  String get noLessonsWeek;

  /// No description provided for @studentsTitle.
  ///
  /// In ar, this message translates to:
  /// **'طلابي'**
  String get studentsTitle;

  /// No description provided for @paused.
  ///
  /// In ar, this message translates to:
  /// **'متوقف مؤقتًا'**
  String get paused;

  /// No description provided for @toMark.
  ///
  /// In ar, this message translates to:
  /// **'{n} للتصحيح'**
  String toMark(int n);

  /// No description provided for @lastReportAt.
  ///
  /// In ar, this message translates to:
  /// **'آخر تقرير: {when}'**
  String lastReportAt(String when);

  /// No description provided for @noStudents.
  ///
  /// In ar, this message translates to:
  /// **'مفيش طلاب لسه.'**
  String get noStudents;

  /// No description provided for @prevMonth.
  ///
  /// In ar, this message translates to:
  /// **'الشهر اللي فات'**
  String get prevMonth;

  /// No description provided for @nextMonth.
  ///
  /// In ar, this message translates to:
  /// **'الشهر الجاي'**
  String get nextMonth;

  /// No description provided for @soFar.
  ///
  /// In ar, this message translates to:
  /// **'لحد النهارده'**
  String get soFar;

  /// No description provided for @monthOpen.
  ///
  /// In ar, this message translates to:
  /// **'الشهر لسه مفتوح'**
  String get monthOpen;

  /// No description provided for @monthClosed.
  ///
  /// In ar, this message translates to:
  /// **'الشهر اتقفل'**
  String get monthClosed;

  /// No description provided for @lessonsHours.
  ///
  /// In ar, this message translates to:
  /// **'{lessons} · {hours}'**
  String lessonsHours(String lessons, String hours);

  /// No description provided for @lessonPay.
  ///
  /// In ar, this message translates to:
  /// **'الحصص'**
  String get lessonPay;

  /// No description provided for @bonuses.
  ///
  /// In ar, this message translates to:
  /// **'مكافآت'**
  String get bonuses;

  /// No description provided for @deductions.
  ///
  /// In ar, this message translates to:
  /// **'خصومات'**
  String get deductions;

  /// No description provided for @unpricedNote.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{حصة لسه مالهاش سعر} =2{حصتين لسه مالهمش سعر} few{{n} حصص لسه مالهاش سعر} other{{n} حصة لسه مالهاش سعر}}. الأكاديمية هتحدده، وهتتحسب في الشهر ده.'**
  String unpricedNote(int n);

  /// No description provided for @noEarnings.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حصص في الشهر ده.'**
  String get noEarnings;

  /// No description provided for @adjustments.
  ///
  /// In ar, this message translates to:
  /// **'تعديلات'**
  String get adjustments;

  /// No description provided for @corrected.
  ///
  /// In ar, this message translates to:
  /// **'اتصحّح'**
  String get corrected;

  /// No description provided for @timeOff.
  ///
  /// In ar, this message translates to:
  /// **'الإجازات'**
  String get timeOff;

  /// No description provided for @requestTimeOff.
  ///
  /// In ar, this message translates to:
  /// **'اطلب إجازة'**
  String get requestTimeOff;

  /// No description provided for @timeOffLead.
  ///
  /// In ar, this message translates to:
  /// **'بتوقيتك: {zone} · الأكاديمية بترد عليك'**
  String timeOffLead(String zone);

  /// No description provided for @wholeDays.
  ///
  /// In ar, this message translates to:
  /// **'أيام كاملة'**
  String get wholeDays;

  /// No description provided for @partOfDay.
  ///
  /// In ar, this message translates to:
  /// **'جزء من يوم'**
  String get partOfDay;

  /// No description provided for @fromDay.
  ///
  /// In ar, this message translates to:
  /// **'من'**
  String get fromDay;

  /// No description provided for @toDay.
  ///
  /// In ar, this message translates to:
  /// **'لحد'**
  String get toDay;

  /// No description provided for @dayLabel.
  ///
  /// In ar, this message translates to:
  /// **'اليوم'**
  String get dayLabel;

  /// No description provided for @fromTime.
  ///
  /// In ar, this message translates to:
  /// **'من الساعة'**
  String get fromTime;

  /// No description provided for @toTime.
  ///
  /// In ar, this message translates to:
  /// **'لحد الساعة'**
  String get toTime;

  /// No description provided for @reasonSick.
  ///
  /// In ar, this message translates to:
  /// **'مرض'**
  String get reasonSick;

  /// No description provided for @reasonVacation.
  ///
  /// In ar, this message translates to:
  /// **'إجازة سنوية'**
  String get reasonVacation;

  /// No description provided for @reasonPersonal.
  ///
  /// In ar, this message translates to:
  /// **'ظرف شخصي'**
  String get reasonPersonal;

  /// No description provided for @reasonFamily.
  ///
  /// In ar, this message translates to:
  /// **'ظرف عائلي'**
  String get reasonFamily;

  /// No description provided for @reasonEmergency.
  ///
  /// In ar, this message translates to:
  /// **'طارئ'**
  String get reasonEmergency;

  /// No description provided for @reasonOther.
  ///
  /// In ar, this message translates to:
  /// **'حاجة تانية'**
  String get reasonOther;

  /// No description provided for @sendRequest.
  ///
  /// In ar, this message translates to:
  /// **'ابعت الطلب'**
  String get sendRequest;

  /// No description provided for @yourRequests.
  ///
  /// In ar, this message translates to:
  /// **'طلباتك'**
  String get yourRequests;

  /// No description provided for @withdraw.
  ///
  /// In ar, this message translates to:
  /// **'اسحبه'**
  String get withdraw;

  /// No description provided for @requestSent.
  ///
  /// In ar, this message translates to:
  /// **'الطلب اتبعت. هيوصلك الرد.'**
  String get requestSent;

  /// No description provided for @withdrawn.
  ///
  /// In ar, this message translates to:
  /// **'الطلب اتسحب.'**
  String get withdrawn;

  /// No description provided for @academyHolidays.
  ///
  /// In ar, this message translates to:
  /// **'إجازات الأكاديمية'**
  String get academyHolidays;

  /// No description provided for @coverState.
  ///
  /// In ar, this message translates to:
  /// **'{covered} من {total} حصص ليها بديل'**
  String coverState(int covered, int total);

  /// No description provided for @fillTimeOff.
  ///
  /// In ar, this message translates to:
  /// **'اختار الأيام والسبب الأول.'**
  String get fillTimeOff;

  /// No description provided for @noTimeOff.
  ///
  /// In ar, this message translates to:
  /// **'مفيش طلبات.'**
  String get noTimeOff;

  /// No description provided for @kidsHello.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا يا بطل'**
  String get kidsHello;

  /// No description provided for @kidsJourney.
  ///
  /// In ar, this message translates to:
  /// **'رحلتي في {program}'**
  String kidsJourney(String program);

  /// No description provided for @kidsStations.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{أول محطة مستنياك!} =1{خلّصت محطة!} =2{خلّصت محطتين!} few{خلّصت {n} محطات!} other{خلّصت {n} محطة!}}'**
  String kidsStations(int n);

  /// No description provided for @kidsNext.
  ///
  /// In ar, this message translates to:
  /// **'الجاية: {title}'**
  String kidsNext(String title);

  /// No description provided for @youAreHere.
  ///
  /// In ar, this message translates to:
  /// **'انت هنا!'**
  String get youAreHere;

  /// No description provided for @levelRank.
  ///
  /// In ar, this message translates to:
  /// **'المستوى {index} · {rank}'**
  String levelRank(int index, String rank);

  /// No description provided for @pointsTo.
  ///
  /// In ar, this message translates to:
  /// **'نقاطك لـ {rank}'**
  String pointsTo(String rank);

  /// No description provided for @pointsLeft.
  ///
  /// In ar, this message translates to:
  /// **'باقيلك {n} نقطة.'**
  String pointsLeft(int n);

  /// No description provided for @topRank.
  ///
  /// In ar, this message translates to:
  /// **'وصلت لأعلى رتبة!'**
  String get topRank;

  /// No description provided for @worldStage.
  ///
  /// In ar, this message translates to:
  /// **'العالم {index} · {title}'**
  String worldStage(int index, String title);

  /// No description provided for @stageOf.
  ///
  /// In ar, this message translates to:
  /// **'المرحلة {i} من {n}'**
  String stageOf(int i, int n);

  /// No description provided for @streak.
  ///
  /// In ar, this message translates to:
  /// **'{n} حصص ورا بعض'**
  String streak(int n);

  /// No description provided for @badgesCount.
  ///
  /// In ar, this message translates to:
  /// **'{earned} من {total}'**
  String badgesCount(int earned, int total);

  /// No description provided for @planFor.
  ///
  /// In ar, this message translates to:
  /// **'خطتك يا {name}'**
  String planFor(String name);

  /// No description provided for @goal.
  ///
  /// In ar, this message translates to:
  /// **'هدفك'**
  String get goal;

  /// No description provided for @finishOn.
  ///
  /// In ar, this message translates to:
  /// **'هتخلّص على كده'**
  String get finishOn;

  /// No description provided for @pace.
  ///
  /// In ar, this message translates to:
  /// **'سرعتك'**
  String get pace;

  /// No description provided for @perWeek.
  ///
  /// In ar, this message translates to:
  /// **'{n} درس في الأسبوع'**
  String perWeek(int n);

  /// No description provided for @milestones.
  ///
  /// In ar, this message translates to:
  /// **'المحطات'**
  String get milestones;

  /// No description provided for @programDone.
  ///
  /// In ar, this message translates to:
  /// **'خلّصت البرنامج!'**
  String get programDone;

  /// No description provided for @weeksLeftLevel.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{المستوى ده قرّب يخلص} =1{باقي أسبوع في المستوى ده} =2{باقي أسبوعين في المستوى ده} few{باقي {n} أسابيع في المستوى ده} other{باقي {n} أسبوع في المستوى ده}}'**
  String weeksLeftLevel(int n);

  /// No description provided for @stars.
  ///
  /// In ar, this message translates to:
  /// **'نجوم'**
  String get stars;

  /// No description provided for @points.
  ///
  /// In ar, this message translates to:
  /// **'نقطة'**
  String get points;

  /// No description provided for @missionsTitle.
  ///
  /// In ar, this message translates to:
  /// **'مهمات الأسبوع'**
  String get missionsTitle;

  /// No description provided for @questsTitle.
  ///
  /// In ar, this message translates to:
  /// **'تحديات الأسبوع'**
  String get questsTitle;

  /// No description provided for @goalsTitle.
  ///
  /// In ar, this message translates to:
  /// **'أهداف الأسبوع'**
  String get goalsTitle;

  /// No description provided for @monthGoal.
  ///
  /// In ar, this message translates to:
  /// **'هدف الشهر'**
  String get monthGoal;

  /// No description provided for @bossFights.
  ///
  /// In ar, this message translates to:
  /// **'تحدي الزعيم'**
  String get bossFights;

  /// No description provided for @waitingRewards.
  ///
  /// In ar, this message translates to:
  /// **'جوايز مستنياك'**
  String get waitingRewards;

  /// No description provided for @fromLastWeek.
  ///
  /// In ar, this message translates to:
  /// **'من الأسبوع اللي فات'**
  String get fromLastWeek;

  /// No description provided for @fromLastMonth.
  ///
  /// In ar, this message translates to:
  /// **'من الشهر اللي فات'**
  String get fromLastMonth;

  /// No description provided for @weekEnds.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{آخر يوم في الأسبوع!} =1{باقي يوم على آخر الأسبوع} =2{باقي يومين على آخر الأسبوع} few{باقي {n} أيام على آخر الأسبوع} other{باقي {n} يوم على آخر الأسبوع}}'**
  String weekEnds(int n);

  /// No description provided for @doneMark.
  ///
  /// In ar, this message translates to:
  /// **'تم!'**
  String get doneMark;

  /// No description provided for @collect.
  ///
  /// In ar, this message translates to:
  /// **'استلم'**
  String get collect;

  /// No description provided for @missionsOf.
  ///
  /// In ar, this message translates to:
  /// **'{done} من {total} مهمات'**
  String missionsOf(int done, int total);

  /// No description provided for @challengesDone.
  ///
  /// In ar, this message translates to:
  /// **'{done} من {total} خلصوا'**
  String challengesDone(int done, int total);

  /// No description provided for @chestReady.
  ///
  /// In ar, this message translates to:
  /// **'الصندوق جاهز! دوس عليه'**
  String get chestReady;

  /// No description provided for @chestSurprise.
  ///
  /// In ar, this message translates to:
  /// **'جواه ستيكر مفاجأة'**
  String get chestSurprise;

  /// No description provided for @chestOpened.
  ///
  /// In ar, this message translates to:
  /// **'فتحت صندوق الأسبوع ده'**
  String get chestOpened;

  /// No description provided for @noChallenges.
  ///
  /// In ar, this message translates to:
  /// **'مفيش تحديات دلوقتي. لما يكون عندك حصص الأسبوع ده، هتظهر هنا.'**
  String get noChallenges;

  /// No description provided for @album.
  ///
  /// In ar, this message translates to:
  /// **'ألبوم الستيكرز'**
  String get album;

  /// No description provided for @albumLead.
  ///
  /// In ar, this message translates to:
  /// **'كل صندوق بتفتحه بيجيبلك ستيكر جديد.'**
  String get albumLead;

  /// No description provided for @newSticker.
  ///
  /// In ar, this message translates to:
  /// **'ستيكر جديد في ألبومك!'**
  String get newSticker;

  /// No description provided for @rewardKids.
  ///
  /// In ar, this message translates to:
  /// **'برافو عليك!'**
  String get rewardKids;

  /// No description provided for @rewardTitle.
  ///
  /// In ar, this message translates to:
  /// **'أحسنت!'**
  String get rewardTitle;

  /// No description provided for @rankUp.
  ///
  /// In ar, this message translates to:
  /// **'رتبة جديدة: {rank}'**
  String rankUp(String rank);

  /// No description provided for @awesome.
  ///
  /// In ar, this message translates to:
  /// **'تمام!'**
  String get awesome;

  /// No description provided for @yourPoints.
  ///
  /// In ar, this message translates to:
  /// **'نقطك'**
  String get yourPoints;

  /// No description provided for @yourGems.
  ///
  /// In ar, this message translates to:
  /// **'جواهرك'**
  String get yourGems;

  /// No description provided for @pointsWord.
  ///
  /// In ar, this message translates to:
  /// **'النقط'**
  String get pointsWord;

  /// No description provided for @thisWeek.
  ///
  /// In ar, this message translates to:
  /// **'الأسبوع ده'**
  String get thisWeek;

  /// No description provided for @readyCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{جايزة مستنياك} =2{جايزتين مستنيينك} few{{n} جوايز مستنياك} other{{n} جايزة مستنياك}}'**
  String readyCount(int n);

  /// No description provided for @cheerFrom.
  ///
  /// In ar, this message translates to:
  /// **'تشجيع من {name}!'**
  String cheerFrom(String name);

  /// No description provided for @cheerThanks.
  ///
  /// In ar, this message translates to:
  /// **'شكرًا!'**
  String get cheerThanks;

  /// No description provided for @cheerClap.
  ///
  /// In ar, this message translates to:
  /// **'تصفيقة'**
  String get cheerClap;

  /// No description provided for @cheerStar.
  ///
  /// In ar, this message translates to:
  /// **'نجمة'**
  String get cheerStar;

  /// No description provided for @cheerHeart.
  ///
  /// In ar, this message translates to:
  /// **'قلب'**
  String get cheerHeart;

  /// No description provided for @cheerFire.
  ///
  /// In ar, this message translates to:
  /// **'شعلة حماس'**
  String get cheerFire;

  /// No description provided for @childChallenges.
  ///
  /// In ar, this message translates to:
  /// **'تحديات {name}'**
  String childChallenges(String name);

  /// No description provided for @myChallenges.
  ///
  /// In ar, this message translates to:
  /// **'تحدياتي'**
  String get myChallenges;

  /// No description provided for @cheerOn.
  ///
  /// In ar, this message translates to:
  /// **'شجّع {name}'**
  String cheerOn(String name);

  /// No description provided for @cheerSent.
  ///
  /// In ar, this message translates to:
  /// **'التشجيع وصل لـ {name}!'**
  String cheerSent(String name);

  /// No description provided for @cheersLeft.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{خلّصت تشجيعات النهارده} =1{فاضل تشجيع واحد النهارده} =2{فاضل تشجيعين النهارده} few{فاضل {n} تشجيعات النهارده} other{فاضل {n} تشجيع النهارده}}'**
  String cheersLeft(int n);

  /// No description provided for @seeAll.
  ///
  /// In ar, this message translates to:
  /// **'شوف الكل'**
  String get seeAll;

  /// No description provided for @allBadges.
  ///
  /// In ar, this message translates to:
  /// **'كل الإنجازات'**
  String get allBadges;

  /// No description provided for @sounds.
  ///
  /// In ar, this message translates to:
  /// **'الأصوات'**
  String get sounds;

  /// No description provided for @soundsHint.
  ///
  /// In ar, this message translates to:
  /// **'أصوات خفيفة لما تدوس أو تستلم جايزة'**
  String get soundsHint;

  /// No description provided for @reportsOpen.
  ///
  /// In ar, this message translates to:
  /// **'مستنية منك'**
  String get reportsOpen;

  /// No description provided for @reportsSent.
  ///
  /// In ar, this message translates to:
  /// **'اتبعتت'**
  String get reportsSent;

  /// No description provided for @noReportsWaiting.
  ///
  /// In ar, this message translates to:
  /// **'مفيش تقارير مستنياك. برافو!'**
  String get noReportsWaiting;

  /// No description provided for @reportLesson.
  ///
  /// In ar, this message translates to:
  /// **'تقرير حصة'**
  String get reportLesson;

  /// No description provided for @reportAbsence.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة غياب'**
  String get reportAbsence;

  /// No description provided for @todaysPlan.
  ///
  /// In ar, this message translates to:
  /// **'خطة النهارده'**
  String get todaysPlan;

  /// No description provided for @planHint.
  ///
  /// In ar, this message translates to:
  /// **'اختار اللي حصل في كل درس.'**
  String get planHint;

  /// No description provided for @reasonNext.
  ///
  /// In ar, this message translates to:
  /// **'الدرس الجاي'**
  String get reasonNext;

  /// No description provided for @reasonReview.
  ///
  /// In ar, this message translates to:
  /// **'مراجعة'**
  String get reasonReview;

  /// No description provided for @outcomeIntroduced.
  ///
  /// In ar, this message translates to:
  /// **'اتشرح'**
  String get outcomeIntroduced;

  /// No description provided for @outcomePracticed.
  ///
  /// In ar, this message translates to:
  /// **'اتدرّب عليه'**
  String get outcomePracticed;

  /// No description provided for @outcomeMastered.
  ///
  /// In ar, this message translates to:
  /// **'اتقن'**
  String get outcomeMastered;

  /// No description provided for @outcomeNeedsReview.
  ///
  /// In ar, this message translates to:
  /// **'محتاج مراجعة'**
  String get outcomeNeedsReview;

  /// No description provided for @itemScore.
  ///
  /// In ar, this message translates to:
  /// **'الدرجة'**
  String get itemScore;

  /// No description provided for @itemNote.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة على الدرس'**
  String get itemNote;

  /// No description provided for @addNote.
  ///
  /// In ar, this message translates to:
  /// **'ضيف ملاحظة'**
  String get addNote;

  /// No description provided for @addLesson.
  ///
  /// In ar, this message translates to:
  /// **'ضيف درس'**
  String get addLesson;

  /// No description provided for @pickLesson.
  ///
  /// In ar, this message translates to:
  /// **'اختار درس'**
  String get pickLesson;

  /// No description provided for @searchLessons.
  ///
  /// In ar, this message translates to:
  /// **'دوّر على درس'**
  String get searchLessons;

  /// No description provided for @remove.
  ///
  /// In ar, this message translates to:
  /// **'شيل'**
  String get remove;

  /// No description provided for @notesForFamily.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظات للطالب وولي الأمر'**
  String get notesForFamily;

  /// No description provided for @notesInternal.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظات داخلية'**
  String get notesInternal;

  /// No description provided for @notesInternalHint.
  ///
  /// In ar, this message translates to:
  /// **'للأكاديمية بس، الأسرة مابتشوفهاش.'**
  String get notesInternalHint;

  /// No description provided for @absenceNote.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة الغياب'**
  String get absenceNote;

  /// No description provided for @absenceHint.
  ///
  /// In ar, this message translates to:
  /// **'الطالب ماحضرش. اكتب اللي حصل وأي حاجة الأسرة محتاجة تعرفها.'**
  String get absenceHint;

  /// No description provided for @giveHomework.
  ///
  /// In ar, this message translates to:
  /// **'ادّي واجب'**
  String get giveHomework;

  /// No description provided for @homeworkField.
  ///
  /// In ar, this message translates to:
  /// **'الواجب'**
  String get homeworkField;

  /// No description provided for @homeworkDetails.
  ///
  /// In ar, this message translates to:
  /// **'التفاصيل'**
  String get homeworkDetails;

  /// No description provided for @acceptsLabel.
  ///
  /// In ar, this message translates to:
  /// **'بيتسلّم'**
  String get acceptsLabel;

  /// No description provided for @acceptsAny.
  ///
  /// In ar, this message translates to:
  /// **'أي حاجة'**
  String get acceptsAny;

  /// No description provided for @acceptsText.
  ///
  /// In ar, this message translates to:
  /// **'كتابة'**
  String get acceptsText;

  /// No description provided for @acceptsAudio.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل صوت'**
  String get acceptsAudio;

  /// No description provided for @acceptsFile.
  ///
  /// In ar, this message translates to:
  /// **'ملف'**
  String get acceptsFile;

  /// No description provided for @dueIn.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{بعد يوم} =2{بعد يومين} few{بعد {n} أيام} other{بعد {n} يوم}}'**
  String dueIn(int n);

  /// No description provided for @homeworkLocked.
  ///
  /// In ar, this message translates to:
  /// **'الطالب سلّم الواجب ده، فمش هيتغير.'**
  String get homeworkLocked;

  /// No description provided for @sendReport.
  ///
  /// In ar, this message translates to:
  /// **'ابعت التقرير'**
  String get sendReport;

  /// No description provided for @updateReport.
  ///
  /// In ar, this message translates to:
  /// **'حدّث التقرير'**
  String get updateReport;

  /// No description provided for @reportSent.
  ///
  /// In ar, this message translates to:
  /// **'التقرير اتبعت.'**
  String get reportSent;

  /// No description provided for @reportLocked.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية راجعت التقرير ده، فمش هيتغير.'**
  String get reportLocked;

  /// No description provided for @pickRatings.
  ///
  /// In ar, this message translates to:
  /// **'اختار التركيز والمستوى العام.'**
  String get pickRatings;

  /// No description provided for @pickOutcomes.
  ///
  /// In ar, this message translates to:
  /// **'اختار اللي حصل في كل درس.'**
  String get pickOutcomes;

  /// No description provided for @markTitle.
  ///
  /// In ar, this message translates to:
  /// **'تصحيح الواجبات'**
  String get markTitle;

  /// No description provided for @markWaiting.
  ///
  /// In ar, this message translates to:
  /// **'مستني التصحيح'**
  String get markWaiting;

  /// No description provided for @markDone.
  ///
  /// In ar, this message translates to:
  /// **'اتصحّح'**
  String get markDone;

  /// No description provided for @noHomeworkToMark.
  ///
  /// In ar, this message translates to:
  /// **'مفيش واجبات مستنية تصحيح.'**
  String get noHomeworkToMark;

  /// No description provided for @gradeLabel.
  ///
  /// In ar, this message translates to:
  /// **'الدرجة'**
  String get gradeLabel;

  /// No description provided for @feedbackLabel.
  ///
  /// In ar, this message translates to:
  /// **'كلمتك للطالب'**
  String get feedbackLabel;

  /// No description provided for @saveMark.
  ///
  /// In ar, this message translates to:
  /// **'سجّل التصحيح'**
  String get saveMark;

  /// No description provided for @marked.
  ///
  /// In ar, this message translates to:
  /// **'اتسجّل التصحيح.'**
  String get marked;

  /// No description provided for @handedInAt.
  ///
  /// In ar, this message translates to:
  /// **'اتسلّم {when}'**
  String handedInAt(String when);

  /// No description provided for @fileOnWeb.
  ///
  /// In ar, this message translates to:
  /// **'الملف ده بيتفتح من الموقع.'**
  String get fileOnWeb;

  /// No description provided for @showPicture.
  ///
  /// In ar, this message translates to:
  /// **'اعرض الصورة'**
  String get showPicture;

  /// No description provided for @playRecording.
  ///
  /// In ar, this message translates to:
  /// **'شغّل التسجيل'**
  String get playRecording;

  /// No description provided for @pause.
  ///
  /// In ar, this message translates to:
  /// **'وقّف'**
  String get pause;

  /// No description provided for @cannotPlay.
  ///
  /// In ar, this message translates to:
  /// **'مش قادرين نشغّل التسجيل ده هنا.'**
  String get cannotPlay;

  /// No description provided for @waitingTap.
  ///
  /// In ar, this message translates to:
  /// **'دوس عشان تبدأ'**
  String get waitingTap;

  /// No description provided for @recordVoice.
  ///
  /// In ar, this message translates to:
  /// **'سجّل صوتك'**
  String get recordVoice;

  /// No description provided for @recordKids.
  ///
  /// In ar, this message translates to:
  /// **'سجّل تسميعك'**
  String get recordKids;

  /// No description provided for @recordingNow.
  ///
  /// In ar, this message translates to:
  /// **'بيسجّل… {time}'**
  String recordingNow(String time);

  /// No description provided for @stopRecording.
  ///
  /// In ar, this message translates to:
  /// **'وقّف التسجيل'**
  String get stopRecording;

  /// No description provided for @recordAgain.
  ///
  /// In ar, this message translates to:
  /// **'سجّل تاني'**
  String get recordAgain;

  /// No description provided for @attachFile.
  ///
  /// In ar, this message translates to:
  /// **'ضيف ملف'**
  String get attachFile;

  /// No description provided for @attachPicture.
  ///
  /// In ar, this message translates to:
  /// **'ضيف صورة أو PDF'**
  String get attachPicture;

  /// No description provided for @micDenied.
  ///
  /// In ar, this message translates to:
  /// **'محتاجين إذن المايك عشان تسجّل. فعّله من إعدادات الموبايل.'**
  String get micDenied;

  /// No description provided for @recordingLimit.
  ///
  /// In ar, this message translates to:
  /// **'التسجيل وقف عند {n} دقايق.'**
  String recordingLimit(int n);

  /// No description provided for @fileTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 25 ميجا.'**
  String get fileTooBig;

  /// No description provided for @answerOptional.
  ///
  /// In ar, this message translates to:
  /// **'اكتب حاجة كمان (اختياري)'**
  String get answerOptional;

  /// No description provided for @myHours.
  ///
  /// In ar, this message translates to:
  /// **'مواعيدي'**
  String get myHours;

  /// No description provided for @hoursOnClock.
  ///
  /// In ar, this message translates to:
  /// **'على توقيت {zone}'**
  String hoursOnClock(String zone);

  /// No description provided for @weeklyHours.
  ///
  /// In ar, this message translates to:
  /// **'{h} في الأسبوع'**
  String weeklyHours(String h);

  /// No description provided for @currentWeek.
  ///
  /// In ar, this message translates to:
  /// **'المواعيد المعتمدة'**
  String get currentWeek;

  /// No description provided for @noHoursYet.
  ///
  /// In ar, this message translates to:
  /// **'لسه مفيش مواعيد معتمدة.'**
  String get noHoursYet;

  /// No description provided for @proposeHours.
  ///
  /// In ar, this message translates to:
  /// **'اقترح مواعيد جديدة'**
  String get proposeHours;

  /// No description provided for @yourProposal.
  ///
  /// In ar, this message translates to:
  /// **'مقترحك'**
  String get yourProposal;

  /// No description provided for @waitingOnAcademy.
  ///
  /// In ar, this message translates to:
  /// **'مستني رد الأكاديمية'**
  String get waitingOnAcademy;

  /// No description provided for @waitingOnYou.
  ///
  /// In ar, this message translates to:
  /// **'مستني ردك'**
  String get waitingOnYou;

  /// No description provided for @startsOn.
  ///
  /// In ar, this message translates to:
  /// **'يبدأ {date}'**
  String startsOn(String date);

  /// No description provided for @academyCounter.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية عدّلت مقترحك. ده اللي اتغير:'**
  String get academyCounter;

  /// No description provided for @timeAdded.
  ///
  /// In ar, this message translates to:
  /// **'اتضاف'**
  String get timeAdded;

  /// No description provided for @timeRemoved.
  ///
  /// In ar, this message translates to:
  /// **'اتشال'**
  String get timeRemoved;

  /// No description provided for @acceptCounter.
  ///
  /// In ar, this message translates to:
  /// **'موافق على التعديل'**
  String get acceptCounter;

  /// No description provided for @editProposal.
  ///
  /// In ar, this message translates to:
  /// **'عدّل المقترح'**
  String get editProposal;

  /// No description provided for @withdrawConfirm.
  ///
  /// In ar, this message translates to:
  /// **'تسحب المقترح؟ المواعيد المعتمدة هتفضل زي ما هي.'**
  String get withdrawConfirm;

  /// No description provided for @proposalAccepted.
  ///
  /// In ar, this message translates to:
  /// **'مواعيدك اتعتمدت.'**
  String get proposalAccepted;

  /// No description provided for @proposalSent.
  ///
  /// In ar, this message translates to:
  /// **'المقترح اتبعت للأكاديمية.'**
  String get proposalSent;

  /// No description provided for @draftSaved.
  ///
  /// In ar, this message translates to:
  /// **'المسودة اتحفظت.'**
  String get draftSaved;

  /// No description provided for @conversation.
  ///
  /// In ar, this message translates to:
  /// **'المحادثة مع الأكاديمية'**
  String get conversation;

  /// No description provided for @writeMessage.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رسالة…'**
  String get writeMessage;

  /// No description provided for @editHours.
  ///
  /// In ar, this message translates to:
  /// **'عدّل مواعيدك'**
  String get editHours;

  /// No description provided for @addTime.
  ///
  /// In ar, this message translates to:
  /// **'ضيف وقت'**
  String get addTime;

  /// No description provided for @dayOff.
  ///
  /// In ar, this message translates to:
  /// **'مفيش'**
  String get dayOff;

  /// No description provided for @effectiveFrom.
  ///
  /// In ar, this message translates to:
  /// **'يبدأ من'**
  String get effectiveFrom;

  /// No description provided for @reduceNotice.
  ///
  /// In ar, this message translates to:
  /// **'لو هتقلل ساعات، أقرب بداية {date}.'**
  String reduceNotice(String date);

  /// No description provided for @minHours.
  ///
  /// In ar, this message translates to:
  /// **'أقل ساعات في الأسبوع'**
  String get minHours;

  /// No description provided for @maxHours.
  ///
  /// In ar, this message translates to:
  /// **'أكتر ساعات في الأسبوع'**
  String get maxHours;

  /// No description provided for @proposalNote.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة للأكاديمية'**
  String get proposalNote;

  /// No description provided for @saveDraft.
  ///
  /// In ar, this message translates to:
  /// **'احفظ مسودة'**
  String get saveDraft;

  /// No description provided for @sendProposal.
  ///
  /// In ar, this message translates to:
  /// **'ابعت للأكاديمية'**
  String get sendProposal;

  /// No description provided for @endAfterStart.
  ///
  /// In ar, this message translates to:
  /// **'وقت النهاية لازم يكون بعد البداية.'**
  String get endAfterStart;

  /// No description provided for @untilMidnight.
  ///
  /// In ar, this message translates to:
  /// **'لحد نص الليل'**
  String get untilMidnight;

  /// No description provided for @inboxTitle.
  ///
  /// In ar, this message translates to:
  /// **'الإشعارات'**
  String get inboxTitle;

  /// No description provided for @markAllRead.
  ///
  /// In ar, this message translates to:
  /// **'علّم الكل مقروء'**
  String get markAllRead;

  /// No description provided for @noNotifications.
  ///
  /// In ar, this message translates to:
  /// **'مفيش إشعارات.'**
  String get noNotifications;

  /// No description provided for @loadMore.
  ///
  /// In ar, this message translates to:
  /// **'اعرض أكتر'**
  String get loadMore;

  /// No description provided for @notificationChoices.
  ///
  /// In ar, this message translates to:
  /// **'اختيارات الإشعارات'**
  String get notificationChoices;

  /// No description provided for @saved.
  ///
  /// In ar, this message translates to:
  /// **'اتحفظ.'**
  String get saved;

  /// No description provided for @openIt.
  ///
  /// In ar, this message translates to:
  /// **'افتح'**
  String get openIt;

  /// No description provided for @unreadCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش جديد} =1{إشعار جديد} =2{إشعارين جداد} few{{n} إشعارات جديدة} other{{n} إشعار جديد}}'**
  String unreadCount(int n);

  /// No description provided for @updateTitle.
  ///
  /// In ar, this message translates to:
  /// **'فيه نسخة جديدة من التطبيق'**
  String get updateTitle;

  /// No description provided for @updateBody.
  ///
  /// In ar, this message translates to:
  /// **'النسخة اللي عندك قديمة ومش هتشتغل مع الأكاديمية. حدّثها عشان تكمل.'**
  String get updateBody;

  /// No description provided for @updateNow.
  ///
  /// In ar, this message translates to:
  /// **'حدّث التطبيق'**
  String get updateNow;

  /// No description provided for @updateFromStore.
  ///
  /// In ar, this message translates to:
  /// **'حدّثه من متجر التطبيقات على موبايلك.'**
  String get updateFromStore;
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) => <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
