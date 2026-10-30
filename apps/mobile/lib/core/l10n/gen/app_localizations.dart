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
  /// **'حاول مرة أخرى'**
  String get retry;

  /// No description provided for @offline.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد اتصال بالإنترنت. تحقّق من الاتصال وحاول مرة أخرى.'**
  String get offline;

  /// No description provided for @problem.
  ///
  /// In ar, this message translates to:
  /// **'حدثت مشكلة. حاول مرة أخرى.'**
  String get problem;

  /// No description provided for @back.
  ///
  /// In ar, this message translates to:
  /// **'رجوع'**
  String get back;

  /// No description provided for @close.
  ///
  /// In ar, this message translates to:
  /// **'إغلاق'**
  String get close;

  /// No description provided for @errorOfflineTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد اتصال بالإنترنت'**
  String get errorOfflineTitle;

  /// No description provided for @errorServerTitle.
  ///
  /// In ar, this message translates to:
  /// **'حدثت مشكلة لدينا'**
  String get errorServerTitle;

  /// No description provided for @errorServerBody.
  ///
  /// In ar, this message translates to:
  /// **'نعمل على إصلاحها. حاول مرة أخرى بعد قليل.'**
  String get errorServerBody;

  /// No description provided for @errorForbiddenTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا يُسمح لك بعرض هذا'**
  String get errorForbiddenTitle;

  /// No description provided for @errorForbiddenBody.
  ///
  /// In ar, this message translates to:
  /// **'إن رأيت أن هذا خطأ، فتواصل مع الأكاديمية.'**
  String get errorForbiddenBody;

  /// No description provided for @reloadFailed.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر التحديث، وقد يكون ما تراه قديمًا.'**
  String get reloadFailed;

  /// No description provided for @send.
  ///
  /// In ar, this message translates to:
  /// **'أرسل'**
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
  /// **'انتهى الدخول إلى هذا الحساب. ادخل مرة أخرى.'**
  String get noticeExpired;

  /// No description provided for @minutes.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{دقيقة} =2{دقيقتان} few{{n} دقائق} other{{n} دقيقة}}'**
  String minutes(int n);

  /// No description provided for @today.
  ///
  /// In ar, this message translates to:
  /// **'اليوم'**
  String get today;

  /// No description provided for @tomorrow.
  ///
  /// In ar, this message translates to:
  /// **'غدًا'**
  String get tomorrow;

  /// No description provided for @inMinutes.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{دقيقة} =2{دقيقتين} few{{n} دقائق} other{{n} دقيقة}}'**
  String inMinutes(int n);

  /// No description provided for @inHours.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{ساعة} =2{ساعتين} few{{n} ساعات} other{{n} ساعة}}'**
  String inHours(int n);

  /// No description provided for @lessonsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد حصص} =1{حصة واحدة} =2{حصتان} few{{n} حصص} other{{n} حصة}}'**
  String lessonsCount(int n);

  /// No description provided for @introSkip.
  ///
  /// In ar, this message translates to:
  /// **'تخطَّ'**
  String get introSkip;

  /// No description provided for @introNext.
  ///
  /// In ar, this message translates to:
  /// **'التالي'**
  String get introNext;

  /// No description provided for @introStart.
  ///
  /// In ar, this message translates to:
  /// **'لنبدأ'**
  String get introStart;

  /// No description provided for @introSlide.
  ///
  /// In ar, this message translates to:
  /// **'شريحة {n} من {total}'**
  String introSlide(int n, int total);

  /// No description provided for @introLive.
  ///
  /// In ar, this message translates to:
  /// **'مباشر'**
  String get introLive;

  /// No description provided for @intro1Title.
  ///
  /// In ar, this message translates to:
  /// **'حصص مباشرة مع معلّمك'**
  String get intro1Title;

  /// No description provided for @intro1Body.
  ///
  /// In ar, this message translates to:
  /// **'حصة فردية بالصوت والصورة، من البيت.'**
  String get intro1Body;

  /// No description provided for @intro2Title.
  ///
  /// In ar, this message translates to:
  /// **'تابع التقدّم خطوة بخطوة'**
  String get intro2Title;

  /// No description provided for @intro2Body.
  ///
  /// In ar, this message translates to:
  /// **'كل حصة تقرّبك من الهدف، والطريق كله أمامك.'**
  String get intro2Body;

  /// No description provided for @intro3Title.
  ///
  /// In ar, this message translates to:
  /// **'المواعيد والواجبات والمدفوعات في مكان واحد'**
  String get intro3Title;

  /// No description provided for @intro3Body.
  ///
  /// In ar, this message translates to:
  /// **'والتنبيه يصلك في وقته، فلا يفوتك شيء.'**
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
  /// **'دُفعت الفاتورة'**
  String get introPaid;

  /// No description provided for @introThanks.
  ///
  /// In ar, this message translates to:
  /// **'شكرًا لك'**
  String get introThanks;

  /// No description provided for @welcomeTitle.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا بك'**
  String get welcomeTitle;

  /// No description provided for @welcomeLead.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رقم هاتفك أو بريدك الإلكتروني، وسنرسل إليك رمزًا تدخل به.'**
  String get welcomeLead;

  /// No description provided for @contactLabel.
  ///
  /// In ar, this message translates to:
  /// **'رقم الهاتف أو البريد الإلكتروني'**
  String get contactLabel;

  /// No description provided for @contactPlaceholder.
  ///
  /// In ar, this message translates to:
  /// **'01xxxxxxxxx أو name@mail.com'**
  String get contactPlaceholder;

  /// No description provided for @contactEmpty.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رقم هاتفك أو بريدك الإلكتروني أولًا.'**
  String get contactEmpty;

  /// No description provided for @contactShortPhone.
  ///
  /// In ar, this message translates to:
  /// **'هذا الرقم ناقص، اكتبه كاملًا.'**
  String get contactShortPhone;

  /// No description provided for @contactBadEmail.
  ///
  /// In ar, this message translates to:
  /// **'ينقص هذا البريد الإلكتروني شيء، مثل name@mail.com'**
  String get contactBadEmail;

  /// No description provided for @countryChange.
  ///
  /// In ar, this message translates to:
  /// **'رمز الدولة {code}، انقر لتغييره'**
  String countryChange(String code);

  /// No description provided for @countrySearch.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن دولة'**
  String get countrySearch;

  /// No description provided for @countryNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد دولة بهذا الاسم.'**
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
  /// **'الولايات المتحدة وكندا'**
  String get countryUs;

  /// No description provided for @countryGb.
  ///
  /// In ar, this message translates to:
  /// **'المملكة المتحدة'**
  String get countryGb;

  /// No description provided for @legalAgree.
  ///
  /// In ar, this message translates to:
  /// **'بالمتابعة، أنت توافق على'**
  String get legalAgree;

  /// No description provided for @legalAnd.
  ///
  /// In ar, this message translates to:
  /// **'و'**
  String get legalAnd;

  /// No description provided for @countryCode.
  ///
  /// In ar, this message translates to:
  /// **'رمز الدولة'**
  String get countryCode;

  /// No description provided for @phoneHint.
  ///
  /// In ar, this message translates to:
  /// **'سيصلك الرمز على واتساب.'**
  String get phoneHint;

  /// No description provided for @emailHint.
  ///
  /// In ar, this message translates to:
  /// **'سيصلك الرمز على هذا البريد الإلكتروني.'**
  String get emailHint;

  /// No description provided for @sendCode.
  ///
  /// In ar, this message translates to:
  /// **'أرسل لي رمزًا'**
  String get sendCode;

  /// No description provided for @havePassword.
  ///
  /// In ar, this message translates to:
  /// **'لديك كلمة مرور؟ ادخل بها'**
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
  /// **'لم يكتمل الدخول بحساب {provider}. حاول مرة أخرى.'**
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
  /// **'اربط حسابك بحساب جوجل أو أبل، وادخل بهما دون رمز أو كلمة مرور.'**
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
  /// **'غير مربوط'**
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
  /// **'أتفصل حساب {provider}؟ لن تستطيع الدخول به حتى تربطه مرة أخرى.'**
  String signInMethodUnlinkConfirm(String provider);

  /// No description provided for @signInMethodLinkedDone.
  ///
  /// In ar, this message translates to:
  /// **'رُبط حساب {provider}.'**
  String signInMethodLinkedDone(String provider);

  /// No description provided for @signInMethodUnlinkedDone.
  ///
  /// In ar, this message translates to:
  /// **'فُصل حساب {provider}.'**
  String signInMethodUnlinkedDone(String provider);

  /// No description provided for @codeTitle.
  ///
  /// In ar, this message translates to:
  /// **'اكتب الرمز'**
  String get codeTitle;

  /// No description provided for @codeSentWhatsapp.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا إليك رمزًا من 6 أرقام على واتساب، إلى الرقم {to}'**
  String codeSentWhatsapp(String to);

  /// No description provided for @codeSentSms.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا إليك رمزًا من 6 أرقام في رسالة نصية، إلى الرقم {to}'**
  String codeSentSms(String to);

  /// No description provided for @codeSentEmail.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا إليك رمزًا من 6 أرقام إلى البريد الإلكتروني {to}'**
  String codeSentEmail(String to);

  /// No description provided for @codeLabel.
  ///
  /// In ar, this message translates to:
  /// **'الرمز'**
  String get codeLabel;

  /// No description provided for @codeValid.
  ///
  /// In ar, this message translates to:
  /// **'الرمز صالح لمدة 10 دقائق.'**
  String get codeValid;

  /// No description provided for @codePaste.
  ///
  /// In ar, this message translates to:
  /// **'الصق الرمز'**
  String get codePaste;

  /// No description provided for @codeChecking.
  ///
  /// In ar, this message translates to:
  /// **'نتحقّق…'**
  String get codeChecking;

  /// No description provided for @codePasteNone.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد رمز منسوخ من 6 أرقام.'**
  String get codePasteNone;

  /// No description provided for @codeRight.
  ///
  /// In ar, this message translates to:
  /// **'تم!'**
  String get codeRight;

  /// No description provided for @codeResent.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا إليك رمزًا جديدًا.'**
  String get codeResent;

  /// No description provided for @noCode.
  ///
  /// In ar, this message translates to:
  /// **'لم يصلك؟'**
  String get noCode;

  /// No description provided for @resendIn.
  ///
  /// In ar, this message translates to:
  /// **'أرسل رمزًا جديدًا بعد {time}'**
  String resendIn(String time);

  /// No description provided for @resend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل رمزًا جديدًا'**
  String get resend;

  /// No description provided for @sendBySms.
  ///
  /// In ar, this message translates to:
  /// **'أرسله في رسالة نصية بدلًا من واتساب'**
  String get sendBySms;

  /// No description provided for @confirm.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد'**
  String get confirm;

  /// No description provided for @otherContact.
  ///
  /// In ar, this message translates to:
  /// **'استخدم رقمًا أو بريدًا إلكترونيًا آخر'**
  String get otherContact;

  /// No description provided for @accountsFound.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لم نجد حسابات} =1{وجدنا لك حسابًا واحدًا} =2{وجدنا لك حسابين} few{وجدنا لك {n} حسابات} other{وجدنا لك {n} حسابًا}}'**
  String accountsFound(int n);

  /// No description provided for @accountsLead.
  ///
  /// In ar, this message translates to:
  /// **'اختر ما تريده على هذا الهاتف. بعد ذلك تنتقل بينها من \"حسابي\" دون أن تدخل مرة أخرى.'**
  String get accountsLead;

  /// No description provided for @accountsLegal.
  ///
  /// In ar, this message translates to:
  /// **'قبل أن تضيف حسابك، اقرأ سياسة الخصوصية وشروط الاستخدام الخاصة بالأكاديمية: فهي التي تحدّد ما يحدث لبياناتك لديها.'**
  String get accountsLegal;

  /// No description provided for @accountsNote.
  ///
  /// In ar, this message translates to:
  /// **'نعرض فقط الحسابات المسجّلة بالرقم نفسه، في الأكاديميات العاملة. لم تجد حسابًا؟ ربما سجّلتك الأكاديمية برقم آخر: تواصل معها.'**
  String get accountsNote;

  /// No description provided for @addAccounts.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{ادخل} =2{أضف الحسابين وادخل} few{أضف {n} حسابات وادخل} other{أضف {n} حسابًا وادخل}}'**
  String addAccounts(int n);

  /// No description provided for @foundYou.
  ///
  /// In ar, this message translates to:
  /// **'وجدناك!'**
  String get foundYou;

  /// No description provided for @foundYouLead.
  ///
  /// In ar, this message translates to:
  /// **'وجدنا لك حسابًا واحدًا.'**
  String get foundYouLead;

  /// No description provided for @enterAcademy.
  ///
  /// In ar, this message translates to:
  /// **'ادخل {academy}'**
  String enterAcademy(String academy);

  /// No description provided for @notYou.
  ///
  /// In ar, this message translates to:
  /// **'ليس حسابك؟ استخدم رقمًا أو بريدًا إلكترونيًا آخر'**
  String get notYou;

  /// No description provided for @pickOne.
  ///
  /// In ar, this message translates to:
  /// **'اختر حسابًا واحدًا على الأقل.'**
  String get pickOne;

  /// No description provided for @notAddedTitle.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{دخلت، لكن هناك حسابًا لم يُضَف:} other{دخلت، لكن هناك حسابات لم تُضَف:}}'**
  String notAddedTitle(int n);

  /// No description provided for @notAddedLine.
  ///
  /// In ar, this message translates to:
  /// **'{name} في {academy}: {reason}'**
  String notAddedLine(String name, String academy, String reason);

  /// No description provided for @noAccounts.
  ///
  /// In ar, this message translates to:
  /// **'لم نجد حسابًا بهذا الرقم أو البريد الإلكتروني في أي أكاديمية. تحقّق منه، أو تواصل مع أكاديميتك.'**
  String get noAccounts;

  /// No description provided for @noAccountTitle.
  ///
  /// In ar, this message translates to:
  /// **'لم نجد حسابًا'**
  String get noAccountTitle;

  /// No description provided for @noAccountByPhone.
  ///
  /// In ar, this message translates to:
  /// **'لم نجد حسابًا بهذا الرقم'**
  String get noAccountByPhone;

  /// No description provided for @noAccountByEmail.
  ///
  /// In ar, this message translates to:
  /// **'لم نجد حسابًا بهذا البريد الإلكتروني'**
  String get noAccountByEmail;

  /// No description provided for @noAccountCheckPhone.
  ///
  /// In ar, this message translates to:
  /// **'تأكّد أن الرقم مكتوب صحيحًا. وربما سجّلتك الأكاديمية برقم أو بريد إلكتروني آخر، فجرّبه أو تواصل معها.'**
  String get noAccountCheckPhone;

  /// No description provided for @noAccountCheckEmail.
  ///
  /// In ar, this message translates to:
  /// **'تأكّد أن البريد الإلكتروني مكتوب صحيحًا. وربما سجّلتك الأكاديمية برقم أو بريد إلكتروني آخر، فجرّبه أو تواصل معها.'**
  String get noAccountCheckEmail;

  /// No description provided for @noAccountTryEmail.
  ///
  /// In ar, this message translates to:
  /// **'جرّب البريد الإلكتروني'**
  String get noAccountTryEmail;

  /// No description provided for @noAccountTryEmailBody.
  ///
  /// In ar, this message translates to:
  /// **'إن كان بريدك الإلكتروني لدى الأكاديمية، فسيصلك الرمز عليه.'**
  String get noAccountTryEmailBody;

  /// No description provided for @noAccountOther.
  ///
  /// In ar, this message translates to:
  /// **'جرّب رقمًا أو بريدًا إلكترونيًا آخر'**
  String get noAccountOther;

  /// No description provided for @contactChange.
  ///
  /// In ar, this message translates to:
  /// **'غيّره'**
  String get contactChange;

  /// No description provided for @consentNeededTitle.
  ///
  /// In ar, this message translates to:
  /// **'نحتاج إلى موافقة ولي أمرك'**
  String get consentNeededTitle;

  /// No description provided for @consentNeededLead.
  ///
  /// In ar, this message translates to:
  /// **'قبل أن تدخل حسابك، يجب أن يوافق ولي أمرك مرة واحدة فقط. وبعدها تدخل مباشرة.'**
  String get consentNeededLead;

  /// No description provided for @consentNeededStep1.
  ///
  /// In ar, this message translates to:
  /// **'يفتح ولي أمرك التطبيق بحسابه.'**
  String get consentNeededStep1;

  /// No description provided for @consentNeededStep2.
  ///
  /// In ar, this message translates to:
  /// **'من «{me}» ثم «{privacy}» يوافق على حسابك.'**
  String consentNeededStep2(String me, String privacy);

  /// No description provided for @consentNeededStep3.
  ///
  /// In ar, this message translates to:
  /// **'ارجع إلى هنا وادخل مرة أخرى.'**
  String get consentNeededStep3;

  /// No description provided for @signInAgain.
  ///
  /// In ar, this message translates to:
  /// **'حاول الدخول مرة أخرى'**
  String get signInAgain;

  /// No description provided for @twoFactorSetupTitle.
  ///
  /// In ar, this message translates to:
  /// **'فعّل الدخول بخطوتين أولًا'**
  String get twoFactorSetupTitle;

  /// No description provided for @twoFactorSetupLead.
  ///
  /// In ar, this message translates to:
  /// **'يجب أن يكون الدخول بخطوتين مفعّلًا على هذا الحساب. فعّله من موقع الأكاديمية على الحاسوب، ثم ادخل من هنا كالمعتاد.'**
  String get twoFactorSetupLead;

  /// No description provided for @tooManyTitle.
  ///
  /// In ar, this message translates to:
  /// **'محاولات كثيرة'**
  String get tooManyTitle;

  /// No description provided for @tooManyBody.
  ///
  /// In ar, this message translates to:
  /// **'لحماية حسابك، أوقفنا المحاولات قليلًا. انتظر حتى ينتهي العدّاد، ثم حاول مرة أخرى.'**
  String get tooManyBody;

  /// No description provided for @retryIn.
  ///
  /// In ar, this message translates to:
  /// **'حاول مرة أخرى بعد {time}'**
  String retryIn(String time);

  /// No description provided for @welcomeHello.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا يا {name}'**
  String welcomeHello(String name);

  /// No description provided for @welcomeIn.
  ///
  /// In ar, this message translates to:
  /// **'أنت الآن في {academy}'**
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
  /// **'معلّم'**
  String get roleTeacher;

  /// No description provided for @roleStaff.
  ///
  /// In ar, this message translates to:
  /// **'إدارة'**
  String get roleStaff;

  /// No description provided for @passwordTitle.
  ///
  /// In ar, this message translates to:
  /// **'ادخل بكلمة المرور'**
  String get passwordTitle;

  /// No description provided for @academyCode.
  ///
  /// In ar, this message translates to:
  /// **'رمز الأكاديمية'**
  String get academyCode;

  /// No description provided for @academyCodeHint.
  ///
  /// In ar, this message translates to:
  /// **'تجده في رسالة الترحيب، أو اسأل الأكاديمية.'**
  String get academyCodeHint;

  /// No description provided for @loginLabel.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني أو الهاتف أو اسم المستخدم'**
  String get loginLabel;

  /// No description provided for @passwordLabel.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور'**
  String get passwordLabel;

  /// No description provided for @academyCodeMissing.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رمز الأكاديمية.'**
  String get academyCodeMissing;

  /// No description provided for @loginMissing.
  ///
  /// In ar, this message translates to:
  /// **'اكتب بريدك الإلكتروني أو هاتفك أو اسم المستخدم.'**
  String get loginMissing;

  /// No description provided for @passwordMissing.
  ///
  /// In ar, this message translates to:
  /// **'اكتب كلمة المرور.'**
  String get passwordMissing;

  /// No description provided for @passwordShow.
  ///
  /// In ar, this message translates to:
  /// **'إظهار كلمة المرور'**
  String get passwordShow;

  /// No description provided for @passwordHide.
  ///
  /// In ar, this message translates to:
  /// **'إخفاء كلمة المرور'**
  String get passwordHide;

  /// No description provided for @signIn.
  ///
  /// In ar, this message translates to:
  /// **'ادخل'**
  String get signIn;

  /// No description provided for @useCodeInstead.
  ///
  /// In ar, this message translates to:
  /// **'ادخل برمز بدلًا من كلمة المرور'**
  String get useCodeInstead;

  /// No description provided for @twoFactorTitle.
  ///
  /// In ar, this message translates to:
  /// **'الدخول بخطوتين'**
  String get twoFactorTitle;

  /// No description provided for @twoFactorLead.
  ///
  /// In ar, this message translates to:
  /// **'افتح تطبيق المصادقة واكتب الرمز الذي فيه (6 أرقام). فقدت هاتفك؟ اكتب رمزًا من رموز الاسترداد.'**
  String get twoFactorLead;

  /// No description provided for @twoFactorAccount.
  ///
  /// In ar, this message translates to:
  /// **'{name} في {academy}'**
  String twoFactorAccount(String name, String academy);

  /// No description provided for @twoFactorCode.
  ///
  /// In ar, this message translates to:
  /// **'الرمز'**
  String get twoFactorCode;

  /// No description provided for @startAgain.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ من جديد'**
  String get startAgain;

  /// No description provided for @webOnlyTitle.
  ///
  /// In ar, this message translates to:
  /// **'هذا الحساب للإدارة'**
  String get webOnlyTitle;

  /// No description provided for @webOnlyBody.
  ///
  /// In ar, this message translates to:
  /// **'حسابات الإدارة تعمل من الموقع. افتح لوحة الأكاديمية من الحاسوب.'**
  String get webOnlyBody;

  /// No description provided for @privacyTitle.
  ///
  /// In ar, this message translates to:
  /// **'خصوصية الأبناء'**
  String get privacyTitle;

  /// No description provided for @privacyLead.
  ///
  /// In ar, this message translates to:
  /// **'الأبناء دون {age, plural, =1{سنة} =2{سنتين} few{{age} سنوات} other{{age} سنة}} يحتاجون إلى موافقتك ليدخلوا حساباتهم ويحضروا بأنفسهم.'**
  String privacyLead(int age);

  /// No description provided for @privacyNone.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد أبناء يحتاجون إلى موافقة.'**
  String get privacyNone;

  /// No description provided for @consentWaiting.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار موافقتك'**
  String get consentWaiting;

  /// No description provided for @consentGranted.
  ///
  /// In ar, this message translates to:
  /// **'تمت الموافقة'**
  String get consentGranted;

  /// No description provided for @consentAgree.
  ///
  /// In ar, this message translates to:
  /// **'موافق'**
  String get consentAgree;

  /// No description provided for @consentAgreed.
  ///
  /// In ar, this message translates to:
  /// **'تم، يستطيع {name} الدخول الآن.'**
  String consentAgreed(String name);

  /// No description provided for @consentWithdraw.
  ///
  /// In ar, this message translates to:
  /// **'اسحب الموافقة'**
  String get consentWithdraw;

  /// No description provided for @consentWithdrawConfirm.
  ///
  /// In ar, this message translates to:
  /// **'سيخرج {name} من حسابه على كل الأجهزة، ولن يستطيع الدخول حتى توافق مرة أخرى. هل أنت متأكد؟'**
  String consentWithdrawConfirm(String name);

  /// No description provided for @consentWithdrawn.
  ///
  /// In ar, this message translates to:
  /// **'سحبت موافقتك، وخرج {name} من حسابه.'**
  String consentWithdrawn(String name);

  /// No description provided for @consentNudge.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{{names} بانتظار موافقتك ليدخل حسابه.} other{{names} بانتظار موافقتك ليدخلوا حساباتهم.}}'**
  String consentNudge(int n, String names);

  /// No description provided for @consentNudgeAction.
  ///
  /// In ar, this message translates to:
  /// **'اقرأ ووافق'**
  String get consentNudgeAction;

  /// No description provided for @myDataTitle.
  ///
  /// In ar, this message translates to:
  /// **'بياناتي'**
  String get myDataTitle;

  /// No description provided for @myDataLead.
  ///
  /// In ar, this message translates to:
  /// **'كل ما تحتفظ به الأكاديمية عنك وعن أبنائك: صحّح بياناتك، أو نزّل نسخة، أو اطلب حذف حساب.'**
  String get myDataLead;

  /// No description provided for @myDataLeadTeacher.
  ///
  /// In ar, this message translates to:
  /// **'كل ما تحتفظ به الأكاديمية عنك: صحّح اسمك، أو نزّل نسخة، أو اطلب حذف حسابك.'**
  String get myDataLeadTeacher;

  /// No description provided for @myDataYoung.
  ///
  /// In ar, this message translates to:
  /// **'ولي أمرك هو من يقدّم الطلبات الخاصة ببياناتك من حسابه.'**
  String get myDataYoung;

  /// No description provided for @myDataCopyTitle.
  ///
  /// In ar, this message translates to:
  /// **'نسخة من البيانات'**
  String get myDataCopyTitle;

  /// No description provided for @myDataCopyBody.
  ///
  /// In ar, this message translates to:
  /// **'ملف ZIP واحد: البيانات في ملف يمكن قراءته، ومعها الملفات التي سُلّمت. تجهز في دقائق وتبقى {days, plural, =1{يومًا واحدًا} =2{يومين} few{{days} أيام} other{{days} يومًا}}.'**
  String myDataCopyBody(int days);

  /// No description provided for @myDataCopyAction.
  ///
  /// In ar, this message translates to:
  /// **'جهّز نسخة'**
  String get myDataCopyAction;

  /// No description provided for @myDataCopyPreparing.
  ///
  /// In ar, this message translates to:
  /// **'قيد التجهيز: سنخبرك فور اكتمالها.'**
  String get myDataCopyPreparing;

  /// No description provided for @myDataCopyDownload.
  ///
  /// In ar, this message translates to:
  /// **'نزّل'**
  String get myDataCopyDownload;

  /// No description provided for @myDataCopyUntil.
  ///
  /// In ar, this message translates to:
  /// **'النسخة جاهزة حتى {when}'**
  String myDataCopyUntil(String when);

  /// No description provided for @myDataEraseTitle.
  ///
  /// In ar, this message translates to:
  /// **'حذف الحساب'**
  String get myDataEraseTitle;

  /// No description provided for @myDataEraseBody.
  ///
  /// In ar, this message translates to:
  /// **'ترد الأكاديمية خلال {days, plural, =1{يوم واحد} =2{يومين} few{{days} أيام} other{{days} يومًا}}، وعندما توافق يُحذف الحساب بعد ذلك بيوم.'**
  String myDataEraseBody(int days);

  /// No description provided for @myDataEraseAction.
  ///
  /// In ar, this message translates to:
  /// **'احذف هذا الحساب'**
  String get myDataEraseAction;

  /// No description provided for @myDataEraseConfirm.
  ///
  /// In ar, this message translates to:
  /// **'أتطلب حذف حساب {name}؟'**
  String myDataEraseConfirm(String name);

  /// No description provided for @myDataEraseCopyFirst.
  ///
  /// In ar, this message translates to:
  /// **'يمكنك تنزيل نسخة أولًا.'**
  String get myDataEraseCopyFirst;

  /// No description provided for @myDataEraseReason.
  ///
  /// In ar, this message translates to:
  /// **'لماذا؟ (إن أردت)'**
  String get myDataEraseReason;

  /// No description provided for @myDataEraseWaiting.
  ///
  /// In ar, this message translates to:
  /// **'طُلب: سترد الأكاديمية قبل {when}.'**
  String myDataEraseWaiting(String when);

  /// No description provided for @myDataEraseApproved.
  ///
  /// In ar, this message translates to:
  /// **'تمت الموافقة: سيُحذف الحساب {when}.'**
  String myDataEraseApproved(String when);

  /// No description provided for @myDataEraseRefused.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية رفضت: {answer}'**
  String myDataEraseRefused(String answer);

  /// No description provided for @myDataEraseCancel.
  ///
  /// In ar, this message translates to:
  /// **'تراجع عن طلبك'**
  String get myDataEraseCancel;

  /// No description provided for @myDataEraseAsked.
  ///
  /// In ar, this message translates to:
  /// **'وصل الطلب إلى الأكاديمية.'**
  String get myDataEraseAsked;

  /// No description provided for @myDataEraseCancelled.
  ///
  /// In ar, this message translates to:
  /// **'تراجعت عن الطلب: سيبقى الحساب.'**
  String get myDataEraseCancelled;

  /// No description provided for @myDataWhatGoes.
  ///
  /// In ar, this message translates to:
  /// **'ما الذي يُحذف وما الذي يبقى'**
  String get myDataWhatGoes;

  /// No description provided for @myDataWhatStays.
  ///
  /// In ar, this message translates to:
  /// **'ما الذي يبقى، دون الاسم'**
  String get myDataWhatStays;

  /// No description provided for @myDataHistory.
  ///
  /// In ar, this message translates to:
  /// **'الطلبات'**
  String get myDataHistory;

  /// No description provided for @myDataNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد طلبات بعد.'**
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
  /// **'حُفظ.'**
  String get myDataFixSaved;

  /// No description provided for @myDataFixUnchanged.
  ///
  /// In ar, this message translates to:
  /// **'لم يتغيّر شيء.'**
  String get myDataFixUnchanged;

  /// No description provided for @myDataFixUnset.
  ///
  /// In ar, this message translates to:
  /// **'غير مسجّل'**
  String get myDataFixUnset;

  /// No description provided for @myDataFixContact.
  ///
  /// In ar, this message translates to:
  /// **'تغيّر البريد الإلكتروني ورقم الهاتف من «حسابي».'**
  String get myDataFixContact;

  /// No description provided for @myDataFixBody.
  ///
  /// In ar, this message translates to:
  /// **'هل هناك خطأ آخر (تاريخ الميلاد، اسم في فاتورة، كلام في تقرير)؟ اكتبه للأكاديمية، وسترد خلال {days, plural, =1{يوم واحد} =2{يومين} few{{days} أيام} other{{days} يومًا}}.'**
  String myDataFixBody(int days);

  /// No description provided for @myDataFixAsk.
  ///
  /// In ar, this message translates to:
  /// **'اطلب تصحيحًا'**
  String get myDataFixAsk;

  /// No description provided for @myDataFixAskTitle.
  ///
  /// In ar, this message translates to:
  /// **'اطلب تصحيح بيانات {name}'**
  String myDataFixAskTitle(String name);

  /// No description provided for @myDataFixAskBody.
  ///
  /// In ar, this message translates to:
  /// **'اكتب ما الخطأ وما الصواب. ترد الأكاديمية خلال {days, plural, =1{يوم واحد} =2{يومين} few{{days} أيام} other{{days} يومًا}}، ويصلك ردها.'**
  String myDataFixAskBody(int days);

  /// No description provided for @myDataFixSays.
  ///
  /// In ar, this message translates to:
  /// **'ما الذي يحتاج إلى تصحيح'**
  String get myDataFixSays;

  /// No description provided for @myDataFixPlaceholder.
  ///
  /// In ar, this message translates to:
  /// **'مثلًا: تاريخ الميلاد المسجّل 2016 والصحيح 2017.'**
  String get myDataFixPlaceholder;

  /// No description provided for @myDataFixTooShort.
  ///
  /// In ar, this message translates to:
  /// **'اكتب {min, plural, =1{حرفًا واحدًا} =2{حرفين} few{{min} أحرف} other{{min} حرفًا}} على الأقل.'**
  String myDataFixTooShort(int min);

  /// No description provided for @myDataFixSend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل الطلب'**
  String get myDataFixSend;

  /// No description provided for @myDataFixAsked.
  ///
  /// In ar, this message translates to:
  /// **'وصل الطلب إلى الأكاديمية.'**
  String get myDataFixAsked;

  /// No description provided for @myDataFixWaiting.
  ///
  /// In ar, this message translates to:
  /// **'طُلب: سترد الأكاديمية قبل {when}.'**
  String myDataFixWaiting(String when);

  /// No description provided for @myDataFixDone.
  ///
  /// In ar, this message translates to:
  /// **'صُحّح'**
  String get myDataFixDone;

  /// No description provided for @myDataFixDoneWith.
  ///
  /// In ar, this message translates to:
  /// **'صُحّح: {answer}'**
  String myDataFixDoneWith(String answer);

  /// No description provided for @myDataFixRefused.
  ///
  /// In ar, this message translates to:
  /// **'لم تصحّحه الأكاديمية: {answer}'**
  String myDataFixRefused(String answer);

  /// No description provided for @myDataFixCancel.
  ///
  /// In ar, this message translates to:
  /// **'ألغِ الطلب'**
  String get myDataFixCancel;

  /// No description provided for @myDataFixCancelled.
  ///
  /// In ar, this message translates to:
  /// **'أُلغي الطلب.'**
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
  /// **'حذف الحساب'**
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
  /// **'المدفوعات'**
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
  /// **'اليوم'**
  String get navToday;

  /// No description provided for @navWeek.
  ///
  /// In ar, this message translates to:
  /// **'أسبوعي'**
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
  /// **'الحصة القادمة'**
  String get nextLesson;

  /// No description provided for @noNextLesson.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص قادمة في الأسابيع الأربعة المقبلة.'**
  String get noNextLesson;

  /// No description provided for @roomOpensIn.
  ///
  /// In ar, this message translates to:
  /// **'يُفتح الفصل بعد {time}'**
  String roomOpensIn(String time);

  /// No description provided for @roomOpen.
  ///
  /// In ar, this message translates to:
  /// **'الفصل مفتوح'**
  String get roomOpen;

  /// No description provided for @lessonOn.
  ///
  /// In ar, this message translates to:
  /// **'الحصة جارية الآن'**
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
  /// **'يُفتح {time}'**
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
  /// **'يُفتح الفصل {time}'**
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
  /// **'عن مَن؟'**
  String get pickLearner;

  /// No description provided for @pickedNow.
  ///
  /// In ar, this message translates to:
  /// **'الآن'**
  String get pickedNow;

  /// No description provided for @balance.
  ///
  /// In ar, this message translates to:
  /// **'الرصيد'**
  String get balance;

  /// No description provided for @owed.
  ///
  /// In ar, this message translates to:
  /// **'المستحق عليك'**
  String get owed;

  /// No description provided for @payNow.
  ///
  /// In ar, this message translates to:
  /// **'ادفع الآن'**
  String get payNow;

  /// No description provided for @nothingOwed.
  ///
  /// In ar, this message translates to:
  /// **'لا شيء مستحق عليك'**
  String get nothingOwed;

  /// No description provided for @lastReport.
  ///
  /// In ar, this message translates to:
  /// **'آخر تقرير'**
  String get lastReport;

  /// No description provided for @readReport.
  ///
  /// In ar, this message translates to:
  /// **'اقرأه'**
  String get readReport;

  /// No description provided for @homeworkLine.
  ///
  /// In ar, this message translates to:
  /// **'واجب: {title}'**
  String homeworkLine(String title);

  /// No description provided for @unfunded.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار الدفع'**
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
  /// **'القادمة'**
  String get upcoming;

  /// No description provided for @past.
  ///
  /// In ar, this message translates to:
  /// **'السابقة'**
  String get past;

  /// No description provided for @noUpcoming.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص قادمة في الأسابيع الأربعة المقبلة.'**
  String get noUpcoming;

  /// No description provided for @noPast.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص في الأسابيع الأربعة الماضية.'**
  String get noPast;

  /// No description provided for @substituteFor.
  ///
  /// In ar, this message translates to:
  /// **'معلّم بديل: {name} في إجازة'**
  String substituteFor(String name);

  /// No description provided for @refuseSubstitute.
  ///
  /// In ar, this message translates to:
  /// **'لا نوافق على البديل'**
  String get refuseSubstitute;

  /// No description provided for @refuseSubstituteTitle.
  ///
  /// In ar, this message translates to:
  /// **'ألا توافقون على البديل؟'**
  String get refuseSubstituteTitle;

  /// No description provided for @refuseSubstituteBody.
  ///
  /// In ar, this message translates to:
  /// **'ستُلغى الحصة على حساب الأكاديمية، وتعود دقائقها إليك حصة تعويضية تحجزها لاحقًا.'**
  String get refuseSubstituteBody;

  /// No description provided for @refuseSubstituteYes.
  ///
  /// In ar, this message translates to:
  /// **'نعم، ألغِها وعوّضونا'**
  String get refuseSubstituteYes;

  /// No description provided for @substituteChip.
  ///
  /// In ar, this message translates to:
  /// **'بديل عن {name}'**
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
  /// **'يُفتح الفصل {when}'**
  String roomOpensOn(String when);

  /// No description provided for @lessonDetails.
  ///
  /// In ar, this message translates to:
  /// **'تفاصيل الحصة'**
  String get lessonDetails;

  /// No description provided for @refusedSubstitute.
  ///
  /// In ar, this message translates to:
  /// **'أُلغيت. لديك {days, plural, =1{يوم واحد} =2{يومان} few{{days} أيام} other{{days} يومًا}} لتحجز فيها حصة التعويض.'**
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
  /// **'الحصة مسجّلة \"{outcome}\". إن لم يكن هذا ما حدث، فأخبرونا خلال 48 ساعة من انتهائها، وستراجع الأكاديمية حضور الفصل وترد عليكم.'**
  String disputeIntroFamily(String outcome);

  /// No description provided for @disputeIntroTeacher.
  ///
  /// In ar, this message translates to:
  /// **'الحصة مسجّلة \"{outcome}\". إن لم يكن هذا ما حدث، فأخبرنا خلال 48 ساعة من انتهائها، وستراجع الأكاديمية حضور الفصل وترد عليك.'**
  String disputeIntroTeacher(String outcome);

  /// No description provided for @disputeWhat.
  ///
  /// In ar, this message translates to:
  /// **'ماذا حدث؟'**
  String get disputeWhat;

  /// No description provided for @disputeNote.
  ///
  /// In ar, this message translates to:
  /// **'التفاصيل'**
  String get disputeNote;

  /// No description provided for @disputeNoteHint.
  ///
  /// In ar, this message translates to:
  /// **'لن يقرأ هذا إلا الأكاديمية.'**
  String get disputeNoteHint;

  /// No description provided for @disputeFillIn.
  ///
  /// In ar, this message translates to:
  /// **'اختر السبب واكتب ما حدث.'**
  String get disputeFillIn;

  /// No description provided for @disputeSend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل الاعتراض'**
  String get disputeSend;

  /// No description provided for @disputeSent.
  ///
  /// In ar, this message translates to:
  /// **'وصل الاعتراض، والحصة قيد المراجعة.'**
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
  /// **'أتلغي حصة {day}؟'**
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
  /// **'لا، أبقِها'**
  String get keepIt;

  /// No description provided for @yesCancel.
  ///
  /// In ar, this message translates to:
  /// **'نعم، ألغِها'**
  String get yesCancel;

  /// No description provided for @cancelled.
  ///
  /// In ar, this message translates to:
  /// **'أُلغيت الحصة.'**
  String get cancelled;

  /// No description provided for @pickReason.
  ///
  /// In ar, this message translates to:
  /// **'اختر سببًا أولًا.'**
  String get pickReason;

  /// No description provided for @rateLesson.
  ///
  /// In ar, this message translates to:
  /// **'قيّم الحصة'**
  String get rateLesson;

  /// No description provided for @rateTitle.
  ///
  /// In ar, this message translates to:
  /// **'كيف كانت الحصة؟'**
  String get rateTitle;

  /// No description provided for @rateGood.
  ///
  /// In ar, this message translates to:
  /// **'ما الذي أعجبكم؟'**
  String get rateGood;

  /// No description provided for @rateBad.
  ///
  /// In ar, this message translates to:
  /// **'ما الذي لم يعجبكم؟'**
  String get rateBad;

  /// No description provided for @rateComment.
  ///
  /// In ar, this message translates to:
  /// **'كلمة للأكاديمية'**
  String get rateComment;

  /// No description provided for @rateAnonymous.
  ///
  /// In ar, this message translates to:
  /// **'يرى المعلّم التقييم دون اسمك.'**
  String get rateAnonymous;

  /// No description provided for @sendRating.
  ///
  /// In ar, this message translates to:
  /// **'أرسل التقييم'**
  String get sendRating;

  /// No description provided for @notNow.
  ///
  /// In ar, this message translates to:
  /// **'ليس الآن'**
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
  /// **'سيئة'**
  String get stars1;

  /// No description provided for @stars2.
  ///
  /// In ar, this message translates to:
  /// **'ليست جيدة تمامًا'**
  String get stars2;

  /// No description provided for @stars3.
  ///
  /// In ar, this message translates to:
  /// **'جيدة'**
  String get stars3;

  /// No description provided for @stars4.
  ///
  /// In ar, this message translates to:
  /// **'جيدة جدًا'**
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
  /// **'الآن: {title}'**
  String hereNow(String title);

  /// No description provided for @weeksLeft.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{أوشك على الانتهاء} =1{بقي نحو أسبوع} =2{بقي نحو أسبوعين} few{بقي نحو {n} أسابيع} other{بقي نحو {n} أسبوعًا}}'**
  String weeksLeft(int n);

  /// No description provided for @homeworkOpen.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{واجب مفتوح} =2{واجبان مفتوحان} few{{n} واجبات مفتوحة} other{{n} واجبًا مفتوحًا}}'**
  String homeworkOpen(int n);

  /// No description provided for @reports.
  ///
  /// In ar, this message translates to:
  /// **'التقارير'**
  String get reports;

  /// No description provided for @noReports.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد تقارير بعد.'**
  String get noReports;

  /// No description provided for @noCourses.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد اشتراكات جارية.'**
  String get noCourses;

  /// No description provided for @noProgram.
  ///
  /// In ar, this message translates to:
  /// **'دون منهج'**
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
  /// **'بالانتظار'**
  String get homeworkWaiting;

  /// No description provided for @homeworkSent.
  ///
  /// In ar, this message translates to:
  /// **'سُلّم'**
  String get homeworkSent;

  /// No description provided for @homeworkMarked.
  ///
  /// In ar, this message translates to:
  /// **'صُحّح'**
  String get homeworkMarked;

  /// No description provided for @homeworkWaitingOnYou.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{واجب ينتظرك} =2{واجبان ينتظرانك} few{{n} واجبات تنتظرك} other{{n} واجبًا ينتظرك}}'**
  String homeworkWaitingOnYou(int n);

  /// No description provided for @homeworkNoneWaiting.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد واجبات تنتظرك الآن.'**
  String get homeworkNoneWaiting;

  /// No description provided for @homeworkNoneSent.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد واجبات تنتظر التصحيح.'**
  String get homeworkNoneSent;

  /// No description provided for @homeworkNoneMarked.
  ///
  /// In ar, this message translates to:
  /// **'لم يُصحَّح أي واجب بعد.'**
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
  /// **'لا توجد واجبات الآن.'**
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
  /// **'سُلّم. سيصحّحه المعلّم.'**
  String get handedIn;

  /// No description provided for @teacherFeedback.
  ///
  /// In ar, this message translates to:
  /// **'رأي المعلّم'**
  String get teacherFeedback;

  /// No description provided for @grade.
  ///
  /// In ar, this message translates to:
  /// **'الدرجة: {g} من 5'**
  String grade(int g);

  /// No description provided for @yourSubmission.
  ///
  /// In ar, this message translates to:
  /// **'ما سلّمته'**
  String get yourSubmission;

  /// No description provided for @kidsHomeworkStar.
  ///
  /// In ar, this message translates to:
  /// **'كل واجب تسلّمه يعطيك نجمة جديدة!'**
  String get kidsHomeworkStar;

  /// No description provided for @owedNow.
  ///
  /// In ar, this message translates to:
  /// **'المستحق الآن'**
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
  /// **'يتم الدفع في صفحة الدفع الخاصة بالأكاديمية. عندما تنتهي، ارجع إلى هنا.'**
  String get payNote;

  /// No description provided for @lessonsLeftTitle.
  ///
  /// In ar, this message translates to:
  /// **'الحصص المتبقية'**
  String get lessonsLeftTitle;

  /// No description provided for @invoices.
  ///
  /// In ar, this message translates to:
  /// **'الفواتير'**
  String get invoices;

  /// No description provided for @noInvoices.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد فواتير.'**
  String get noInvoices;

  /// No description provided for @openInvoice.
  ///
  /// In ar, this message translates to:
  /// **'افتح الفاتورة'**
  String get openInvoice;

  /// No description provided for @extraAction.
  ///
  /// In ar, this message translates to:
  /// **'حصص إضافية'**
  String get extraAction;

  /// No description provided for @extraHint.
  ///
  /// In ar, this message translates to:
  /// **'سعر الحصة الإضافية {price} ({minutes, plural, =1{دقيقة} =2{دقيقتان} few{{minutes} دقائق} other{{minutes} دقيقة}}).'**
  String extraHint(String price, int minutes);

  /// No description provided for @extraTitle.
  ///
  /// In ar, this message translates to:
  /// **'حصص إضافية في {subject}'**
  String extraTitle(String subject);

  /// No description provided for @extraBody.
  ///
  /// In ar, this message translates to:
  /// **'اختر العدد، وستصدر لك فاتورة تدفعها مثل أي فاتورة. بعد أن تدفع، تحجز الأكاديمية الحصص معك.'**
  String get extraBody;

  /// No description provided for @extraEach.
  ///
  /// In ar, this message translates to:
  /// **'{n} × {price}'**
  String extraEach(int n, String price);

  /// No description provided for @extraSubmit.
  ///
  /// In ar, this message translates to:
  /// **'أصدر الفاتورة'**
  String get extraSubmit;

  /// No description provided for @extraDone.
  ///
  /// In ar, this message translates to:
  /// **'الفاتورة {number} جاهزة ({total}).'**
  String extraDone(String number, String total);

  /// No description provided for @extraWaiting.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة الحصص الإضافية {number} بانتظار الدفع.'**
  String extraWaiting(String number);

  /// No description provided for @invoiceNote.
  ///
  /// In ar, this message translates to:
  /// **'افتح أي فاتورة لتعرضها وتطبعها، وفيها رمز QR الخاص بالفاتورة الضريبية.'**
  String get invoiceNote;

  /// No description provided for @cantPayOnline.
  ///
  /// In ar, this message translates to:
  /// **'الدفع الإلكتروني غير متاح لهذه الفاتورة. تواصل مع الأكاديمية.'**
  String get cantPayOnline;

  /// No description provided for @pickGateway.
  ///
  /// In ar, this message translates to:
  /// **'بماذا تدفع؟'**
  String get pickGateway;

  /// No description provided for @paidByTransfer.
  ///
  /// In ar, this message translates to:
  /// **'دفعت بالتحويل'**
  String get paidByTransfer;

  /// No description provided for @proofTitle.
  ///
  /// In ar, this message translates to:
  /// **'أرسل إثبات الدفع'**
  String get proofTitle;

  /// No description provided for @proofHint.
  ///
  /// In ar, this message translates to:
  /// **'ادفع أولًا، ثم أرسل الإيصال هنا. ستتحقّق الأكاديمية من وصول المبلغ وتؤكّد الدفعة.'**
  String get proofHint;

  /// No description provided for @proofHowTo.
  ///
  /// In ar, this message translates to:
  /// **'كيف تدفع بالتحويل'**
  String get proofHowTo;

  /// No description provided for @proofMethod.
  ///
  /// In ar, this message translates to:
  /// **'طريقة الدفع'**
  String get proofMethod;

  /// No description provided for @proofAmount.
  ///
  /// In ar, this message translates to:
  /// **'المبلغ الذي دفعته'**
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
  /// **'اختر صورة أو ملف PDF'**
  String get proofPickReceipt;

  /// No description provided for @proofReceiptHint.
  ///
  /// In ar, this message translates to:
  /// **'صورة أو لقطة شاشة أو ملف PDF من البنك، حتى 10 ميغابايت.'**
  String get proofReceiptHint;

  /// No description provided for @proofSend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل الإثبات'**
  String get proofSend;

  /// No description provided for @proofSent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسل الإثبات. سنخبرك فور تأكيد الأكاديمية له.'**
  String get proofSent;

  /// No description provided for @proofNeedsReceipt.
  ///
  /// In ar, this message translates to:
  /// **'اختر الإيصال أولًا.'**
  String get proofNeedsReceipt;

  /// No description provided for @proofFileTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 10 ميغابايت.'**
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
  /// **'نُسخت بيانات التحويل.'**
  String get transferCopied;

  /// No description provided for @joining.
  ///
  /// In ar, this message translates to:
  /// **'ندخلك إلى الحصة…'**
  String get joining;

  /// No description provided for @linkNotOpened.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد تطبيق على هذا الهاتف يفتح هذا الرابط.'**
  String get linkNotOpened;

  /// No description provided for @openedOutside.
  ///
  /// In ar, this message translates to:
  /// **'فُتحت الحصة في تطبيق آخر.'**
  String get openedOutside;

  /// No description provided for @leave.
  ///
  /// In ar, this message translates to:
  /// **'اخرج من الحصة'**
  String get leave;

  /// No description provided for @micOff.
  ///
  /// In ar, this message translates to:
  /// **'أغلق الميكروفون'**
  String get micOff;

  /// No description provided for @micOn.
  ///
  /// In ar, this message translates to:
  /// **'افتح الميكروفون'**
  String get micOn;

  /// No description provided for @camOff.
  ///
  /// In ar, this message translates to:
  /// **'أغلق الكاميرا'**
  String get camOff;

  /// No description provided for @camOn.
  ///
  /// In ar, this message translates to:
  /// **'افتح الكاميرا'**
  String get camOn;

  /// No description provided for @audioOnly.
  ///
  /// In ar, this message translates to:
  /// **'الصوت فقط'**
  String get audioOnly;

  /// No description provided for @rejoinHint.
  ///
  /// In ar, this message translates to:
  /// **'إن خرجت بالخطأ، فادخل مرة أخرى من الزر نفسه حتى تنتهي الحصة.'**
  String get rejoinHint;

  /// No description provided for @weakNetwork.
  ///
  /// In ar, this message translates to:
  /// **'الاتصال ضعيف. جرّب \"الصوت فقط\" إن كانت الصورة تتقطّع.'**
  String get weakNetwork;

  /// No description provided for @waitingOther.
  ///
  /// In ar, this message translates to:
  /// **'ننتظر دخول الطرف الآخر…'**
  String get waitingOther;

  /// No description provided for @you.
  ///
  /// In ar, this message translates to:
  /// **'أنت'**
  String get you;

  /// No description provided for @minutesLeft.
  ///
  /// In ar, this message translates to:
  /// **'بقي {n, plural, =1{دقيقة} =2{دقيقتان} few{{n} دقائق} other{{n} دقيقة}}'**
  String minutesLeft(int n);

  /// No description provided for @sharedPackage.
  ///
  /// In ar, this message translates to:
  /// **'{subjects} · باقة مشتركة'**
  String sharedPackage(String subjects);

  /// No description provided for @familyInvoice.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة العائلة'**
  String get familyInvoice;

  /// No description provided for @coursePaused.
  ///
  /// In ar, this message translates to:
  /// **'متوقف حتى {day}'**
  String coursePaused(String day);

  /// No description provided for @coursePausedKids.
  ///
  /// In ar, this message translates to:
  /// **'في إجازة حتى {day}'**
  String coursePausedKids(String day);

  /// No description provided for @coursePauseAhead.
  ///
  /// In ar, this message translates to:
  /// **'سيتوقف من {from} حتى {until}'**
  String coursePauseAhead(String from, String until);

  /// No description provided for @courseEnding.
  ///
  /// In ar, this message translates to:
  /// **'ينتهي من {day}'**
  String courseEnding(String day);

  /// No description provided for @courseEnded.
  ///
  /// In ar, this message translates to:
  /// **'انتهى، والدقائق المتبقية ما زالت في الرصيد'**
  String get courseEnded;

  /// No description provided for @lessonUntil.
  ///
  /// In ar, this message translates to:
  /// **'الحصة حتى {time}'**
  String lessonUntil(String time);

  /// No description provided for @meTitle.
  ///
  /// In ar, this message translates to:
  /// **'حسابي'**
  String get meTitle;

  /// No description provided for @moreChildren.
  ///
  /// In ar, this message translates to:
  /// **'الأبناء'**
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
  /// **'عملي'**
  String get moreWork;

  /// No description provided for @moreStudy.
  ///
  /// In ar, this message translates to:
  /// **'الدراسة'**
  String get moreStudy;

  /// No description provided for @moreHelp.
  ///
  /// In ar, this message translates to:
  /// **'المساعدة'**
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
  /// **'غير محدد'**
  String get meetingLinkMissing;

  /// No description provided for @moreAcademies.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{أكاديمية واحدة} =2{أكاديميتان} few{{n} أكاديميات} other{{n} أكاديمية}}'**
  String moreAcademies(int n);

  /// No description provided for @guardianOf.
  ///
  /// In ar, this message translates to:
  /// **'ولي أمر {names}'**
  String guardianOf(String names);

  /// No description provided for @appVersion.
  ///
  /// In ar, this message translates to:
  /// **'الإصدار {version}'**
  String appVersion(String version);

  /// No description provided for @contactTitle.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني والهاتف'**
  String get contactTitle;

  /// No description provided for @contactHint.
  ///
  /// In ar, this message translates to:
  /// **'هذه طرق دخولك، وتصلك عليها الرموز والرسائل. عندما تغيّر أحدها، نرسل رمزًا إلى الجديد لنتأكد أنه لك.'**
  String get contactHint;

  /// No description provided for @contactEmail.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني'**
  String get contactEmail;

  /// No description provided for @contactPhone.
  ///
  /// In ar, this message translates to:
  /// **'رقم الهاتف'**
  String get contactPhone;

  /// No description provided for @contactNone.
  ///
  /// In ar, this message translates to:
  /// **'غير مسجّل'**
  String get contactNone;

  /// No description provided for @contactChangeEmail.
  ///
  /// In ar, this message translates to:
  /// **'غيّر البريد'**
  String get contactChangeEmail;

  /// No description provided for @contactChangePhone.
  ///
  /// In ar, this message translates to:
  /// **'غيّر الرقم'**
  String get contactChangePhone;

  /// No description provided for @contactAddEmail.
  ///
  /// In ar, this message translates to:
  /// **'أضف بريدًا'**
  String get contactAddEmail;

  /// No description provided for @contactAddPhone.
  ///
  /// In ar, this message translates to:
  /// **'أضف رقمًا'**
  String get contactAddPhone;

  /// No description provided for @contactNewEmail.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني الجديد'**
  String get contactNewEmail;

  /// No description provided for @contactNewPhone.
  ///
  /// In ar, this message translates to:
  /// **'الرقم الجديد'**
  String get contactNewPhone;

  /// No description provided for @contactPhoneHint.
  ///
  /// In ar, this message translates to:
  /// **'مع رمز الدولة، أو رقمًا محليًا من بلدك.'**
  String get contactPhoneHint;

  /// No description provided for @contactSend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل رمزًا'**
  String get contactSend;

  /// No description provided for @contactCode.
  ///
  /// In ar, this message translates to:
  /// **'الرمز الذي وصلك'**
  String get contactCode;

  /// No description provided for @contactCodeHint.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا رمزًا إلى {contact}. صالح حتى {until}.'**
  String contactCodeHint(String contact, String until);

  /// No description provided for @contactConfirm.
  ///
  /// In ar, this message translates to:
  /// **'أكّد'**
  String get contactConfirm;

  /// No description provided for @contactResend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل رمزًا آخر'**
  String get contactResend;

  /// No description provided for @contactDoneEmail.
  ///
  /// In ar, this message translates to:
  /// **'تغيّر البريد الإلكتروني. من الآن تدخل به وتصلك الرسائل عليه.'**
  String get contactDoneEmail;

  /// No description provided for @contactDonePhone.
  ///
  /// In ar, this message translates to:
  /// **'تغيّر الرقم. من الآن تدخل به وتصلك الرسائل عليه.'**
  String get contactDonePhone;

  /// No description provided for @contactManaged.
  ///
  /// In ar, this message translates to:
  /// **'الأكاديمية أو ولي أمرك هما من يغيّران بريدك الإلكتروني ورقمك.'**
  String get contactManaged;

  /// No description provided for @openNow.
  ///
  /// In ar, this message translates to:
  /// **'مفتوح الآن'**
  String get openNow;

  /// No description provided for @switchTo.
  ///
  /// In ar, this message translates to:
  /// **'انتقل'**
  String get switchTo;

  /// No description provided for @addAccount.
  ///
  /// In ar, this message translates to:
  /// **'أضف حسابًا آخر'**
  String get addAccount;

  /// No description provided for @clockPrompt.
  ///
  /// In ar, this message translates to:
  /// **'هاتفك على توقيت {device}، وحسابك على {profile}. هل نعرض المواعيد بتوقيت {device}؟'**
  String clockPrompt(String device, String profile);

  /// No description provided for @clockYes.
  ///
  /// In ar, this message translates to:
  /// **'نعم، {zone}'**
  String clockYes(String zone);

  /// No description provided for @clockKeep.
  ///
  /// In ar, this message translates to:
  /// **'أبقِه {zone}'**
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

  /// No description provided for @zoneChip.
  ///
  /// In ar, this message translates to:
  /// **'توقيتك {city}، والساعة الآن {time}'**
  String zoneChip(String city, String time);

  /// No description provided for @zoneChipMismatch.
  ///
  /// In ar, this message translates to:
  /// **'توقيت هاتفك يختلف عن توقيت حسابك'**
  String get zoneChipMismatch;

  /// No description provided for @zoneShownIn.
  ///
  /// In ar, this message translates to:
  /// **'المواعيد في التطبيق بتوقيت {zone}.'**
  String zoneShownIn(String zone);

  /// No description provided for @zoneYours.
  ///
  /// In ar, this message translates to:
  /// **'توقيت حسابك'**
  String get zoneYours;

  /// No description provided for @zoneAcademy.
  ///
  /// In ar, this message translates to:
  /// **'توقيت الأكاديمية'**
  String get zoneAcademy;

  /// No description provided for @zoneDevice.
  ///
  /// In ar, this message translates to:
  /// **'توقيت هاتفك'**
  String get zoneDevice;

  /// No description provided for @zoneUseDevice.
  ///
  /// In ar, this message translates to:
  /// **'استخدم توقيت هاتفي'**
  String get zoneUseDevice;

  /// No description provided for @zoneChange.
  ///
  /// In ar, this message translates to:
  /// **'غيّر توقيتي'**
  String get zoneChange;

  /// No description provided for @zonePick.
  ///
  /// In ar, this message translates to:
  /// **'اختر توقيتك'**
  String get zonePick;

  /// No description provided for @zoneSearch.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن مدينة أو منطقة'**
  String get zoneSearch;

  /// No description provided for @zoneNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مدينة بهذا الاسم.'**
  String get zoneNone;

  /// No description provided for @zoneSaved.
  ///
  /// In ar, this message translates to:
  /// **'صارت مواعيدك بتوقيت {zone}.'**
  String zoneSaved(String zone);

  /// No description provided for @zoneHint.
  ///
  /// In ar, this message translates to:
  /// **'تظهر مواعيد حصصك وتذكيراتها بهذا التوقيت، واضغط لتغييره.'**
  String get zoneHint;

  /// No description provided for @zoneCurrent.
  ///
  /// In ar, this message translates to:
  /// **'الحالي'**
  String get zoneCurrent;

  /// No description provided for @timeFormat.
  ///
  /// In ar, this message translates to:
  /// **'شكل الساعة'**
  String get timeFormat;

  /// No description provided for @timeFormatExample.
  ///
  /// In ar, this message translates to:
  /// **'تظهر المواعيد هكذا: {time}'**
  String timeFormatExample(String time);

  /// No description provided for @timeFormatAcademy.
  ///
  /// In ar, this message translates to:
  /// **'مثل الأكاديمية ({hours})'**
  String timeFormatAcademy(String hours);

  /// No description provided for @timeFormat12.
  ///
  /// In ar, this message translates to:
  /// **'12 ساعة'**
  String get timeFormat12;

  /// No description provided for @timeFormat24.
  ///
  /// In ar, this message translates to:
  /// **'24 ساعة'**
  String get timeFormat24;

  /// No description provided for @myDevices.
  ///
  /// In ar, this message translates to:
  /// **'أجهزتي'**
  String get myDevices;

  /// No description provided for @thisDevice.
  ///
  /// In ar, this message translates to:
  /// **'هذا الجهاز'**
  String get thisDevice;

  /// No description provided for @lastUsed.
  ///
  /// In ar, this message translates to:
  /// **'آخر مرة {when}'**
  String lastUsed(String when);

  /// No description provided for @signOutDevice.
  ///
  /// In ar, this message translates to:
  /// **'أخرِجه'**
  String get signOutDevice;

  /// No description provided for @deviceSignedOut.
  ///
  /// In ar, this message translates to:
  /// **'خرج الجهاز من الحساب.'**
  String get deviceSignedOut;

  /// No description provided for @signOut.
  ///
  /// In ar, this message translates to:
  /// **'اخرج من هذا الحساب'**
  String get signOut;

  /// No description provided for @signOutConfirm.
  ///
  /// In ar, this message translates to:
  /// **'أتخرج من {academy} على هذا الهاتف؟'**
  String signOutConfirm(String academy);

  /// No description provided for @todayHeader.
  ///
  /// In ar, this message translates to:
  /// **'{date} · بتوقيت {zone}'**
  String todayHeader(String date, String zone);

  /// No description provided for @todayCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد حصص اليوم} =1{اليوم: حصة واحدة} =2{اليوم: حصتان} few{اليوم: {n} حصص} other{اليوم: {n} حصة}}'**
  String todayCount(int n);

  /// No description provided for @hours.
  ///
  /// In ar, this message translates to:
  /// **'{h} ساعة'**
  String hours(String h);

  /// No description provided for @nextIn.
  ///
  /// In ar, this message translates to:
  /// **'القادمة بعد {time}'**
  String nextIn(String time);

  /// No description provided for @reportsWaiting.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{تقرير ينتظرك} =2{تقريران ينتظرانك} few{{n} تقارير تنتظرك} other{{n} تقريرًا ينتظرك}}'**
  String reportsWaiting(int n);

  /// No description provided for @homeworkToMark.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{واجب تصحّحه} =2{واجبان تصحّحهما} few{{n} واجبات تصحّحها} other{{n} واجبًا تصحّحها}}'**
  String homeworkToMark(int n);

  /// No description provided for @reportDueBy.
  ///
  /// In ar, this message translates to:
  /// **'قبل {when}'**
  String reportDueBy(String when);

  /// No description provided for @prevWeek.
  ///
  /// In ar, this message translates to:
  /// **'الأسبوع الماضي'**
  String get prevWeek;

  /// No description provided for @nextWeek.
  ///
  /// In ar, this message translates to:
  /// **'الأسبوع القادم'**
  String get nextWeek;

  /// No description provided for @noLessonsDay.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص'**
  String get noLessonsDay;

  /// No description provided for @noLessonsWeek.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص هذا الأسبوع.'**
  String get noLessonsWeek;

  /// No description provided for @thisWeek.
  ///
  /// In ar, this message translates to:
  /// **'هذا الأسبوع'**
  String get thisWeek;

  /// No description provided for @weekdaysShort.
  ///
  /// In ar, this message translates to:
  /// **'سبت،أحد،اثنين،ثلاثاء،أربعاء،خميس،جمعة'**
  String get weekdaysShort;

  /// No description provided for @dayToday.
  ///
  /// In ar, this message translates to:
  /// **'{day} · اليوم'**
  String dayToday(String day);

  /// No description provided for @noLessonsLater.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص من هذا اليوم حتى آخر الأسبوع.'**
  String get noLessonsLater;

  /// No description provided for @laterToday.
  ///
  /// In ar, this message translates to:
  /// **'بقية اليوم'**
  String get laterToday;

  /// No description provided for @doneToday.
  ///
  /// In ar, this message translates to:
  /// **'انتهت اليوم'**
  String get doneToday;

  /// No description provided for @restDay.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص اليوم. حصصك القادمة خلال الأسبوع.'**
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
  /// **'كُتبت كلها'**
  String get allWritten;

  /// No description provided for @nothingToMark.
  ///
  /// In ar, this message translates to:
  /// **'لا شيء'**
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
  /// **'لا يوجد طلاب بعد.'**
  String get noStudents;

  /// No description provided for @studentsIn.
  ///
  /// In ar, this message translates to:
  /// **'{students} في {subjects}'**
  String studentsIn(String students, String subjects);

  /// No description provided for @studentsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا يوجد طلاب} =1{طالب واحد} =2{طالبان} few{{n} طلاب} other{{n} طالبًا}}'**
  String studentsCount(int n);

  /// No description provided for @subjectsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد مواد} =1{مادة واحدة} =2{مادتين} few{{n} مواد} other{{n} مادة}}'**
  String subjectsCount(int n);

  /// No description provided for @searchByName.
  ///
  /// In ar, this message translates to:
  /// **'ابحث بالاسم'**
  String get searchByName;

  /// No description provided for @allSubjects.
  ///
  /// In ar, this message translates to:
  /// **'الكل'**
  String get allSubjects;

  /// No description provided for @noStudentsFound.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد طالب بهذا الاسم.'**
  String get noStudentsFound;

  /// No description provided for @reportWaitingChip.
  ///
  /// In ar, this message translates to:
  /// **'تقرير ينتظرك'**
  String get reportWaitingChip;

  /// No description provided for @messageStudent.
  ///
  /// In ar, this message translates to:
  /// **'أرسل إليه رسالة'**
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
  /// **'الشهر الماضي'**
  String get prevMonth;

  /// No description provided for @nextMonth.
  ///
  /// In ar, this message translates to:
  /// **'الشهر القادم'**
  String get nextMonth;

  /// No description provided for @soFar.
  ///
  /// In ar, this message translates to:
  /// **'حتى اليوم'**
  String get soFar;

  /// No description provided for @monthOpen.
  ///
  /// In ar, this message translates to:
  /// **'الشهر ما زال مفتوحًا'**
  String get monthOpen;

  /// No description provided for @monthClosed.
  ///
  /// In ar, this message translates to:
  /// **'أُغلق الشهر'**
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
  /// **'{n, plural, =1{حصة لم يُحدَّد سعرها بعد} =2{حصتان لم يُحدَّد سعرهما بعد} few{{n} حصص لم يُحدَّد سعرها بعد} other{{n} حصة لم يُحدَّد سعرها بعد}}. ستحدّد الأكاديمية السعر، وتُحسب في هذا الشهر.'**
  String unpricedNote(int n);

  /// No description provided for @noEarnings.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد حصص في هذا الشهر.'**
  String get noEarnings;

  /// No description provided for @adjustments.
  ///
  /// In ar, this message translates to:
  /// **'تعديلات'**
  String get adjustments;

  /// No description provided for @corrected.
  ///
  /// In ar, this message translates to:
  /// **'صُحّح'**
  String get corrected;

  /// No description provided for @meetingLinkTitle.
  ///
  /// In ar, this message translates to:
  /// **'رابط حصتك'**
  String get meetingLinkTitle;

  /// No description provided for @meetingLinkNeeded.
  ///
  /// In ar, this message translates to:
  /// **'تعمل الأكاديمية بروابط خارجية، ولن تُفتح أي حصة دون رابطك.'**
  String get meetingLinkNeeded;

  /// No description provided for @meetingLinkSave.
  ///
  /// In ar, this message translates to:
  /// **'احفظ الرابط'**
  String get meetingLinkSave;

  /// No description provided for @meetingLinkSaved.
  ///
  /// In ar, this message translates to:
  /// **'حُفظ الرابط.'**
  String get meetingLinkSaved;

  /// No description provided for @meetingLinkRemoved.
  ///
  /// In ar, this message translates to:
  /// **'أُزيل الرابط.'**
  String get meetingLinkRemoved;

  /// No description provided for @teacherCardTitle.
  ///
  /// In ar, this message translates to:
  /// **'بطاقتي في صفحة الأكاديمية'**
  String get teacherCardTitle;

  /// No description provided for @teacherCardLead.
  ///
  /// In ar, this message translates to:
  /// **'البطاقة التي تراها الأسر عنك في صفحة الأكاديمية. أنت من يكتبها، وأنت من يقرّر أن تظهر أو لا. رقمك وبريدك الإلكتروني لا يظهران أبدًا.'**
  String get teacherCardLead;

  /// No description provided for @teacherCardShown.
  ///
  /// In ar, this message translates to:
  /// **'أظهرني في صفحة الأكاديمية'**
  String get teacherCardShown;

  /// No description provided for @teacherCardShownHint.
  ///
  /// In ar, this message translates to:
  /// **'ما دام مغلقًا، فلن يرى أحد بطاقتك.'**
  String get teacherCardShownHint;

  /// No description provided for @teacherCardName.
  ///
  /// In ar, this message translates to:
  /// **'الاسم الذي يظهر'**
  String get teacherCardName;

  /// No description provided for @teacherCardNameHint.
  ///
  /// In ar, this message translates to:
  /// **'مثل «أ. سارة» أو اسمك الأول فقط.'**
  String get teacherCardNameHint;

  /// No description provided for @teacherCardBio.
  ///
  /// In ar, this message translates to:
  /// **'كلمات قليلة عنك'**
  String get teacherCardBio;

  /// No description provided for @teacherCardBioHint.
  ///
  /// In ar, this message translates to:
  /// **'خبرتك وما تحب تدريسه، حتى 280 حرفًا. دون أرقام أو روابط.'**
  String get teacherCardBioHint;

  /// No description provided for @teacherCardPhoto.
  ///
  /// In ar, this message translates to:
  /// **'صورتك (اختياري)'**
  String get teacherCardPhoto;

  /// No description provided for @teacherCardPhotoHint.
  ///
  /// In ar, this message translates to:
  /// **'PNG أو JPEG أو WebP حتى 1 ميغابايت. يراها كل من يفتح الصفحة.'**
  String get teacherCardPhotoHint;

  /// No description provided for @teacherCardPickPhoto.
  ///
  /// In ar, this message translates to:
  /// **'اختر صورة'**
  String get teacherCardPickPhoto;

  /// No description provided for @teacherCardChangePhoto.
  ///
  /// In ar, this message translates to:
  /// **'غيّر الصورة'**
  String get teacherCardChangePhoto;

  /// No description provided for @teacherCardRemovePhoto.
  ///
  /// In ar, this message translates to:
  /// **'أزل الصورة'**
  String get teacherCardRemovePhoto;

  /// No description provided for @teacherCardTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الصورة أكبر من 1 ميغابايت.'**
  String get teacherCardTooBig;

  /// No description provided for @teacherCardPreview.
  ///
  /// In ar, this message translates to:
  /// **'هكذا سترى الأسر بطاقتك'**
  String get teacherCardPreview;

  /// No description provided for @teacherCardSubjectsFrom.
  ///
  /// In ar, this message translates to:
  /// **'المواد من ملفك في الأكاديمية'**
  String get teacherCardSubjectsFrom;

  /// No description provided for @teacherCardSave.
  ///
  /// In ar, this message translates to:
  /// **'احفظ'**
  String get teacherCardSave;

  /// No description provided for @teacherCardSaved.
  ///
  /// In ar, this message translates to:
  /// **'حُفظت بطاقتك.'**
  String get teacherCardSaved;

  /// No description provided for @teacherCardLevels.
  ///
  /// In ar, this message translates to:
  /// **'{from} ← {to}'**
  String teacherCardLevels(String from, String to);

  /// No description provided for @teacherCardFromStart.
  ///
  /// In ar, this message translates to:
  /// **'من البداية'**
  String get teacherCardFromStart;

  /// No description provided for @teacherCardToEnd.
  ///
  /// In ar, this message translates to:
  /// **'حتى النهاية'**
  String get teacherCardToEnd;

  /// No description provided for @performanceTitle.
  ///
  /// In ar, this message translates to:
  /// **'أدائي'**
  String get performanceTitle;

  /// No description provided for @performanceLead.
  ///
  /// In ar, this message translates to:
  /// **'{days, plural, =1{آخر يوم} =2{آخر يومين} few{آخر {days} أيام} other{آخر {days} يومًا}} · الحصص التي كان سببها المنصة لا تُحسب'**
  String performanceLead(int days);

  /// No description provided for @performanceGroupCommitment.
  ///
  /// In ar, this message translates to:
  /// **'الالتزام'**
  String get performanceGroupCommitment;

  /// No description provided for @performanceGroupQuality.
  ///
  /// In ar, this message translates to:
  /// **'الجودة'**
  String get performanceGroupQuality;

  /// No description provided for @performanceGroupProfessionalism.
  ///
  /// In ar, this message translates to:
  /// **'الاحترافية'**
  String get performanceGroupProfessionalism;

  /// No description provided for @performanceGroupBusiness.
  ///
  /// In ar, this message translates to:
  /// **'النمو'**
  String get performanceGroupBusiness;

  /// No description provided for @performanceGood.
  ///
  /// In ar, this message translates to:
  /// **'جيد'**
  String get performanceGood;

  /// No description provided for @performanceBad.
  ///
  /// In ar, this message translates to:
  /// **'يحتاج إلى تحسين'**
  String get performanceBad;

  /// No description provided for @performanceNone.
  ///
  /// In ar, this message translates to:
  /// **'ليس بعد'**
  String get performanceNone;

  /// No description provided for @performanceCounted.
  ///
  /// In ar, this message translates to:
  /// **'ما حُسب'**
  String get performanceCounted;

  /// No description provided for @performanceNoEvents.
  ///
  /// In ar, this message translates to:
  /// **'لم يُحسب عليك شيء. استمر!'**
  String get performanceNoEvents;

  /// No description provided for @performanceExcluded.
  ///
  /// In ar, this message translates to:
  /// **'غير محسوبة'**
  String get performanceExcluded;

  /// No description provided for @performanceRatings.
  ///
  /// In ar, this message translates to:
  /// **'تقييمات الأسر'**
  String get performanceRatings;

  /// No description provided for @performanceAverage.
  ///
  /// In ar, this message translates to:
  /// **'{avg} من 5 · {n, plural, =1{تقييم واحد} =2{تقييمان} few{{n} تقييمات} other{{n} تقييمًا}}'**
  String performanceAverage(String avg, int n);

  /// No description provided for @performanceNoRatings.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد تقييمات بعد.'**
  String get performanceNoRatings;

  /// No description provided for @performanceStars.
  ///
  /// In ar, this message translates to:
  /// **'{n} من 5'**
  String performanceStars(int n);

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
  /// **'بتوقيتك: {zone} · الأكاديمية ترد عليك'**
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
  /// **'حتى'**
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
  /// **'حتى الساعة'**
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
  /// **'سبب آخر'**
  String get reasonOther;

  /// No description provided for @sendRequest.
  ///
  /// In ar, this message translates to:
  /// **'أرسل الطلب'**
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
  /// **'أُرسل الطلب. سيصلك الرد.'**
  String get requestSent;

  /// No description provided for @withdrawn.
  ///
  /// In ar, this message translates to:
  /// **'سُحب الطلب.'**
  String get withdrawn;

  /// No description provided for @academyHolidays.
  ///
  /// In ar, this message translates to:
  /// **'إجازات الأكاديمية'**
  String get academyHolidays;

  /// No description provided for @coverState.
  ///
  /// In ar, this message translates to:
  /// **'{covered} من {total, plural, =1{حصة واحدة} =2{حصتين} few{{total} حصص} other{{total} حصة}} لها بديل'**
  String coverState(int covered, int total);

  /// No description provided for @fillTimeOff.
  ///
  /// In ar, this message translates to:
  /// **'اختر الأيام والسبب أولًا.'**
  String get fillTimeOff;

  /// No description provided for @noTimeOff.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد طلبات.'**
  String get noTimeOff;

  /// No description provided for @youAreHere.
  ///
  /// In ar, this message translates to:
  /// **'أنت هنا!'**
  String get youAreHere;

  /// No description provided for @topRank.
  ///
  /// In ar, this message translates to:
  /// **'وصلت إلى أعلى رتبة!'**
  String get topRank;

  /// No description provided for @streak.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد حصص متتالية بعد} =1{حصة واحدة} =2{حصتان متتاليتان} few{{n} حصص متتالية} other{{n} حصة متتالية}}'**
  String streak(int n);

  /// No description provided for @badgesCount.
  ///
  /// In ar, this message translates to:
  /// **'{earned} من {total}'**
  String badgesCount(int earned, int total);

  /// No description provided for @finishOn.
  ///
  /// In ar, this message translates to:
  /// **'بهذه الوتيرة تنتهي'**
  String get finishOn;

  /// No description provided for @programDone.
  ///
  /// In ar, this message translates to:
  /// **'أنهيت البرنامج!'**
  String get programDone;

  /// No description provided for @weeksLeftLevel.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{أوشك هذا المستوى على الانتهاء} =1{بقي أسبوع في هذا المستوى} =2{بقي أسبوعان في هذا المستوى} few{بقيت {n} أسابيع في هذا المستوى} other{بقي {n} أسبوعًا في هذا المستوى}}'**
  String weeksLeftLevel(int n);

  /// No description provided for @fromLastMonth.
  ///
  /// In ar, this message translates to:
  /// **'مقارنة بالشهر الماضي'**
  String get fromLastMonth;

  /// No description provided for @doneMark.
  ///
  /// In ar, this message translates to:
  /// **'تم!'**
  String get doneMark;

  /// No description provided for @chestReady.
  ///
  /// In ar, this message translates to:
  /// **'الصندوق جاهز! انقر عليه'**
  String get chestReady;

  /// No description provided for @chestOpened.
  ///
  /// In ar, this message translates to:
  /// **'فتحت صندوق هذا الأسبوع'**
  String get chestOpened;

  /// No description provided for @album.
  ///
  /// In ar, this message translates to:
  /// **'ألبوم الملصقات'**
  String get album;

  /// No description provided for @albumLead.
  ///
  /// In ar, this message translates to:
  /// **'كل صندوق تفتحه يعطيك ملصقًا جديدًا.'**
  String get albumLead;

  /// No description provided for @newSticker.
  ///
  /// In ar, this message translates to:
  /// **'ملصق جديد في ألبومك!'**
  String get newSticker;

  /// No description provided for @rewardKids.
  ///
  /// In ar, this message translates to:
  /// **'أحسنت!'**
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
  /// **'رائع!'**
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
  /// **'وصل التشجيع إلى {name}!'**
  String cheerSent(String name);

  /// No description provided for @cheersLeft.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{انتهت تشجيعات اليوم} =1{بقي تشجيع واحد اليوم} =2{بقي تشجيعان اليوم} few{بقيت {n} تشجيعات اليوم} other{بقي {n} تشجيعًا اليوم}}'**
  String cheersLeft(int n);

  /// No description provided for @sounds.
  ///
  /// In ar, this message translates to:
  /// **'الأصوات'**
  String get sounds;

  /// No description provided for @soundsHint.
  ///
  /// In ar, this message translates to:
  /// **'أصوات خفيفة عند النقر أو عند استلام جائزة'**
  String get soundsHint;

  /// No description provided for @reportsOpen.
  ///
  /// In ar, this message translates to:
  /// **'تقارير تنتظرك'**
  String get reportsOpen;

  /// No description provided for @reportsSent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسلت'**
  String get reportsSent;

  /// No description provided for @writeReport.
  ///
  /// In ar, this message translates to:
  /// **'اكتب'**
  String get writeReport;

  /// No description provided for @waitingFor.
  ///
  /// In ar, this message translates to:
  /// **'ينتظرك: {what}'**
  String waitingFor(String what);

  /// No description provided for @waitingNothing.
  ///
  /// In ar, this message translates to:
  /// **'لا شيء ينتظرك'**
  String get waitingNothing;

  /// No description provided for @bothWaiting.
  ///
  /// In ar, this message translates to:
  /// **'{a} و{b}'**
  String bothWaiting(String a, String b);

  /// No description provided for @reportsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد تقارير} =1{تقرير واحد} =2{تقريران} few{{n} تقارير} other{{n} تقريرًا}}'**
  String reportsCount(int n);

  /// No description provided for @homeworkCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد واجبات} =1{واجب واحد} =2{واجبان} few{{n} واجبات} other{{n} واجبًا}}'**
  String homeworkCount(int n);

  /// No description provided for @markingRow.
  ///
  /// In ar, this message translates to:
  /// **'واجبات تنتظر التصحيح'**
  String get markingRow;

  /// No description provided for @markingNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد واجبات تنتظر الآن'**
  String get markingNone;

  /// No description provided for @noReportsWaiting.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد تقارير تنتظرك. أحسنت!'**
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
  /// **'خطة اليوم'**
  String get todaysPlan;

  /// No description provided for @planHint.
  ///
  /// In ar, this message translates to:
  /// **'اختر ما حدث في كل درس.'**
  String get planHint;

  /// No description provided for @reportTitle.
  ///
  /// In ar, this message translates to:
  /// **'تقرير الحصة'**
  String get reportTitle;

  /// No description provided for @workedOn.
  ///
  /// In ar, this message translates to:
  /// **'على ماذا عملنا'**
  String get workedOn;

  /// No description provided for @howItWent.
  ///
  /// In ar, this message translates to:
  /// **'كيف كانت الحصة'**
  String get howItWent;

  /// No description provided for @talkSection.
  ///
  /// In ar, this message translates to:
  /// **'الملاحظات'**
  String get talkSection;

  /// No description provided for @keepDraft.
  ///
  /// In ar, this message translates to:
  /// **'مسودة'**
  String get keepDraft;

  /// No description provided for @reportDraftSaved.
  ///
  /// In ar, this message translates to:
  /// **'حُفظت مسودة على هذا الهاتف.'**
  String get reportDraftSaved;

  /// No description provided for @draftFrom.
  ///
  /// In ar, this message translates to:
  /// **'أكملت من مسودة حُفظت قبل {ago}.'**
  String draftFrom(String ago);

  /// No description provided for @discardDraft.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ من جديد'**
  String get discardDraft;

  /// No description provided for @voiceTitle.
  ///
  /// In ar, this message translates to:
  /// **'مسودة من رسالة صوتية'**
  String get voiceTitle;

  /// No description provided for @voiceHint.
  ///
  /// In ar, this message translates to:
  /// **'قل ماذا فعلتم، وكيف كان أداء الطالب، والواجب. المسودة تملأ الخانات الفارغة فقط، وأنت تراجعها قبل أن ترسل.'**
  String get voiceHint;

  /// No description provided for @voiceRecord.
  ///
  /// In ar, this message translates to:
  /// **'سجّل رسالة'**
  String get voiceRecord;

  /// No description provided for @voiceWorking.
  ///
  /// In ar, this message translates to:
  /// **'نستمع ونكتب المسودة…'**
  String get voiceWorking;

  /// No description provided for @voiceDone.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لم تُضف الرسالة شيئًا جديدًا: كان كل ذلك في النموذج.} =1{ملأت المسودة خانة واحدة. راجعها قبل أن ترسل.} =2{ملأت المسودة خانتين. راجعهما قبل أن ترسل.} few{ملأت المسودة {n} خانات. راجعها قبل أن ترسل.} other{ملأت المسودة {n} خانة. راجعها قبل أن ترسل.}}'**
  String voiceDone(int n);

  /// No description provided for @voiceHeard.
  ///
  /// In ar, this message translates to:
  /// **'ما سُمع'**
  String get voiceHeard;

  /// No description provided for @voiceUnsure.
  ///
  /// In ar, this message translates to:
  /// **'راجع هذه: لم تكن المسودة متأكدة منها'**
  String get voiceUnsure;

  /// No description provided for @voiceLeft.
  ///
  /// In ar, this message translates to:
  /// **'ما زال ينقص: {fields}'**
  String voiceLeft(String fields);

  /// No description provided for @voiceLimit.
  ///
  /// In ar, this message translates to:
  /// **'حتى دقيقتين'**
  String get voiceLimit;

  /// No description provided for @voiceKept.
  ///
  /// In ar, this message translates to:
  /// **'يُحذف التسجيل فور سماعه، ولا يُحفظ أبدًا.'**
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
  /// **'تصحيح التسميع آليًا'**
  String get reciteToggle;

  /// No description provided for @reciteHint.
  ///
  /// In ar, this message translates to:
  /// **'عندما يسلّم الطالب تسجيلًا، نعلّم لك الكلمات الناقصة أو الخاطئة. هذا لك وحدك، والأسرة لا تراه.'**
  String get reciteHint;

  /// No description provided for @reciteSuggestion.
  ///
  /// In ar, this message translates to:
  /// **'من نص الدرس: {title}'**
  String reciteSuggestion(String title);

  /// No description provided for @reciteFromMistakes.
  ///
  /// In ar, this message translates to:
  /// **'من أخطاء الحصة: {title}'**
  String reciteFromMistakes(String title);

  /// No description provided for @reciteSurah.
  ///
  /// In ar, this message translates to:
  /// **'السورة'**
  String get reciteSurah;

  /// No description provided for @reciteChooseSurah.
  ///
  /// In ar, this message translates to:
  /// **'اختر السورة'**
  String get reciteChooseSurah;

  /// No description provided for @reciteSearch.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن سورة'**
  String get reciteSearch;

  /// No description provided for @reciteFrom.
  ///
  /// In ar, this message translates to:
  /// **'من آية'**
  String get reciteFrom;

  /// No description provided for @reciteTo.
  ///
  /// In ar, this message translates to:
  /// **'إلى آية'**
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
  /// **'خطأ'**
  String get reciteWrong;

  /// No description provided for @reciteExtra.
  ///
  /// In ar, this message translates to:
  /// **'زيادة'**
  String get reciteExtra;

  /// No description provided for @reciteSaid.
  ///
  /// In ar, this message translates to:
  /// **'قيل: {word}'**
  String reciteSaid(String word);

  /// No description provided for @reciteHeard.
  ///
  /// In ar, this message translates to:
  /// **'ما سُمع'**
  String get reciteHeard;

  /// No description provided for @drafts.
  ///
  /// In ar, this message translates to:
  /// **'مسودات'**
  String get drafts;

  /// No description provided for @savedAgo.
  ///
  /// In ar, this message translates to:
  /// **'حُفظت قبل {ago}'**
  String savedAgo(String ago);

  /// No description provided for @continueDraft.
  ///
  /// In ar, this message translates to:
  /// **'أكمل'**
  String get continueDraft;

  /// No description provided for @reasonNext.
  ///
  /// In ar, this message translates to:
  /// **'الدرس القادم'**
  String get reasonNext;

  /// No description provided for @reasonReview.
  ///
  /// In ar, this message translates to:
  /// **'مراجعة'**
  String get reasonReview;

  /// No description provided for @outcomeIntroduced.
  ///
  /// In ar, this message translates to:
  /// **'شُرح'**
  String get outcomeIntroduced;

  /// No description provided for @outcomePracticed.
  ///
  /// In ar, this message translates to:
  /// **'تدرّب عليه'**
  String get outcomePracticed;

  /// No description provided for @outcomeMastered.
  ///
  /// In ar, this message translates to:
  /// **'أتقنه'**
  String get outcomeMastered;

  /// No description provided for @outcomeNeedsReview.
  ///
  /// In ar, this message translates to:
  /// **'يحتاج إلى مراجعة'**
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
  /// **'أضف ملاحظة'**
  String get addNote;

  /// No description provided for @addLesson.
  ///
  /// In ar, this message translates to:
  /// **'أضف درسًا'**
  String get addLesson;

  /// No description provided for @pickLesson.
  ///
  /// In ar, this message translates to:
  /// **'اختر درسًا'**
  String get pickLesson;

  /// No description provided for @searchLessons.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن درس'**
  String get searchLessons;

  /// No description provided for @remove.
  ///
  /// In ar, this message translates to:
  /// **'أزل'**
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
  /// **'للأكاديمية فقط، ولا تراها الأسرة.'**
  String get notesInternalHint;

  /// No description provided for @questionsTitle.
  ///
  /// In ar, this message translates to:
  /// **'أسئلة الأكاديمية'**
  String get questionsTitle;

  /// No description provided for @questionsHint.
  ///
  /// In ar, this message translates to:
  /// **'أسئلة تطرحها الأكاديمية في كل تقرير لهذه المادة.'**
  String get questionsHint;

  /// No description provided for @answerRequired.
  ///
  /// In ar, this message translates to:
  /// **'أجب عن \"{question}\".'**
  String answerRequired(String question);

  /// No description provided for @answerYes.
  ///
  /// In ar, this message translates to:
  /// **'نعم'**
  String get answerYes;

  /// No description provided for @answerNo.
  ///
  /// In ar, this message translates to:
  /// **'لا'**
  String get answerNo;

  /// No description provided for @academyOnly.
  ///
  /// In ar, this message translates to:
  /// **'للأكاديمية فقط'**
  String get academyOnly;

  /// No description provided for @lessonMaterials.
  ///
  /// In ar, this message translates to:
  /// **'مواد الدرس'**
  String get lessonMaterials;

  /// No description provided for @teachersOnly.
  ///
  /// In ar, this message translates to:
  /// **'للمعلّم فقط'**
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
  /// **'لا تسجّل الأكاديمية الحصص الآن.'**
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
  /// **'لا توجد تسجيلات بعد.'**
  String get recordingsEmpty;

  /// No description provided for @recordingsNotShared.
  ///
  /// In ar, this message translates to:
  /// **'لا تعرض الأكاديمية التسجيلات هنا.'**
  String get recordingsNotShared;

  /// No description provided for @recordingWatch.
  ///
  /// In ar, this message translates to:
  /// **'شاهد'**
  String get recordingWatch;

  /// No description provided for @recordingAgreed.
  ///
  /// In ar, this message translates to:
  /// **'موافق'**
  String get recordingAgreed;

  /// No description provided for @recordingNotAgreed.
  ///
  /// In ar, this message translates to:
  /// **'غير موافق'**
  String get recordingNotAgreed;

  /// No description provided for @recordingAgree.
  ///
  /// In ar, this message translates to:
  /// **'موافق'**
  String get recordingAgree;

  /// No description provided for @recordingAgreedDone.
  ///
  /// In ar, this message translates to:
  /// **'شكرًا. يمكن تسجيل الحصص من الآن.'**
  String get recordingAgreedDone;

  /// No description provided for @recordingWithdraw.
  ///
  /// In ar, this message translates to:
  /// **'اسحب الموافقة'**
  String get recordingWithdraw;

  /// No description provided for @recordingWithdrawConfirm.
  ///
  /// In ar, this message translates to:
  /// **'هل أنت متأكد؟ سيتوقف أي تسجيل جارٍ، ولن يُسجَّل شيء بعد ذلك.'**
  String get recordingWithdrawConfirm;

  /// No description provided for @recordingWithdrawn.
  ///
  /// In ar, this message translates to:
  /// **'سُحبت الموافقة، ولن يُسجَّل شيء بعد ذلك.'**
  String get recordingWithdrawn;

  /// No description provided for @recordingUntil.
  ///
  /// In ar, this message translates to:
  /// **'متاح حتى {date}'**
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
  /// **'يُسجَّل فتحك للتسجيل باسمك.'**
  String get recordingLogged;

  /// No description provided for @recordingOn.
  ///
  /// In ar, this message translates to:
  /// **'يسجّل'**
  String get recordingOn;

  /// No description provided for @recordStart.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الحصة'**
  String get recordStart;

  /// No description provided for @recordStop.
  ///
  /// In ar, this message translates to:
  /// **'أوقف التسجيل'**
  String get recordStop;

  /// No description provided for @recordingMissing.
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن التسجيل حتى يوافقوا على التسجيل: {names}.'**
  String recordingMissing(String names);

  /// No description provided for @absenceNote.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة الغياب'**
  String get absenceNote;

  /// No description provided for @absenceHint.
  ///
  /// In ar, this message translates to:
  /// **'لم يحضر الطالب. اكتب ما حدث وأي شيء تحتاج الأسرة إلى معرفته.'**
  String get absenceHint;

  /// No description provided for @giveHomework.
  ///
  /// In ar, this message translates to:
  /// **'أعطِ واجبًا'**
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
  /// **'يُسلَّم'**
  String get acceptsLabel;

  /// No description provided for @acceptsAny.
  ///
  /// In ar, this message translates to:
  /// **'أي شيء'**
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
  /// **'{n, plural, =1{بعد يوم} =2{بعد يومين} few{بعد {n} أيام} other{بعد {n} يومًا}}'**
  String dueIn(int n);

  /// No description provided for @homeworkLocked.
  ///
  /// In ar, this message translates to:
  /// **'سلّم الطالب هذا الواجب، فلن يتغيّر.'**
  String get homeworkLocked;

  /// No description provided for @sendReport.
  ///
  /// In ar, this message translates to:
  /// **'أرسل التقرير'**
  String get sendReport;

  /// No description provided for @updateReport.
  ///
  /// In ar, this message translates to:
  /// **'حدّث التقرير'**
  String get updateReport;

  /// No description provided for @reportSent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسل التقرير.'**
  String get reportSent;

  /// No description provided for @reportLocked.
  ///
  /// In ar, this message translates to:
  /// **'راجعت الأكاديمية هذا التقرير، فلن يتغيّر.'**
  String get reportLocked;

  /// No description provided for @pickRatings.
  ///
  /// In ar, this message translates to:
  /// **'اختر التركيز والمستوى العام.'**
  String get pickRatings;

  /// No description provided for @pickOutcomes.
  ///
  /// In ar, this message translates to:
  /// **'اختر ما حدث في كل درس.'**
  String get pickOutcomes;

  /// No description provided for @markTitle.
  ///
  /// In ar, this message translates to:
  /// **'تصحيح الواجبات'**
  String get markTitle;

  /// No description provided for @markWaiting.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار التصحيح'**
  String get markWaiting;

  /// No description provided for @markDone.
  ///
  /// In ar, this message translates to:
  /// **'صُحّح'**
  String get markDone;

  /// No description provided for @noHomeworkToMark.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد واجبات تنتظر التصحيح.'**
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
  /// **'سُجّل التصحيح.'**
  String get marked;

  /// No description provided for @handedInAt.
  ///
  /// In ar, this message translates to:
  /// **'سُلّم {when}'**
  String handedInAt(String when);

  /// No description provided for @fileOnWeb.
  ///
  /// In ar, this message translates to:
  /// **'يُفتح هذا الملف من الموقع.'**
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
  /// **'أوقف'**
  String get pause;

  /// No description provided for @cannotPlay.
  ///
  /// In ar, this message translates to:
  /// **'لا نستطيع تشغيل هذا التسجيل هنا.'**
  String get cannotPlay;

  /// No description provided for @waitingTap.
  ///
  /// In ar, this message translates to:
  /// **'انقر لتبدأ'**
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
  /// **'يسجّل… {time}'**
  String recordingNow(String time);

  /// No description provided for @stopRecording.
  ///
  /// In ar, this message translates to:
  /// **'أوقف التسجيل'**
  String get stopRecording;

  /// No description provided for @recordAgain.
  ///
  /// In ar, this message translates to:
  /// **'سجّل مرة أخرى'**
  String get recordAgain;

  /// No description provided for @attachFile.
  ///
  /// In ar, this message translates to:
  /// **'أضف ملفًا'**
  String get attachFile;

  /// No description provided for @attachPicture.
  ///
  /// In ar, this message translates to:
  /// **'أضف صورة أو ملف PDF'**
  String get attachPicture;

  /// No description provided for @micDenied.
  ///
  /// In ar, this message translates to:
  /// **'نحتاج إلى إذن الميكروفون لتسجّل. فعّله من إعدادات الهاتف.'**
  String get micDenied;

  /// No description provided for @recordingLimit.
  ///
  /// In ar, this message translates to:
  /// **'توقف التسجيل عند {n, plural, =1{دقيقة} =2{دقيقتين} few{{n} دقائق} other{{n} دقيقة}}.'**
  String recordingLimit(int n);

  /// No description provided for @fileTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 25 ميغابايت.'**
  String get fileTooBig;

  /// No description provided for @answerOptional.
  ///
  /// In ar, this message translates to:
  /// **'اكتب شيئًا آخر (اختياري)'**
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
  /// **'جدولك الحالي'**
  String get currentWeek;

  /// No description provided for @noHoursYet.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مواعيد معتمدة بعد.'**
  String get noHoursYet;

  /// No description provided for @proposeHours.
  ///
  /// In ar, this message translates to:
  /// **'اقترح مواعيد جديدة'**
  String get proposeHours;

  /// No description provided for @startsOn.
  ///
  /// In ar, this message translates to:
  /// **'يبدأ {date}'**
  String startsOn(String date);

  /// No description provided for @academyCounter.
  ///
  /// In ar, this message translates to:
  /// **'عدّلت الأكاديمية مقترحك. هذا ما تغيّر:'**
  String get academyCounter;

  /// No description provided for @timeAdded.
  ///
  /// In ar, this message translates to:
  /// **'أُضيف'**
  String get timeAdded;

  /// No description provided for @timeRemoved.
  ///
  /// In ar, this message translates to:
  /// **'أُزيل'**
  String get timeRemoved;

  /// No description provided for @acceptCounter.
  ///
  /// In ar, this message translates to:
  /// **'موافق على التعديل'**
  String get acceptCounter;

  /// No description provided for @withdrawConfirm.
  ///
  /// In ar, this message translates to:
  /// **'أتسحب المقترح؟ ستبقى المواعيد المعتمدة كما هي.'**
  String get withdrawConfirm;

  /// No description provided for @proposalAccepted.
  ///
  /// In ar, this message translates to:
  /// **'اعتُمدت مواعيدك.'**
  String get proposalAccepted;

  /// No description provided for @proposalSent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسل المقترح إلى الأكاديمية.'**
  String get proposalSent;

  /// No description provided for @draftSaved.
  ///
  /// In ar, this message translates to:
  /// **'حُفظت المسودة.'**
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
  /// **'أضف وقتًا'**
  String get addTime;

  /// No description provided for @dayOff.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد'**
  String get dayOff;

  /// No description provided for @effectiveFrom.
  ///
  /// In ar, this message translates to:
  /// **'يبدأ من'**
  String get effectiveFrom;

  /// No description provided for @reduceNotice.
  ///
  /// In ar, this message translates to:
  /// **'إن كنت ستقلّل الساعات، فأقرب بداية {date}.'**
  String reduceNotice(String date);

  /// No description provided for @minHours.
  ///
  /// In ar, this message translates to:
  /// **'أقل عدد من الساعات في الأسبوع'**
  String get minHours;

  /// No description provided for @maxHours.
  ///
  /// In ar, this message translates to:
  /// **'أكثر عدد من الساعات في الأسبوع'**
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
  /// **'أرسل إلى الأكاديمية'**
  String get sendProposal;

  /// No description provided for @endAfterStart.
  ///
  /// In ar, this message translates to:
  /// **'يجب أن يكون وقت النهاية بعد البداية.'**
  String get endAfterStart;

  /// No description provided for @untilMidnight.
  ///
  /// In ar, this message translates to:
  /// **'حتى منتصف الليل'**
  String get untilMidnight;

  /// No description provided for @upcomingNote.
  ///
  /// In ar, this message translates to:
  /// **'اعتمدته الأكاديمية، ويبقى جدولك الحالي حتى ذلك اليوم.'**
  String get upcomingNote;

  /// No description provided for @hoursSame.
  ///
  /// In ar, this message translates to:
  /// **'{h} في الأسبوع كما هي، بأوقات أخرى'**
  String hoursSame(String h);

  /// No description provided for @stepDraft.
  ///
  /// In ar, this message translates to:
  /// **'مسودة'**
  String get stepDraft;

  /// No description provided for @stepSent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسل'**
  String get stepSent;

  /// No description provided for @stepReview.
  ///
  /// In ar, this message translates to:
  /// **'قيد المراجعة'**
  String get stepReview;

  /// No description provided for @stepDecision.
  ///
  /// In ar, this message translates to:
  /// **'القرار'**
  String get stepDecision;

  /// No description provided for @stepCountered.
  ///
  /// In ar, this message translates to:
  /// **'عرض مضاد'**
  String get stepCountered;

  /// No description provided for @stepChanges.
  ///
  /// In ar, this message translates to:
  /// **'طُلب تعديل'**
  String get stepChanges;

  /// No description provided for @stepApproved.
  ///
  /// In ar, this message translates to:
  /// **'معتمد'**
  String get stepApproved;

  /// No description provided for @stepRejected.
  ///
  /// In ar, this message translates to:
  /// **'مرفوض'**
  String get stepRejected;

  /// No description provided for @stageDraft.
  ///
  /// In ar, this message translates to:
  /// **'المسودة محفوظة على حسابك ولم تُرسل بعد.'**
  String get stageDraft;

  /// No description provided for @stageReview.
  ///
  /// In ar, this message translates to:
  /// **'وصل مقترحك إلى الأكاديمية، وسنخبرك حين ترد.'**
  String get stageReview;

  /// No description provided for @stageChanges.
  ///
  /// In ar, this message translates to:
  /// **'طلبت الأكاديمية تعديلًا. اقرأ المحادثة، ثم عدّل المقترح وأرسله.'**
  String get stageChanges;

  /// No description provided for @stageRejected.
  ///
  /// In ar, this message translates to:
  /// **'لم تعتمد الأكاديمية مقترحك، ويبقى جدولك كما هو.'**
  String get stageRejected;

  /// No description provided for @reasonLine.
  ///
  /// In ar, this message translates to:
  /// **'السبب: {reason}'**
  String reasonLine(String reason);

  /// No description provided for @editAndSend.
  ///
  /// In ar, this message translates to:
  /// **'عدّل وأرسل'**
  String get editAndSend;

  /// No description provided for @withdrawProposal.
  ///
  /// In ar, this message translates to:
  /// **'اسحب المقترح'**
  String get withdrawProposal;

  /// No description provided for @newProposalHint.
  ///
  /// In ar, this message translates to:
  /// **'تريد تغيير أوقاتك؟ اقترح جدولًا، وتراجعه الأكاديمية.'**
  String get newProposalHint;

  /// No description provided for @conversationCount.
  ///
  /// In ar, this message translates to:
  /// **'المحادثة ({n})'**
  String conversationCount(int n);

  /// No description provided for @conversationStart.
  ///
  /// In ar, this message translates to:
  /// **'اكتب للأكاديمية عن مقترحك.'**
  String get conversationStart;

  /// No description provided for @conversationEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا رسائل بعد. اكتب للأكاديمية، وسيصلك ردها هنا.'**
  String get conversationEmpty;

  /// No description provided for @conversationClosed.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد مقترح مفتوح الآن، فلا يمكن الكتابة هنا.'**
  String get conversationClosed;

  /// No description provided for @proposalSentEvent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسل المقترح'**
  String get proposalSentEvent;

  /// No description provided for @askedToStart.
  ///
  /// In ar, this message translates to:
  /// **'طُلب أن يبدأ {date}'**
  String askedToStart(String date);

  /// No description provided for @heroDraftTitle.
  ///
  /// In ar, this message translates to:
  /// **'مسودة لم تُرسل بعد'**
  String get heroDraftTitle;

  /// No description provided for @heroReviewTitle.
  ///
  /// In ar, this message translates to:
  /// **'في انتظار الأكاديمية'**
  String get heroReviewTitle;

  /// No description provided for @heroChangesTitle.
  ///
  /// In ar, this message translates to:
  /// **'طلبت الأكاديمية تعديلًا'**
  String get heroChangesTitle;

  /// No description provided for @heroCounterTitle.
  ///
  /// In ar, this message translates to:
  /// **'عرض من الأكاديمية'**
  String get heroCounterTitle;

  /// No description provided for @continueAndSend.
  ///
  /// In ar, this message translates to:
  /// **'أكمل وأرسل'**
  String get continueAndSend;

  /// No description provided for @proposalDays.
  ///
  /// In ar, this message translates to:
  /// **'ما يتغيّر في مقترحك'**
  String get proposalDays;

  /// No description provided for @upcomingHeroTitle.
  ///
  /// In ar, this message translates to:
  /// **'جدولك الجديد يبدأ {date}'**
  String upcomingHeroTitle(String date);

  /// No description provided for @startsInDays.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{اليوم} =1{غدًا} =2{بعد يومين} few{بعد {n} أيام} other{بعد {n} يومًا}}'**
  String startsInDays(int n);

  /// No description provided for @upcomingApproved.
  ///
  /// In ar, this message translates to:
  /// **'اعتمدته الأكاديمية.'**
  String get upcomingApproved;

  /// No description provided for @rejectedHeroTitle.
  ///
  /// In ar, this message translates to:
  /// **'لم تُعتمد مواعيدك'**
  String get rejectedHeroTitle;

  /// No description provided for @weekNow.
  ///
  /// In ar, this message translates to:
  /// **'الآن'**
  String get weekNow;

  /// No description provided for @weekFrom.
  ///
  /// In ar, this message translates to:
  /// **'من {date}'**
  String weekFrom(String date);

  /// No description provided for @noHoursBefore.
  ///
  /// In ar, this message translates to:
  /// **'لا مواعيد قبل {date}'**
  String noHoursBefore(String date);

  /// No description provided for @proposeChange.
  ///
  /// In ar, this message translates to:
  /// **'اقترح تغييرًا'**
  String get proposeChange;

  /// No description provided for @builtOnUpcoming.
  ///
  /// In ar, this message translates to:
  /// **'سيُبنى على جدولك من {date}'**
  String builtOnUpcoming(String date);

  /// No description provided for @historyCount.
  ///
  /// In ar, this message translates to:
  /// **'السجل ({n})'**
  String historyCount(int n);

  /// No description provided for @supersededLabel.
  ///
  /// In ar, this message translates to:
  /// **'حلّ محلّه مقترح أحدث'**
  String get supersededLabel;

  /// No description provided for @dayNew.
  ///
  /// In ar, this message translates to:
  /// **'جديد'**
  String get dayNew;

  /// No description provided for @dayChanged.
  ///
  /// In ar, this message translates to:
  /// **'تغيّر'**
  String get dayChanged;

  /// No description provided for @inboxTitle.
  ///
  /// In ar, this message translates to:
  /// **'الإشعارات'**
  String get inboxTitle;

  /// No description provided for @markAllRead.
  ///
  /// In ar, this message translates to:
  /// **'علّم الكل كمقروء'**
  String get markAllRead;

  /// No description provided for @noNotifications.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد إشعارات.'**
  String get noNotifications;

  /// No description provided for @loadMore.
  ///
  /// In ar, this message translates to:
  /// **'اعرض المزيد'**
  String get loadMore;

  /// No description provided for @notificationChoices.
  ///
  /// In ar, this message translates to:
  /// **'اختيارات الإشعارات'**
  String get notificationChoices;

  /// No description provided for @saved.
  ///
  /// In ar, this message translates to:
  /// **'حُفظ.'**
  String get saved;

  /// No description provided for @openIt.
  ///
  /// In ar, this message translates to:
  /// **'افتح'**
  String get openIt;

  /// No description provided for @unreadCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا جديد} =1{إشعار جديد} =2{إشعاران جديدان} few{{n} إشعارات جديدة} other{{n} إشعارًا جديدًا}}'**
  String unreadCount(int n);

  /// No description provided for @updateTitle.
  ///
  /// In ar, this message translates to:
  /// **'يوجد إصدار جديد من التطبيق'**
  String get updateTitle;

  /// No description provided for @updateBody.
  ///
  /// In ar, this message translates to:
  /// **'الإصدار الذي لديك قديم ولن يعمل مع الأكاديمية. حدّثه لتتابع.'**
  String get updateBody;

  /// No description provided for @updateNow.
  ///
  /// In ar, this message translates to:
  /// **'حدّث التطبيق'**
  String get updateNow;

  /// No description provided for @updateFromStore.
  ///
  /// In ar, this message translates to:
  /// **'حدّثه من متجر التطبيقات على هاتفك.'**
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
  /// **'اكتب للأكاديمية عن حصة، أو دفعة، أو مشكلة تقنية، أو أي شيء آخر. يصلك الرد هنا وفي رسالة.'**
  String get helpLead;

  /// No description provided for @supportLead.
  ///
  /// In ar, this message translates to:
  /// **'اكتب للأكاديمية عن مشكلة تقنية، أو راتبك، أو أي شيء آخر. يصلك الرد هنا وفي رسالة.'**
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
  /// **'عمّ تكتب؟'**
  String get ticketKind;

  /// No description provided for @ticketHintComplaint.
  ///
  /// In ar, this message translates to:
  /// **'عن معلّم أو حصة. تبحث الأكاديمية في الأمر وتخبرك بما فعلت.'**
  String get ticketHintComplaint;

  /// No description provided for @ticketHintBilling.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة، أو دفعة، أو مبلغ مسترد، أو باقتك.'**
  String get ticketHintBilling;

  /// No description provided for @ticketHintPay.
  ///
  /// In ar, this message translates to:
  /// **'راتبك أو كشف حسابك.'**
  String get ticketHintPay;

  /// No description provided for @ticketHintTechnical.
  ///
  /// In ar, this message translates to:
  /// **'الفصل أو التطبيق أو الدخول لا يعمل.'**
  String get ticketHintTechnical;

  /// No description provided for @ticketHintOther.
  ///
  /// In ar, this message translates to:
  /// **'سؤال أو طلب.'**
  String get ticketHintOther;

  /// No description provided for @ticketLesson.
  ///
  /// In ar, this message translates to:
  /// **'أي حصة؟ (إن وُجدت)'**
  String get ticketLesson;

  /// No description provided for @ticketNoLesson.
  ///
  /// In ar, this message translates to:
  /// **'ليست حصة معيّنة'**
  String get ticketNoLesson;

  /// No description provided for @ticketSubject.
  ///
  /// In ar, this message translates to:
  /// **'العنوان'**
  String get ticketSubject;

  /// No description provided for @ticketSubjectHint.
  ///
  /// In ar, this message translates to:
  /// **'في كلمات قليلة'**
  String get ticketSubjectHint;

  /// No description provided for @ticketBody.
  ///
  /// In ar, this message translates to:
  /// **'أخبرنا بما حدث'**
  String get ticketBody;

  /// No description provided for @ticketAttach.
  ///
  /// In ar, this message translates to:
  /// **'أرفق صورة أو ملف PDF'**
  String get ticketAttach;

  /// No description provided for @ticketTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 10 ميغابايت.'**
  String get ticketTooBig;

  /// No description provided for @ticketSent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسلت. سترد الأكاديمية هنا.'**
  String get ticketSent;

  /// No description provided for @ticketsEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد تذاكر بعد. إن كان هناك أي شيء غير سليم، فاكتب إلينا.'**
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
  /// **'ردّت الأكاديمية'**
  String get ticketYourTurn;

  /// No description provided for @ticketYou.
  ///
  /// In ar, this message translates to:
  /// **'أنت'**
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
  /// **'لم تُحَل بعد'**
  String get ticketReopen;

  /// No description provided for @ticketReopenHint.
  ///
  /// In ar, this message translates to:
  /// **'لم تُحَل بعد؟ يمكنك إعادة فتحها حتى {date}.'**
  String ticketReopenHint(String date);

  /// No description provided for @ticketClose.
  ///
  /// In ar, this message translates to:
  /// **'أغلق التذكرة'**
  String get ticketClose;

  /// No description provided for @ticketCloseHint.
  ///
  /// In ar, this message translates to:
  /// **'أغلقها عندما تُحَل أو إن لم تعد تحتاج إليها. لن تستقبل رسائل بعد ذلك.'**
  String get ticketCloseHint;

  /// No description provided for @ticketClosed.
  ///
  /// In ar, this message translates to:
  /// **'أُغلقت هذه التذكرة. افتح تذكرة جديدة إن احتجت إلى شيء.'**
  String get ticketClosed;

  /// No description provided for @tellUsTitle.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن قلق'**
  String get tellUsTitle;

  /// No description provided for @tellUsTitleStudent.
  ///
  /// In ar, this message translates to:
  /// **'هل هناك شيء غير سليم؟ أخبرنا'**
  String get tellUsTitleStudent;

  /// No description provided for @tellUsTitleKids.
  ///
  /// In ar, this message translates to:
  /// **'هل أحزنك شيء؟'**
  String get tellUsTitleKids;

  /// No description provided for @tellUsNavKids.
  ///
  /// In ar, this message translates to:
  /// **'أخبر شخصًا كبيرًا'**
  String get tellUsNavKids;

  /// No description provided for @tellUsNavMiddle.
  ///
  /// In ar, this message translates to:
  /// **'أخبرنا إن كان هناك ما يزعجك'**
  String get tellUsNavMiddle;

  /// No description provided for @tellUsLead.
  ///
  /// In ar, this message translates to:
  /// **'إن أقلقك أي شيء في حصة، أو من معلّم، أو من أي شخص على المنصة، فأخبرنا هنا. لا يقرؤه إلا المسؤولون عن حماية الأطفال في الأكاديمية.'**
  String get tellUsLead;

  /// No description provided for @tellUsLeadStudent.
  ///
  /// In ar, this message translates to:
  /// **'إن جعلك أحد تشعر بعدم الارتياح، أو بالخوف، أو بأنك لست في أمان، فأخبرنا. لا يقرؤه إلا المسؤولون عن حماية الطلاب في الأكاديمية، ولن تُعاقَب.'**
  String get tellUsLeadStudent;

  /// No description provided for @tellUsLeadKids.
  ///
  /// In ar, this message translates to:
  /// **'إن أحزنك أحد أو أخافك، فاضغط على زر. سيساعدك شخص كبير في الأكاديمية.'**
  String get tellUsLeadKids;

  /// No description provided for @concernTeacherNav.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن قلق'**
  String get concernTeacherNav;

  /// No description provided for @concernTeacherTitle.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن قلق على سلامة طالب'**
  String get concernTeacherTitle;

  /// No description provided for @concernTeacherLead.
  ///
  /// In ar, this message translates to:
  /// **'إن أقلقك شيء في سلامة طالب أو حاله، أو تصرّف شخص بالغ آخر، فأخبر مسؤولي الحماية في الأكاديمية هنا. هم وحدهم من يقرؤونه. لا تحقّق فيه ولا تتواصل مع الأسرة بشأنه بنفسك.'**
  String get concernTeacherLead;

  /// No description provided for @concernAbout.
  ///
  /// In ar, this message translates to:
  /// **'عن {name}'**
  String concernAbout(String name);

  /// No description provided for @concernWhat.
  ///
  /// In ar, this message translates to:
  /// **'عمّ يتعلّق الأمر؟'**
  String get concernWhat;

  /// No description provided for @concernWhatKids.
  ///
  /// In ar, this message translates to:
  /// **'ماذا حدث؟'**
  String get concernWhatKids;

  /// No description provided for @concernPick.
  ///
  /// In ar, this message translates to:
  /// **'اختر موضوع الأمر.'**
  String get concernPick;

  /// No description provided for @concernText.
  ///
  /// In ar, this message translates to:
  /// **'ماذا حدث؟'**
  String get concernText;

  /// No description provided for @concernTextKids.
  ///
  /// In ar, this message translates to:
  /// **'هل تريد أن تقول المزيد؟ ليس ضروريًا.'**
  String get concernTextKids;

  /// No description provided for @concernTextHint.
  ///
  /// In ar, this message translates to:
  /// **'متى حدث، ومن كان موجودًا، وماذا قيل.'**
  String get concernTextHint;

  /// No description provided for @concernLesson.
  ///
  /// In ar, this message translates to:
  /// **'في أي حصة (إن وُجدت)؟'**
  String get concernLesson;

  /// No description provided for @concernNoLesson.
  ///
  /// In ar, this message translates to:
  /// **'ليس عن حصة'**
  String get concernNoLesson;

  /// No description provided for @concernStudent.
  ///
  /// In ar, this message translates to:
  /// **'أي طالب؟'**
  String get concernStudent;

  /// No description provided for @concernNoStudent.
  ///
  /// In ar, this message translates to:
  /// **'ليس عن طالب معيّن'**
  String get concernNoStudent;

  /// No description provided for @concernUrgent.
  ///
  /// In ar, this message translates to:
  /// **'قد يكون أحد في خطر الآن'**
  String get concernUrgent;

  /// No description provided for @concernSend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل'**
  String get concernSend;

  /// No description provided for @concernSendTeacher.
  ///
  /// In ar, this message translates to:
  /// **'أرسل إلى مسؤولي الحماية'**
  String get concernSendTeacher;

  /// No description provided for @concernAgain.
  ///
  /// In ar, this message translates to:
  /// **'أخبرنا بشيء آخر'**
  String get concernAgain;

  /// No description provided for @concernAgainTeacher.
  ///
  /// In ar, this message translates to:
  /// **'بلّغ عن أمر آخر'**
  String get concernAgainTeacher;

  /// No description provided for @concernNumber.
  ///
  /// In ar, this message translates to:
  /// **'رقم {number}'**
  String concernNumber(String number);

  /// No description provided for @concernsMine.
  ///
  /// In ar, this message translates to:
  /// **'ما أخبرتنا به'**
  String get concernsMine;

  /// No description provided for @concernsNone.
  ///
  /// In ar, this message translates to:
  /// **'لا شيء بعد.'**
  String get concernsNone;

  /// No description provided for @documentsTitle.
  ///
  /// In ar, this message translates to:
  /// **'مستنداتي'**
  String get documentsTitle;

  /// No description provided for @documentsLead.
  ///
  /// In ar, this message translates to:
  /// **'تراجع الأكاديمية هذه المستندات مقارنة بالأصل؛ وشهادة خلو السوابق الجنائية هي المطلوبة لتدرّس من هم دون 18 عامًا.'**
  String get documentsLead;

  /// No description provided for @documentSend.
  ///
  /// In ar, this message translates to:
  /// **'أرسل مستندًا'**
  String get documentSend;

  /// No description provided for @documentSendHint.
  ///
  /// In ar, this message translates to:
  /// **'صورة واضحة أو ملف PDF من الجهة المختصة. احتفظ بالأصل: فقد تطلب الأكاديمية رؤيته.'**
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
  /// **'صالح حتى (اختياري)'**
  String get documentExpires;

  /// No description provided for @documentUntil.
  ///
  /// In ar, this message translates to:
  /// **'صالح حتى {day}'**
  String documentUntil(String day);

  /// No description provided for @documentNoEnd.
  ///
  /// In ar, this message translates to:
  /// **'دون تاريخ انتهاء'**
  String get documentNoEnd;

  /// No description provided for @documentPick.
  ///
  /// In ar, this message translates to:
  /// **'اختر صورة أو ملف PDF'**
  String get documentPick;

  /// No description provided for @documentPickFirst.
  ///
  /// In ar, this message translates to:
  /// **'اختر الصورة أو ملف PDF أولًا.'**
  String get documentPickFirst;

  /// No description provided for @documentTooBig.
  ///
  /// In ar, this message translates to:
  /// **'الملف أكبر من 10 ميغابايت.'**
  String get documentTooBig;

  /// No description provided for @documentSent.
  ///
  /// In ar, this message translates to:
  /// **'أُرسل: ستراجعه الأكاديمية.'**
  String get documentSent;

  /// No description provided for @documentsList.
  ///
  /// In ar, this message translates to:
  /// **'ما أرسلته'**
  String get documentsList;

  /// No description provided for @documentsNone.
  ///
  /// In ar, this message translates to:
  /// **'لم ترسل أي مستندات بعد.'**
  String get documentsNone;

  /// No description provided for @messagesTitle.
  ///
  /// In ar, this message translates to:
  /// **'الرسائل'**
  String get messagesTitle;

  /// No description provided for @messagesFamilyTitle.
  ///
  /// In ar, this message translates to:
  /// **'الرسائل مع المعلّمين'**
  String get messagesFamilyTitle;

  /// No description provided for @messagesStudentTitle.
  ///
  /// In ar, this message translates to:
  /// **'الرسائل مع معلّميك'**
  String get messagesStudentTitle;

  /// No description provided for @messagesKidsTitle.
  ///
  /// In ar, this message translates to:
  /// **'تحدّث مع معلّمك'**
  String get messagesKidsTitle;

  /// No description provided for @messagesEmptyTeacher.
  ///
  /// In ar, this message translates to:
  /// **'عندما يصبح لديك طلاب، ستجدهم هنا وتستطيع التحدث معهم ومع أسرهم.'**
  String get messagesEmptyTeacher;

  /// No description provided for @messagesEmptyFamily.
  ///
  /// In ar, this message translates to:
  /// **'عندما يصبح للطالب معلّم، ستجده هنا.'**
  String get messagesEmptyFamily;

  /// No description provided for @messagesStart.
  ///
  /// In ar, this message translates to:
  /// **'لم يكتب أحد بعد'**
  String get messagesStart;

  /// No description provided for @messagesYou.
  ///
  /// In ar, this message translates to:
  /// **'أنت'**
  String get messagesYou;

  /// No description provided for @messagesAlsoHere.
  ///
  /// In ar, this message translates to:
  /// **'معكم: {names}'**
  String messagesAlsoHere(String names);

  /// No description provided for @messagesFirst.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ المحادثة: يقرأ أولياء الأمور والأكاديمية ما يُكتب هنا.'**
  String get messagesFirst;

  /// No description provided for @messagesWrite.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رسالة…'**
  String get messagesWrite;

  /// No description provided for @gamesStars.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{دون نجوم} =1{نجمة واحدة} =2{نجمتان} few{{n} نجوم} other{{n} نجمة}}'**
  String gamesStars(int n);

  /// No description provided for @gamesSoundOn.
  ///
  /// In ar, this message translates to:
  /// **'شغّل الموسيقى والأصوات'**
  String get gamesSoundOn;

  /// No description provided for @gamesSoundOff.
  ///
  /// In ar, this message translates to:
  /// **'أوقف الموسيقى والأصوات'**
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
  /// **'البيضة القادمة: {have} من {need} ⭐'**
  String gamesEggCardKids(int have, int need);

  /// No description provided for @gamesIslandDone.
  ///
  /// In ar, this message translates to:
  /// **'جمعت كل كائنات الجزيرة!'**
  String get gamesIslandDone;

  /// No description provided for @gamesClose.
  ///
  /// In ar, this message translates to:
  /// **'أغلق اللعبة وارجع'**
  String get gamesClose;

  /// No description provided for @gamesToIsland.
  ///
  /// In ar, this message translates to:
  /// **'ارجع إلى الجزيرة'**
  String get gamesToIsland;

  /// No description provided for @gamesAlbum.
  ///
  /// In ar, this message translates to:
  /// **'كائناتي'**
  String get gamesAlbum;

  /// No description provided for @gamesItems.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{سؤال واحد} =2{سؤالان} few{{n} أسئلة} other{{n} سؤالًا}}'**
  String gamesItems(int n);

  /// No description provided for @gamesPractising.
  ///
  /// In ar, this message translates to:
  /// **'تتدرّب على: {what}'**
  String gamesPractising(String what);

  /// No description provided for @gamesResult0.
  ///
  /// In ar, this message translates to:
  /// **'استمر وحاول مرة أخرى!'**
  String get gamesResult0;

  /// No description provided for @gamesResult1.
  ///
  /// In ar, this message translates to:
  /// **'بداية جميلة!'**
  String get gamesResult1;

  /// No description provided for @gamesResult2.
  ///
  /// In ar, this message translates to:
  /// **'أحسنت!'**
  String get gamesResult2;

  /// No description provided for @gamesResult3.
  ///
  /// In ar, this message translates to:
  /// **'رائع! كله صحيح تقريبًا'**
  String get gamesResult3;

  /// No description provided for @gamesScore.
  ///
  /// In ar, this message translates to:
  /// **'{correct} من {total} صحيحة من أول مرة'**
  String gamesScore(int correct, int total);

  /// No description provided for @gamesHatched.
  ///
  /// In ar, this message translates to:
  /// **'فقس {name}!'**
  String gamesHatched(String name);

  /// No description provided for @gamesHomeworkDone.
  ///
  /// In ar, this message translates to:
  /// **'سُلّم الواجب «{title}» بهذا التدريب.'**
  String gamesHomeworkDone(String title);

  /// No description provided for @gamesEgg.
  ///
  /// In ar, this message translates to:
  /// **'البيضة القادمة: {have} من {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}}'**
  String gamesEgg(int have, int need);

  /// No description provided for @gamesAgain.
  ///
  /// In ar, this message translates to:
  /// **'العب مرة أخرى'**
  String get gamesAgain;

  /// No description provided for @gamesPlay.
  ///
  /// In ar, this message translates to:
  /// **'العب'**
  String get gamesPlay;

  /// No description provided for @gamesLoading.
  ///
  /// In ar, this message translates to:
  /// **'نجهّز الجولة…'**
  String get gamesLoading;

  /// No description provided for @gamesSending.
  ///
  /// In ar, this message translates to:
  /// **'نحسب النجوم…'**
  String get gamesSending;

  /// No description provided for @gamesPageProblem.
  ///
  /// In ar, this message translates to:
  /// **'لم تعمل اللعبة. حاول مرة أخرى.'**
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
  /// **'كلما جمعت {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}}، فقست لك بيضة جديدة'**
  String gamesHatchDoneSub(int need);

  /// No description provided for @gamesHatchTap0.
  ///
  /// In ar, this message translates to:
  /// **'انقر على البيضة 3 مرات'**
  String get gamesHatchTap0;

  /// No description provided for @gamesHatchTap1.
  ///
  /// In ar, this message translates to:
  /// **'مرتان أخريان!'**
  String get gamesHatchTap1;

  /// No description provided for @gamesHatchTap2.
  ///
  /// In ar, this message translates to:
  /// **'مرة أخرى!'**
  String get gamesHatchTap2;

  /// No description provided for @gamesHatchTap3.
  ///
  /// In ar, this message translates to:
  /// **'تفقس...'**
  String get gamesHatchTap3;

  /// No description provided for @gamesHatchEgg.
  ///
  /// In ar, this message translates to:
  /// **'البيضة، انقر عليها. بقي {left}'**
  String gamesHatchEgg(int left);

  /// No description provided for @gamesHatchNew.
  ///
  /// In ar, this message translates to:
  /// **'كائن جديد في ألبومك · {found} من {total}'**
  String gamesHatchNew(int found, int total);

  /// No description provided for @gamesHatchAsk.
  ///
  /// In ar, this message translates to:
  /// **'ماذا تحب أن تسمّيه؟'**
  String get gamesHatchAsk;

  /// No description provided for @gamesHatchKeep.
  ///
  /// In ar, this message translates to:
  /// **'ضعه في الألبوم'**
  String get gamesHatchKeep;

  /// No description provided for @gamesAlbumTitle.
  ///
  /// In ar, this message translates to:
  /// **'ألبوم كائناتي'**
  String get gamesAlbumTitle;

  /// No description provided for @gamesAlbumHint.
  ///
  /// In ar, this message translates to:
  /// **'كل {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}} في رحلتك تفقس لك بيضة، وكل درس تتقنه يفقس لك كائنًا.'**
  String gamesAlbumHint(int need);

  /// No description provided for @gamesAlbumGuardian.
  ///
  /// In ar, this message translates to:
  /// **'هذا ألبوم {name}. هو وحده من يختار صديقه ويسمّي كائناته.'**
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
  /// **'ليس بعد'**
  String get gamesNotYet;

  /// No description provided for @gamesAriaFound.
  ///
  /// In ar, this message translates to:
  /// **'{name}، من {island}'**
  String gamesAriaFound(String name, String island);

  /// No description provided for @gamesAriaShadow.
  ///
  /// In ar, this message translates to:
  /// **'كائن لم يفقس بعد، من {island}'**
  String gamesAriaShadow(String island);

  /// No description provided for @gamesVoice.
  ///
  /// In ar, this message translates to:
  /// **'اسمع صوته'**
  String get gamesVoice;

  /// No description provided for @gamesMakeBuddy.
  ///
  /// In ar, this message translates to:
  /// **'اجعله صديقك'**
  String get gamesMakeBuddy;

  /// No description provided for @gamesIsBuddyKids.
  ///
  /// In ar, this message translates to:
  /// **'هذا صديقك 💛'**
  String get gamesIsBuddyKids;

  /// No description provided for @gamesIsBuddy.
  ///
  /// In ar, this message translates to:
  /// **'هذا صديقك'**
  String get gamesIsBuddy;

  /// No description provided for @gamesBuddyDone.
  ///
  /// In ar, this message translates to:
  /// **'سيطير {name} معك في الألعاب من الآن'**
  String gamesBuddyDone(String name);

  /// No description provided for @gamesShadowStory.
  ///
  /// In ar, this message translates to:
  /// **'لم يفقس بعد.'**
  String get gamesShadowStory;

  /// No description provided for @gamesHowCommon.
  ///
  /// In ar, this message translates to:
  /// **'كيف تفقسه: اجمع النجوم وأنت في {island}: كل {need, plural, =1{نجمة} =2{نجمتين} few{{need} نجوم} other{{need} نجمة}} بيضة.'**
  String gamesHowCommon(String island, int need);

  /// No description provided for @gamesHowRare.
  ///
  /// In ar, this message translates to:
  /// **'كيف تفقسه: كائن نادر في {island}: يفقس بعد كائناتها العادية.'**
  String gamesHowRare(String island);

  /// No description provided for @gamesHowLegendary.
  ///
  /// In ar, this message translates to:
  /// **'كيف تفقسه: أسطوري! آخر كائن في {island}. اجمع كل ما قبله.'**
  String gamesHowLegendary(String island);

  /// No description provided for @gamesNameIt.
  ///
  /// In ar, this message translates to:
  /// **'سمِّه:'**
  String get gamesNameIt;

  /// No description provided for @gamesNamed.
  ///
  /// In ar, this message translates to:
  /// **'اسمه الآن {name}'**
  String gamesNamed(String name);

  /// No description provided for @gamesFromStars.
  ///
  /// In ar, this message translates to:
  /// **'فقس من نجوم رحلتك'**
  String get gamesFromStars;

  /// No description provided for @gamesFromMastery.
  ///
  /// In ar, this message translates to:
  /// **'فقس عندما أتقنت درسًا'**
  String get gamesFromMastery;

  /// No description provided for @gamesRace.
  ///
  /// In ar, this message translates to:
  /// **'سباق مع نفسك'**
  String get gamesRace;

  /// No description provided for @gamesBest.
  ///
  /// In ar, this message translates to:
  /// **'أفضل وقت: {s}'**
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
  /// **'اقتربت!'**
  String get gamesSoClose;

  /// No description provided for @gamesFirst.
  ///
  /// In ar, this message translates to:
  /// **'أول رقم لك!'**
  String get gamesFirst;

  /// No description provided for @gamesOver.
  ///
  /// In ar, this message translates to:
  /// **'{correct} من {total} صحيحة في {s}'**
  String gamesOver(int correct, int total, String s);

  /// No description provided for @gamesReviewAgain.
  ///
  /// In ar, this message translates to:
  /// **'راجع مرة أخرى'**
  String get gamesReviewAgain;

  /// No description provided for @gamesGuardianTitle.
  ///
  /// In ar, this message translates to:
  /// **'ألعاب {name}'**
  String gamesGuardianTitle(String name);

  /// No description provided for @gamesPlayTime.
  ///
  /// In ar, this message translates to:
  /// **'وقت اللعب اليوم'**
  String get gamesPlayTime;

  /// No description provided for @gamesTimeText.
  ///
  /// In ar, this message translates to:
  /// **'{used} من {limit, plural, =1{دقيقة} =2{دقيقتين} few{{limit} دقائق} other{{limit} دقيقة}} لعب اليوم. بقي {left, plural, =1{دقيقة} =2{دقيقتان} few{{left} دقائق} other{{left} دقيقة}}.'**
  String gamesTimeText(int used, int limit, int left);

  /// No description provided for @gamesRefusedOff.
  ///
  /// In ar, this message translates to:
  /// **'الألعاب مغلقة في الأكاديمية الآن.'**
  String get gamesRefusedOff;

  /// No description provided for @gamesRefusedPaused.
  ///
  /// In ar, this message translates to:
  /// **'أوقف ولي الأمر الألعاب الآن.'**
  String get gamesRefusedPaused;

  /// No description provided for @gamesRefusedNight.
  ///
  /// In ar, this message translates to:
  /// **'نامت الطيور. تعود الألعاب في الساعة 7 صباحًا.'**
  String get gamesRefusedNight;

  /// No description provided for @gamesRefusedLimit.
  ///
  /// In ar, this message translates to:
  /// **'انتهت دقائق اللعب اليوم. نكمل غدًا!'**
  String get gamesRefusedLimit;

  /// No description provided for @gamesTimeOpen.
  ///
  /// In ar, this message translates to:
  /// **'{used, plural, =1{دقيقة} =2{دقيقتان} few{{used} دقائق} other{{used} دقيقة}} لعب اليوم، دون حد.'**
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
  /// **'أغلق اللعب من 9 مساءً إلى 7 صباحًا'**
  String get gamesNightLock;

  /// No description provided for @gamesEnabled.
  ///
  /// In ar, this message translates to:
  /// **'الألعاب مفعّلة'**
  String get gamesEnabled;

  /// No description provided for @gamesLearned.
  ///
  /// In ar, this message translates to:
  /// **'ما رُوجع في اللعب'**
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
  /// **'رُوجع «{title}» {rounds} في {games}'**
  String gamesPractised(String title, String rounds, String games);

  /// No description provided for @gamesHardest.
  ///
  /// In ar, this message translates to:
  /// **'أصعب سؤال: «{text}». يمكنكما مراجعته معًا.'**
  String gamesHardest(String text);

  /// No description provided for @gamesBestRound.
  ///
  /// In ar, this message translates to:
  /// **'أفضل جولة: {correct} من {total} صحيحة من أول مرة في «{game}»'**
  String gamesBestRound(int correct, int total, String game);

  /// No description provided for @gamesQuiet.
  ///
  /// In ar, this message translates to:
  /// **'لم يلعب هذا الأسبوع. الألعاب تدرّب على دروسه فقط، والمعلّم هو من يضع التدريبات على الدرس.'**
  String get gamesQuiet;

  /// No description provided for @gamesHatchedToday.
  ///
  /// In ar, this message translates to:
  /// **'فقس «{name}» اليوم'**
  String gamesHatchedToday(String name);

  /// No description provided for @gamesHatchedStars.
  ///
  /// In ar, this message translates to:
  /// **'من نجوم الرحلة · {found} في الألبوم'**
  String gamesHatchedStars(int found);

  /// No description provided for @gamesHatchedMastery.
  ///
  /// In ar, this message translates to:
  /// **'من درس أتقنه · {found} في الألبوم'**
  String gamesHatchedMastery(int found);

  /// No description provided for @gamesCheer.
  ///
  /// In ar, this message translates to:
  /// **'هنّئه'**
  String get gamesCheer;

  /// No description provided for @gamesCheered.
  ///
  /// In ar, this message translates to:
  /// **'وصلته تهنئتك'**
  String get gamesCheered;

  /// No description provided for @gamesSafety.
  ///
  /// In ar, this message translates to:
  /// **'لا مشتريات داخل الألعاب، ولا إعلانات، ولا محادثة مع غرباء. كل كائن وكل نجمة تُكسب بالمذاكرة.'**
  String get gamesSafety;

  /// No description provided for @gamesTogether.
  ///
  /// In ar, this message translates to:
  /// **'العبوا معًا'**
  String get gamesTogether;

  /// No description provided for @gamesSaved.
  ///
  /// In ar, this message translates to:
  /// **'حُفظ.'**
  String get gamesSaved;

  /// No description provided for @gamesCardKids.
  ///
  /// In ar, this message translates to:
  /// **'الجزر تنتظرك 🏝️'**
  String get gamesCardKids;

  /// No description provided for @gamesCardLead.
  ///
  /// In ar, this message translates to:
  /// **'تدرّب على دروسك بالألعاب: {what}'**
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
  /// **'كيف يتقدّم {name}'**
  String journeyHow(String name);

  /// No description provided for @journeyNextStation.
  ///
  /// In ar, this message translates to:
  /// **'القادمة: {title}'**
  String journeyNextStation(String title);

  /// No description provided for @journeyLevels.
  ///
  /// In ar, this message translates to:
  /// **'المستويات'**
  String get journeyLevels;

  /// No description provided for @journeyLessonsDone.
  ///
  /// In ar, this message translates to:
  /// **'{done} من {total, plural, =1{درس واحد} =2{درسين} few{{total} دروس} other{{total} درسًا}}'**
  String journeyLessonsDone(int done, int total);

  /// No description provided for @journeyAheadLocked.
  ///
  /// In ar, this message translates to:
  /// **'تُفتح عندما تنهي {title}'**
  String journeyAheadLocked(String title);

  /// No description provided for @journeyAheadFirst.
  ///
  /// In ar, this message translates to:
  /// **'تُفتح عندما تصل إليها'**
  String get journeyAheadFirst;

  /// No description provided for @journeyAriaDone.
  ///
  /// In ar, this message translates to:
  /// **'{title}، أنهيتها'**
  String journeyAriaDone(String title);

  /// No description provided for @journeyAriaHere.
  ///
  /// In ar, this message translates to:
  /// **'{title}، أنت هنا'**
  String journeyAriaHere(String title);

  /// No description provided for @journeyAriaAhead.
  ///
  /// In ar, this message translates to:
  /// **'{title}، ما زالت مغلقة'**
  String journeyAriaAhead(String title);

  /// No description provided for @journeyStationDone.
  ///
  /// In ar, this message translates to:
  /// **'أنهيته'**
  String get journeyStationDone;

  /// No description provided for @journeyStationReview.
  ///
  /// In ar, this message translates to:
  /// **'يحتاج إلى مراجعة'**
  String get journeyStationReview;

  /// No description provided for @journeyStationHere.
  ///
  /// In ar, this message translates to:
  /// **'أنت هنا'**
  String get journeyStationHere;

  /// No description provided for @journeyStationTodo.
  ///
  /// In ar, this message translates to:
  /// **'لم يأتِ بعد'**
  String get journeyStationTodo;

  /// No description provided for @journeyStationTodoNote.
  ///
  /// In ar, this message translates to:
  /// **'هذا الدرس لم يأتِ بعد. ستتدرّب عليه عندما تصل إليه.'**
  String get journeyStationTodoNote;

  /// No description provided for @journeyStationReviewNote.
  ///
  /// In ar, this message translates to:
  /// **'حان وقت مراجعة هذا الدرس: العب لعبة عليه.'**
  String get journeyStationReviewNote;

  /// No description provided for @journeyPractised.
  ///
  /// In ar, this message translates to:
  /// **'تدرّبت عليه {rounds} · {p}% صحيح'**
  String journeyPractised(String rounds, int p);

  /// No description provided for @journeyPlayStation.
  ///
  /// In ar, this message translates to:
  /// **'العب على هذا الدرس'**
  String get journeyPlayStation;

  /// No description provided for @journeyNoGames.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد لعبة على تدريبات هذا الدرس بعد.'**
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
  /// **'{n, plural, =1{مهمة} =2{مهمتان} few{{n} مهمات} other{{n} مهمة}}'**
  String journeyMissionsOn(int n);

  /// No description provided for @journeyOpenMissions.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مهمة تنتظرك} =2{مهمتان تنتظرانك} few{{n} مهمات تنتظرك} other{{n} مهمة تنتظرك}}'**
  String journeyOpenMissions(int n);

  /// No description provided for @journeyNoMissions.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مهمات الآن. استمتع بيومك!'**
  String get journeyNoMissions;

  /// No description provided for @journeyReview.
  ///
  /// In ar, this message translates to:
  /// **'راجع'**
  String get journeyReview;

  /// No description provided for @reviewsWaiting.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مراجعة تنتظرك} =2{مراجعتان تنتظرانك} few{{n} مراجعات تنتظرك} other{{n} مراجعة تنتظرك}}'**
  String reviewsWaiting(int n);

  /// No description provided for @reviewsHint.
  ///
  /// In ar, this message translates to:
  /// **'كل مراجعة لعبة قصيرة'**
  String get reviewsHint;

  /// No description provided for @hereAndNext.
  ///
  /// In ar, this message translates to:
  /// **'أنت هنا: {here} · بعدها: {next}'**
  String hereAndNext(String here, String next);

  /// No description provided for @hereOnly.
  ///
  /// In ar, this message translates to:
  /// **'أنت هنا: {here}'**
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
  /// **'هدفك: أن تنهي البرنامج'**
  String get goalTitle;

  /// No description provided for @goalDone.
  ///
  /// In ar, this message translates to:
  /// **'أنهيتها من البرنامج'**
  String get goalDone;

  /// No description provided for @goalPace.
  ///
  /// In ar, this message translates to:
  /// **'بهذه السرعة تنهيه يوم {day}'**
  String goalPace(String day);

  /// No description provided for @nowTitle.
  ///
  /// In ar, this message translates to:
  /// **'الآن'**
  String get nowTitle;

  /// No description provided for @nextIs.
  ///
  /// In ar, this message translates to:
  /// **'القادمة: {title}'**
  String nextIs(String title);

  /// No description provided for @weekPlan.
  ///
  /// In ar, this message translates to:
  /// **'خطة الأسبوع'**
  String get weekPlan;

  /// No description provided for @notHandedIn.
  ///
  /// In ar, this message translates to:
  /// **'لم يُسلَّم بعد'**
  String get notHandedIn;

  /// No description provided for @planNoDay.
  ///
  /// In ar, this message translates to:
  /// **'دون موعد'**
  String get planNoDay;

  /// No description provided for @lessonsToReview.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{درس يحتاج إلى مراجعة} =2{درسان يحتاجان إلى مراجعة} few{{n} دروس تحتاج إلى مراجعة} other{{n} درسًا يحتاج إلى مراجعة}}'**
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
  /// **'{n, plural, =1{مستوى واحد} =2{مستويان} few{{n} مستويات} other{{n} مستوى}}'**
  String levelsCount(int n);

  /// No description provided for @curriculumLessons.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{درس واحد} =2{درسان} few{{n} دروس} other{{n} درسًا}}'**
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
  /// **'{done} من {total, plural, =1{مهمة واحدة} =2{مهمتين} few{{total} مهمات} other{{total} مهمة}} هذا الأسبوع'**
  String journeyChestProgress(int done, int total);

  /// No description provided for @journeyChestHint.
  ///
  /// In ar, this message translates to:
  /// **'احضر كل حصص الأسبوع وسلّم واجباته، وسيُفتح الصندوق بـ {n, plural, =1{نجمة} =2{نجمتين} few{{n} نجوم} other{{n} نجمة}}'**
  String journeyChestHint(int n);

  /// No description provided for @journeyChestMissed.
  ///
  /// In ar, this message translates to:
  /// **'فاتك هذا الصندوق. هناك صندوق جديد هذا الأسبوع!'**
  String get journeyChestMissed;

  /// No description provided for @journeyChestWaiting.
  ///
  /// In ar, this message translates to:
  /// **'الصندوق جاهز ليُفتح من حساب {name}'**
  String journeyChestWaiting(String name);

  /// No description provided for @journeyChestGot.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{+نجمة في رحلتك!} =2{+نجمتان في رحلتك!} few{+{n} نجوم في رحلتك!} other{+{n} نجمة في رحلتك!}}'**
  String journeyChestGot(int n);

  /// No description provided for @journeyYourStars.
  ///
  /// In ar, this message translates to:
  /// **'نجومك'**
  String get journeyYourStars;

  /// No description provided for @journeyStarsTo.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{بقيت لك نجمة حتى {rank}} =2{بقيت لك نجمتان حتى {rank}} few{بقيت لك {n} نجوم حتى {rank}} other{بقيت لك {n} نجمة حتى {rank}}}'**
  String journeyStarsTo(int n, String rank);

  /// No description provided for @journeyStarsCount.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد نجوم بعد} =1{نجمة واحدة} =2{نجمتان} few{{n} نجوم} other{{n} نجمة}}'**
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
  /// **'{n, plural, =0{لم تأخذ نجومًا في آخر حصة} =1{أخذت نجمة في آخر حصة} =2{أخذت نجمتين في آخر حصة!} few{أخذت {n} نجوم في آخر حصة!} other{أخذت {n} نجمة في آخر حصة!}}'**
  String journeyLessonStars(int n);

  /// No description provided for @journeyLessonCanBring.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{تمنحك نجمة} =2{تمنحك نجمتين} few{تمنحك حتى {n} نجوم} other{تمنحك حتى {n} نجمة}}'**
  String journeyLessonCanBring(int n);

  /// No description provided for @journeyEggReady.
  ///
  /// In ar, this message translates to:
  /// **'بيضتك جاهزة!'**
  String get journeyEggReady;

  /// No description provided for @journeyEggHint.
  ///
  /// In ar, this message translates to:
  /// **'افقسها وانظر من سيخرج'**
  String get journeyEggHint;

  /// No description provided for @journeyEggHatch.
  ///
  /// In ar, this message translates to:
  /// **'افقسها'**
  String get journeyEggHatch;

  /// No description provided for @journeyEggNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد بيضة جاهزة الآن. استمر في جمع النجوم!'**
  String get journeyEggNone;

  /// No description provided for @journeyNoCourses.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد دورات جارية بعد.'**
  String get journeyNoCourses;

  /// No description provided for @journeyNoCurriculum.
  ///
  /// In ar, this message translates to:
  /// **'ليس لهذه الدورة منهج بعد. تظهر مهماتها هنا.'**
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
  /// **'لم تُلعب جولته بعد.'**
  String get homeworkPracticeNotYet;

  /// No description provided for @homeworkByPlaying.
  ///
  /// In ar, this message translates to:
  /// **'يُسلَّم هذا الواجب عندما تلعب جولته.'**
  String get homeworkByPlaying;

  /// No description provided for @homeworkPracticeResult.
  ///
  /// In ar, this message translates to:
  /// **'التدريب: {correct} من {total} صحيحة · {stars} ({game})'**
  String homeworkPracticeResult(int correct, int total, String stars, String game);

  /// No description provided for @homeworkNoGame.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد لعبة لهذا الواجب الآن.'**
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
  /// **'نص القرآن غير موجود بعد، وستظهر الخريطة فور وصوله.'**
  String get hifzNoText;

  /// No description provided for @hifzMemorized.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد صفحات محفوظة بعد من {total}} =1{صفحة واحدة محفوظة من {total}} =2{صفحتان محفوظتان من {total}} few{{n} صفحات محفوظة من {total}} other{{n} صفحة محفوظة من {total}}}'**
  String hifzMemorized(int n, int total);

  /// No description provided for @hifzMemorizedKids.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لم تحفظ صفحات بعد. هيا نبدأ!} =1{حفظت صفحة واحدة! ⭐} =2{حفظت صفحتين! ⭐} few{حفظت {n} صفحات! ⭐} other{حفظت {n} صفحة! ⭐}}'**
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
  /// **'ورد اليوم'**
  String get hifzToday;

  /// No description provided for @hifzTodayProgress.
  ///
  /// In ar, this message translates to:
  /// **'أُنجز {done} من {daily, plural, =1{صفحة واحدة} =2{صفحتين} few{{daily} صفحات} other{{daily} صفحة}}'**
  String hifzTodayProgress(int done, int daily);

  /// No description provided for @hifzTodayDone.
  ///
  /// In ar, this message translates to:
  /// **'انتهى ورد اليوم. أحسنت!'**
  String get hifzTodayDone;

  /// No description provided for @hifzTodayNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد صفحات عليها مراجعة اليوم.'**
  String get hifzTodayNone;

  /// No description provided for @hifzWirdDone.
  ///
  /// In ar, this message translates to:
  /// **'انتهى ورد اليوم!'**
  String get hifzWirdDone;

  /// No description provided for @hifzWirdDoneKids.
  ///
  /// In ar, this message translates to:
  /// **'أنهيت وردك! 🎉'**
  String get hifzWirdDoneKids;

  /// No description provided for @hifzOverdue.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{متأخرة يومًا} =2{متأخرة يومين} few{متأخرة {n} أيام} other{متأخرة {n} يومًا}}'**
  String hifzOverdue(int n);

  /// No description provided for @hifzReveal.
  ///
  /// In ar, this message translates to:
  /// **'اكشف الصفحة'**
  String get hifzReveal;

  /// No description provided for @hifzRevealHint.
  ///
  /// In ar, this message translates to:
  /// **'سمّع الصفحة من حفظك أولًا، ثم اكشفها وتأكّد.'**
  String get hifzRevealHint;

  /// No description provided for @hifzHowWent.
  ///
  /// In ar, this message translates to:
  /// **'كيف كان الحفظ؟'**
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
  /// **'جيد'**
  String get hifzGrade3;

  /// No description provided for @hifzGrade4.
  ///
  /// In ar, this message translates to:
  /// **'سهلة'**
  String get hifzGrade4;

  /// No description provided for @hifzNextReview.
  ///
  /// In ar, this message translates to:
  /// **'سُجّلت. ستعود إليك هذه الصفحة {day}.'**
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
  /// **'سجّل الصفحة من حفظك، وسنصحّحها كلمة كلمة.'**
  String get hifzReciteHint;

  /// No description provided for @hifzSendRecitation.
  ///
  /// In ar, this message translates to:
  /// **'أرسل التسميع'**
  String get hifzSendRecitation;

  /// No description provided for @hifzListening.
  ///
  /// In ar, this message translates to:
  /// **'نستمع إلى التسميع…'**
  String get hifzListening;

  /// No description provided for @hifzListeningHint.
  ///
  /// In ar, this message translates to:
  /// **'ستظهر النتيجة هنا تلقائيًا.'**
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
  /// **'{n, plural, =1{بقيت صفحة واحدة اليوم} =2{بقيت صفحتان اليوم} few{بقيت {n} صفحات اليوم} other{بقيت {n} صفحة اليوم}}'**
  String hifzCardLeft(int n);

  /// No description provided for @hifzCardDone.
  ///
  /// In ar, this message translates to:
  /// **'انتهى اليوم'**
  String get hifzCardDone;

  /// No description provided for @hifzCardNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد صفحات اليوم'**
  String get hifzCardNone;

  /// No description provided for @hifzCardKids.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{أنهيت وردك اليوم! ⭐} =1{بقيت لك صفحة واحدة تسمّعها} =2{بقيت لك صفحتان تسمّعهما} few{بقيت لك {n} صفحات تسمّعها} other{بقيت لك {n} صفحة تسمّعها}}'**
  String hifzCardKids(int n);

  /// No description provided for @hifzTell.
  ///
  /// In ar, this message translates to:
  /// **'سجّل ما يحفظه'**
  String get hifzTell;

  /// No description provided for @hifzClear.
  ///
  /// In ar, this message translates to:
  /// **'أزل صفحات'**
  String get hifzClear;

  /// No description provided for @hifzClearHint.
  ///
  /// In ar, this message translates to:
  /// **'الصفحات التي تزيلها تعود جديدة على الخريطة، وتبقى مراجعاتها السابقة.'**
  String get hifzClearHint;

  /// No description provided for @hifzClearConfirm.
  ///
  /// In ar, this message translates to:
  /// **'أتزيل هذه الصفحات من الخريطة؟'**
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
  /// **'ما درجة حفظها؟'**
  String get hifzHowWell;

  /// No description provided for @hifzChosen.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لم تختر صفحات بعد} =1{صفحة واحدة} =2{صفحتان} few{{n} صفحات} other{{n} صفحة}}'**
  String hifzChosen(int n);

  /// No description provided for @hifzSave.
  ///
  /// In ar, this message translates to:
  /// **'سجّل'**
  String get hifzSave;

  /// No description provided for @hifzMarked.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لم تُسجَّل صفحات} =1{سُجّلت صفحة واحدة} =2{سُجّلت صفحتان} few{سُجّلت {n} صفحات} other{سُجّلت {n} صفحة}}'**
  String hifzMarked(int n);

  /// No description provided for @hifzCleared.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لم تُزَل صفحات} =1{أُزيلت صفحة واحدة} =2{أُزيلت صفحتان} few{أُزيلت {n} صفحات} other{أُزيلت {n} صفحة}}'**
  String hifzCleared(int n);

  /// No description provided for @hifzRecent.
  ///
  /// In ar, this message translates to:
  /// **'آخر المراجعات'**
  String get hifzRecent;

  /// No description provided for @hifzRecentNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مراجعات في آخر أسبوعين.'**
  String get hifzRecentNone;

  /// No description provided for @mushafTitle.
  ///
  /// In ar, this message translates to:
  /// **'المصحف'**
  String get mushafTitle;

  /// No description provided for @mushafOpen.
  ///
  /// In ar, this message translates to:
  /// **'افتح المصحف'**
  String get mushafOpen;

  /// No description provided for @mushafNone.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد مصحف في هذه الحصة.'**
  String get mushafNone;

  /// No description provided for @mushafPrevious.
  ///
  /// In ar, this message translates to:
  /// **'الصفحة السابقة'**
  String get mushafPrevious;

  /// No description provided for @mushafNext.
  ///
  /// In ar, this message translates to:
  /// **'الصفحة التالية'**
  String get mushafNext;

  /// No description provided for @mushafPageField.
  ///
  /// In ar, this message translates to:
  /// **'صفحة'**
  String get mushafPageField;

  /// No description provided for @mushafPageOfTotal.
  ///
  /// In ar, this message translates to:
  /// **'من {total}'**
  String mushafPageOfTotal(int total);

  /// No description provided for @mushafFollowing.
  ///
  /// In ar, this message translates to:
  /// **'تتقلّب الصفحة مع المعلّم.'**
  String get mushafFollowing;

  /// No description provided for @mushafBackToTeacher.
  ///
  /// In ar, this message translates to:
  /// **'ارجع إلى صفحة المعلّم'**
  String get mushafBackToTeacher;

  /// No description provided for @mushafTeacherOn.
  ///
  /// In ar, this message translates to:
  /// **'المعلّم على صفحة {page}'**
  String mushafTeacherOn(int page);

  /// No description provided for @mushafHintTeacher.
  ///
  /// In ar, this message translates to:
  /// **'انقر على أي كلمة لتعلّمها، ويرى الطالب الصفحة التي تفتحها.'**
  String get mushafHintTeacher;

  /// No description provided for @mushafMarkingClosed.
  ///
  /// In ar, this message translates to:
  /// **'يُفتح تعليم الكلمات مع غرفة الحصة، ويُغلق عندما تسلّم التقرير.'**
  String get mushafMarkingClosed;

  /// No description provided for @mushafMarkWord.
  ///
  /// In ar, this message translates to:
  /// **'علّم الكلمة'**
  String get mushafMarkWord;

  /// No description provided for @mushafClearMark.
  ///
  /// In ar, this message translates to:
  /// **'امسح العلامة'**
  String get mushafClearMark;

  /// No description provided for @mushafEarlier.
  ///
  /// In ar, this message translates to:
  /// **'عُلّمت في حصة سابقة'**
  String get mushafEarlier;

  /// No description provided for @mushafWordMarked.
  ///
  /// In ar, this message translates to:
  /// **'{word}: {kind}'**
  String mushafWordMarked(String word, String kind);

  /// No description provided for @mushafWordEarlier.
  ///
  /// In ar, this message translates to:
  /// **'{word}: {kind}، عُلّمت في حصص سابقة {times}'**
  String mushafWordEarlier(String word, String kind, String times);

  /// No description provided for @mushafVerseEnd.
  ///
  /// In ar, this message translates to:
  /// **'آخر الآية {n}'**
  String mushafVerseEnd(int n);

  /// No description provided for @mushafSurah.
  ///
  /// In ar, this message translates to:
  /// **'سورة {name}'**
  String mushafSurah(String name);

  /// No description provided for @mushafShow.
  ///
  /// In ar, this message translates to:
  /// **'المصحف'**
  String get mushafShow;

  /// No description provided for @mushafShowVideo.
  ///
  /// In ar, this message translates to:
  /// **'الفيديو'**
  String get mushafShowVideo;

  /// No description provided for @mushafBackToVideo.
  ///
  /// In ar, this message translates to:
  /// **'ارجع إلى الفيديو'**
  String get mushafBackToVideo;

  /// No description provided for @mushafFailed.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر الحفظ. حاول مرة أخرى.'**
  String get mushafFailed;

  /// No description provided for @mistakesTitle.
  ///
  /// In ar, this message translates to:
  /// **'دفتر الأخطاء'**
  String get mistakesTitle;

  /// No description provided for @mistakesTitleKids.
  ///
  /// In ar, this message translates to:
  /// **'كلمات نراجعها'**
  String get mistakesTitleKids;

  /// No description provided for @mistakesLead.
  ///
  /// In ar, this message translates to:
  /// **'الكلمات التي عُلّمت في الحصص خلال الأشهر الستة الأخيرة، لتراجعها.'**
  String get mistakesLead;

  /// No description provided for @mistakesLeadTeacher.
  ///
  /// In ar, this message translates to:
  /// **'الكلمات التي عُلّمت في حصص الطالب خلال الأشهر الستة الأخيرة.'**
  String get mistakesLeadTeacher;

  /// No description provided for @mistakesLeadKids.
  ///
  /// In ar, this message translates to:
  /// **'هذه كلمات سنقولها معًا مرة أخرى حتى تصير سهلة.'**
  String get mistakesLeadKids;

  /// No description provided for @mistakesEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد كلمات معلَّمة في الأشهر الستة الأخيرة.'**
  String get mistakesEmpty;

  /// No description provided for @mistakesEmptyKids.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد كلمات نراجعها الآن. أحسنت! ⭐'**
  String get mistakesEmptyKids;

  /// No description provided for @mistakesWords.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =0{لا توجد كلمات} =1{كلمة واحدة} =2{كلمتان} few{{n} كلمات} other{{n} كلمة}}'**
  String mistakesWords(int n);

  /// No description provided for @mistakesTimes.
  ///
  /// In ar, this message translates to:
  /// **'{n, plural, =1{مرة} =2{مرتين} few{{n} مرات} other{{n} مرة}}'**
  String mistakesTimes(int n);

  /// No description provided for @mistakesWord.
  ///
  /// In ar, this message translates to:
  /// **'{word}: {kinds}، {times}، آخر مرة {when}'**
  String mistakesWord(String word, String kinds, String times, String when);

  /// No description provided for @mistakesWordKids.
  ///
  /// In ar, this message translates to:
  /// **'{word}: كلمة نراجعها'**
  String mistakesWordKids(String word);
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
