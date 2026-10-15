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

  /// No description provided for @errorOfflineTitle.
  ///
  /// In ar, this message translates to:
  /// **'مفيش نت'**
  String get errorOfflineTitle;

  /// No description provided for @errorServerTitle.
  ///
  /// In ar, this message translates to:
  /// **'حصلت مشكلة عندنا'**
  String get errorServerTitle;

  /// No description provided for @errorServerBody.
  ///
  /// In ar, this message translates to:
  /// **'بنصلّحها. جرّب كمان شوية.'**
  String get errorServerBody;

  /// No description provided for @errorForbiddenTitle.
  ///
  /// In ar, this message translates to:
  /// **'مش مسموح تشوف ده'**
  String get errorForbiddenTitle;

  /// No description provided for @errorForbiddenBody.
  ///
  /// In ar, this message translates to:
  /// **'لو شايف إن ده غلط، كلّم الأكاديمية.'**
  String get errorForbiddenBody;

  /// No description provided for @reloadFailed.
  ///
  /// In ar, this message translates to:
  /// **'ماقدرناش نحدّث، واللي قدامك ممكن يكون قديم.'**
  String get reloadFailed;

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
  /// **'{n, plural, =1{دقيقة} =2{دقيقتين} few{{n} دقايق} other{{n} دقيقة}}'**
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

  /// No description provided for @introSkip.
  ///
  /// In ar, this message translates to:
  /// **'تخطّي'**
  String get introSkip;

  /// No description provided for @introNext.
  ///
  /// In ar, this message translates to:
  /// **'التالي'**
  String get introNext;

  /// No description provided for @introStart.
  ///
  /// In ar, this message translates to:
  /// **'يلا نبدأ'**
  String get introStart;

  /// No description provided for @introSlide.
  ///
  /// In ar, this message translates to:
  /// **'شريحة {n} من {total}'**
  String introSlide(int n, int total);

  /// No description provided for @introLive.
  ///
  /// In ar, this message translates to:
  /// **'لايف'**
  String get introLive;

  /// No description provided for @intro1Title.
  ///
  /// In ar, this message translates to:
  /// **'حصص لايف مع مدرّسك'**
  String get intro1Title;

  /// No description provided for @intro1Body.
  ///
  /// In ar, this message translates to:
  /// **'حصة واحد لواحد بالصوت والصورة، من البيت.'**
  String get intro1Body;

  /// No description provided for @intro2Title.
  ///
  /// In ar, this message translates to:
  /// **'تابع التقدّم خطوة بخطوة'**
  String get intro2Title;

  /// No description provided for @intro2Body.
  ///
  /// In ar, this message translates to:
  /// **'كل حصة بتقرّب من الهدف، والطريق كله قدامك.'**
  String get intro2Body;

  /// No description provided for @intro3Title.
  ///
  /// In ar, this message translates to:
  /// **'المواعيد والواجبات والفلوس في مكان واحد'**
  String get intro3Title;

  /// No description provided for @intro3Body.
  ///
  /// In ar, this message translates to:
  /// **'والتنبيه بيوصلك في وقته، فمفيش حاجة بتفوتك.'**
  String get intro3Body;

  /// No description provided for @introLessonWhen.
  ///
  /// In ar, this message translates to:
  /// **'السبت · 7:00 م'**
  String get introLessonWhen;

  /// No description provided for @introHomework.
  ///
  /// In ar, this message translates to:
  /// **'واجب جديد'**
  String get introHomework;

  /// No description provided for @introHomeworkWhen.
  ///
  /// In ar, this message translates to:
  /// **'قبل الثلاثاء'**
  String get introHomeworkWhen;

  /// No description provided for @introPaid.
  ///
  /// In ar, this message translates to:
  /// **'الفاتورة اتدفعت'**
  String get introPaid;

  /// No description provided for @introThanks.
  ///
  /// In ar, this message translates to:
  /// **'شكرًا ليك'**
  String get introThanks;

  /// No description provided for @welcomeTitle.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا بيك'**
  String get welcomeTitle;

  /// No description provided for @welcomeLead.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رقم موبايلك أو إيميلك، وهنبعتلك كود تدخل بيه.'**
  String get welcomeLead;

  /// No description provided for @contactLabel.
  ///
  /// In ar, this message translates to:
  /// **'رقم الموبايل أو الإيميل'**
  String get contactLabel;

  /// No description provided for @contactPlaceholder.
  ///
  /// In ar, this message translates to:
  /// **'01xxxxxxxxx أو name@mail.com'**
  String get contactPlaceholder;

  /// No description provided for @contactEmpty.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رقم موبايلك أو إيميلك الأول.'**
  String get contactEmpty;

  /// No description provided for @contactShortPhone.
  ///
  /// In ar, this message translates to:
  /// **'الرقم ده ناقص، اكتبه كامل.'**
  String get contactShortPhone;

  /// No description provided for @contactBadEmail.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل ده ناقص حاجة، زي name@mail.com'**
  String get contactBadEmail;

  /// No description provided for @countryChange.
  ///
  /// In ar, this message translates to:
  /// **'كود الدولة {code}، دوس عشان تغيّره'**
  String countryChange(String code);

  /// No description provided for @countrySearch.
  ///
  /// In ar, this message translates to:
  /// **'دوّر على دولة'**
  String get countrySearch;

  /// No description provided for @countryNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش دولة بالاسم ده.'**
  String get countryNone;

  /// No description provided for @countrySa.
  ///
  /// In ar, this message translates to:
  /// **'السعودية'**
  String get countrySa;

  /// No description provided for @countryEg.
  ///
  /// In ar, this message translates to:
  /// **'مصر'**
  String get countryEg;

  /// No description provided for @countryAe.
  ///
  /// In ar, this message translates to:
  /// **'الإمارات'**
  String get countryAe;

  /// No description provided for @countryKw.
  ///
  /// In ar, this message translates to:
  /// **'الكويت'**
  String get countryKw;

  /// No description provided for @countryQa.
  ///
  /// In ar, this message translates to:
  /// **'قطر'**
  String get countryQa;

  /// No description provided for @countryBh.
  ///
  /// In ar, this message translates to:
  /// **'البحرين'**
  String get countryBh;

  /// No description provided for @countryOm.
  ///
  /// In ar, this message translates to:
  /// **'عُمان'**
  String get countryOm;

  /// No description provided for @countryJo.
  ///
  /// In ar, this message translates to:
  /// **'الأردن'**
  String get countryJo;

  /// No description provided for @countryMa.
  ///
  /// In ar, this message translates to:
  /// **'المغرب'**
  String get countryMa;

  /// No description provided for @countryUs.
  ///
  /// In ar, this message translates to:
  /// **'أمريكا وكندا'**
  String get countryUs;

  /// No description provided for @countryGb.
  ///
  /// In ar, this message translates to:
  /// **'بريطانيا'**
  String get countryGb;

  /// No description provided for @legalAgree.
  ///
  /// In ar, this message translates to:
  /// **'لما تكمّل، تبقى موافق على'**
  String get legalAgree;

  /// No description provided for @legalAnd.
  ///
  /// In ar, this message translates to:
  /// **'و'**
  String get legalAnd;

  /// No description provided for @countryCode.
  ///
  /// In ar, this message translates to:
  /// **'كود الدولة'**
  String get countryCode;

  /// No description provided for @phoneHint.
  ///
  /// In ar, this message translates to:
  /// **'الكود هيوصلك على واتساب.'**
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

  /// No description provided for @signInOr.
  ///
  /// In ar, this message translates to:
  /// **'أو'**
  String get signInOr;

  /// No description provided for @signInWithGoogle.
  ///
  /// In ar, this message translates to:
  /// **'ادخل بحساب جوجل'**
  String get signInWithGoogle;

  /// No description provided for @signInWithApple.
  ///
  /// In ar, this message translates to:
  /// **'ادخل بحساب أبل'**
  String get signInWithApple;

  /// No description provided for @socialFailed.
  ///
  /// In ar, this message translates to:
  /// **'الدخول بحساب {provider} ماكملش. جرّب تاني.'**
  String socialFailed(String provider);

  /// No description provided for @providerGoogle.
  ///
  /// In ar, this message translates to:
  /// **'جوجل'**
  String get providerGoogle;

  /// No description provided for @providerApple.
  ///
  /// In ar, this message translates to:
  /// **'أبل'**
  String get providerApple;

  /// No description provided for @signInMethodsTitle.
  ///
  /// In ar, this message translates to:
  /// **'الدخول بجوجل وأبل'**
  String get signInMethodsTitle;

  /// No description provided for @signInMethodsHint.
  ///
  /// In ar, this message translates to:
  /// **'اربط حسابك بجوجل أو أبل، وادخل بيهم من غير كود ولا كلمة سر.'**
  String get signInMethodsHint;

  /// No description provided for @signInMethodLinkedAs.
  ///
  /// In ar, this message translates to:
  /// **'مربوط: {email}'**
  String signInMethodLinkedAs(String email);

  /// No description provided for @signInMethodLinked.
  ///
  /// In ar, this message translates to:
  /// **'مربوط'**
  String get signInMethodLinked;

  /// No description provided for @signInMethodNotLinked.
  ///
  /// In ar, this message translates to:
  /// **'مش مربوط'**
  String get signInMethodNotLinked;

  /// No description provided for @signInMethodLink.
  ///
  /// In ar, this message translates to:
  /// **'اربط'**
  String get signInMethodLink;

  /// No description provided for @signInMethodUnlink.
  ///
  /// In ar, this message translates to:
  /// **'افصل'**
  String get signInMethodUnlink;

  /// No description provided for @signInMethodUnlinkConfirm.
  ///
  /// In ar, this message translates to:
  /// **'تفصل حساب {provider}؟ مش هتقدر تدخل بيه لحد ما تربطه تاني.'**
  String signInMethodUnlinkConfirm(String provider);

  /// No description provided for @signInMethodLinkedDone.
  ///
  /// In ar, this message translates to:
  /// **'حساب {provider} اتربط.'**
  String signInMethodLinkedDone(String provider);

  /// No description provided for @signInMethodUnlinkedDone.
  ///
  /// In ar, this message translates to:
  /// **'حساب {provider} اتفصل.'**
  String signInMethodUnlinkedDone(String provider);

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

  /// No description provided for @codePaste.
  ///
  /// In ar, this message translates to:
  /// **'الصق الكود'**
  String get codePaste;

  /// No description provided for @codeChecking.
  ///
  /// In ar, this message translates to:
  /// **'بنتأكد…'**
  String get codeChecking;

  /// No description provided for @codePasteNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش كود من 6 أرقام منسوخ.'**
  String get codePasteNone;

  /// No description provided for @codeRight.
  ///
  /// In ar, this message translates to:
  /// **'تمام!'**
  String get codeRight;

  /// No description provided for @codeResent.
  ///
  /// In ar, this message translates to:
  /// **'بعتنالك كود جديد.'**
  String get codeResent;

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

  /// No description provided for @accountsLegal.
  ///
  /// In ar, this message translates to:
  /// **'قبل ما تضيف حسابك، اقرا سياسة الخصوصية وشروط الاستخدام بتاعة الأكاديمية: هي اللي بتقرر إيه اللي بيحصل لبياناتك عندها.'**
  String get accountsLegal;

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

  /// No description provided for @foundYou.
  ///
  /// In ar, this message translates to:
  /// **'لقيناك!'**
  String get foundYou;

  /// No description provided for @foundYouLead.
  ///
  /// In ar, this message translates to:
  /// **'لقينا حساب واحد ليك.'**
  String get foundYouLead;

  /// No description provided for @enterAcademy.
  ///
  /// In ar, this message translates to:
  /// **'ادخل {academy}'**
  String enterAcademy(String academy);

  /// No description provided for @notYou.
  ///
  /// In ar, this message translates to:
  /// **'مش حسابك؟ استخدم رقم أو إيميل تاني'**
  String get notYou;

  /// No description provided for @pickOne.
  ///
  /// In ar, this message translates to:
  /// **'اختار حساب واحد على الأقل.'**
  String get pickOne;

  /// No description provided for @notAddedTitle.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{دخلت، بس فيه حساب ماتضافش:} other{دخلت، بس فيه حسابات ماتضافتش:}}'**
  String notAddedTitle(int n);

  /// No description provided for @notAddedLine.
  ///
  /// In ar, this message translates to:
  /// **'{name} في {academy}: {reason}'**
  String notAddedLine(String name, String academy, String reason);

  /// No description provided for @noAccounts.
  ///
  /// In ar, this message translates to:
  /// **'مالقيناش حساب بالرقم أو الإيميل ده في أي أكاديمية. اتأكد منه، أو كلّم أكاديميتك.'**
  String get noAccounts;

  /// No description provided for @noAccountTitle.
  ///
  /// In ar, this message translates to:
  /// **'مالقيناش حساب'**
  String get noAccountTitle;

  /// No description provided for @noAccountByPhone.
  ///
  /// In ar, this message translates to:
  /// **'مالقيناش حساب بالرقم ده'**
  String get noAccountByPhone;

  /// No description provided for @noAccountByEmail.
  ///
  /// In ar, this message translates to:
  /// **'مالقيناش حساب بالإيميل ده'**
  String get noAccountByEmail;

  /// No description provided for @noAccountCheckPhone.
  ///
  /// In ar, this message translates to:
  /// **'اتأكد إن الرقم مكتوب صح. ويمكن الأكاديمية مسجلاك برقم أو إيميل تاني، فجرّبه أو كلّمها.'**
  String get noAccountCheckPhone;

  /// No description provided for @noAccountCheckEmail.
  ///
  /// In ar, this message translates to:
  /// **'اتأكد إن الإيميل مكتوب صح. ويمكن الأكاديمية مسجلاك برقم أو إيميل تاني، فجرّبه أو كلّمها.'**
  String get noAccountCheckEmail;

  /// No description provided for @noAccountTryEmail.
  ///
  /// In ar, this message translates to:
  /// **'جرّب بالإيميل'**
  String get noAccountTryEmail;

  /// No description provided for @noAccountTryEmailBody.
  ///
  /// In ar, this message translates to:
  /// **'لو الأكاديمية عندها إيميلك، الكود هيوصلك عليه.'**
  String get noAccountTryEmailBody;

  /// No description provided for @noAccountOther.
  ///
  /// In ar, this message translates to:
  /// **'جرّب رقم أو إيميل تاني'**
  String get noAccountOther;

  /// No description provided for @contactChange.
  ///
  /// In ar, this message translates to:
  /// **'غيّره'**
  String get contactChange;

  /// No description provided for @consentNeededTitle.
  ///
  /// In ar, this message translates to:
  /// **'محتاجين موافقة ولي أمرك'**
  String get consentNeededTitle;

  /// No description provided for @consentNeededLead.
  ///
  /// In ar, this message translates to:
  /// **'قبل ما تدخل حسابك، ولي أمرك لازم يوافق مرة واحدة بس. وبعدها تدخل على طول.'**
  String get consentNeededLead;

  /// No description provided for @consentNeededStep1.
  ///
  /// In ar, this message translates to:
  /// **'ولي أمرك يفتح التطبيق بحسابه.'**
  String get consentNeededStep1;

  /// No description provided for @consentNeededStep2.
  ///
  /// In ar, this message translates to:
  /// **'من «{me}» ثم «{privacy}» يوافق على حسابك.'**
  String consentNeededStep2(String me, String privacy);

  /// No description provided for @consentNeededStep3.
  ///
  /// In ar, this message translates to:
  /// **'ارجع هنا وادخل تاني.'**
  String get consentNeededStep3;

  /// No description provided for @signInAgain.
  ///
  /// In ar, this message translates to:
  /// **'جرّب تدخل تاني'**
  String get signInAgain;

  /// No description provided for @twoFactorSetupTitle.
  ///
  /// In ar, this message translates to:
  /// **'فعّل الدخول بخطوتين الأول'**
  String get twoFactorSetupTitle;

  /// No description provided for @twoFactorSetupLead.
  ///
  /// In ar, this message translates to:
  /// **'حسابك ده لازم يكون عليه الدخول بخطوتين. فعّله من موقع الأكاديمية على الكمبيوتر، وبعدها ادخل من هنا عادي.'**
  String get twoFactorSetupLead;

  /// No description provided for @tooManyTitle.
  ///
  /// In ar, this message translates to:
  /// **'جرّبت كتير'**
  String get tooManyTitle;

  /// No description provided for @tooManyBody.
  ///
  /// In ar, this message translates to:
  /// **'علشان نحمي حسابك، وقّفنا المحاولات شوية. استنى لحد ما العدّاد يخلص، وبعدها جرّب تاني.'**
  String get tooManyBody;

  /// No description provided for @retryIn.
  ///
  /// In ar, this message translates to:
  /// **'جرّب تاني بعد {time}'**
  String retryIn(String time);

  /// No description provided for @welcomeHello.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا يا {name}'**
  String welcomeHello(String name);

  /// No description provided for @welcomeIn.
  ///
  /// In ar, this message translates to:
  /// **'إنت دلوقتي في {academy}'**
  String welcomeIn(String academy);

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

  /// No description provided for @academyCodeMissing.
  ///
  /// In ar, this message translates to:
  /// **'اكتب كود الأكاديمية.'**
  String get academyCodeMissing;

  /// No description provided for @loginMissing.
  ///
  /// In ar, this message translates to:
  /// **'اكتب إيميلك أو موبايلك أو اسم المستخدم.'**
  String get loginMissing;

  /// No description provided for @passwordMissing.
  ///
  /// In ar, this message translates to:
  /// **'اكتب كلمة السر.'**
  String get passwordMissing;

  /// No description provided for @passwordShow.
  ///
  /// In ar, this message translates to:
  /// **'إظهار كلمة السر'**
  String get passwordShow;

  /// No description provided for @passwordHide.
  ///
  /// In ar, this message translates to:
  /// **'إخفاء كلمة السر'**
  String get passwordHide;

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

  /// No description provided for @privacyTitle.
  ///
  /// In ar, this message translates to:
  /// **'خصوصية الأولاد'**
  String get privacyTitle;

  /// No description provided for @privacyLead.
  ///
  /// In ar, this message translates to:
  /// **'الأولاد اللي تحت {age, plural, =1{سنة} =2{سنتين} few{{age} سنين} other{{age} سنة}} محتاجين موافقتك عشان يدخلوا حساباتهم ويحضروا بنفسهم.'**
  String privacyLead(int age);

  /// No description provided for @privacyNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش ولاد محتاجين موافقة.'**
  String get privacyNone;

  /// No description provided for @consentWaiting.
  ///
  /// In ar, this message translates to:
  /// **'مستني موافقتك'**
  String get consentWaiting;

  /// No description provided for @consentGranted.
  ///
  /// In ar, this message translates to:
  /// **'موافق عليه'**
  String get consentGranted;

  /// No description provided for @consentAgree.
  ///
  /// In ar, this message translates to:
  /// **'موافق'**
  String get consentAgree;

  /// No description provided for @consentAgreed.
  ///
  /// In ar, this message translates to:
  /// **'تمام، {name} يقدر يدخل دلوقتي.'**
  String consentAgreed(String name);

  /// No description provided for @consentWithdraw.
  ///
  /// In ar, this message translates to:
  /// **'اسحب الموافقة'**
  String get consentWithdraw;

  /// No description provided for @consentWithdrawConfirm.
  ///
  /// In ar, this message translates to:
  /// **'{name} هيخرج من حسابه على كل الأجهزة، ومش هيقدر يدخل لحد ما توافق تاني. متأكد؟'**
  String consentWithdrawConfirm(String name);

  /// No description provided for @consentWithdrawn.
  ///
  /// In ar, this message translates to:
  /// **'سحبت موافقتك، و{name} خرج من حسابه.'**
  String consentWithdrawn(String name);

  /// No description provided for @consentNudge.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{{names} مستني موافقتك عشان يدخل حسابه.} other{{names} مستنيين موافقتك عشان يدخلوا حساباتهم.}}'**
  String consentNudge(int n, String names);

  /// No description provided for @consentNudgeAction.
  ///
  /// In ar, this message translates to:
  /// **'اقرا ووافق'**
  String get consentNudgeAction;

  /// No description provided for @myDataTitle.
  ///
  /// In ar, this message translates to:
  /// **'بياناتي'**
  String get myDataTitle;

  /// No description provided for @myDataLead.
  ///
  /// In ar, this message translates to:
  /// **'كل اللي الأكاديمية محتفظة بيه عنك وعن ولادك: صحّح بياناتك، أو نزّل نسخة، أو اطلب مسح حساب.'**
  String get myDataLead;

  /// No description provided for @myDataLeadTeacher.
  ///
  /// In ar, this message translates to:
  /// **'كل اللي الأكاديمية محتفظة بيه عنك: صحّح اسمك، أو نزّل نسخة، أو اطلب مسح حسابك.'**
  String get myDataLeadTeacher;

  /// No description provided for @myDataYoung.
  ///
  /// In ar, this message translates to:
  /// **'ولي أمرك هو اللي بيطلب بخصوص بياناتك من حسابه.'**
  String get myDataYoung;

  /// No description provided for @myDataCopyTitle.
  ///
  /// In ar, this message translates to:
  /// **'نسخة من البيانات'**
  String get myDataCopyTitle;

  /// No description provided for @myDataCopyBody.
  ///
  /// In ar, this message translates to:
  /// **'ملف ZIP واحد: البيانات في ملف يتقري، ومعاها الملفات اللي اتسلمت. بتجهز في دقايق وبتفضل {days, plural, =1{يوم} =2{يومين} few{{days} أيام} other{{days} يوم}}.'**
  String myDataCopyBody(int days);

  /// No description provided for @myDataCopyAction.
  ///
  /// In ar, this message translates to:
  /// **'جهّز نسخة'**
  String get myDataCopyAction;

  /// No description provided for @myDataCopyPreparing.
  ///
  /// In ar, this message translates to:
  /// **'بتتجهز: هنقولك أول ما تخلص.'**
  String get myDataCopyPreparing;

  /// No description provided for @myDataCopyDownload.
  ///
  /// In ar, this message translates to:
  /// **'نزّل'**
  String get myDataCopyDownload;

  /// No description provided for @myDataCopyUntil.
  ///
  /// In ar, this message translates to:
  /// **'النسخة جاهزة لحد {when}'**
  String myDataCopyUntil(String when);

  /// No description provided for @myDataEraseTitle.
  ///
  /// In ar, this message translates to:
  /// **'مسح الحساب'**
  String get myDataEraseTitle;

  /// No description provided for @myDataEraseBody.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية بترد في خلال {days, plural, =1{يوم} =2{يومين} few{{days} أيام} other{{days} يوم}}، ولما توافق الحساب بيتمسح بعدها بيوم.'**
  String myDataEraseBody(int days);

  /// No description provided for @myDataEraseAction.
  ///
  /// In ar, this message translates to:
  /// **'امسح الحساب ده'**
  String get myDataEraseAction;

  /// No description provided for @myDataEraseConfirm.
  ///
  /// In ar, this message translates to:
  /// **'تطلب مسح حساب {name}؟'**
  String myDataEraseConfirm(String name);

  /// No description provided for @myDataEraseCopyFirst.
  ///
  /// In ar, this message translates to:
  /// **'تقدر تنزّل نسخة الأول.'**
  String get myDataEraseCopyFirst;

  /// No description provided for @myDataEraseReason.
  ///
  /// In ar, this message translates to:
  /// **'ليه؟ (لو حابب)'**
  String get myDataEraseReason;

  /// No description provided for @myDataEraseWaiting.
  ///
  /// In ar, this message translates to:
  /// **'اتطلب: الأكاديمية هترد قبل {when}.'**
  String myDataEraseWaiting(String when);

  /// No description provided for @myDataEraseApproved.
  ///
  /// In ar, this message translates to:
  /// **'اتوافق: الحساب هيتمسح {when}.'**
  String myDataEraseApproved(String when);

  /// No description provided for @myDataEraseRefused.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية رفضت: {answer}'**
  String myDataEraseRefused(String answer);

  /// No description provided for @myDataEraseCancel.
  ///
  /// In ar, this message translates to:
  /// **'ارجع في طلبك'**
  String get myDataEraseCancel;

  /// No description provided for @myDataEraseAsked.
  ///
  /// In ar, this message translates to:
  /// **'الطلب وصل للأكاديمية.'**
  String get myDataEraseAsked;

  /// No description provided for @myDataEraseCancelled.
  ///
  /// In ar, this message translates to:
  /// **'رجعت في الطلب: الحساب هيفضل.'**
  String get myDataEraseCancelled;

  /// No description provided for @myDataWhatGoes.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي بيتمسح وإيه اللي بيفضل'**
  String get myDataWhatGoes;

  /// No description provided for @myDataWhatStays.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي بيفضل، من غير اسم'**
  String get myDataWhatStays;

  /// No description provided for @myDataHistory.
  ///
  /// In ar, this message translates to:
  /// **'الطلبات'**
  String get myDataHistory;

  /// No description provided for @myDataNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش طلبات لسه.'**
  String get myDataNone;

  /// No description provided for @myDataAbout.
  ///
  /// In ar, this message translates to:
  /// **'عن {name}'**
  String myDataAbout(String name);

  /// No description provided for @myDataFixTitle.
  ///
  /// In ar, this message translates to:
  /// **'تصحيح البيانات'**
  String get myDataFixTitle;

  /// No description provided for @myDataFixEdit.
  ///
  /// In ar, this message translates to:
  /// **'صحّح'**
  String get myDataFixEdit;

  /// No description provided for @myDataFixEditTitle.
  ///
  /// In ar, this message translates to:
  /// **'تصحيح بيانات {name}'**
  String myDataFixEditTitle(String name);

  /// No description provided for @myDataFixSave.
  ///
  /// In ar, this message translates to:
  /// **'احفظ'**
  String get myDataFixSave;

  /// No description provided for @myDataFixSaved.
  ///
  /// In ar, this message translates to:
  /// **'اتحفظ.'**
  String get myDataFixSaved;

  /// No description provided for @myDataFixUnchanged.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حاجة اتغيرت.'**
  String get myDataFixUnchanged;

  /// No description provided for @myDataFixUnset.
  ///
  /// In ar, this message translates to:
  /// **'مش متسجل'**
  String get myDataFixUnset;

  /// No description provided for @myDataFixContact.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل ورقم الموبايل بتغيّرهم من «حسابي».'**
  String get myDataFixContact;

  /// No description provided for @myDataFixBody.
  ///
  /// In ar, this message translates to:
  /// **'حاجة تانية غلط (تاريخ الميلاد، اسم في فاتورة، كلام في تقرير)؟ اكتبها للأكاديمية، وهترد في خلال {days, plural, =1{يوم} =2{يومين} few{{days} أيام} other{{days} يوم}}.'**
  String myDataFixBody(int days);

  /// No description provided for @myDataFixAsk.
  ///
  /// In ar, this message translates to:
  /// **'اطلب تصحيح'**
  String get myDataFixAsk;

  /// No description provided for @myDataFixAskTitle.
  ///
  /// In ar, this message translates to:
  /// **'اطلب تصحيح بيانات {name}'**
  String myDataFixAskTitle(String name);

  /// No description provided for @myDataFixAskBody.
  ///
  /// In ar, this message translates to:
  /// **'اكتب إيه اللي غلط وإيه الصح. الأكاديمية بترد في خلال {days, plural, =1{يوم} =2{يومين} few{{days} أيام} other{{days} يوم}}، وبيوصلك ردها.'**
  String myDataFixAskBody(int days);

  /// No description provided for @myDataFixSays.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي محتاج يتصلح'**
  String get myDataFixSays;

  /// No description provided for @myDataFixPlaceholder.
  ///
  /// In ar, this message translates to:
  /// **'مثلًا: تاريخ الميلاد المتسجل 2016 والصح 2017.'**
  String get myDataFixPlaceholder;

  /// No description provided for @myDataFixTooShort.
  ///
  /// In ar, this message translates to:
  /// **'اكتب {min, plural, =1{حرف} =2{حرفين} few{{min} حروف} other{{min} حرف}} على الأقل.'**
  String myDataFixTooShort(int min);

  /// No description provided for @myDataFixSend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت الطلب'**
  String get myDataFixSend;

  /// No description provided for @myDataFixAsked.
  ///
  /// In ar, this message translates to:
  /// **'الطلب وصل للأكاديمية.'**
  String get myDataFixAsked;

  /// No description provided for @myDataFixWaiting.
  ///
  /// In ar, this message translates to:
  /// **'اتطلب: الأكاديمية هترد قبل {when}.'**
  String myDataFixWaiting(String when);

  /// No description provided for @myDataFixDone.
  ///
  /// In ar, this message translates to:
  /// **'اتصلح'**
  String get myDataFixDone;

  /// No description provided for @myDataFixDoneWith.
  ///
  /// In ar, this message translates to:
  /// **'اتصلح: {answer}'**
  String myDataFixDoneWith(String answer);

  /// No description provided for @myDataFixRefused.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية ماصلحتش: {answer}'**
  String myDataFixRefused(String answer);

  /// No description provided for @myDataFixCancel.
  ///
  /// In ar, this message translates to:
  /// **'الغي الطلب'**
  String get myDataFixCancel;

  /// No description provided for @myDataFixCancelled.
  ///
  /// In ar, this message translates to:
  /// **'الطلب اتلغى.'**
  String get myDataFixCancelled;

  /// No description provided for @myDataPrivacy.
  ///
  /// In ar, this message translates to:
  /// **'سياسة الخصوصية'**
  String get myDataPrivacy;

  /// No description provided for @myDataTerms.
  ///
  /// In ar, this message translates to:
  /// **'شروط الاستخدام'**
  String get myDataTerms;

  /// No description provided for @myDataDeletion.
  ///
  /// In ar, this message translates to:
  /// **'مسح الحساب'**
  String get myDataDeletion;

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

  /// No description provided for @navJourneyTab.
  ///
  /// In ar, this message translates to:
  /// **'الرحلة'**
  String get navJourneyTab;

  /// No description provided for @navMoney.
  ///
  /// In ar, this message translates to:
  /// **'الفلوس'**
  String get navMoney;

  /// No description provided for @moneyEyebrow.
  ///
  /// In ar, this message translates to:
  /// **'الفواتير والرصيد'**
  String get moneyEyebrow;

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
  /// **'الأسبوع'**
  String get navWeek;

  /// No description provided for @navStudents.
  ///
  /// In ar, this message translates to:
  /// **'الطلاب'**
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

  /// No description provided for @greetingHello.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا يا {name}'**
  String greetingHello(String name);

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

  /// No description provided for @startsIn.
  ///
  /// In ar, this message translates to:
  /// **'بعد {time}'**
  String startsIn(String time);

  /// No description provided for @roomOpensAt.
  ///
  /// In ar, this message translates to:
  /// **'الفصل بيفتح {time}'**
  String roomOpensAt(String time);

  /// No description provided for @balanceOf.
  ///
  /// In ar, this message translates to:
  /// **'رصيد {name}'**
  String balanceOf(String name);

  /// No description provided for @payShort.
  ///
  /// In ar, this message translates to:
  /// **'ادفع'**
  String get payShort;

  /// No description provided for @openJourney.
  ///
  /// In ar, this message translates to:
  /// **'افتح الرحلة'**
  String get openJourney;

  /// No description provided for @allReports.
  ///
  /// In ar, this message translates to:
  /// **'كل التقارير'**
  String get allReports;

  /// No description provided for @allHomework.
  ///
  /// In ar, this message translates to:
  /// **'كل الواجبات'**
  String get allHomework;

  /// No description provided for @aboutLearner.
  ///
  /// In ar, this message translates to:
  /// **'عن {name}'**
  String aboutLearner(String name);

  /// No description provided for @pickLearner.
  ///
  /// In ar, this message translates to:
  /// **'عن مين؟'**
  String get pickLearner;

  /// No description provided for @pickedNow.
  ///
  /// In ar, this message translates to:
  /// **'دلوقتي'**
  String get pickedNow;

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

  /// No description provided for @substituteChip.
  ///
  /// In ar, this message translates to:
  /// **'بديل بدل {name}'**
  String substituteChip(String name);

  /// No description provided for @minutesShort.
  ///
  /// In ar, this message translates to:
  /// **'{n} د'**
  String minutesShort(int n);

  /// No description provided for @lessonTime.
  ///
  /// In ar, this message translates to:
  /// **'الوقت'**
  String get lessonTime;

  /// No description provided for @lessonWho.
  ///
  /// In ar, this message translates to:
  /// **'مع'**
  String get lessonWho;

  /// No description provided for @lessonState.
  ///
  /// In ar, this message translates to:
  /// **'الحالة'**
  String get lessonState;

  /// No description provided for @timeRange.
  ///
  /// In ar, this message translates to:
  /// **'{from} – {to} {clock}'**
  String timeRange(String from, String to, String clock);

  /// No description provided for @roomOpensOn.
  ///
  /// In ar, this message translates to:
  /// **'الفصل بيفتح {when}'**
  String roomOpensOn(String when);

  /// No description provided for @lessonDetails.
  ///
  /// In ar, this message translates to:
  /// **'تفاصيل الحصة'**
  String get lessonDetails;

  /// No description provided for @refusedSubstitute.
  ///
  /// In ar, this message translates to:
  /// **'اتلغت. عندك {days, plural, =1{يوم} =2{يومين} few{{days} أيام} other{{days} يوم}} تحجز فيهم حصة التعويض.'**
  String refusedSubstitute(int days);

  /// No description provided for @cancelLesson.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الحصة'**
  String get cancelLesson;

  /// No description provided for @disputeLesson.
  ///
  /// In ar, this message translates to:
  /// **'اعترض'**
  String get disputeLesson;

  /// No description provided for @disputeTitle.
  ///
  /// In ar, this message translates to:
  /// **'اعتراض على نتيجة الحصة'**
  String get disputeTitle;

  /// No description provided for @disputeIntroFamily.
  ///
  /// In ar, this message translates to:
  /// **'الحصة متسجلة \"{outcome}\". لو ده مش اللي حصل، قولولنا خلال 48 ساعة من آخرها، والأكاديمية هتراجع حضور الفصل وترد عليكم.'**
  String disputeIntroFamily(String outcome);

  /// No description provided for @disputeIntroTeacher.
  ///
  /// In ar, this message translates to:
  /// **'الحصة متسجلة \"{outcome}\". لو ده مش اللي حصل، قولنا خلال 48 ساعة من آخرها، والأكاديمية هتراجع حضور الفصل وترد عليك.'**
  String disputeIntroTeacher(String outcome);

  /// No description provided for @disputeWhat.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي حصل؟'**
  String get disputeWhat;

  /// No description provided for @disputeNote.
  ///
  /// In ar, this message translates to:
  /// **'التفاصيل'**
  String get disputeNote;

  /// No description provided for @disputeNoteHint.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية بس اللي هتقرا ده.'**
  String get disputeNoteHint;

  /// No description provided for @disputeFillIn.
  ///
  /// In ar, this message translates to:
  /// **'اختار السبب واكتب اللي حصل.'**
  String get disputeFillIn;

  /// No description provided for @disputeSend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت الاعتراض'**
  String get disputeSend;

  /// No description provided for @disputeSent.
  ///
  /// In ar, this message translates to:
  /// **'الاعتراض وصل، والحصة تحت المراجعة.'**
  String get disputeSent;

  /// No description provided for @disputeState.
  ///
  /// In ar, this message translates to:
  /// **'الاعتراض: {status}'**
  String disputeState(String status);

  /// No description provided for @disputeAnswer.
  ///
  /// In ar, this message translates to:
  /// **'رد الأكاديمية: {answer}'**
  String disputeAnswer(String answer);

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

  /// No description provided for @stars1.
  ///
  /// In ar, this message translates to:
  /// **'وحشة'**
  String get stars1;

  /// No description provided for @stars2.
  ///
  /// In ar, this message translates to:
  /// **'مش أحسن حاجة'**
  String get stars2;

  /// No description provided for @stars3.
  ///
  /// In ar, this message translates to:
  /// **'كويسة'**
  String get stars3;

  /// No description provided for @stars4.
  ///
  /// In ar, this message translates to:
  /// **'حلوة'**
  String get stars4;

  /// No description provided for @stars5.
  ///
  /// In ar, this message translates to:
  /// **'ممتازة'**
  String get stars5;

  /// No description provided for @yourRating.
  ///
  /// In ar, this message translates to:
  /// **'تقييمك'**
  String get yourRating;

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

  /// No description provided for @homeworkWaiting.
  ///
  /// In ar, this message translates to:
  /// **'مستني'**
  String get homeworkWaiting;

  /// No description provided for @homeworkSent.
  ///
  /// In ar, this message translates to:
  /// **'اتسلّم'**
  String get homeworkSent;

  /// No description provided for @homeworkMarked.
  ///
  /// In ar, this message translates to:
  /// **'اتصحّح'**
  String get homeworkMarked;

  /// No description provided for @homeworkWaitingOnYou.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{واجب مستني منك} =2{واجبين مستنيين منك} few{{n} واجبات مستنية منك} other{{n} واجب مستني منك}}'**
  String homeworkWaitingOnYou(int n);

  /// No description provided for @homeworkNoneWaiting.
  ///
  /// In ar, this message translates to:
  /// **'مفيش واجبات مستنياك دلوقتي.'**
  String get homeworkNoneWaiting;

  /// No description provided for @homeworkNoneSent.
  ///
  /// In ar, this message translates to:
  /// **'مفيش واجبات مستنية تصحيح.'**
  String get homeworkNoneSent;

  /// No description provided for @homeworkNoneMarked.
  ///
  /// In ar, this message translates to:
  /// **'لسه مفيش واجبات اتصحّحت.'**
  String get homeworkNoneMarked;

  /// No description provided for @homeworkLastMarked.
  ///
  /// In ar, this message translates to:
  /// **'آخر تصحيح'**
  String get homeworkLastMarked;

  /// No description provided for @homeworkHandIn.
  ///
  /// In ar, this message translates to:
  /// **'سلّم الواجب'**
  String get homeworkHandIn;

  /// No description provided for @homeworkStartRound.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ الجولة'**
  String get homeworkStartRound;

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

  /// No description provided for @extraAction.
  ///
  /// In ar, this message translates to:
  /// **'حصص زيادة'**
  String get extraAction;

  /// No description provided for @extraHint.
  ///
  /// In ar, this message translates to:
  /// **'الحصة الزيادة بـ {price} ({minutes, plural, =1{دقيقة} =2{دقيقتين} few{{minutes} دقايق} other{{minutes} دقيقة}}).'**
  String extraHint(String price, int minutes);

  /// No description provided for @extraTitle.
  ///
  /// In ar, this message translates to:
  /// **'حصص زيادة في {subject}'**
  String extraTitle(String subject);

  /// No description provided for @extraBody.
  ///
  /// In ar, this message translates to:
  /// **'اختار العدد، وهتطلعلك فاتورة تدفعها زي أي فاتورة. بعد ما تدفع، الأكاديمية بتحجز الحصص معاك.'**
  String get extraBody;

  /// No description provided for @extraEach.
  ///
  /// In ar, this message translates to:
  /// **'{n} × {price}'**
  String extraEach(int n, String price);

  /// No description provided for @extraSubmit.
  ///
  /// In ar, this message translates to:
  /// **'اعمل الفاتورة'**
  String get extraSubmit;

  /// No description provided for @extraDone.
  ///
  /// In ar, this message translates to:
  /// **'الفاتورة {number} جاهزة ({total}).'**
  String extraDone(String number, String total);

  /// No description provided for @extraWaiting.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة الحصص الزيادة {number} مستنية الدفع.'**
  String extraWaiting(String number);

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

  /// No description provided for @transferTitle.
  ///
  /// In ar, this message translates to:
  /// **'بيانات التحويل'**
  String get transferTitle;

  /// No description provided for @transferCopy.
  ///
  /// In ar, this message translates to:
  /// **'انسخ'**
  String get transferCopy;

  /// No description provided for @transferCopied.
  ///
  /// In ar, this message translates to:
  /// **'بيانات التحويل اتنسخت.'**
  String get transferCopied;

  /// No description provided for @joining.
  ///
  /// In ar, this message translates to:
  /// **'بندخّلك الحصة…'**
  String get joining;

  /// No description provided for @linkNotOpened.
  ///
  /// In ar, this message translates to:
  /// **'مفيش برنامج على الموبايل يفتح اللينك ده.'**
  String get linkNotOpened;

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
  /// **'فاضل {n, plural, =1{دقيقة} =2{دقيقتين} few{{n} دقايق} other{{n} دقيقة}}'**
  String minutesLeft(int n);

  /// No description provided for @sharedPackage.
  ///
  /// In ar, this message translates to:
  /// **'{subjects} · باقة مشتركة'**
  String sharedPackage(String subjects);

  /// No description provided for @familyInvoice.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة العيلة'**
  String get familyInvoice;

  /// No description provided for @coursePaused.
  ///
  /// In ar, this message translates to:
  /// **'موقوف لحد {day}'**
  String coursePaused(String day);

  /// No description provided for @coursePausedKids.
  ///
  /// In ar, this message translates to:
  /// **'في إجازة لحد {day}'**
  String coursePausedKids(String day);

  /// No description provided for @coursePauseAhead.
  ///
  /// In ar, this message translates to:
  /// **'هيتوقف من {from} لحد {until}'**
  String coursePauseAhead(String from, String until);

  /// No description provided for @courseEnding.
  ///
  /// In ar, this message translates to:
  /// **'بينتهي من {day}'**
  String courseEnding(String day);

  /// No description provided for @courseEnded.
  ///
  /// In ar, this message translates to:
  /// **'انتهى، والدقايق اللي فاضلة لسه في الرصيد'**
  String get courseEnded;

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

  /// No description provided for @moreChildren.
  ///
  /// In ar, this message translates to:
  /// **'الأولاد'**
  String get moreChildren;

  /// No description provided for @moreTalk.
  ///
  /// In ar, this message translates to:
  /// **'التواصل'**
  String get moreTalk;

  /// No description provided for @moreMine.
  ///
  /// In ar, this message translates to:
  /// **'حسابي'**
  String get moreMine;

  /// No description provided for @moreAccounts.
  ///
  /// In ar, this message translates to:
  /// **'الحسابات'**
  String get moreAccounts;

  /// No description provided for @moreLanguage.
  ///
  /// In ar, this message translates to:
  /// **'اللغة والتوقيت'**
  String get moreLanguage;

  /// No description provided for @moreSecurity.
  ///
  /// In ar, this message translates to:
  /// **'الدخول والأمان'**
  String get moreSecurity;

  /// No description provided for @moreTeaching.
  ///
  /// In ar, this message translates to:
  /// **'التدريس'**
  String get moreTeaching;

  /// No description provided for @moreWork.
  ///
  /// In ar, this message translates to:
  /// **'شغلي'**
  String get moreWork;

  /// No description provided for @moreStudy.
  ///
  /// In ar, this message translates to:
  /// **'الدراسة'**
  String get moreStudy;

  /// No description provided for @moreHelp.
  ///
  /// In ar, this message translates to:
  /// **'مساعدة'**
  String get moreHelp;

  /// No description provided for @yourAccount.
  ///
  /// In ar, this message translates to:
  /// **'حسابك وإعداداتك'**
  String get yourAccount;

  /// No description provided for @moreAccountSettings.
  ///
  /// In ar, this message translates to:
  /// **'الحساب والإعدادات'**
  String get moreAccountSettings;

  /// No description provided for @meetingLinkMissing.
  ///
  /// In ar, this message translates to:
  /// **'مش متحط'**
  String get meetingLinkMissing;

  /// No description provided for @moreAcademies.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{أكاديمية واحدة} =2{أكاديميتين} few{{n} أكاديميات} other{{n} أكاديمية}}'**
  String moreAcademies(int n);

  /// No description provided for @guardianOf.
  ///
  /// In ar, this message translates to:
  /// **'ولي أمر {names}'**
  String guardianOf(String names);

  /// No description provided for @appVersion.
  ///
  /// In ar, this message translates to:
  /// **'النسخة {version}'**
  String appVersion(String version);

  /// No description provided for @contactTitle.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل والموبايل'**
  String get contactTitle;

  /// No description provided for @contactHint.
  ///
  /// In ar, this message translates to:
  /// **'دول طرق دخولك، والأكواد والرسايل بتوصلك عليهم. لما تغيّر واحد، بنبعت كود على الجديد عشان نتأكد إنه بتاعك.'**
  String get contactHint;

  /// No description provided for @contactEmail.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل'**
  String get contactEmail;

  /// No description provided for @contactPhone.
  ///
  /// In ar, this message translates to:
  /// **'رقم الموبايل'**
  String get contactPhone;

  /// No description provided for @contactNone.
  ///
  /// In ar, this message translates to:
  /// **'مش متسجل'**
  String get contactNone;

  /// No description provided for @contactChangeEmail.
  ///
  /// In ar, this message translates to:
  /// **'غيّر الإيميل'**
  String get contactChangeEmail;

  /// No description provided for @contactChangePhone.
  ///
  /// In ar, this message translates to:
  /// **'غيّر الرقم'**
  String get contactChangePhone;

  /// No description provided for @contactAddEmail.
  ///
  /// In ar, this message translates to:
  /// **'ضيف إيميل'**
  String get contactAddEmail;

  /// No description provided for @contactAddPhone.
  ///
  /// In ar, this message translates to:
  /// **'ضيف رقم'**
  String get contactAddPhone;

  /// No description provided for @contactNewEmail.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل الجديد'**
  String get contactNewEmail;

  /// No description provided for @contactNewPhone.
  ///
  /// In ar, this message translates to:
  /// **'الرقم الجديد'**
  String get contactNewPhone;

  /// No description provided for @contactPhoneHint.
  ///
  /// In ar, this message translates to:
  /// **'بكود الدولة، أو رقم محلي من نفس بلدك.'**
  String get contactPhoneHint;

  /// No description provided for @contactSend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت كود'**
  String get contactSend;

  /// No description provided for @contactCode.
  ///
  /// In ar, this message translates to:
  /// **'الكود اللي وصلك'**
  String get contactCode;

  /// No description provided for @contactCodeHint.
  ///
  /// In ar, this message translates to:
  /// **'بعتنا كود على {contact}. صالح لحد {until}.'**
  String contactCodeHint(String contact, String until);

  /// No description provided for @contactConfirm.
  ///
  /// In ar, this message translates to:
  /// **'أكّد'**
  String get contactConfirm;

  /// No description provided for @contactResend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت كود تاني'**
  String get contactResend;

  /// No description provided for @contactDoneEmail.
  ///
  /// In ar, this message translates to:
  /// **'الإيميل اتغيّر. من دلوقتي بتدخل وبتوصلك الرسايل عليه.'**
  String get contactDoneEmail;

  /// No description provided for @contactDonePhone.
  ///
  /// In ar, this message translates to:
  /// **'الرقم اتغيّر. من دلوقتي بتدخل وبتوصلك الرسايل عليه.'**
  String get contactDonePhone;

  /// No description provided for @contactManaged.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية أو ولي أمرك هم اللي بيغيّروا الإيميل والرقم بتوعك.'**
  String get contactManaged;

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

  /// No description provided for @thisWeek.
  ///
  /// In ar, this message translates to:
  /// **'الأسبوع ده'**
  String get thisWeek;

  /// No description provided for @weekdaysShort.
  ///
  /// In ar, this message translates to:
  /// **'سبت،حد،اتنين،تلات،أربع،خميس،جمعة'**
  String get weekdaysShort;

  /// No description provided for @dayToday.
  ///
  /// In ar, this message translates to:
  /// **'{day} · النهارده'**
  String dayToday(String day);

  /// No description provided for @noLessonsLater.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حصص من اليوم ده لآخر الأسبوع.'**
  String get noLessonsLater;

  /// No description provided for @laterToday.
  ///
  /// In ar, this message translates to:
  /// **'باقي النهارده'**
  String get laterToday;

  /// No description provided for @doneToday.
  ///
  /// In ar, this message translates to:
  /// **'خلصت النهارده'**
  String get doneToday;

  /// No description provided for @restDay.
  ///
  /// In ar, this message translates to:
  /// **'النهارده مفيش حصص. حصصك الجاية في الأسبوع.'**
  String get restDay;

  /// No description provided for @openWeek.
  ///
  /// In ar, this message translates to:
  /// **'افتح الأسبوع'**
  String get openWeek;

  /// No description provided for @writeTheReport.
  ///
  /// In ar, this message translates to:
  /// **'اكتب التقرير'**
  String get writeTheReport;

  /// No description provided for @markingTile.
  ///
  /// In ar, this message translates to:
  /// **'واجبات للتصحيح'**
  String get markingTile;

  /// No description provided for @allWritten.
  ///
  /// In ar, this message translates to:
  /// **'كلها اتكتبت'**
  String get allWritten;

  /// No description provided for @nothingToMark.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حاجة'**
  String get nothingToMark;

  /// No description provided for @oldestFor.
  ///
  /// In ar, this message translates to:
  /// **'أقدمها: {name}'**
  String oldestFor(String name);

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

  /// No description provided for @studentsIn.
  ///
  /// In ar, this message translates to:
  /// **'{students} في {subjects}'**
  String studentsIn(String students, String subjects);

  /// No description provided for @studentsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش طلاب} =1{طالب واحد} =2{طالبين} few{{n} طلاب} other{{n} طالب}}'**
  String studentsCount(int n);

  /// No description provided for @subjectsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش مواد} =1{مادة واحدة} =2{مادتين} few{{n} مواد} other{{n} مادة}}'**
  String subjectsCount(int n);

  /// No description provided for @searchByName.
  ///
  /// In ar, this message translates to:
  /// **'دوّر بالاسم'**
  String get searchByName;

  /// No description provided for @allSubjects.
  ///
  /// In ar, this message translates to:
  /// **'الكل'**
  String get allSubjects;

  /// No description provided for @noStudentsFound.
  ///
  /// In ar, this message translates to:
  /// **'مفيش طالب بالاسم ده.'**
  String get noStudentsFound;

  /// No description provided for @reportWaitingChip.
  ///
  /// In ar, this message translates to:
  /// **'تقرير مستنيك'**
  String get reportWaitingChip;

  /// No description provided for @messageStudent.
  ///
  /// In ar, this message translates to:
  /// **'ابعتله رسالة'**
  String get messageStudent;

  /// No description provided for @markHomework.
  ///
  /// In ar, this message translates to:
  /// **'صحّح الواجبات'**
  String get markHomework;

  /// No description provided for @ofCourse.
  ///
  /// In ar, this message translates to:
  /// **'{n}% من البرنامج'**
  String ofCourse(int n);

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

  /// No description provided for @meetingLinkTitle.
  ///
  /// In ar, this message translates to:
  /// **'لينك الحصة بتاعك'**
  String get meetingLinkTitle;

  /// No description provided for @meetingLinkNeeded.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية شغالة على لينكات خارجية، ومفيش حصة هتفتح من غير لينكك.'**
  String get meetingLinkNeeded;

  /// No description provided for @meetingLinkSave.
  ///
  /// In ar, this message translates to:
  /// **'احفظ اللينك'**
  String get meetingLinkSave;

  /// No description provided for @meetingLinkSaved.
  ///
  /// In ar, this message translates to:
  /// **'اللينك اتحفظ.'**
  String get meetingLinkSaved;

  /// No description provided for @meetingLinkRemoved.
  ///
  /// In ar, this message translates to:
  /// **'اللينك اتشال.'**
  String get meetingLinkRemoved;

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
  /// **'{covered} من {total, plural, =1{حصة واحدة} =2{حصتين} few{{total} حصص} other{{total} حصة}} ليها بديل'**
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

  /// No description provided for @youAreHere.
  ///
  /// In ar, this message translates to:
  /// **'انت هنا!'**
  String get youAreHere;

  /// No description provided for @topRank.
  ///
  /// In ar, this message translates to:
  /// **'وصلت لأعلى رتبة!'**
  String get topRank;

  /// No description provided for @streak.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لسه مفيش حصص ورا بعض} =1{حصة واحدة} =2{حصتين ورا بعض} few{{n} حصص ورا بعض} other{{n} حصة ورا بعض}}'**
  String streak(int n);

  /// No description provided for @badgesCount.
  ///
  /// In ar, this message translates to:
  /// **'{earned} من {total}'**
  String badgesCount(int earned, int total);

  /// No description provided for @finishOn.
  ///
  /// In ar, this message translates to:
  /// **'هتخلّص على كده'**
  String get finishOn;

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

  /// No description provided for @fromLastMonth.
  ///
  /// In ar, this message translates to:
  /// **'من الشهر اللي فات'**
  String get fromLastMonth;

  /// No description provided for @doneMark.
  ///
  /// In ar, this message translates to:
  /// **'تم!'**
  String get doneMark;

  /// No description provided for @chestReady.
  ///
  /// In ar, this message translates to:
  /// **'الصندوق جاهز! دوس عليه'**
  String get chestReady;

  /// No description provided for @chestOpened.
  ///
  /// In ar, this message translates to:
  /// **'فتحت صندوق الأسبوع ده'**
  String get chestOpened;

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

  /// No description provided for @cheerFromFamily.
  ///
  /// In ar, this message translates to:
  /// **'تشجيع من أهلك!'**
  String get cheerFromFamily;

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
  /// **'تقارير مستنياك'**
  String get reportsOpen;

  /// No description provided for @reportsSent.
  ///
  /// In ar, this message translates to:
  /// **'اتبعتت'**
  String get reportsSent;

  /// No description provided for @writeReport.
  ///
  /// In ar, this message translates to:
  /// **'اكتب'**
  String get writeReport;

  /// No description provided for @waitingFor.
  ///
  /// In ar, this message translates to:
  /// **'مستنيك: {what}'**
  String waitingFor(String what);

  /// No description provided for @waitingNothing.
  ///
  /// In ar, this message translates to:
  /// **'مفيش حاجة مستنياك'**
  String get waitingNothing;

  /// No description provided for @bothWaiting.
  ///
  /// In ar, this message translates to:
  /// **'{a} و{b}'**
  String bothWaiting(String a, String b);

  /// No description provided for @reportsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش تقارير} =1{تقرير واحد} =2{تقريرين} few{{n} تقارير} other{{n} تقرير}}'**
  String reportsCount(int n);

  /// No description provided for @homeworkCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش واجبات} =1{واجب واحد} =2{واجبين} few{{n} واجبات} other{{n} واجب}}'**
  String homeworkCount(int n);

  /// No description provided for @markingRow.
  ///
  /// In ar, this message translates to:
  /// **'واجبات مستنية تصحيح'**
  String get markingRow;

  /// No description provided for @markingNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش واجبات مستنية دلوقتي'**
  String get markingNone;

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

  /// No description provided for @reportTitle.
  ///
  /// In ar, this message translates to:
  /// **'تقرير الحصة'**
  String get reportTitle;

  /// No description provided for @workedOn.
  ///
  /// In ar, this message translates to:
  /// **'اشتغلنا على إيه'**
  String get workedOn;

  /// No description provided for @howItWent.
  ///
  /// In ar, this message translates to:
  /// **'الحصة كانت إزاي'**
  String get howItWent;

  /// No description provided for @talkSection.
  ///
  /// In ar, this message translates to:
  /// **'الكلام'**
  String get talkSection;

  /// No description provided for @keepDraft.
  ///
  /// In ar, this message translates to:
  /// **'مسودة'**
  String get keepDraft;

  /// No description provided for @reportDraftSaved.
  ///
  /// In ar, this message translates to:
  /// **'اتحفظت مسودة على الموبايل ده.'**
  String get reportDraftSaved;

  /// No description provided for @draftFrom.
  ///
  /// In ar, this message translates to:
  /// **'كمّلت من مسودة اتحفظت من {ago}.'**
  String draftFrom(String ago);

  /// No description provided for @discardDraft.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ من الأول'**
  String get discardDraft;

  /// No description provided for @voiceTitle.
  ///
  /// In ar, this message translates to:
  /// **'مسودة من رسالة صوتية'**
  String get voiceTitle;

  /// No description provided for @voiceHint.
  ///
  /// In ar, this message translates to:
  /// **'قول عملتوا إيه، والطالب كان عامل إزاي، والواجب. المسودة بتملا الخانات الفاضية بس، وإنت بتراجعها قبل ما تبعت.'**
  String get voiceHint;

  /// No description provided for @voiceRecord.
  ///
  /// In ar, this message translates to:
  /// **'سجّل رسالة'**
  String get voiceRecord;

  /// No description provided for @voiceWorking.
  ///
  /// In ar, this message translates to:
  /// **'بنسمع ونكتب المسودة…'**
  String get voiceWorking;

  /// No description provided for @voiceDone.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{الرسالة مازوّدتش حاجة جديدة: الفورم كان فيه كل ده.} =1{المسودة ملت خانة واحدة. راجعها قبل ما تبعت.} =2{المسودة ملت خانتين. راجعهم قبل ما تبعت.} few{المسودة ملت {n} خانات. راجعهم قبل ما تبعت.} other{المسودة ملت {n} خانة. راجعهم قبل ما تبعت.}}'**
  String voiceDone(int n);

  /// No description provided for @voiceHeard.
  ///
  /// In ar, this message translates to:
  /// **'اللي اتسمع'**
  String get voiceHeard;

  /// No description provided for @voiceUnsure.
  ///
  /// In ar, this message translates to:
  /// **'راجع دول: المسودة مكانتش متأكدة'**
  String get voiceUnsure;

  /// No description provided for @voiceLeft.
  ///
  /// In ar, this message translates to:
  /// **'لسه محتاج: {fields}'**
  String voiceLeft(String fields);

  /// No description provided for @voiceLimit.
  ///
  /// In ar, this message translates to:
  /// **'لحد دقيقتين'**
  String get voiceLimit;

  /// No description provided for @voiceKept.
  ///
  /// In ar, this message translates to:
  /// **'التسجيل بيتمسح أول ما يتسمع، وعمره ما بيتحفظ.'**
  String get voiceKept;

  /// No description provided for @suggested.
  ///
  /// In ar, this message translates to:
  /// **'مقترح'**
  String get suggested;

  /// No description provided for @checkThis.
  ///
  /// In ar, this message translates to:
  /// **'راجعه'**
  String get checkThis;

  /// No description provided for @reciteTitle.
  ///
  /// In ar, this message translates to:
  /// **'تصحيح التسميع'**
  String get reciteTitle;

  /// No description provided for @reciteToggle.
  ///
  /// In ar, this message translates to:
  /// **'تصحيح التسميع آلي'**
  String get reciteToggle;

  /// No description provided for @reciteHint.
  ///
  /// In ar, this message translates to:
  /// **'لما الطالب يسلّم تسجيل، بنعلّم لك على الكلمات اللي ناقصة أو غلط. ليك إنت بس، والأسرة مابتشوفوش.'**
  String get reciteHint;

  /// No description provided for @reciteSuggestion.
  ///
  /// In ar, this message translates to:
  /// **'من نص الدرس: {title}'**
  String reciteSuggestion(String title);

  /// No description provided for @reciteSurah.
  ///
  /// In ar, this message translates to:
  /// **'السورة'**
  String get reciteSurah;

  /// No description provided for @reciteChooseSurah.
  ///
  /// In ar, this message translates to:
  /// **'اختار السورة'**
  String get reciteChooseSurah;

  /// No description provided for @reciteSearch.
  ///
  /// In ar, this message translates to:
  /// **'دوّر على سورة'**
  String get reciteSearch;

  /// No description provided for @reciteFrom.
  ///
  /// In ar, this message translates to:
  /// **'من آية'**
  String get reciteFrom;

  /// No description provided for @reciteTo.
  ///
  /// In ar, this message translates to:
  /// **'لحد آية'**
  String get reciteTo;

  /// No description provided for @reciteWhat.
  ///
  /// In ar, this message translates to:
  /// **'التسميع: {title}'**
  String reciteWhat(String title);

  /// No description provided for @reciteMissing.
  ///
  /// In ar, this message translates to:
  /// **'ناقصة'**
  String get reciteMissing;

  /// No description provided for @reciteWrong.
  ///
  /// In ar, this message translates to:
  /// **'غلط'**
  String get reciteWrong;

  /// No description provided for @reciteExtra.
  ///
  /// In ar, this message translates to:
  /// **'زيادة'**
  String get reciteExtra;

  /// No description provided for @reciteSaid.
  ///
  /// In ar, this message translates to:
  /// **'اتقال: {word}'**
  String reciteSaid(String word);

  /// No description provided for @reciteHeard.
  ///
  /// In ar, this message translates to:
  /// **'اللي اتسمع'**
  String get reciteHeard;

  /// No description provided for @drafts.
  ///
  /// In ar, this message translates to:
  /// **'مسودات'**
  String get drafts;

  /// No description provided for @savedAgo.
  ///
  /// In ar, this message translates to:
  /// **'اتحفظت من {ago}'**
  String savedAgo(String ago);

  /// No description provided for @continueDraft.
  ///
  /// In ar, this message translates to:
  /// **'كمّل'**
  String get continueDraft;

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

  /// No description provided for @questionsTitle.
  ///
  /// In ar, this message translates to:
  /// **'أسئلة الأكاديمية'**
  String get questionsTitle;

  /// No description provided for @questionsHint.
  ///
  /// In ar, this message translates to:
  /// **'أسئلة الأكاديمية بتسألها في كل تقرير للمادة دي.'**
  String get questionsHint;

  /// No description provided for @answerRequired.
  ///
  /// In ar, this message translates to:
  /// **'جاوب على \"{question}\".'**
  String answerRequired(String question);

  /// No description provided for @answerYes.
  ///
  /// In ar, this message translates to:
  /// **'أيوه'**
  String get answerYes;

  /// No description provided for @answerNo.
  ///
  /// In ar, this message translates to:
  /// **'لأ'**
  String get answerNo;

  /// No description provided for @academyOnly.
  ///
  /// In ar, this message translates to:
  /// **'للأكاديمية بس'**
  String get academyOnly;

  /// No description provided for @lessonMaterials.
  ///
  /// In ar, this message translates to:
  /// **'مواد الدرس'**
  String get lessonMaterials;

  /// No description provided for @teachersOnly.
  ///
  /// In ar, this message translates to:
  /// **'للمدرّس بس'**
  String get teachersOnly;

  /// No description provided for @recordingsTitle.
  ///
  /// In ar, this message translates to:
  /// **'تسجيلات الحصص'**
  String get recordingsTitle;

  /// No description provided for @recordingsMine.
  ///
  /// In ar, this message translates to:
  /// **'تسجيلات حصصي'**
  String get recordingsMine;

  /// No description provided for @recordingsOff.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية مش بتسجل الحصص دلوقتي.'**
  String get recordingsOff;

  /// No description provided for @recordingsOf.
  ///
  /// In ar, this message translates to:
  /// **'تسجيلات حصص {name}'**
  String recordingsOf(String name);

  /// No description provided for @recordingsTeacherList.
  ///
  /// In ar, this message translates to:
  /// **'تسجيلات حصصك'**
  String get recordingsTeacherList;

  /// No description provided for @recordingsEmpty.
  ///
  /// In ar, this message translates to:
  /// **'مفيش تسجيلات لسه.'**
  String get recordingsEmpty;

  /// No description provided for @recordingsNotShared.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية مش بتعرض التسجيلات هنا.'**
  String get recordingsNotShared;

  /// No description provided for @recordingWatch.
  ///
  /// In ar, this message translates to:
  /// **'شوف'**
  String get recordingWatch;

  /// No description provided for @recordingAgreed.
  ///
  /// In ar, this message translates to:
  /// **'موافق'**
  String get recordingAgreed;

  /// No description provided for @recordingNotAgreed.
  ///
  /// In ar, this message translates to:
  /// **'مش موافق'**
  String get recordingNotAgreed;

  /// No description provided for @recordingAgree.
  ///
  /// In ar, this message translates to:
  /// **'موافق'**
  String get recordingAgree;

  /// No description provided for @recordingAgreedDone.
  ///
  /// In ar, this message translates to:
  /// **'شكرًا. الحصص ممكن تتسجل من دلوقتي.'**
  String get recordingAgreedDone;

  /// No description provided for @recordingWithdraw.
  ///
  /// In ar, this message translates to:
  /// **'اسحب الموافقة'**
  String get recordingWithdraw;

  /// No description provided for @recordingWithdrawConfirm.
  ///
  /// In ar, this message translates to:
  /// **'متأكد؟ أي تسجيل شغال هيقف، ومش هيتسجل حاجة تاني.'**
  String get recordingWithdrawConfirm;

  /// No description provided for @recordingWithdrawn.
  ///
  /// In ar, this message translates to:
  /// **'الموافقة اتسحبت، ومش هيتسجل حاجة تاني.'**
  String get recordingWithdrawn;

  /// No description provided for @recordingUntil.
  ///
  /// In ar, this message translates to:
  /// **'متاح لحد {date}'**
  String recordingUntil(String date);

  /// No description provided for @recordingVideo.
  ///
  /// In ar, this message translates to:
  /// **'فيديو'**
  String get recordingVideo;

  /// No description provided for @recordingAudio.
  ///
  /// In ar, this message translates to:
  /// **'صوت'**
  String get recordingAudio;

  /// No description provided for @recordingLogged.
  ///
  /// In ar, this message translates to:
  /// **'فتحك للتسجيل بيتسجل باسمك.'**
  String get recordingLogged;

  /// No description provided for @recordingOn.
  ///
  /// In ar, this message translates to:
  /// **'بيسجل'**
  String get recordingOn;

  /// No description provided for @recordStart.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الحصة'**
  String get recordStart;

  /// No description provided for @recordStop.
  ///
  /// In ar, this message translates to:
  /// **'وقّف التسجيل'**
  String get recordStop;

  /// No description provided for @recordingMissing.
  ///
  /// In ar, this message translates to:
  /// **'مينفعش نسجل لحد ما يوافقوا على التسجيل: {names}.'**
  String recordingMissing(String names);

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
  /// **'التسجيل وقف عند {n, plural, =1{دقيقة} =2{دقيقتين} few{{n} دقايق} other{{n} دقيقة}}.'**
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

  /// No description provided for @helpTitle.
  ///
  /// In ar, this message translates to:
  /// **'المساعدة'**
  String get helpTitle;

  /// No description provided for @supportTitle.
  ///
  /// In ar, this message translates to:
  /// **'المساعدة'**
  String get supportTitle;

  /// No description provided for @helpLead.
  ///
  /// In ar, this message translates to:
  /// **'اكتب للأكاديمية عن حصة، أو دفع، أو مشكلة تقنية، أو أي حاجة تانية. الرد هيوصلك هنا وفي رسالة.'**
  String get helpLead;

  /// No description provided for @supportLead.
  ///
  /// In ar, this message translates to:
  /// **'اكتب للأكاديمية عن مشكلة تقنية، أو مرتبك، أو أي حاجة تانية. الرد هيوصلك هنا وفي رسالة.'**
  String get supportLead;

  /// No description provided for @ticketNew.
  ///
  /// In ar, this message translates to:
  /// **'تذكرة جديدة'**
  String get ticketNew;

  /// No description provided for @ticketNewTitle.
  ///
  /// In ar, this message translates to:
  /// **'اكتب للأكاديمية'**
  String get ticketNewTitle;

  /// No description provided for @ticketKind.
  ///
  /// In ar, this message translates to:
  /// **'عن إيه؟'**
  String get ticketKind;

  /// No description provided for @ticketHintComplaint.
  ///
  /// In ar, this message translates to:
  /// **'على مدرّس أو حصة. الأكاديمية بتدوّر فيها وبتقولك عملت إيه.'**
  String get ticketHintComplaint;

  /// No description provided for @ticketHintBilling.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة، أو دفعة، أو فلوس راجعة، أو باقتك.'**
  String get ticketHintBilling;

  /// No description provided for @ticketHintPay.
  ///
  /// In ar, this message translates to:
  /// **'مرتبك أو كشف حسابك.'**
  String get ticketHintPay;

  /// No description provided for @ticketHintTechnical.
  ///
  /// In ar, this message translates to:
  /// **'الفصل، أو التطبيق، أو الدخول مش شغال.'**
  String get ticketHintTechnical;

  /// No description provided for @ticketHintOther.
  ///
  /// In ar, this message translates to:
  /// **'سؤال أو طلب.'**
  String get ticketHintOther;

  /// No description provided for @ticketLesson.
  ///
  /// In ar, this message translates to:
  /// **'أنهي حصة؟ (لو فيه)'**
  String get ticketLesson;

  /// No description provided for @ticketNoLesson.
  ///
  /// In ar, this message translates to:
  /// **'مش حصة معينة'**
  String get ticketNoLesson;

  /// No description provided for @ticketSubject.
  ///
  /// In ar, this message translates to:
  /// **'العنوان'**
  String get ticketSubject;

  /// No description provided for @ticketSubjectHint.
  ///
  /// In ar, this message translates to:
  /// **'في كلمتين'**
  String get ticketSubjectHint;

  /// No description provided for @ticketBody.
  ///
  /// In ar, this message translates to:
  /// **'احكيلنا حصل إيه'**
  String get ticketBody;

  /// No description provided for @ticketAttach.
  ///
  /// In ar, this message translates to:
  /// **'ارفق صورة أو PDF'**
  String get ticketAttach;

  /// No description provided for @ticketTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 10 ميجا.'**
  String get ticketTooBig;

  /// No description provided for @ticketSent.
  ///
  /// In ar, this message translates to:
  /// **'اتبعتت. الأكاديمية هترد هنا.'**
  String get ticketSent;

  /// No description provided for @ticketsEmpty.
  ///
  /// In ar, this message translates to:
  /// **'مفيش تذاكر لسه. لو فيه أي حاجة مش مظبوطة، اكتبلنا.'**
  String get ticketsEmpty;

  /// No description provided for @ticketsMine.
  ///
  /// In ar, this message translates to:
  /// **'تذاكري'**
  String get ticketsMine;

  /// No description provided for @ticketAbout.
  ///
  /// In ar, this message translates to:
  /// **'عن {name}'**
  String ticketAbout(String name);

  /// No description provided for @ticketYourTurn.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية ردت'**
  String get ticketYourTurn;

  /// No description provided for @ticketYou.
  ///
  /// In ar, this message translates to:
  /// **'انت'**
  String get ticketYou;

  /// No description provided for @ticketAcademy.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية'**
  String get ticketAcademy;

  /// No description provided for @ticketReplyHint.
  ///
  /// In ar, this message translates to:
  /// **'اكتب للأكاديمية…'**
  String get ticketReplyHint;

  /// No description provided for @ticketReopen.
  ///
  /// In ar, this message translates to:
  /// **'لسه ماتحلّتش'**
  String get ticketReopen;

  /// No description provided for @ticketReopenHint.
  ///
  /// In ar, this message translates to:
  /// **'لسه ماتحلّتش؟ تقدر تفتحها تاني لحد {date}.'**
  String ticketReopenHint(String date);

  /// No description provided for @ticketClose.
  ///
  /// In ar, this message translates to:
  /// **'اقفل التذكرة'**
  String get ticketClose;

  /// No description provided for @ticketCloseHint.
  ///
  /// In ar, this message translates to:
  /// **'اقفلها لما تتحل أو لو مابقيتش محتاجها. مش هتاخد رسايل تاني.'**
  String get ticketCloseHint;

  /// No description provided for @ticketClosed.
  ///
  /// In ar, this message translates to:
  /// **'التذكرة دي اتقفلت. افتح تذكرة جديدة لو محتاج حاجة.'**
  String get ticketClosed;

  /// No description provided for @tellUsTitle.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن قلق'**
  String get tellUsTitle;

  /// No description provided for @tellUsTitleStudent.
  ///
  /// In ar, this message translates to:
  /// **'فيه حاجة مش مظبوطة؟ قولّنا'**
  String get tellUsTitleStudent;

  /// No description provided for @tellUsTitleKids.
  ///
  /// In ar, this message translates to:
  /// **'فيه حاجة زعّلتك؟'**
  String get tellUsTitleKids;

  /// No description provided for @tellUsNavKids.
  ///
  /// In ar, this message translates to:
  /// **'قول لحد كبير'**
  String get tellUsNavKids;

  /// No description provided for @tellUsNavMiddle.
  ///
  /// In ar, this message translates to:
  /// **'قولّنا لو فيه حاجة مضايقاك'**
  String get tellUsNavMiddle;

  /// No description provided for @tellUsLead.
  ///
  /// In ar, this message translates to:
  /// **'لو أي حاجة في حصة، أو من مدرّس، أو من أي حد على المنصة قلقاك، قولّنا هنا. اللي بيقراها بس المسؤولين عن حماية الأطفال في الأكاديمية.'**
  String get tellUsLead;

  /// No description provided for @tellUsLeadStudent.
  ///
  /// In ar, this message translates to:
  /// **'لو حد خلّاك تحس إنك مش مرتاح، أو خايف، أو مش في أمان، قولّنا. اللي بيقراها بس المسؤولين عن حماية الطلاب في الأكاديمية، ومش هتتعاقب.'**
  String get tellUsLeadStudent;

  /// No description provided for @tellUsLeadKids.
  ///
  /// In ar, this message translates to:
  /// **'لو حد زعّلك أو خوّفك، دوس على زرار. حد كبير في الأكاديمية هيساعدك.'**
  String get tellUsLeadKids;

  /// No description provided for @concernTeacherNav.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن قلق'**
  String get concernTeacherNav;

  /// No description provided for @concernTeacherTitle.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن قلق على أمان طالب'**
  String get concernTeacherTitle;

  /// No description provided for @concernTeacherLead.
  ///
  /// In ar, this message translates to:
  /// **'لو حاجة في أمان طالب أو حالته قلقاك، أو تصرّف حد كبير تاني، قول لمسؤولين الحماية في الأكاديمية هنا. هما بس اللي بيقروه. ماتحققش فيه ولا تكلّم الأسرة عنه بنفسك.'**
  String get concernTeacherLead;

  /// No description provided for @concernAbout.
  ///
  /// In ar, this message translates to:
  /// **'عن {name}'**
  String concernAbout(String name);

  /// No description provided for @concernWhat.
  ///
  /// In ar, this message translates to:
  /// **'الموضوع عن إيه؟'**
  String get concernWhat;

  /// No description provided for @concernWhatKids.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي حصل؟'**
  String get concernWhatKids;

  /// No description provided for @concernPick.
  ///
  /// In ar, this message translates to:
  /// **'اختار الموضوع عن إيه.'**
  String get concernPick;

  /// No description provided for @concernText.
  ///
  /// In ar, this message translates to:
  /// **'إيه اللي حصل؟'**
  String get concernText;

  /// No description provided for @concernTextKids.
  ///
  /// In ar, this message translates to:
  /// **'عايز تقول أكتر؟ مش لازم.'**
  String get concernTextKids;

  /// No description provided for @concernTextHint.
  ///
  /// In ar, this message translates to:
  /// **'حصل إمتى، ومين كان موجود، وإيه اللي اتقال.'**
  String get concernTextHint;

  /// No description provided for @concernLesson.
  ///
  /// In ar, this message translates to:
  /// **'في أنهي حصة (لو فيه)؟'**
  String get concernLesson;

  /// No description provided for @concernNoLesson.
  ///
  /// In ar, this message translates to:
  /// **'مش عن حصة'**
  String get concernNoLesson;

  /// No description provided for @concernStudent.
  ///
  /// In ar, this message translates to:
  /// **'أنهي طالب؟'**
  String get concernStudent;

  /// No description provided for @concernNoStudent.
  ///
  /// In ar, this message translates to:
  /// **'مش عن طالب معيّن'**
  String get concernNoStudent;

  /// No description provided for @concernUrgent.
  ///
  /// In ar, this message translates to:
  /// **'ممكن حد يكون في خطر دلوقتي'**
  String get concernUrgent;

  /// No description provided for @concernSend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت'**
  String get concernSend;

  /// No description provided for @concernSendTeacher.
  ///
  /// In ar, this message translates to:
  /// **'ابعت لمسؤولين الحماية'**
  String get concernSendTeacher;

  /// No description provided for @concernAgain.
  ///
  /// In ar, this message translates to:
  /// **'قولّنا حاجة تانية'**
  String get concernAgain;

  /// No description provided for @concernAgainTeacher.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن حاجة تانية'**
  String get concernAgainTeacher;

  /// No description provided for @concernNumber.
  ///
  /// In ar, this message translates to:
  /// **'رقم {number}'**
  String concernNumber(String number);

  /// No description provided for @concernsMine.
  ///
  /// In ar, this message translates to:
  /// **'اللي قولتهولنا'**
  String get concernsMine;

  /// No description provided for @concernsNone.
  ///
  /// In ar, this message translates to:
  /// **'لسه مفيش.'**
  String get concernsNone;

  /// No description provided for @documentsTitle.
  ///
  /// In ar, this message translates to:
  /// **'مستنداتي'**
  String get documentsTitle;

  /// No description provided for @documentsLead.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية بتراجع المستندات دي على الأصل؛ وفيش الصحيفة الجنائية هو اللي مطلوب عشان تدرّس للي تحت 18.'**
  String get documentsLead;

  /// No description provided for @documentSend.
  ///
  /// In ar, this message translates to:
  /// **'ابعت مستند'**
  String get documentSend;

  /// No description provided for @documentSendHint.
  ///
  /// In ar, this message translates to:
  /// **'صورة واضحة أو الـ PDF من الجهة. خلّي الأصل معاك: الأكاديمية ممكن تطلب تشوفه.'**
  String get documentSendHint;

  /// No description provided for @documentKind.
  ///
  /// In ar, this message translates to:
  /// **'المستند'**
  String get documentKind;

  /// No description provided for @documentReference.
  ///
  /// In ar, this message translates to:
  /// **'رقم الشهادة (اختياري)'**
  String get documentReference;

  /// No description provided for @documentExpires.
  ///
  /// In ar, this message translates to:
  /// **'صالح لحد (اختياري)'**
  String get documentExpires;

  /// No description provided for @documentUntil.
  ///
  /// In ar, this message translates to:
  /// **'صالح لحد {day}'**
  String documentUntil(String day);

  /// No description provided for @documentNoEnd.
  ///
  /// In ar, this message translates to:
  /// **'مالوش تاريخ انتهاء'**
  String get documentNoEnd;

  /// No description provided for @documentPick.
  ///
  /// In ar, this message translates to:
  /// **'اختار صورة أو PDF'**
  String get documentPick;

  /// No description provided for @documentPickFirst.
  ///
  /// In ar, this message translates to:
  /// **'اختار الصورة أو الـ PDF الأول.'**
  String get documentPickFirst;

  /// No description provided for @documentTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 10 ميجا.'**
  String get documentTooBig;

  /// No description provided for @documentSent.
  ///
  /// In ar, this message translates to:
  /// **'اتبعت: الأكاديمية هتراجعه.'**
  String get documentSent;

  /// No description provided for @documentsList.
  ///
  /// In ar, this message translates to:
  /// **'اللي بعته'**
  String get documentsList;

  /// No description provided for @documentsNone.
  ///
  /// In ar, this message translates to:
  /// **'لسه ما بعتش أي مستندات.'**
  String get documentsNone;

  /// No description provided for @messagesTitle.
  ///
  /// In ar, this message translates to:
  /// **'الرسايل'**
  String get messagesTitle;

  /// No description provided for @messagesFamilyTitle.
  ///
  /// In ar, this message translates to:
  /// **'الرسايل مع المدرّسين'**
  String get messagesFamilyTitle;

  /// No description provided for @messagesStudentTitle.
  ///
  /// In ar, this message translates to:
  /// **'الرسايل مع مدرّسينك'**
  String get messagesStudentTitle;

  /// No description provided for @messagesKidsTitle.
  ///
  /// In ar, this message translates to:
  /// **'كلّم مدرسك'**
  String get messagesKidsTitle;

  /// No description provided for @messagesEmptyTeacher.
  ///
  /// In ar, this message translates to:
  /// **'لما يبقى عندك طلاب، هتلاقيهم هنا وتقدر تكلّمهم وتكلّم أهلهم.'**
  String get messagesEmptyTeacher;

  /// No description provided for @messagesEmptyFamily.
  ///
  /// In ar, this message translates to:
  /// **'لما يبقى للطالب مدرّس، هتلاقيه هنا.'**
  String get messagesEmptyFamily;

  /// No description provided for @messagesStart.
  ///
  /// In ar, this message translates to:
  /// **'لسه ماحدش كتب'**
  String get messagesStart;

  /// No description provided for @messagesYou.
  ///
  /// In ar, this message translates to:
  /// **'إنت'**
  String get messagesYou;

  /// No description provided for @messagesAlsoHere.
  ///
  /// In ar, this message translates to:
  /// **'معاكم: {names}'**
  String messagesAlsoHere(String names);

  /// No description provided for @messagesFirst.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ المحادثة: الكلام هنا بيشوفه أولياء الأمور والأكاديمية.'**
  String get messagesFirst;

  /// No description provided for @messagesWrite.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رسالة…'**
  String get messagesWrite;

  /// No description provided for @gamesStars.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{من غير نجوم} =1{نجمة واحدة} =2{نجمتين} few{{n} نجوم} other{{n} نجمة}}'**
  String gamesStars(int n);

  /// No description provided for @gamesSoundOn.
  ///
  /// In ar, this message translates to:
  /// **'شغّل المزيكا والأصوات'**
  String get gamesSoundOn;

  /// No description provided for @gamesSoundOff.
  ///
  /// In ar, this message translates to:
  /// **'اقفل المزيكا والأصوات'**
  String get gamesSoundOff;

  /// No description provided for @gamesOf.
  ///
  /// In ar, this message translates to:
  /// **'{found} من {total}'**
  String gamesOf(int found, int total);

  /// No description provided for @gamesHomeworkPin.
  ///
  /// In ar, this message translates to:
  /// **'واجب'**
  String get gamesHomeworkPin;

  /// No description provided for @gamesToMap.
  ///
  /// In ar, this message translates to:
  /// **'الخريطة'**
  String get gamesToMap;

  /// No description provided for @gamesIslandGames.
  ///
  /// In ar, this message translates to:
  /// **'ألعاب الجزيرة'**
  String get gamesIslandGames;

  /// No description provided for @gamesBadgeHomework.
  ///
  /// In ar, this message translates to:
  /// **'واجب'**
  String get gamesBadgeHomework;

  /// No description provided for @gamesCreaturesCard.
  ///
  /// In ar, this message translates to:
  /// **'كائنات الجزيرة · {found} من {total}'**
  String gamesCreaturesCard(int found, int total);

  /// No description provided for @gamesEggCardKids.
  ///
  /// In ar, this message translates to:
  /// **'البيضة الجاية: {have} من {need} ⭐'**
  String gamesEggCardKids(int have, int need);

  /// No description provided for @gamesIslandDone.
  ///
  /// In ar, this message translates to:
  /// **'لمّيت كل كائنات الجزيرة!'**
  String get gamesIslandDone;

  /// No description provided for @gamesClose.
  ///
  /// In ar, this message translates to:
  /// **'اقفل اللعبة وارجع'**
  String get gamesClose;

  /// No description provided for @gamesToIsland.
  ///
  /// In ar, this message translates to:
  /// **'ارجع للجزيرة'**
  String get gamesToIsland;

  /// No description provided for @gamesAlbum.
  ///
  /// In ar, this message translates to:
  /// **'كائناتي'**
  String get gamesAlbum;

  /// No description provided for @gamesItems.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{سؤال واحد} =2{سؤالين} few{{n} أسئلة} other{{n} سؤال}}'**
  String gamesItems(int n);

  /// No description provided for @gamesPractising.
  ///
  /// In ar, this message translates to:
  /// **'بتتمرن على: {what}'**
  String gamesPractising(String what);

  /// No description provided for @gamesResult0.
  ///
  /// In ar, this message translates to:
  /// **'كمّل وحاول تاني!'**
  String get gamesResult0;

  /// No description provided for @gamesResult1.
  ///
  /// In ar, this message translates to:
  /// **'بداية حلوة!'**
  String get gamesResult1;

  /// No description provided for @gamesResult2.
  ///
  /// In ar, this message translates to:
  /// **'شاطر!'**
  String get gamesResult2;

  /// No description provided for @gamesResult3.
  ///
  /// In ar, this message translates to:
  /// **'رائع! كله صح تقريبًا'**
  String get gamesResult3;

  /// No description provided for @gamesScore.
  ///
  /// In ar, this message translates to:
  /// **'{correct} من {total} صح من أول مرة'**
  String gamesScore(int correct, int total);

  /// No description provided for @gamesHatched.
  ///
  /// In ar, this message translates to:
  /// **'فقس {name}!'**
  String gamesHatched(String name);

  /// No description provided for @gamesHomeworkDone.
  ///
  /// In ar, this message translates to:
  /// **'الواجب «{title}» اتسلّم بالتدريب ده.'**
  String gamesHomeworkDone(String title);

  /// No description provided for @gamesEgg.
  ///
  /// In ar, this message translates to:
  /// **'البيضة الجاية: {have} من {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}}'**
  String gamesEgg(int have, int need);

  /// No description provided for @gamesAgain.
  ///
  /// In ar, this message translates to:
  /// **'العب تاني'**
  String get gamesAgain;

  /// No description provided for @gamesPlay.
  ///
  /// In ar, this message translates to:
  /// **'العب'**
  String get gamesPlay;

  /// No description provided for @gamesLoading.
  ///
  /// In ar, this message translates to:
  /// **'بنجهّز الجولة…'**
  String get gamesLoading;

  /// No description provided for @gamesSending.
  ///
  /// In ar, this message translates to:
  /// **'بنحسب النجوم…'**
  String get gamesSending;

  /// No description provided for @gamesPageProblem.
  ///
  /// In ar, this message translates to:
  /// **'اللعبة ما اشتغلتش. جرّب تاني.'**
  String get gamesPageProblem;

  /// No description provided for @gamesHatchReady.
  ///
  /// In ar, this message translates to:
  /// **'البيضة جاهزة!'**
  String get gamesHatchReady;

  /// No description provided for @gamesHatchReadySub.
  ///
  /// In ar, this message translates to:
  /// **'جمعت {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}} في رحلتك'**
  String gamesHatchReadySub(int need);

  /// No description provided for @gamesHatchDone.
  ///
  /// In ar, this message translates to:
  /// **'فقست!'**
  String get gamesHatchDone;

  /// No description provided for @gamesHatchDoneSub.
  ///
  /// In ar, this message translates to:
  /// **'كل ما تجمع {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}}، تفقسلك بيضة جديدة'**
  String gamesHatchDoneSub(int need);

  /// No description provided for @gamesHatchTap0.
  ///
  /// In ar, this message translates to:
  /// **'دوس على البيضة 3 مرات'**
  String get gamesHatchTap0;

  /// No description provided for @gamesHatchTap1.
  ///
  /// In ar, this message translates to:
  /// **'كمان مرتين!'**
  String get gamesHatchTap1;

  /// No description provided for @gamesHatchTap2.
  ///
  /// In ar, this message translates to:
  /// **'مرة كمان!'**
  String get gamesHatchTap2;

  /// No description provided for @gamesHatchTap3.
  ///
  /// In ar, this message translates to:
  /// **'بتفقس...'**
  String get gamesHatchTap3;

  /// No description provided for @gamesHatchEgg.
  ///
  /// In ar, this message translates to:
  /// **'البيضة، دوس عليها. فاضل {left}'**
  String gamesHatchEgg(int left);

  /// No description provided for @gamesHatchNew.
  ///
  /// In ar, this message translates to:
  /// **'كائن جديد في ألبومك · {found} من {total}'**
  String gamesHatchNew(int found, int total);

  /// No description provided for @gamesHatchAsk.
  ///
  /// In ar, this message translates to:
  /// **'تحب تسميه إيه؟'**
  String get gamesHatchAsk;

  /// No description provided for @gamesHatchKeep.
  ///
  /// In ar, this message translates to:
  /// **'حطّه في الألبوم'**
  String get gamesHatchKeep;

  /// No description provided for @gamesAlbumTitle.
  ///
  /// In ar, this message translates to:
  /// **'ألبوم كائناتي'**
  String get gamesAlbumTitle;

  /// No description provided for @gamesAlbumHint.
  ///
  /// In ar, this message translates to:
  /// **'كل {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}} في رحلتك بتفقسلك بيضة، وكل درس تتقنه بيفقسلك كائن.'**
  String gamesAlbumHint(int need);

  /// No description provided for @gamesAlbumGuardian.
  ///
  /// In ar, this message translates to:
  /// **'ده ألبوم {name}. هو بس اللي يختار صاحبه ويسمّي كائناته.'**
  String gamesAlbumGuardian(String name);

  /// No description provided for @gamesAlbumAll.
  ///
  /// In ar, this message translates to:
  /// **'الكل'**
  String get gamesAlbumAll;

  /// No description provided for @gamesRarityCommon.
  ///
  /// In ar, this message translates to:
  /// **'عادي'**
  String get gamesRarityCommon;

  /// No description provided for @gamesRarityRare.
  ///
  /// In ar, this message translates to:
  /// **'نادر'**
  String get gamesRarityRare;

  /// No description provided for @gamesRarityLegendary.
  ///
  /// In ar, this message translates to:
  /// **'أسطوري'**
  String get gamesRarityLegendary;

  /// No description provided for @gamesAllIslands.
  ///
  /// In ar, this message translates to:
  /// **'كل الجزر'**
  String get gamesAllIslands;

  /// No description provided for @gamesNotYet.
  ///
  /// In ar, this message translates to:
  /// **'لسه'**
  String get gamesNotYet;

  /// No description provided for @gamesAriaFound.
  ///
  /// In ar, this message translates to:
  /// **'{name}، من {island}'**
  String gamesAriaFound(String name, String island);

  /// No description provided for @gamesAriaShadow.
  ///
  /// In ar, this message translates to:
  /// **'كائن لسه ما فقسش، من {island}'**
  String gamesAriaShadow(String island);

  /// No description provided for @gamesVoice.
  ///
  /// In ar, this message translates to:
  /// **'اسمع صوته'**
  String get gamesVoice;

  /// No description provided for @gamesMakeBuddy.
  ///
  /// In ar, this message translates to:
  /// **'خليه صاحبك'**
  String get gamesMakeBuddy;

  /// No description provided for @gamesIsBuddyKids.
  ///
  /// In ar, this message translates to:
  /// **'ده صاحبك 💛'**
  String get gamesIsBuddyKids;

  /// No description provided for @gamesIsBuddy.
  ///
  /// In ar, this message translates to:
  /// **'ده صاحبك'**
  String get gamesIsBuddy;

  /// No description provided for @gamesBuddyDone.
  ///
  /// In ar, this message translates to:
  /// **'{name} هيطير معاك في الألعاب من دلوقتي'**
  String gamesBuddyDone(String name);

  /// No description provided for @gamesShadowStory.
  ///
  /// In ar, this message translates to:
  /// **'لسه ما فقسش.'**
  String get gamesShadowStory;

  /// No description provided for @gamesHowCommon.
  ///
  /// In ar, this message translates to:
  /// **'إزاي تفقسه: اجمع نجوم وانت على {island}: كل {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}} بيضة.'**
  String gamesHowCommon(String island, int need);

  /// No description provided for @gamesHowRare.
  ///
  /// In ar, this message translates to:
  /// **'إزاي تفقسه: كائن نادر في {island}: بيفقس بعد الكائنات العادية بتاعتها.'**
  String gamesHowRare(String island);

  /// No description provided for @gamesHowLegendary.
  ///
  /// In ar, this message translates to:
  /// **'إزاي تفقسه: أسطوري! آخر كائن في {island}. اجمع اللي قبله كلهم.'**
  String gamesHowLegendary(String island);

  /// No description provided for @gamesNameIt.
  ///
  /// In ar, this message translates to:
  /// **'سمّيه:'**
  String get gamesNameIt;

  /// No description provided for @gamesNamed.
  ///
  /// In ar, this message translates to:
  /// **'اتسمّى {name}'**
  String gamesNamed(String name);

  /// No description provided for @gamesFromStars.
  ///
  /// In ar, this message translates to:
  /// **'فقس من نجوم رحلتك'**
  String get gamesFromStars;

  /// No description provided for @gamesFromMastery.
  ///
  /// In ar, this message translates to:
  /// **'فقس لما اتقنت درس'**
  String get gamesFromMastery;

  /// No description provided for @gamesRace.
  ///
  /// In ar, this message translates to:
  /// **'سباق مع نفسك'**
  String get gamesRace;

  /// No description provided for @gamesBest.
  ///
  /// In ar, this message translates to:
  /// **'أحسن وقت: {s}'**
  String gamesBest(String s);

  /// No description provided for @gamesSeconds.
  ///
  /// In ar, this message translates to:
  /// **'{s}ث'**
  String gamesSeconds(int s);

  /// No description provided for @gamesBeat.
  ///
  /// In ar, this message translates to:
  /// **'كسرت رقمك!'**
  String get gamesBeat;

  /// No description provided for @gamesSoClose.
  ///
  /// In ar, this message translates to:
  /// **'قربت!'**
  String get gamesSoClose;

  /// No description provided for @gamesFirst.
  ///
  /// In ar, this message translates to:
  /// **'أول رقم ليك!'**
  String get gamesFirst;

  /// No description provided for @gamesOver.
  ///
  /// In ar, this message translates to:
  /// **'{correct} من {total} صح في {s}'**
  String gamesOver(int correct, int total, String s);

  /// No description provided for @gamesReviewAgain.
  ///
  /// In ar, this message translates to:
  /// **'راجع تاني'**
  String get gamesReviewAgain;

  /// No description provided for @gamesGuardianTitle.
  ///
  /// In ar, this message translates to:
  /// **'ألعاب {name}'**
  String gamesGuardianTitle(String name);

  /// No description provided for @gamesPlayTime.
  ///
  /// In ar, this message translates to:
  /// **'وقت اللعب النهارده'**
  String get gamesPlayTime;

  /// No description provided for @gamesTimeText.
  ///
  /// In ar, this message translates to:
  /// **'{used} من {limit, plural, =1{دقيقة} =2{دقيقتين} few{{limit} دقايق} other{{limit} دقيقة}} لعب النهارده. فاضل {left, plural, =1{دقيقة} =2{دقيقتين} few{{left} دقايق} other{{left} دقيقة}}.'**
  String gamesTimeText(int used, int limit, int left);

  /// No description provided for @gamesRefusedOff.
  ///
  /// In ar, this message translates to:
  /// **'الألعاب مقفولة في الأكاديمية دلوقتي.'**
  String get gamesRefusedOff;

  /// No description provided for @gamesRefusedPaused.
  ///
  /// In ar, this message translates to:
  /// **'ولي الأمر قفل الألعاب دلوقتي.'**
  String get gamesRefusedPaused;

  /// No description provided for @gamesRefusedNight.
  ///
  /// In ar, this message translates to:
  /// **'الطيور نامت. الألعاب بترجع الساعة 7 الصبح.'**
  String get gamesRefusedNight;

  /// No description provided for @gamesRefusedLimit.
  ///
  /// In ar, this message translates to:
  /// **'خلصت دقايق اللعب النهارده. نكمل بكرة!'**
  String get gamesRefusedLimit;

  /// No description provided for @gamesTimeOpen.
  ///
  /// In ar, this message translates to:
  /// **'{used, plural, =1{دقيقة} =2{دقيقتين} few{{used} دقايق} other{{used} دقيقة}} لعب النهارده، من غير حد.'**
  String gamesTimeOpen(int used);

  /// No description provided for @gamesMinutesShort.
  ///
  /// In ar, this message translates to:
  /// **'دقيقة'**
  String get gamesMinutesShort;

  /// No description provided for @gamesMaxTime.
  ///
  /// In ar, this message translates to:
  /// **'أقصى وقت في اليوم'**
  String get gamesMaxTime;

  /// No description provided for @gamesChoice.
  ///
  /// In ar, this message translates to:
  /// **'{n} د'**
  String gamesChoice(int n);

  /// No description provided for @gamesAcademyChoice.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية'**
  String get gamesAcademyChoice;

  /// No description provided for @gamesNightLock.
  ///
  /// In ar, this message translates to:
  /// **'قفل اللعب من 9 بالليل لـ 7 الصبح'**
  String get gamesNightLock;

  /// No description provided for @gamesEnabled.
  ///
  /// In ar, this message translates to:
  /// **'الألعاب شغالة'**
  String get gamesEnabled;

  /// No description provided for @gamesLearned.
  ///
  /// In ar, this message translates to:
  /// **'اتراجع إيه في اللعب'**
  String get gamesLearned;

  /// No description provided for @gamesRoundsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مرة واحدة} =2{مرتين} few{{n} مرات} other{{n} مرة}}'**
  String gamesRoundsCount(int n);

  /// No description provided for @gamesGamesCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{لعبة واحدة} =2{لعبتين} few{{n} ألعاب} other{{n} لعبة}}'**
  String gamesGamesCount(int n);

  /// No description provided for @gamesPractised.
  ///
  /// In ar, this message translates to:
  /// **'اتراجع «{title}» {rounds} في {games}'**
  String gamesPractised(String title, String rounds, String games);

  /// No description provided for @gamesHardest.
  ///
  /// In ar, this message translates to:
  /// **'أصعب سؤال: «{text}». ممكن تراجعوه سوا.'**
  String gamesHardest(String text);

  /// No description provided for @gamesBestRound.
  ///
  /// In ar, this message translates to:
  /// **'أحسن جولة: {correct} من {total} صح من أول مرة في «{game}»'**
  String gamesBestRound(int correct, int total, String game);

  /// No description provided for @gamesQuiet.
  ///
  /// In ar, this message translates to:
  /// **'مفيش لعب الأسبوع ده. الألعاب بتتمرن على دروسه بس، والمدرّس بيحط التدريبات على الدرس.'**
  String get gamesQuiet;

  /// No description provided for @gamesHatchedToday.
  ///
  /// In ar, this message translates to:
  /// **'فقس «{name}» النهارده'**
  String gamesHatchedToday(String name);

  /// No description provided for @gamesHatchedStars.
  ///
  /// In ar, this message translates to:
  /// **'من نجوم الرحلة · {found} في الألبوم'**
  String gamesHatchedStars(int found);

  /// No description provided for @gamesHatchedMastery.
  ///
  /// In ar, this message translates to:
  /// **'من درس اتقنه · {found} في الألبوم'**
  String gamesHatchedMastery(int found);

  /// No description provided for @gamesCheer.
  ///
  /// In ar, this message translates to:
  /// **'هنّيه'**
  String get gamesCheer;

  /// No description provided for @gamesCheered.
  ///
  /// In ar, this message translates to:
  /// **'وصلته تهنئتك'**
  String get gamesCheered;

  /// No description provided for @gamesSafety.
  ///
  /// In ar, this message translates to:
  /// **'مفيش شراء جوه الألعاب ولا إعلانات ولا كلام مع حد غريب. كل كائن وكل نجمة بتتكسب بالمذاكرة.'**
  String get gamesSafety;

  /// No description provided for @gamesTogether.
  ///
  /// In ar, this message translates to:
  /// **'العبوا سوا'**
  String get gamesTogether;

  /// No description provided for @gamesSaved.
  ///
  /// In ar, this message translates to:
  /// **'اتحفظ.'**
  String get gamesSaved;

  /// No description provided for @gamesCardKids.
  ///
  /// In ar, this message translates to:
  /// **'الجزر بتستناك 🏝️'**
  String get gamesCardKids;

  /// No description provided for @gamesCardLead.
  ///
  /// In ar, this message translates to:
  /// **'اتمرن على دروسك بألعاب: {what}'**
  String gamesCardLead(String what);

  /// No description provided for @journeyTitle.
  ///
  /// In ar, this message translates to:
  /// **'رحلة {name}'**
  String journeyTitle(String name);

  /// No description provided for @journeyYours.
  ///
  /// In ar, this message translates to:
  /// **'رحلتك يا {name}'**
  String journeyYours(String name);

  /// No description provided for @journeyLevel.
  ///
  /// In ar, this message translates to:
  /// **'المستوى {n}'**
  String journeyLevel(int n);

  /// No description provided for @journeyLevelOf.
  ///
  /// In ar, this message translates to:
  /// **'المستوى {i} من {n}'**
  String journeyLevelOf(int i, int n);

  /// No description provided for @journeyStationOf.
  ///
  /// In ar, this message translates to:
  /// **'المحطة {i} من {n}'**
  String journeyStationOf(int i, int n);

  /// No description provided for @journeyHow.
  ///
  /// In ar, this message translates to:
  /// **'إزاي {name} ماشي'**
  String journeyHow(String name);

  /// No description provided for @journeyNextStation.
  ///
  /// In ar, this message translates to:
  /// **'الجاية: {title}'**
  String journeyNextStation(String title);

  /// No description provided for @journeyLevels.
  ///
  /// In ar, this message translates to:
  /// **'المستويات'**
  String get journeyLevels;

  /// No description provided for @journeyLessonsDone.
  ///
  /// In ar, this message translates to:
  /// **'{done} من {total, plural, =1{درس واحد} =2{درسين} few{{total} دروس} other{{total} درس}}'**
  String journeyLessonsDone(int done, int total);

  /// No description provided for @journeyAheadLocked.
  ///
  /// In ar, this message translates to:
  /// **'بتتفتح لما تخلّص {title}'**
  String journeyAheadLocked(String title);

  /// No description provided for @journeyAheadFirst.
  ///
  /// In ar, this message translates to:
  /// **'بتتفتح لما توصلها'**
  String get journeyAheadFirst;

  /// No description provided for @journeyAriaDone.
  ///
  /// In ar, this message translates to:
  /// **'{title}، خلّصتها'**
  String journeyAriaDone(String title);

  /// No description provided for @journeyAriaHere.
  ///
  /// In ar, this message translates to:
  /// **'{title}، انت هنا'**
  String journeyAriaHere(String title);

  /// No description provided for @journeyAriaAhead.
  ///
  /// In ar, this message translates to:
  /// **'{title}، لسه مقفولة'**
  String journeyAriaAhead(String title);

  /// No description provided for @journeyStationDone.
  ///
  /// In ar, this message translates to:
  /// **'خلّصته'**
  String get journeyStationDone;

  /// No description provided for @journeyStationReview.
  ///
  /// In ar, this message translates to:
  /// **'محتاج مراجعة'**
  String get journeyStationReview;

  /// No description provided for @journeyStationHere.
  ///
  /// In ar, this message translates to:
  /// **'انت هنا'**
  String get journeyStationHere;

  /// No description provided for @journeyStationTodo.
  ///
  /// In ar, this message translates to:
  /// **'لسه جاي'**
  String get journeyStationTodo;

  /// No description provided for @journeyStationTodoNote.
  ///
  /// In ar, this message translates to:
  /// **'الدرس ده لسه جاي. هتتدرّب عليه لما توصله.'**
  String get journeyStationTodoNote;

  /// No description provided for @journeyStationReviewNote.
  ///
  /// In ar, this message translates to:
  /// **'وقت تراجع الدرس ده: العب لعبة عليه.'**
  String get journeyStationReviewNote;

  /// No description provided for @journeyPractised.
  ///
  /// In ar, this message translates to:
  /// **'اتدرّبت عليه {rounds} · {p}% صح'**
  String journeyPractised(String rounds, int p);

  /// No description provided for @journeyPlayStation.
  ///
  /// In ar, this message translates to:
  /// **'العب على الدرس ده'**
  String get journeyPlayStation;

  /// No description provided for @journeyNoGames.
  ///
  /// In ar, this message translates to:
  /// **'مفيش لعبة على تدريبات الدرس ده لسه.'**
  String get journeyNoGames;

  /// No description provided for @journeyRaceLast.
  ///
  /// In ar, this message translates to:
  /// **'آخر مرة: {s}'**
  String journeyRaceLast(String s);

  /// No description provided for @journeyMissions.
  ///
  /// In ar, this message translates to:
  /// **'مهماتك'**
  String get journeyMissions;

  /// No description provided for @journeyMissionsOf.
  ///
  /// In ar, this message translates to:
  /// **'مهمات {name}'**
  String journeyMissionsOf(String name);

  /// No description provided for @journeyMissionsOn.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مهمة} =2{مهمتين} few{{n} مهمات} other{{n} مهمة}}'**
  String journeyMissionsOn(int n);

  /// No description provided for @journeyOpenMissions.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مهمة مستنية} =2{مهمتين مستنيين} few{{n} مهمات مستنية} other{{n} مهمة مستنية}}'**
  String journeyOpenMissions(int n);

  /// No description provided for @journeyNoMissions.
  ///
  /// In ar, this message translates to:
  /// **'مفيش مهمات دلوقتي. استمتع بيومك!'**
  String get journeyNoMissions;

  /// No description provided for @journeyReview.
  ///
  /// In ar, this message translates to:
  /// **'راجع'**
  String get journeyReview;

  /// No description provided for @reviewsWaiting.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مراجعة مستنياك} =2{مراجعتين مستنينك} few{{n} مراجعات مستنياك} other{{n} مراجعة مستنياك}}'**
  String reviewsWaiting(int n);

  /// No description provided for @reviewsHint.
  ///
  /// In ar, this message translates to:
  /// **'كل مراجعة لعبة قصيرة'**
  String get reviewsHint;

  /// No description provided for @hereAndNext.
  ///
  /// In ar, this message translates to:
  /// **'انت هنا: {here} · بعدها: {next}'**
  String hereAndNext(String here, String next);

  /// No description provided for @hereOnly.
  ///
  /// In ar, this message translates to:
  /// **'انت هنا: {here}'**
  String hereOnly(String here);

  /// No description provided for @levelNamed.
  ///
  /// In ar, this message translates to:
  /// **'المستوى {n}: {title}'**
  String levelNamed(int n, String title);

  /// No description provided for @allLevels.
  ///
  /// In ar, this message translates to:
  /// **'كل المستويات'**
  String get allLevels;

  /// No description provided for @goalTitle.
  ///
  /// In ar, this message translates to:
  /// **'هدفك: تخلّص البرنامج'**
  String get goalTitle;

  /// No description provided for @goalDone.
  ///
  /// In ar, this message translates to:
  /// **'خلّصتهم من البرنامج'**
  String get goalDone;

  /// No description provided for @goalPace.
  ///
  /// In ar, this message translates to:
  /// **'على السرعة دي هتخلّص يوم {day}'**
  String goalPace(String day);

  /// No description provided for @nowTitle.
  ///
  /// In ar, this message translates to:
  /// **'دلوقتي'**
  String get nowTitle;

  /// No description provided for @nextIs.
  ///
  /// In ar, this message translates to:
  /// **'الجاية: {title}'**
  String nextIs(String title);

  /// No description provided for @weekPlan.
  ///
  /// In ar, this message translates to:
  /// **'خطة الأسبوع'**
  String get weekPlan;

  /// No description provided for @notHandedIn.
  ///
  /// In ar, this message translates to:
  /// **'لسه ماتسلّمش'**
  String get notHandedIn;

  /// No description provided for @planNoDay.
  ///
  /// In ar, this message translates to:
  /// **'من غير ميعاد'**
  String get planNoDay;

  /// No description provided for @lessonsToReview.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{درس محتاج مراجعة} =2{درسين محتاجين مراجعة} few{{n} دروس محتاجة مراجعة} other{{n} درس محتاجين مراجعة}}'**
  String lessonsToReview(int n);

  /// No description provided for @reviewEachRound.
  ///
  /// In ar, this message translates to:
  /// **'جولة قصيرة لكل درس'**
  String get reviewEachRound;

  /// No description provided for @wholeCurriculum.
  ///
  /// In ar, this message translates to:
  /// **'المنهج كله'**
  String get wholeCurriculum;

  /// No description provided for @levelsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مستوى واحد} =2{مستويين} few{{n} مستويات} other{{n} مستوى}}'**
  String levelsCount(int n);

  /// No description provided for @curriculumLessons.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{درس واحد} =2{درسين} few{{n} دروس} other{{n} درس}}'**
  String curriculumLessons(int n);

  /// No description provided for @journeyLate.
  ///
  /// In ar, this message translates to:
  /// **'متأخر'**
  String get journeyLate;

  /// No description provided for @journeyChestTitle.
  ///
  /// In ar, this message translates to:
  /// **'صندوق الأسبوع'**
  String get journeyChestTitle;

  /// No description provided for @journeyChestProgress.
  ///
  /// In ar, this message translates to:
  /// **'{done} من {total, plural, =1{مهمة واحدة} =2{مهمتين} few{{total} مهمات} other{{total} مهمة}} الأسبوع'**
  String journeyChestProgress(int done, int total);

  /// No description provided for @journeyChestHint.
  ///
  /// In ar, this message translates to:
  /// **'احضر كل حصص الأسبوع وسلّم واجباته، والصندوق يتفتح بـ {n, plural, =1{نجمة} =2{نجمتين} few{{n} نجوم} other{{n} نجمة}}'**
  String journeyChestHint(int n);

  /// No description provided for @journeyChestMissed.
  ///
  /// In ar, this message translates to:
  /// **'الصندوق ده فاتك. فيه صندوق جديد الأسبوع ده!'**
  String get journeyChestMissed;

  /// No description provided for @journeyChestWaiting.
  ///
  /// In ar, this message translates to:
  /// **'الصندوق جاهز يتفتح من حساب {name}'**
  String journeyChestWaiting(String name);

  /// No description provided for @journeyChestGot.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{+نجمة في رحلتك!} =2{+نجمتين في رحلتك!} few{+{n} نجوم في رحلتك!} other{+{n} نجمة في رحلتك!}}'**
  String journeyChestGot(int n);

  /// No description provided for @journeyYourStars.
  ///
  /// In ar, this message translates to:
  /// **'نجومك'**
  String get journeyYourStars;

  /// No description provided for @journeyStarsTo.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{باقيلك نجمة لـ {rank}} =2{باقيلك نجمتين لـ {rank}} few{باقيلك {n} نجوم لـ {rank}} other{باقيلك {n} نجمة لـ {rank}}}'**
  String journeyStarsTo(int n, String rank);

  /// No description provided for @journeyStarsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش نجوم لسه} =1{نجمة واحدة} =2{نجمتين} few{{n} نجوم} other{{n} نجمة}}'**
  String journeyStarsCount(int n);

  /// No description provided for @journeyStarsOf.
  ///
  /// In ar, this message translates to:
  /// **'نجوم {name}'**
  String journeyStarsOf(String name);

  /// No description provided for @journeyLastLesson.
  ///
  /// In ar, this message translates to:
  /// **'آخر حصة'**
  String get journeyLastLesson;

  /// No description provided for @journeyLastLessonOf.
  ///
  /// In ar, this message translates to:
  /// **'آخر حصة لـ {name}'**
  String journeyLastLessonOf(String name);

  /// No description provided for @journeyLessonStars.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{ماخدتش نجوم في آخر حصة} =1{خدت نجمة في آخر حصة} =2{خدت نجمتين في آخر حصة!} few{خدت {n} نجوم في آخر حصة!} other{خدت {n} نجمة في آخر حصة!}}'**
  String journeyLessonStars(int n);

  /// No description provided for @journeyLessonCanBring.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{تجيبلك نجمة} =2{تجيبلك نجمتين} few{تجيبلك لحد {n} نجوم} other{تجيبلك لحد {n} نجمة}}'**
  String journeyLessonCanBring(int n);

  /// No description provided for @journeyEggReady.
  ///
  /// In ar, this message translates to:
  /// **'بيضتك جاهزة!'**
  String get journeyEggReady;

  /// No description provided for @journeyEggHint.
  ///
  /// In ar, this message translates to:
  /// **'افقسها وشوف مين هيطلع'**
  String get journeyEggHint;

  /// No description provided for @journeyEggHatch.
  ///
  /// In ar, this message translates to:
  /// **'افقسها'**
  String get journeyEggHatch;

  /// No description provided for @journeyEggNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش بيضة جاهزة دلوقتي. كمّل جمع نجوم!'**
  String get journeyEggNone;

  /// No description provided for @journeyNoCourses.
  ///
  /// In ar, this message translates to:
  /// **'مفيش كورسات شغالة لسه.'**
  String get journeyNoCourses;

  /// No description provided for @journeyNoCurriculum.
  ///
  /// In ar, this message translates to:
  /// **'الكورس ده لسه مالوش منهج. مهماته بتظهر هنا.'**
  String get journeyNoCurriculum;

  /// No description provided for @journeyCourses.
  ///
  /// In ar, this message translates to:
  /// **'مواد الدروس والتقارير'**
  String get journeyCourses;

  /// No description provided for @homeworkPracticeTitle.
  ///
  /// In ar, this message translates to:
  /// **'التدريب بالألعاب'**
  String get homeworkPracticeTitle;

  /// No description provided for @homeworkPracticeNotYet.
  ///
  /// In ar, this message translates to:
  /// **'لسه ما اتلعبتش جولته.'**
  String get homeworkPracticeNotYet;

  /// No description provided for @homeworkByPlaying.
  ///
  /// In ar, this message translates to:
  /// **'الواجب ده بيتسلّم لما تلعب جولته.'**
  String get homeworkByPlaying;

  /// No description provided for @homeworkPracticeResult.
  ///
  /// In ar, this message translates to:
  /// **'التدريب: {correct} من {total} صح · {stars} ({game})'**
  String homeworkPracticeResult(int correct, int total, String stars, String game);

  /// No description provided for @homeworkNoGame.
  ///
  /// In ar, this message translates to:
  /// **'مفيش لعبة للواجب ده دلوقتي.'**
  String get homeworkNoGame;

  /// No description provided for @hifzTitle.
  ///
  /// In ar, this message translates to:
  /// **'خريطة الحفظ'**
  String get hifzTitle;

  /// No description provided for @hifzTitleKids.
  ///
  /// In ar, this message translates to:
  /// **'خريطة حفظي'**
  String get hifzTitleKids;

  /// No description provided for @hifzTitleOf.
  ///
  /// In ar, this message translates to:
  /// **'خريطة حفظ {name}'**
  String hifzTitleOf(String name);

  /// No description provided for @hifzNoText.
  ///
  /// In ar, this message translates to:
  /// **'نص القرآن لسه مش موجود، والخريطة هتظهر أول ما يوصل.'**
  String get hifzNoText;

  /// No description provided for @hifzMemorized.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لسه مفيش صفحات محفوظة من {total}} =1{صفحة واحدة محفوظة من {total}} =2{صفحتين محفوظين من {total}} few{{n} صفحات محفوظة من {total}} other{{n} صفحة محفوظة من {total}}}'**
  String hifzMemorized(int n, int total);

  /// No description provided for @hifzMemorizedKids.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لسه ماحفظتش صفحات. يلا نبدأ!} =1{حفظت صفحة واحدة! ⭐} =2{حفظت صفحتين! ⭐} few{حفظت {n} صفحات! ⭐} other{حفظت {n} صفحة! ⭐}}'**
  String hifzMemorizedKids(int n);

  /// No description provided for @hifzJuz.
  ///
  /// In ar, this message translates to:
  /// **'الجزء {n}'**
  String hifzJuz(int n);

  /// No description provided for @hifzJuzSummary.
  ///
  /// In ar, this message translates to:
  /// **'الجزء {n}: {summary}'**
  String hifzJuzSummary(int n, String summary);

  /// No description provided for @hifzPageNumber.
  ///
  /// In ar, this message translates to:
  /// **'صفحة {page}'**
  String hifzPageNumber(int page);

  /// No description provided for @hifzPageOf.
  ///
  /// In ar, this message translates to:
  /// **'صفحة {page} · الجزء {juz}'**
  String hifzPageOf(int page, int juz);

  /// No description provided for @hifzToday.
  ///
  /// In ar, this message translates to:
  /// **'ورد النهارده'**
  String get hifzToday;

  /// No description provided for @hifzTodayProgress.
  ///
  /// In ar, this message translates to:
  /// **'خلص {done} من {daily, plural, =1{صفحة واحدة} =2{صفحتين} few{{daily} صفحات} other{{daily} صفحة}}'**
  String hifzTodayProgress(int done, int daily);

  /// No description provided for @hifzTodayDone.
  ///
  /// In ar, this message translates to:
  /// **'ورد النهارده خلص. برافو!'**
  String get hifzTodayDone;

  /// No description provided for @hifzTodayNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش صفحات عليها مراجعة النهارده.'**
  String get hifzTodayNone;

  /// No description provided for @hifzWirdDone.
  ///
  /// In ar, this message translates to:
  /// **'ورد النهارده خلص!'**
  String get hifzWirdDone;

  /// No description provided for @hifzWirdDoneKids.
  ///
  /// In ar, this message translates to:
  /// **'خلّصت وردك! 🎉'**
  String get hifzWirdDoneKids;

  /// No description provided for @hifzOverdue.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{متأخرة يوم} =2{متأخرة يومين} few{متأخرة {n} أيام} other{متأخرة {n} يوم}}'**
  String hifzOverdue(int n);

  /// No description provided for @hifzReveal.
  ///
  /// In ar, this message translates to:
  /// **'اكشف الصفحة'**
  String get hifzReveal;

  /// No description provided for @hifzRevealHint.
  ///
  /// In ar, this message translates to:
  /// **'سمّع الصفحة من حفظك الأول، وبعدين اكشفها واتأكد.'**
  String get hifzRevealHint;

  /// No description provided for @hifzHowWent.
  ///
  /// In ar, this message translates to:
  /// **'الحفظ كان عامل إزاي؟'**
  String get hifzHowWent;

  /// No description provided for @hifzGrade1.
  ///
  /// In ar, this message translates to:
  /// **'نسيتها'**
  String get hifzGrade1;

  /// No description provided for @hifzGrade2.
  ///
  /// In ar, this message translates to:
  /// **'بصعوبة'**
  String get hifzGrade2;

  /// No description provided for @hifzGrade3.
  ///
  /// In ar, this message translates to:
  /// **'كويس'**
  String get hifzGrade3;

  /// No description provided for @hifzGrade4.
  ///
  /// In ar, this message translates to:
  /// **'سهلة'**
  String get hifzGrade4;

  /// No description provided for @hifzNextReview.
  ///
  /// In ar, this message translates to:
  /// **'اتسجّلت. الصفحة دي هترجعلك {day}.'**
  String hifzNextReview(String day);

  /// No description provided for @hifzRecite.
  ///
  /// In ar, this message translates to:
  /// **'سمّع الصفحة'**
  String get hifzRecite;

  /// No description provided for @hifzReciteKids.
  ///
  /// In ar, this message translates to:
  /// **'سمّع صفحة'**
  String get hifzReciteKids;

  /// No description provided for @hifzReciteHint.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الصفحة من حفظك، وهنصحّحها كلمة كلمة.'**
  String get hifzReciteHint;

  /// No description provided for @hifzSendRecitation.
  ///
  /// In ar, this message translates to:
  /// **'ابعت التسميع'**
  String get hifzSendRecitation;

  /// No description provided for @hifzListening.
  ///
  /// In ar, this message translates to:
  /// **'بنسمع التسميع…'**
  String get hifzListening;

  /// No description provided for @hifzListeningHint.
  ///
  /// In ar, this message translates to:
  /// **'النتيجة هتظهر هنا لوحدها.'**
  String get hifzListeningHint;

  /// No description provided for @hifzSelfGrade.
  ///
  /// In ar, this message translates to:
  /// **'قيّم الصفحة بنفسك'**
  String get hifzSelfGrade;

  /// No description provided for @hifzLatest.
  ///
  /// In ar, this message translates to:
  /// **'آخر تسميع'**
  String get hifzLatest;

  /// No description provided for @hifzWird.
  ///
  /// In ar, this message translates to:
  /// **'ورد الحفظ'**
  String get hifzWird;

  /// No description provided for @hifzCardLeft.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{فاضل صفحة واحدة النهارده} =2{فاضل صفحتين النهارده} few{فاضل {n} صفحات النهارده} other{فاضل {n} صفحة النهارده}}'**
  String hifzCardLeft(int n);

  /// No description provided for @hifzCardDone.
  ///
  /// In ar, this message translates to:
  /// **'خلص النهارده'**
  String get hifzCardDone;

  /// No description provided for @hifzCardNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش صفحات النهارده'**
  String get hifzCardNone;

  /// No description provided for @hifzCardKids.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{خلّصت وردك النهارده! ⭐} =1{فاضلك صفحة واحدة تسمّعها} =2{فاضلك صفحتين تسمّعهم} few{فاضلك {n} صفحات تسمّعها} other{فاضلك {n} صفحة تسمّعها}}'**
  String hifzCardKids(int n);

  /// No description provided for @hifzTell.
  ///
  /// In ar, this message translates to:
  /// **'قول اللي حافظه'**
  String get hifzTell;

  /// No description provided for @hifzClear.
  ///
  /// In ar, this message translates to:
  /// **'شيل صفحات'**
  String get hifzClear;

  /// No description provided for @hifzClearHint.
  ///
  /// In ar, this message translates to:
  /// **'الصفحات اللي هتشيلها هترجع جديدة على الخريطة، ومراجعاتها القديمة هتفضل.'**
  String get hifzClearHint;

  /// No description provided for @hifzClearConfirm.
  ///
  /// In ar, this message translates to:
  /// **'تشيل الصفحات دي من الخريطة؟'**
  String get hifzClearConfirm;

  /// No description provided for @hifzParts.
  ///
  /// In ar, this message translates to:
  /// **'الأجزاء'**
  String get hifzParts;

  /// No description provided for @hifzSurahs.
  ///
  /// In ar, this message translates to:
  /// **'السور'**
  String get hifzSurahs;

  /// No description provided for @hifzSurahHint.
  ///
  /// In ar, this message translates to:
  /// **'اكتب اسم السورة أو رقمها'**
  String get hifzSurahHint;

  /// No description provided for @hifzHowWell.
  ///
  /// In ar, this message translates to:
  /// **'حافظها إزاي؟'**
  String get hifzHowWell;

  /// No description provided for @hifzChosen.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مااخترتش صفحات لسه} =1{صفحة واحدة} =2{صفحتين} few{{n} صفحات} other{{n} صفحة}}'**
  String hifzChosen(int n);

  /// No description provided for @hifzSave.
  ///
  /// In ar, this message translates to:
  /// **'سجّل'**
  String get hifzSave;

  /// No description provided for @hifzMarked.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مااتسجّلش صفحات} =1{اتسجّلت صفحة واحدة} =2{اتسجّلت صفحتين} few{اتسجّلت {n} صفحات} other{اتسجّلت {n} صفحة}}'**
  String hifzMarked(int n);

  /// No description provided for @hifzCleared.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{مفيش صفحات اتشالت} =1{اتشالت صفحة واحدة} =2{اتشالت صفحتين} few{اتشالت {n} صفحات} other{اتشالت {n} صفحة}}'**
  String hifzCleared(int n);

  /// No description provided for @hifzRecent.
  ///
  /// In ar, this message translates to:
  /// **'آخر المراجعات'**
  String get hifzRecent;

  /// No description provided for @hifzRecentNone.
  ///
  /// In ar, this message translates to:
  /// **'مفيش مراجعات في آخر أسبوعين.'**
  String get hifzRecentNone;
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
