// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'Academy';

  @override
  String get retry => 'Try again';

  @override
  String get offline => 'You\'re offline. Check your connection and try again.';

  @override
  String get problem => 'Something went wrong. Try again.';

  @override
  String get back => 'Back';

  @override
  String get close => 'Close';

  @override
  String get send => 'Send';

  @override
  String get optional => '(optional)';

  @override
  String get noticeSignedOut => 'Signed out.';

  @override
  String get noticeExpired => 'That account\'s sign-in ended. Please sign in again.';

  @override
  String minutes(int n) {
    return '$n min';
  }

  @override
  String get today => 'Today';

  @override
  String get tomorrow => 'Tomorrow';

  @override
  String inMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n minutes', one: '1 minute');
    return '$_temp0';
  }

  @override
  String inHours(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n hours', one: '1 hour');
    return '$_temp0';
  }

  @override
  String lessonsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n lessons', one: '1 lesson', zero: 'No lessons');
    return '$_temp0';
  }

  @override
  String get welcomeTitle => 'Welcome';

  @override
  String get welcomeLead =>
      'Enter your mobile number or email and we\'ll send you a code. Then you\'ll find your accounts in every academy you belong to.';

  @override
  String get byPhone => 'Mobile number';

  @override
  String get byEmail => 'Email';

  @override
  String get countryCode => 'Country code';

  @override
  String get phoneHint => 'The code comes on WhatsApp. No WhatsApp? You can ask for a text message instead.';

  @override
  String get emailHint => 'The code comes to this email.';

  @override
  String get sendCode => 'Send me a code';

  @override
  String get havePassword => 'Have a password? Sign in with it';

  @override
  String get signInOr => 'or';

  @override
  String get signInWithGoogle => 'Sign in with Google';

  @override
  String get signInWithApple => 'Sign in with Apple';

  @override
  String socialFailed(String provider) {
    return 'Signing in with $provider did not finish. Try again.';
  }

  @override
  String get providerGoogle => 'Google';

  @override
  String get providerApple => 'Apple';

  @override
  String get signInMethodsTitle => 'Sign in with Google and Apple';

  @override
  String get signInMethodsHint => 'Link your account to Google or Apple and sign in with them, without a code or a password.';

  @override
  String signInMethodLinkedAs(String email) {
    return 'Linked: $email';
  }

  @override
  String get signInMethodLinked => 'Linked';

  @override
  String get signInMethodNotLinked => 'Not linked';

  @override
  String get signInMethodLink => 'Link';

  @override
  String get signInMethodUnlink => 'Unlink';

  @override
  String signInMethodUnlinkConfirm(String provider) {
    return 'Unlink your $provider account? You will not be able to sign in with it until you link it again.';
  }

  @override
  String signInMethodLinkedDone(String provider) {
    return 'Your $provider account is linked.';
  }

  @override
  String signInMethodUnlinkedDone(String provider) {
    return 'Your $provider account is unlinked.';
  }

  @override
  String get codeTitle => 'Enter the code';

  @override
  String codeSentWhatsapp(String to) {
    return 'We sent a 6-digit code on WhatsApp to $to';
  }

  @override
  String codeSentSms(String to) {
    return 'We sent a 6-digit code by text to $to';
  }

  @override
  String codeSentEmail(String to) {
    return 'We sent a 6-digit code to $to';
  }

  @override
  String get codeLabel => 'Code';

  @override
  String get codeValid => 'The code is valid for 10 minutes.';

  @override
  String get noCode => 'Didn\'t get it?';

  @override
  String resendIn(String time) {
    return 'Send a new code in $time';
  }

  @override
  String get resend => 'Send a new code';

  @override
  String get sendBySms => 'Send it by text instead of WhatsApp';

  @override
  String get confirm => 'Confirm';

  @override
  String get otherContact => 'Use another number or email';

  @override
  String get accountsTitle => 'Your accounts in every academy';

  @override
  String accountsFound(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'We found $n accounts',
      one: 'We found one account',
      zero: 'No accounts found',
    );
    return '$_temp0';
  }

  @override
  String get accountsLead => 'Pick the ones you want on this phone. Then switch between them from \"Me\" without signing in again.';

  @override
  String get accountsLegal =>
      'Before adding your account, read the academy\'s privacy notice and terms of use: it decides what happens to your data there.';

  @override
  String get accountsNote =>
      'Only accounts with this same contact, in active academies. Missing one? The academy may have you under another number: ask them.';

  @override
  String addAccounts(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'Add $n accounts and sign in', one: 'Sign in');
    return '$_temp0';
  }

  @override
  String get noAccounts => 'No academy has an account with this contact. Check it, or ask your academy.';

  @override
  String get roleStudent => 'Student';

  @override
  String get roleGuardian => 'Guardian';

  @override
  String get roleTeacher => 'Teacher';

  @override
  String get roleStaff => 'Staff';

  @override
  String get passwordTitle => 'Sign in with a password';

  @override
  String get academyCode => 'Academy code';

  @override
  String get academyCodeHint => 'In your welcome message, or ask the academy.';

  @override
  String get loginLabel => 'Email, mobile or username';

  @override
  String get passwordLabel => 'Password';

  @override
  String get signIn => 'Sign in';

  @override
  String get useCodeInstead => 'Sign in with a code instead';

  @override
  String get twoFactorTitle => 'Two-step sign-in';

  @override
  String get twoFactorLead => 'Open your authenticator app and type the 6-digit code. Lost your phone? Type one of your recovery codes.';

  @override
  String twoFactorAccount(String name, String academy) {
    return '$name at $academy';
  }

  @override
  String get twoFactorCode => 'Code';

  @override
  String get startAgain => 'Start again';

  @override
  String get webOnlyTitle => 'This is a staff account';

  @override
  String get webOnlyBody => 'Staff accounts work on the website. Open the academy\'s back office on a computer.';

  @override
  String get privacyTitle => 'Children\'s privacy';

  @override
  String privacyLead(int age) {
    return 'Children under $age need your consent to sign in and join on their own.';
  }

  @override
  String get privacyNone => 'No child needs your consent.';

  @override
  String get consentWaiting => 'Waiting for your consent';

  @override
  String get consentGranted => 'Agreed';

  @override
  String get consentAgree => 'I agree';

  @override
  String consentAgreed(String name) {
    return 'Done: $name can sign in now.';
  }

  @override
  String get consentWithdraw => 'Withdraw consent';

  @override
  String consentWithdrawConfirm(String name) {
    return '$name will be signed out on every device and cannot sign in until you agree again. Are you sure?';
  }

  @override
  String consentWithdrawn(String name) {
    return 'You withdrew your consent, and $name was signed out.';
  }

  @override
  String consentNudge(int n, String names) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$names are waiting for your consent to sign in.',
      one: '$names is waiting for your consent to sign in.',
    );
    return '$_temp0';
  }

  @override
  String get consentNudgeAction => 'Read and agree';

  @override
  String get myDataTitle => 'My data';

  @override
  String get myDataLead =>
      'Everything the academy keeps about you and your children: correct your details, download a copy, or ask for an account to be erased.';

  @override
  String get myDataLeadTeacher =>
      'Everything the academy keeps about you: correct your name, download a copy, or ask for your account to be erased.';

  @override
  String get myDataYoung => 'Your guardian asks about your data from their account.';

  @override
  String get myDataCopyTitle => 'A copy of the data';

  @override
  String myDataCopyBody(int days) {
    return 'One ZIP file: the data in a readable file, with the files handed in. Ready in a few minutes and kept $days days.';
  }

  @override
  String get myDataCopyAction => 'Get a copy';

  @override
  String get myDataCopyPreparing => 'Being prepared: you will hear when it is ready.';

  @override
  String get myDataCopyDownload => 'Download';

  @override
  String myDataCopyUntil(String when) {
    return 'Your copy is ready until $when';
  }

  @override
  String get myDataEraseTitle => 'Delete the account';

  @override
  String myDataEraseBody(int days) {
    return 'The academy answers within $days days; once it approves, the account is erased a day later.';
  }

  @override
  String get myDataEraseAction => 'Delete this account';

  @override
  String myDataEraseConfirm(String name) {
    return 'Ask to erase $name\'s account?';
  }

  @override
  String get myDataEraseCopyFirst => 'You can download a copy first.';

  @override
  String get myDataEraseReason => 'Why (if you like)';

  @override
  String myDataEraseWaiting(String when) {
    return 'Asked: the academy answers by $when.';
  }

  @override
  String myDataEraseApproved(String when) {
    return 'Approved: the account is erased $when.';
  }

  @override
  String myDataEraseRefused(String answer) {
    return 'The academy refused: $answer';
  }

  @override
  String get myDataEraseCancel => 'Take it back';

  @override
  String get myDataEraseAsked => 'Sent to the academy.';

  @override
  String get myDataEraseCancelled => 'Taken back: the account stays.';

  @override
  String get myDataWhatGoes => 'What goes and what stays';

  @override
  String get myDataWhatStays => 'What stays, without the name';

  @override
  String get myDataHistory => 'Requests';

  @override
  String get myDataNone => 'No requests yet.';

  @override
  String myDataAbout(String name) {
    return 'About $name';
  }

  @override
  String get myDataFixTitle => 'Correct the data';

  @override
  String get myDataFixEdit => 'Correct';

  @override
  String myDataFixEditTitle(String name) {
    return 'Correct $name\'s details';
  }

  @override
  String get myDataFixSave => 'Save';

  @override
  String get myDataFixSaved => 'Saved.';

  @override
  String get myDataFixUnchanged => 'Nothing changed.';

  @override
  String get myDataFixUnset => 'Not set';

  @override
  String get myDataFixContact => 'The email and mobile number change on Me.';

  @override
  String myDataFixBody(int days) {
    return 'Something else wrong (a birth date, a name on an invoice, words in a report)? Write it to the academy; it answers within $days days.';
  }

  @override
  String get myDataFixAsk => 'Ask for a correction';

  @override
  String myDataFixAskTitle(String name) {
    return 'Ask to correct $name\'s data';
  }

  @override
  String myDataFixAskBody(int days) {
    return 'Write what is wrong and what is right. The academy answers within $days days, and you get its answer.';
  }

  @override
  String get myDataFixSays => 'What needs correcting';

  @override
  String get myDataFixPlaceholder => 'For example: the birth year on file is 2016; it is 2017.';

  @override
  String myDataFixTooShort(int min) {
    return 'Write at least $min characters.';
  }

  @override
  String get myDataFixSend => 'Send the request';

  @override
  String get myDataFixAsked => 'The request reached the academy.';

  @override
  String myDataFixWaiting(String when) {
    return 'Asked: the academy answers by $when.';
  }

  @override
  String get myDataFixDone => 'Corrected';

  @override
  String myDataFixDoneWith(String answer) {
    return 'Corrected: $answer';
  }

  @override
  String myDataFixRefused(String answer) {
    return 'The academy did not correct it: $answer';
  }

  @override
  String get myDataFixCancel => 'Cancel the request';

  @override
  String get myDataFixCancelled => 'The request was cancelled.';

  @override
  String get myDataPrivacy => 'Privacy notice';

  @override
  String get myDataTerms => 'Terms of use';

  @override
  String get myDataDeletion => 'Delete your account';

  @override
  String get navHome => 'Home';

  @override
  String get navLessons => 'Lessons';

  @override
  String get navJourneyTab => 'Journey';

  @override
  String get navMoney => 'Payments';

  @override
  String get navMe => 'Me';

  @override
  String get navJourney => 'Journey';

  @override
  String get navMyLessons => 'Lessons';

  @override
  String get navMyHomework => 'Homework';

  @override
  String get navHomework => 'Homework';

  @override
  String get navReports => 'Reports';

  @override
  String get navToday => 'Today';

  @override
  String get navWeek => 'Week';

  @override
  String get navStudents => 'Students';

  @override
  String get navEarnings => 'Earnings';

  @override
  String get navMore => 'More';

  @override
  String greetingMorning(String name) {
    return 'Good morning, $name';
  }

  @override
  String greetingEvening(String name) {
    return 'Good evening, $name';
  }

  @override
  String get nextLesson => 'Next lesson';

  @override
  String get noNextLesson => 'No lessons in the next four weeks.';

  @override
  String roomOpensIn(String time) {
    return 'The room opens in $time';
  }

  @override
  String get roomOpen => 'The room is open';

  @override
  String get lessonOn => 'The lesson is on now';

  @override
  String lessonWith(String subject, String teacher) {
    return '$subject with $teacher';
  }

  @override
  String get joinLesson => 'Join the lesson';

  @override
  String joinOpensAt(String time) {
    return 'Opens $time';
  }

  @override
  String get allLessons => 'All lessons';

  @override
  String get balance => 'Balance';

  @override
  String get owed => 'You owe';

  @override
  String get payNow => 'Pay now';

  @override
  String get nothingOwed => 'Nothing to pay';

  @override
  String get lastReport => 'Latest report';

  @override
  String get readReport => 'Read it';

  @override
  String homeworkLine(String title) {
    return 'Homework: $title';
  }

  @override
  String get unfunded => 'Awaiting payment';

  @override
  String get notices => 'Notices';

  @override
  String lessonsOf(String name) {
    return '$name\'s lessons';
  }

  @override
  String onClock(String zone) {
    return '$zone time';
  }

  @override
  String get upcoming => 'Upcoming';

  @override
  String get past => 'Past';

  @override
  String get noUpcoming => 'No lessons in the next four weeks.';

  @override
  String get noPast => 'No lessons in the last four weeks.';

  @override
  String substituteFor(String name) {
    return 'Substitute: $name is on leave';
  }

  @override
  String get refuseSubstitute => 'We don\'t want the substitute';

  @override
  String get refuseSubstituteTitle => 'Turn down the substitute?';

  @override
  String get refuseSubstituteBody =>
      'The lesson is cancelled on the academy\'s account and its minutes come back as a makeup lesson to book later.';

  @override
  String get refuseSubstituteYes => 'Yes, cancel it for a makeup';

  @override
  String refusedSubstitute(int days) {
    return 'Cancelled. You have $days days to book the makeup.';
  }

  @override
  String get cancelLesson => 'Cancel the lesson';

  @override
  String get disputeLesson => 'Dispute';

  @override
  String get disputeTitle => 'Dispute this lesson\'s outcome';

  @override
  String disputeIntroFamily(String outcome) {
    return 'The lesson is recorded as \"$outcome\". If that is not what happened, tell us within 48 hours of its end; the academy checks the attendance and answers you.';
  }

  @override
  String disputeIntroTeacher(String outcome) {
    return 'The lesson is recorded as \"$outcome\". If that is not what happened, tell us within 48 hours of its end; the academy checks the attendance and answers you.';
  }

  @override
  String get disputeWhat => 'What happened?';

  @override
  String get disputeNote => 'Details';

  @override
  String get disputeNoteHint => 'Only the academy reads this.';

  @override
  String get disputeFillIn => 'Pick a reason and say what happened.';

  @override
  String get disputeSend => 'Send the dispute';

  @override
  String get disputeSent => 'Your dispute reached the academy; the lesson is under review.';

  @override
  String disputeState(String status) {
    return 'Dispute: $status';
  }

  @override
  String disputeAnswer(String answer) {
    return 'The academy\'s answer: $answer';
  }

  @override
  String cancelTitle(String day) {
    return 'Cancel the lesson on $day?';
  }

  @override
  String get reason => 'Reason';

  @override
  String get noteForAcademy => 'Note for the academy';

  @override
  String get keepIt => 'No, keep it';

  @override
  String get yesCancel => 'Yes, cancel it';

  @override
  String get cancelled => 'The lesson is cancelled.';

  @override
  String get pickReason => 'Pick a reason first.';

  @override
  String get rateLesson => 'Rate the lesson';

  @override
  String get rateTitle => 'How was the lesson?';

  @override
  String get rateGood => 'What went well?';

  @override
  String get rateBad => 'What could be better?';

  @override
  String get rateComment => 'A word for the academy';

  @override
  String get rateAnonymous => 'The teacher sees the rating without your name.';

  @override
  String get sendRating => 'Send the rating';

  @override
  String get notNow => 'Not now';

  @override
  String get rated => 'Thanks! Your rating is in.';

  @override
  String starsOf(int n) {
    return '$n of 5';
  }

  @override
  String get yourRating => 'Your rating';

  @override
  String percentDone(int p) {
    return '$p% of the program';
  }

  @override
  String hereNow(String title) {
    return 'Now: $title';
  }

  @override
  String weeksLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'About $n weeks left',
      one: 'About a week left',
      zero: 'Almost done',
    );
    return '$_temp0';
  }

  @override
  String homeworkOpen(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n open homework', one: '1 open homework');
    return '$_temp0';
  }

  @override
  String get reports => 'Reports';

  @override
  String get noReports => 'No reports yet.';

  @override
  String get noCourses => 'No active courses.';

  @override
  String get noProgram => 'No curriculum';

  @override
  String reportOf(String day) {
    return 'Report of $day';
  }

  @override
  String get engagement => 'Focus';

  @override
  String get overall => 'Overall';

  @override
  String get homeworkTitle => 'Homework';

  @override
  String get noHomework => 'No homework right now.';

  @override
  String dueBy(String when) {
    return 'Due $when';
  }

  @override
  String get handIn => 'Hand in';

  @override
  String get yourAnswer => 'Your answer';

  @override
  String get handedIn => 'Handed in. The teacher will mark it.';

  @override
  String get teacherFeedback => 'Teacher\'s feedback';

  @override
  String grade(int g) {
    return 'Grade: $g of 5';
  }

  @override
  String get yourSubmission => 'What you handed in';

  @override
  String get kidsHomeworkStar => 'Every homework you hand in brings a new star!';

  @override
  String get owedNow => 'Due now';

  @override
  String invoiceDue(String number, String when) {
    return 'Invoice $number · due $when';
  }

  @override
  String payAmount(String amount) {
    return 'Pay $amount';
  }

  @override
  String get payNote => 'You pay on the academy\'s payment page. Come back here when you\'re done.';

  @override
  String get lessonsLeftTitle => 'Lessons left';

  @override
  String get invoices => 'Invoices';

  @override
  String get noInvoices => 'No invoices.';

  @override
  String get openInvoice => 'Open the invoice';

  @override
  String get extraAction => 'Extra lessons';

  @override
  String extraHint(String price, int minutes) {
    return 'An extra lesson is $price ($minutes min).';
  }

  @override
  String extraTitle(String subject) {
    return 'Extra lessons in $subject';
  }

  @override
  String get extraBody =>
      'Choose how many; you get an invoice to pay like any other. Once it is paid, the academy books the lessons with you.';

  @override
  String extraEach(int n, String price) {
    return '$n × $price';
  }

  @override
  String get extraSubmit => 'Make the invoice';

  @override
  String extraDone(String number, String total) {
    return 'Invoice $number is ready ($total).';
  }

  @override
  String extraWaiting(String number) {
    return 'Invoice $number for extra lessons is waiting to be paid.';
  }

  @override
  String get invoiceNote => 'Open any invoice to see and print it, with its tax invoice QR code.';

  @override
  String get cantPayOnline => 'Online payment isn\'t available for this invoice. Contact the academy.';

  @override
  String get pickGateway => 'Pay with?';

  @override
  String get paidByTransfer => 'I paid by transfer';

  @override
  String get proofTitle => 'Send proof of payment';

  @override
  String get proofHint => 'Pay first, then send the receipt here. The academy checks the money arrived and confirms it.';

  @override
  String get proofHowTo => 'How to pay by hand';

  @override
  String get proofMethod => 'How you paid';

  @override
  String get proofAmount => 'Amount you paid';

  @override
  String get proofPaidOn => 'Day you paid';

  @override
  String get proofReference => 'Transfer number (optional)';

  @override
  String get proofNote => 'Note (optional)';

  @override
  String get proofReceipt => 'Receipt';

  @override
  String get proofPickReceipt => 'Choose a photo or PDF';

  @override
  String get proofReceiptHint => 'A photo, a screenshot or the bank\'s PDF, up to 10 MB.';

  @override
  String get proofSend => 'Send proof';

  @override
  String get proofSent => 'Proof sent. You\'ll hear once the academy confirms it.';

  @override
  String get proofNeedsReceipt => 'Choose the receipt first.';

  @override
  String get proofFileTooBig => 'The file is larger than 10 MB.';

  @override
  String get transferTitle => 'Where to transfer';

  @override
  String get transferCopy => 'Copy';

  @override
  String get transferCopied => 'Transfer details copied.';

  @override
  String get joining => 'Joining…';

  @override
  String get linkNotOpened => 'No app on this phone can open this link.';

  @override
  String get openedOutside => 'The lesson opened in another app.';

  @override
  String get leave => 'Leave the lesson';

  @override
  String get micOff => 'Mute';

  @override
  String get micOn => 'Unmute';

  @override
  String get camOff => 'Camera off';

  @override
  String get camOn => 'Camera on';

  @override
  String get audioOnly => 'Audio only';

  @override
  String get rejoinHint => 'Left by mistake? Join again from the same button until the lesson ends.';

  @override
  String get weakNetwork => 'Weak connection. Try \"Audio only\" if the video stutters.';

  @override
  String get waitingOther => 'Waiting for the other side to join…';

  @override
  String get you => 'You';

  @override
  String minutesLeft(int n) {
    return '$n min left';
  }

  @override
  String sharedPackage(String subjects) {
    return '$subjects · shared package';
  }

  @override
  String get familyInvoice => 'family invoice';

  @override
  String coursePaused(String day) {
    return 'Paused until $day';
  }

  @override
  String coursePausedKids(String day) {
    return 'On a break until $day';
  }

  @override
  String coursePauseAhead(String from, String until) {
    return 'Paused from $from until $until';
  }

  @override
  String courseEnding(String day) {
    return 'Ends from $day';
  }

  @override
  String get courseEnded => 'Ended: the minutes left stay in the balance';

  @override
  String lessonUntil(String time) {
    return 'Until $time';
  }

  @override
  String get meTitle => 'Me';

  @override
  String get contactTitle => 'Email and mobile';

  @override
  String get contactHint =>
      'These are your ways in, and where codes and messages reach you. When you change one, we send a code to the new one to make sure it is yours.';

  @override
  String get contactEmail => 'Email';

  @override
  String get contactPhone => 'Mobile number';

  @override
  String get contactNone => 'Not set';

  @override
  String get contactChangeEmail => 'Change email';

  @override
  String get contactChangePhone => 'Change number';

  @override
  String get contactAddEmail => 'Add an email';

  @override
  String get contactAddPhone => 'Add a number';

  @override
  String get contactNewEmail => 'New email';

  @override
  String get contactNewPhone => 'New number';

  @override
  String get contactPhoneHint => 'With the country code, or a local number from your own country.';

  @override
  String get contactSend => 'Send a code';

  @override
  String get contactCode => 'The code you received';

  @override
  String contactCodeHint(String contact, String until) {
    return 'We sent a code to $contact. It works until $until.';
  }

  @override
  String get contactConfirm => 'Confirm';

  @override
  String get contactResend => 'Send another code';

  @override
  String get contactDoneEmail => 'Your email changed. From now on you sign in and get messages there.';

  @override
  String get contactDonePhone => 'Your number changed. From now on you sign in and get messages there.';

  @override
  String get contactManaged => 'The academy or your guardian changes your email and number.';

  @override
  String get openNow => 'Open';

  @override
  String get switchTo => 'Switch';

  @override
  String get addAccount => 'Add another account';

  @override
  String clockPrompt(String device, String profile) {
    return 'Your phone is on $device time and your account on $profile. Show times on $device time?';
  }

  @override
  String clockYes(String zone) {
    return 'Yes, $zone';
  }

  @override
  String clockKeep(String zone) {
    return 'Keep $zone';
  }

  @override
  String get language => 'Language';

  @override
  String get timezone => 'Time zone';

  @override
  String get myDevices => 'My devices';

  @override
  String get thisDevice => 'This device';

  @override
  String lastUsed(String when) {
    return 'Last used $when';
  }

  @override
  String get signOutDevice => 'Sign out';

  @override
  String get deviceSignedOut => 'Device signed out.';

  @override
  String get signOut => 'Sign out of this account';

  @override
  String signOutConfirm(String academy) {
    return 'Sign out of $academy on this phone?';
  }

  @override
  String todayHeader(String date, String zone) {
    return '$date · $zone time';
  }

  @override
  String todayCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'Today: $n lessons',
      one: 'Today: 1 lesson',
      zero: 'No lessons today',
    );
    return '$_temp0';
  }

  @override
  String hours(String h) {
    return '$h hours';
  }

  @override
  String nextIn(String time) {
    return 'Next in $time';
  }

  @override
  String reportsWaiting(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n reports to write', one: '1 report to write');
    return '$_temp0';
  }

  @override
  String homeworkToMark(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n homework to mark', one: '1 homework to mark');
    return '$_temp0';
  }

  @override
  String reportDueBy(String when) {
    return 'Before $when';
  }

  @override
  String get prevWeek => 'Previous week';

  @override
  String get nextWeek => 'Next week';

  @override
  String get noLessonsDay => 'No lessons';

  @override
  String get noLessonsWeek => 'No lessons this week.';

  @override
  String get studentsTitle => 'My students';

  @override
  String get paused => 'Paused';

  @override
  String toMark(int n) {
    return '$n to mark';
  }

  @override
  String lastReportAt(String when) {
    return 'Last report: $when';
  }

  @override
  String get noStudents => 'No students yet.';

  @override
  String get prevMonth => 'Previous month';

  @override
  String get nextMonth => 'Next month';

  @override
  String get soFar => 'So far';

  @override
  String get monthOpen => 'Month still open';

  @override
  String get monthClosed => 'Month closed';

  @override
  String lessonsHours(String lessons, String hours) {
    return '$lessons · $hours';
  }

  @override
  String get lessonPay => 'Lessons';

  @override
  String get bonuses => 'Bonuses';

  @override
  String get deductions => 'Deductions';

  @override
  String unpricedNote(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n lessons have no price yet', one: '1 lesson has no price yet');
    return '$_temp0. The academy will set it and they count in this month.';
  }

  @override
  String get noEarnings => 'No lessons this month.';

  @override
  String get adjustments => 'Adjustments';

  @override
  String get corrected => 'Corrected';

  @override
  String get meetingLinkTitle => 'Your lesson link';

  @override
  String get meetingLinkNeeded => 'The academy runs lessons on outside links, and no lesson can open without yours.';

  @override
  String get meetingLinkSave => 'Save the link';

  @override
  String get meetingLinkSaved => 'Link saved.';

  @override
  String get meetingLinkRemoved => 'Link removed.';

  @override
  String get timeOff => 'Time off';

  @override
  String get requestTimeOff => 'Request time off';

  @override
  String timeOffLead(String zone) {
    return 'On your clock: $zone · the academy decides';
  }

  @override
  String get wholeDays => 'Whole days';

  @override
  String get partOfDay => 'Part of a day';

  @override
  String get fromDay => 'From';

  @override
  String get toDay => 'To';

  @override
  String get dayLabel => 'Day';

  @override
  String get fromTime => 'From';

  @override
  String get toTime => 'Until';

  @override
  String get reasonSick => 'Illness';

  @override
  String get reasonVacation => 'Vacation';

  @override
  String get reasonPersonal => 'Personal';

  @override
  String get reasonFamily => 'Family';

  @override
  String get reasonEmergency => 'Emergency';

  @override
  String get reasonOther => 'Other';

  @override
  String get sendRequest => 'Send the request';

  @override
  String get yourRequests => 'Your requests';

  @override
  String get withdraw => 'Withdraw';

  @override
  String get requestSent => 'Request sent. You\'ll hear back.';

  @override
  String get withdrawn => 'Request withdrawn.';

  @override
  String get academyHolidays => 'Academy holidays';

  @override
  String coverState(int covered, int total) {
    return '$covered of $total lessons covered';
  }

  @override
  String get fillTimeOff => 'Pick the days and a reason first.';

  @override
  String get noTimeOff => 'No requests.';

  @override
  String get youAreHere => 'You\'re here!';

  @override
  String get topRank => 'You reached the top rank!';

  @override
  String streak(int n) {
    return '$n in a row';
  }

  @override
  String badgesCount(int earned, int total) {
    return '$earned of $total';
  }

  @override
  String get finishOn => 'At this pace you finish';

  @override
  String get programDone => 'You finished the program!';

  @override
  String weeksLeftLevel(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n weeks left in this level',
      one: 'A week left in this level',
      zero: 'This level is almost done',
    );
    return '$_temp0';
  }

  @override
  String get fromLastMonth => 'From last month';

  @override
  String get doneMark => 'Done!';

  @override
  String get chestReady => 'The chest is ready! Tap it';

  @override
  String get chestOpened => 'You opened this week\'s chest';

  @override
  String get album => 'Sticker album';

  @override
  String get albumLead => 'Every chest you open brings a new sticker.';

  @override
  String get newSticker => 'A new sticker for your album!';

  @override
  String get rewardKids => 'Bravo!';

  @override
  String get rewardTitle => 'Well done!';

  @override
  String rankUp(String rank) {
    return 'New rank: $rank';
  }

  @override
  String get awesome => 'Awesome!';

  @override
  String get cheerFromFamily => 'A cheer from your family!';

  @override
  String get cheerThanks => 'Thanks!';

  @override
  String get cheerClap => 'Clap';

  @override
  String get cheerStar => 'Star';

  @override
  String get cheerHeart => 'Heart';

  @override
  String get cheerFire => 'Fire';

  @override
  String cheerOn(String name) {
    return 'Cheer $name on';
  }

  @override
  String cheerSent(String name) {
    return 'Your cheer reached $name!';
  }

  @override
  String cheersLeft(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n cheers left today',
      one: '1 cheer left today',
      zero: 'No cheers left today',
    );
    return '$_temp0';
  }

  @override
  String get sounds => 'Sounds';

  @override
  String get soundsHint => 'Little sounds on taps and rewards';

  @override
  String get reportsOpen => 'Waiting for you';

  @override
  String get reportsSent => 'Sent';

  @override
  String get noReportsWaiting => 'No reports waiting. Well done!';

  @override
  String get reportLesson => 'Lesson report';

  @override
  String get reportAbsence => 'Absence note';

  @override
  String get todaysPlan => 'Today\'s plan';

  @override
  String get planHint => 'Pick what happened with each lesson.';

  @override
  String get reasonNext => 'Next lesson';

  @override
  String get reasonReview => 'Review';

  @override
  String get outcomeIntroduced => 'Introduced';

  @override
  String get outcomePracticed => 'Practised';

  @override
  String get outcomeMastered => 'Mastered';

  @override
  String get outcomeNeedsReview => 'Needs review';

  @override
  String get itemScore => 'Score';

  @override
  String get itemNote => 'Note on this lesson';

  @override
  String get addNote => 'Add a note';

  @override
  String get addLesson => 'Add a lesson';

  @override
  String get pickLesson => 'Pick a lesson';

  @override
  String get searchLessons => 'Search lessons';

  @override
  String get remove => 'Remove';

  @override
  String get notesForFamily => 'Notes for the student and family';

  @override
  String get notesInternal => 'Internal notes';

  @override
  String get notesInternalHint => 'For the academy only; the family never sees them.';

  @override
  String get questionsTitle => 'The academy\'s questions';

  @override
  String get questionsHint => 'Questions your academy asks in every report of this subject.';

  @override
  String answerRequired(String question) {
    return 'Answer \"$question\".';
  }

  @override
  String get answerYes => 'Yes';

  @override
  String get answerNo => 'No';

  @override
  String get academyOnly => 'Academy only';

  @override
  String get lessonMaterials => 'Lesson materials';

  @override
  String get teachersOnly => 'Teachers only';

  @override
  String get recordingsTitle => 'Lesson recordings';

  @override
  String get recordingsMine => 'My lesson recordings';

  @override
  String get recordingsOff => 'The academy does not record lessons now.';

  @override
  String recordingsOf(String name) {
    return '$name\'s lesson recordings';
  }

  @override
  String get recordingsTeacherList => 'Your lessons\' recordings';

  @override
  String get recordingsEmpty => 'No recordings yet.';

  @override
  String get recordingsNotShared => 'The academy does not show recordings here.';

  @override
  String get recordingWatch => 'Watch';

  @override
  String get recordingAgreed => 'Agreed';

  @override
  String get recordingNotAgreed => 'Not agreed';

  @override
  String get recordingAgree => 'Agree';

  @override
  String get recordingAgreedDone => 'Thank you. Lessons may be recorded from now on.';

  @override
  String get recordingWithdraw => 'Withdraw consent';

  @override
  String get recordingWithdrawConfirm => 'Are you sure? Any recording running stops, and nothing more is recorded.';

  @override
  String get recordingWithdrawn => 'Consent withdrawn; nothing more will be recorded.';

  @override
  String recordingUntil(String date) {
    return 'available until $date';
  }

  @override
  String get recordingVideo => 'Video';

  @override
  String get recordingAudio => 'Sound';

  @override
  String get recordingLogged => 'Opening a recording is logged under your name.';

  @override
  String get recordingOn => 'Recording';

  @override
  String get recordStart => 'Record the lesson';

  @override
  String get recordStop => 'Stop recording';

  @override
  String recordingMissing(String names) {
    return 'It cannot be recorded until they agree to recording: $names.';
  }

  @override
  String get absenceNote => 'Absence note';

  @override
  String get absenceHint => 'The student didn\'t come. Say what happened and anything the family should know.';

  @override
  String get giveHomework => 'Give homework';

  @override
  String get homeworkField => 'Homework';

  @override
  String get homeworkDetails => 'Details';

  @override
  String get acceptsLabel => 'Handed in as';

  @override
  String get acceptsAny => 'Anything';

  @override
  String get acceptsText => 'Text';

  @override
  String get acceptsAudio => 'Voice recording';

  @override
  String get acceptsFile => 'File';

  @override
  String dueIn(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'In $n days', one: 'In 1 day');
    return '$_temp0';
  }

  @override
  String get homeworkLocked => 'The student handed this homework in, so it stays as it is.';

  @override
  String get sendReport => 'Send the report';

  @override
  String get updateReport => 'Update the report';

  @override
  String get reportSent => 'Report sent.';

  @override
  String get reportLocked => 'The academy reviewed this report, so it stays as it is.';

  @override
  String get pickRatings => 'Pick focus and overall.';

  @override
  String get pickOutcomes => 'Pick what happened with each lesson.';

  @override
  String get markTitle => 'Marking';

  @override
  String get markWaiting => 'To mark';

  @override
  String get markDone => 'Marked';

  @override
  String get noHomeworkToMark => 'No homework to mark.';

  @override
  String get gradeLabel => 'Grade';

  @override
  String get feedbackLabel => 'Your words for the student';

  @override
  String get saveMark => 'Save the mark';

  @override
  String get marked => 'Marked.';

  @override
  String handedInAt(String when) {
    return 'Handed in $when';
  }

  @override
  String get fileOnWeb => 'Open this file on the website.';

  @override
  String get showPicture => 'Show the picture';

  @override
  String get playRecording => 'Play the recording';

  @override
  String get pause => 'Pause';

  @override
  String get cannotPlay => 'This recording can\'t play here.';

  @override
  String get waitingTap => 'Tap to start';

  @override
  String get recordVoice => 'Record your voice';

  @override
  String get recordKids => 'Record yourself';

  @override
  String recordingNow(String time) {
    return 'Recording… $time';
  }

  @override
  String get stopRecording => 'Stop';

  @override
  String get recordAgain => 'Record again';

  @override
  String get attachFile => 'Attach a file';

  @override
  String get attachPicture => 'Attach a picture or PDF';

  @override
  String get micDenied => 'We need the microphone to record. Allow it in your phone settings.';

  @override
  String recordingLimit(int n) {
    return 'Recording stopped at $n minutes.';
  }

  @override
  String get fileTooBig => 'The file is larger than 25 MB.';

  @override
  String get answerOptional => 'Add a few words (optional)';

  @override
  String get myHours => 'My hours';

  @override
  String hoursOnClock(String zone) {
    return 'On $zone time';
  }

  @override
  String weeklyHours(String h) {
    return '$h a week';
  }

  @override
  String get currentWeek => 'Approved hours';

  @override
  String get noHoursYet => 'No approved hours yet.';

  @override
  String get proposeHours => 'Propose new hours';

  @override
  String get yourProposal => 'Your proposal';

  @override
  String get waitingOnAcademy => 'Waiting for the academy';

  @override
  String get waitingOnYou => 'Waiting for you';

  @override
  String startsOn(String date) {
    return 'Starts $date';
  }

  @override
  String get academyCounter => 'The academy changed your proposal. This is what changed:';

  @override
  String get timeAdded => 'Added';

  @override
  String get timeRemoved => 'Removed';

  @override
  String get acceptCounter => 'Accept the change';

  @override
  String get editProposal => 'Edit the proposal';

  @override
  String get withdrawConfirm => 'Withdraw the proposal? Your approved hours stay as they are.';

  @override
  String get proposalAccepted => 'Your hours are approved.';

  @override
  String get proposalSent => 'Proposal sent to the academy.';

  @override
  String get draftSaved => 'Draft saved.';

  @override
  String get conversation => 'Conversation with the academy';

  @override
  String get writeMessage => 'Write a message…';

  @override
  String get editHours => 'Edit your hours';

  @override
  String get addTime => 'Add time';

  @override
  String get dayOff => 'Off';

  @override
  String get effectiveFrom => 'Starts on';

  @override
  String reduceNotice(String date) {
    return 'If you cut hours, the earliest start is $date.';
  }

  @override
  String get minHours => 'Fewest hours a week';

  @override
  String get maxHours => 'Most hours a week';

  @override
  String get proposalNote => 'Note for the academy';

  @override
  String get saveDraft => 'Save a draft';

  @override
  String get sendProposal => 'Send to the academy';

  @override
  String get endAfterStart => 'The end must be after the start.';

  @override
  String get untilMidnight => 'Until midnight';

  @override
  String get inboxTitle => 'Notifications';

  @override
  String get markAllRead => 'Mark all read';

  @override
  String get noNotifications => 'No notifications.';

  @override
  String get loadMore => 'Show more';

  @override
  String get notificationChoices => 'Notification choices';

  @override
  String get saved => 'Saved.';

  @override
  String get openIt => 'Open';

  @override
  String unreadCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n new notifications',
      one: '1 new notification',
      zero: 'Nothing new',
    );
    return '$_temp0';
  }

  @override
  String get updateTitle => 'A new version of the app is out';

  @override
  String get updateBody => 'Your version is too old to work with the academy. Update it to carry on.';

  @override
  String get updateNow => 'Update the app';

  @override
  String get updateFromStore => 'Update it from your phone\'s app store.';

  @override
  String get helpTitle => 'Help';

  @override
  String get supportTitle => 'Support';

  @override
  String get helpLead =>
      'Write to the academy about a lesson, a payment, a technical problem or anything else. The answers come here and in a message.';

  @override
  String get supportLead =>
      'Write to the academy about a technical problem, your pay or anything else. The answers come here and in a message.';

  @override
  String get ticketNew => 'New ticket';

  @override
  String get ticketNewTitle => 'Write to the academy';

  @override
  String get ticketKind => 'What is it about?';

  @override
  String get ticketHintComplaint => 'About a teacher or a lesson. The academy looks into it and tells you what it did.';

  @override
  String get ticketHintBilling => 'An invoice, a payment, a refund or your package.';

  @override
  String get ticketHintPay => 'Your pay or your statement.';

  @override
  String get ticketHintTechnical => 'The class, the app or signing in not working.';

  @override
  String get ticketHintOther => 'A question or a request.';

  @override
  String get ticketLesson => 'Which lesson? (if any)';

  @override
  String get ticketNoLesson => 'No particular lesson';

  @override
  String get ticketSubject => 'Subject';

  @override
  String get ticketSubjectHint => 'In a few words';

  @override
  String get ticketBody => 'Tell us what happened';

  @override
  String get ticketAttach => 'Attach a picture or PDF';

  @override
  String get ticketTooBig => 'The file is bigger than 10 MB.';

  @override
  String get ticketSent => 'Sent. The academy answers here.';

  @override
  String get ticketsEmpty => 'No tickets yet. If anything is wrong, write to us.';

  @override
  String get ticketsMine => 'My tickets';

  @override
  String ticketAbout(String name) {
    return 'About $name';
  }

  @override
  String get ticketYourTurn => 'The academy answered';

  @override
  String get ticketYou => 'You';

  @override
  String get ticketAcademy => 'The academy';

  @override
  String get ticketReplyHint => 'Write to the academy…';

  @override
  String get ticketReopen => 'It is not solved';

  @override
  String ticketReopenHint(String date) {
    return 'Not solved? You can reopen it until $date.';
  }

  @override
  String get ticketClose => 'Close the ticket';

  @override
  String get ticketCloseHint => 'Close it when it is solved or you no longer need it. It takes no more messages.';

  @override
  String get ticketClosed => 'This ticket is closed. Open a new one if you need anything.';

  @override
  String get tellUsTitle => 'Report a concern';

  @override
  String get tellUsTitleStudent => 'Something wrong? Tell us';

  @override
  String get tellUsTitleKids => 'Is something wrong?';

  @override
  String get tellUsNavKids => 'Tell a grown-up';

  @override
  String get tellUsNavMiddle => 'Tell us';

  @override
  String get tellUsLead =>
      'If anything about a lesson, a teacher or anyone on the platform worries you, tell us here. Only the people who keep children safe at the academy read it.';

  @override
  String get tellUsLeadStudent =>
      'If someone made you feel uncomfortable, scared or unsafe, tell us. Only the people who keep students safe at the academy read it, and you will not get in trouble.';

  @override
  String get tellUsLeadKids => 'If someone made you sad or scared, press a button. A grown-up at the academy will help.';

  @override
  String get concernTeacherNav => 'Raise a concern';

  @override
  String get concernTeacherTitle => 'Raise a safeguarding concern';

  @override
  String get concernTeacherLead =>
      'If something about a student\'s safety or wellbeing worries you, or another adult\'s behaviour, tell the academy\'s safeguarding leads here. Only they read it. Do not look into it or contact the family about it yourself.';

  @override
  String concernAbout(String name) {
    return 'About $name';
  }

  @override
  String get concernWhat => 'What is it about?';

  @override
  String get concernWhatKids => 'What happened?';

  @override
  String get concernPick => 'Choose what it is about.';

  @override
  String get concernText => 'What happened?';

  @override
  String get concernTextKids => 'Do you want to say more? You don\'t have to.';

  @override
  String get concernTextHint => 'When it happened, who was there, what was said.';

  @override
  String get concernLesson => 'In which lesson (if any)?';

  @override
  String get concernNoLesson => 'Not about a lesson';

  @override
  String get concernStudent => 'Which student?';

  @override
  String get concernNoStudent => 'Not about one student';

  @override
  String get concernUrgent => 'Someone may be in danger right now';

  @override
  String get concernSend => 'Send';

  @override
  String get concernSendTeacher => 'Send to the safeguarding leads';

  @override
  String get concernAgain => 'Tell us something else';

  @override
  String get concernAgainTeacher => 'Raise another';

  @override
  String concernNumber(String number) {
    return 'No. $number';
  }

  @override
  String get concernsMine => 'What you told us';

  @override
  String get concernsNone => 'Nothing yet.';

  @override
  String get documentsTitle => 'My documents';

  @override
  String get documentsLead =>
      'The academy checks these documents against the original; a criminal record check is what teaching students under 18 needs.';

  @override
  String get documentSend => 'Send a document';

  @override
  String get documentSendHint => 'A clear photo or the PDF from the authority. Keep the original: the academy may ask to see it.';

  @override
  String get documentKind => 'Document';

  @override
  String get documentReference => 'Certificate number (optional)';

  @override
  String get documentExpires => 'Valid until (optional)';

  @override
  String documentUntil(String day) {
    return 'valid until $day';
  }

  @override
  String get documentNoEnd => 'no end date';

  @override
  String get documentPick => 'Choose a photo or PDF';

  @override
  String get documentPickFirst => 'Choose the photo or PDF first.';

  @override
  String get documentTooBig => 'The file is larger than 10 MB.';

  @override
  String get documentSent => 'Sent: the academy will check it.';

  @override
  String get documentsList => 'What you sent';

  @override
  String get documentsNone => 'You have not sent any documents yet.';

  @override
  String get messagesTitle => 'Messages';

  @override
  String get messagesFamilyTitle => 'Messages with teachers';

  @override
  String get messagesStudentTitle => 'Messages with your teachers';

  @override
  String get messagesKidsTitle => 'Talk to your teacher';

  @override
  String get messagesEmptyTeacher => 'When you have students, you will find them here to talk to them and their families.';

  @override
  String get messagesEmptyFamily => 'When the student has a teacher, you will find them here.';

  @override
  String get messagesStart => 'Nobody has written yet';

  @override
  String get messagesYou => 'You';

  @override
  String messagesAlsoHere(String names) {
    return 'Also here: $names';
  }

  @override
  String get messagesFirst => 'Start the conversation: guardians and the academy can read what is said here.';

  @override
  String get messagesWrite => 'Write a message…';

  @override
  String gamesStars(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n stars', one: 'one star', zero: 'no stars');
    return '$_temp0';
  }

  @override
  String get gamesSoundOn => 'Turn music and sounds on';

  @override
  String get gamesSoundOff => 'Turn music and sounds off';

  @override
  String gamesOf(int found, int total) {
    return '$found of $total';
  }

  @override
  String get gamesHomeworkPin => 'Homework';

  @override
  String get gamesToMap => 'Map';

  @override
  String get gamesIslandGames => 'The island\'s games';

  @override
  String get gamesBadgeHomework => 'Homework';

  @override
  String gamesCreaturesCard(int found, int total) {
    return 'Island creatures · $found of $total';
  }

  @override
  String gamesEggCard(int have, int need) {
    return 'Next egg: $have of $need ⭐';
  }

  @override
  String get gamesIslandDone => 'You found every creature here!';

  @override
  String get gamesClose => 'Close the game';

  @override
  String get gamesToIsland => 'Back to the island';

  @override
  String get gamesAlbum => 'My creatures';

  @override
  String gamesItems(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n questions', one: 'one question');
    return '$_temp0';
  }

  @override
  String gamesPractising(String what) {
    return 'Practising: $what';
  }

  @override
  String get gamesResult0 => 'Keep going, try again!';

  @override
  String get gamesResult1 => 'A good start!';

  @override
  String get gamesResult2 => 'Well done!';

  @override
  String get gamesResult3 => 'Brilliant! Almost all right';

  @override
  String gamesScore(int correct, int total) {
    return '$correct of $total right first time';
  }

  @override
  String gamesHatched(String name) {
    return '$name hatched!';
  }

  @override
  String gamesHomeworkDone(String title) {
    return 'The homework “$title” is handed in with this practice.';
  }

  @override
  String gamesEgg(int have, int need) {
    return 'Next egg: $have of $need stars';
  }

  @override
  String get gamesAgain => 'Play again';

  @override
  String get gamesPlay => 'Play';

  @override
  String get gamesLoading => 'Getting the round ready…';

  @override
  String get gamesSending => 'Counting the stars…';

  @override
  String get gamesPageProblem => 'The game didn\'t start. Try again.';

  @override
  String get gamesHatchReady => 'The egg is ready!';

  @override
  String gamesHatchReadySub(int need) {
    return 'You collected $need stars on your journey';
  }

  @override
  String get gamesHatchDone => 'It hatched!';

  @override
  String gamesHatchDoneSub(int need) {
    return 'Every $need stars hatch a new egg';
  }

  @override
  String get gamesHatchTap0 => 'Tap the egg 3 times';

  @override
  String get gamesHatchTap1 => 'Two more!';

  @override
  String get gamesHatchTap2 => 'One more!';

  @override
  String get gamesHatchTap3 => 'Hatching...';

  @override
  String gamesHatchEgg(int left) {
    return 'The egg, tap it. $left to go';
  }

  @override
  String gamesHatchNew(int found, int total) {
    return 'New in your album · $found of $total';
  }

  @override
  String get gamesHatchAsk => 'What will you call it?';

  @override
  String get gamesHatchKeep => 'Put it in the album';

  @override
  String get gamesAlbumTitle => 'My creatures';

  @override
  String gamesAlbumHint(int need) {
    return 'Every $need stars on your journey hatch an egg, and every lesson you master hatches a creature.';
  }

  @override
  String gamesAlbumGuardian(String name) {
    return 'This is $name\'s album. Only they choose their buddy and name their creatures.';
  }

  @override
  String get gamesAlbumAll => 'All';

  @override
  String get gamesRarityCommon => 'Common';

  @override
  String get gamesRarityRare => 'Rare';

  @override
  String get gamesRarityLegendary => 'Legendary';

  @override
  String get gamesAllIslands => 'All islands';

  @override
  String get gamesNotYet => 'Not yet';

  @override
  String gamesAriaFound(String name, String island) {
    return '$name, from $island';
  }

  @override
  String gamesAriaShadow(String island) {
    return 'A creature not hatched yet, from $island';
  }

  @override
  String get gamesVoice => 'Hear it';

  @override
  String get gamesMakeBuddy => 'Make it your buddy';

  @override
  String get gamesIsBuddy => 'Your buddy 💛';

  @override
  String gamesBuddyDone(String name) {
    return '$name flies with you in the games now';
  }

  @override
  String get gamesShadowStory => 'Not hatched yet.';

  @override
  String gamesHowCommon(String island, int need) {
    return 'How to hatch it: collect stars while you are on $island: every $need stars hatch an egg.';
  }

  @override
  String gamesHowRare(String island) {
    return 'How to hatch it: a rare creature of $island: it hatches after its common ones.';
  }

  @override
  String gamesHowLegendary(String island) {
    return 'How to hatch it: legendary! The last creature of $island. Collect all the others first.';
  }

  @override
  String get gamesNameIt => 'Name it:';

  @override
  String gamesNamed(String name) {
    return 'Now called $name';
  }

  @override
  String get gamesFromStars => 'Hatched from your journey’s stars';

  @override
  String get gamesFromMastery => 'Hatched when you mastered a lesson';

  @override
  String get gamesRace => 'Race yourself';

  @override
  String gamesBest(String s) {
    return 'Best time: $s';
  }

  @override
  String gamesSeconds(int s) {
    return '${s}s';
  }

  @override
  String get gamesBeat => 'New record! 🔥';

  @override
  String get gamesSoClose => 'So close!';

  @override
  String get gamesFirst => 'Your first time!';

  @override
  String gamesOver(int correct, int total, String s) {
    return '$correct of $total right in $s';
  }

  @override
  String get gamesReviewAgain => 'Review again';

  @override
  String gamesGuardianTitle(String name) {
    return '$name\'s games';
  }

  @override
  String get gamesPlayTime => 'Play time today';

  @override
  String gamesTimeText(int used, int limit, int left) {
    return '$used of $limit minutes played today. $left minutes left.';
  }

  @override
  String gamesTimeOpen(int used) {
    return '$used minutes played today, no limit.';
  }

  @override
  String get gamesMinutesShort => 'min';

  @override
  String get gamesMaxTime => 'Most time a day';

  @override
  String gamesChoice(int n) {
    return '$n m';
  }

  @override
  String get gamesAcademyChoice => 'Academy';

  @override
  String get gamesNightLock => 'Lock games from 9 pm to 7 am';

  @override
  String get gamesEnabled => 'Games on';

  @override
  String get gamesLearned => 'What was practised';

  @override
  String gamesRoundsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n times', two: 'twice', one: 'once');
    return '$_temp0';
  }

  @override
  String gamesGamesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n games', one: 'one game');
    return '$_temp0';
  }

  @override
  String gamesPractised(String title, String rounds, String games) {
    return 'Practised “$title” $rounds in $games';
  }

  @override
  String gamesHardest(String text) {
    return 'Hardest question: “$text”. Worth going over together.';
  }

  @override
  String gamesBestRound(int correct, int total, String game) {
    return 'Best round: $correct of $total right first time in “$game”';
  }

  @override
  String get gamesQuiet => 'No play this week. The games only practise their lessons, from the practice the teacher adds.';

  @override
  String gamesHatchedToday(String name) {
    return '“$name” hatched today';
  }

  @override
  String gamesHatchedStars(int found) {
    return 'From the journey’s stars · $found in the album';
  }

  @override
  String gamesHatchedMastery(int found) {
    return 'From a mastered lesson · $found in the album';
  }

  @override
  String get gamesCheer => 'Cheer';

  @override
  String get gamesCheered => 'Your cheer is on its way 💛';

  @override
  String get gamesSafety =>
      'No purchases inside the games, no ads, no chat with strangers. Every creature and every star is earned by studying.';

  @override
  String get gamesTogether => 'Play together';

  @override
  String get gamesSaved => 'Saved.';

  @override
  String get gamesCardKids => 'The islands are waiting 🏝️';

  @override
  String gamesCardLead(String what) {
    return 'Practise your lessons with games: $what';
  }

  @override
  String journeyTitle(String name) {
    return '$name\'s journey';
  }

  @override
  String journeyYours(String name) {
    return 'Your journey, $name';
  }

  @override
  String journeyLevel(int n) {
    return 'Level $n';
  }

  @override
  String journeyLevelOf(int i, int n) {
    return 'Level $i of $n';
  }

  @override
  String get journeyLevels => 'Levels';

  @override
  String journeyLessonsDone(int done, int total) {
    return '$done of $total lessons';
  }

  @override
  String journeyAheadLocked(String title) {
    return 'Opens once you finish $title';
  }

  @override
  String get journeyAheadFirst => 'Opens when you get there';

  @override
  String journeyAriaDone(String title) {
    return '$title, done';
  }

  @override
  String journeyAriaHere(String title) {
    return '$title, you\'re here';
  }

  @override
  String journeyAriaAhead(String title) {
    return '$title, still locked';
  }

  @override
  String get journeyStationDone => 'Done';

  @override
  String get journeyStationReview => 'To review';

  @override
  String get journeyStationHere => 'You\'re here';

  @override
  String get journeyStationTodo => 'Still ahead';

  @override
  String get journeyStationTodoNote => 'This lesson is still ahead. You\'ll practise it when you get there.';

  @override
  String get journeyStationReviewNote => 'Time to review this lesson: play a game on it.';

  @override
  String journeyPractised(String rounds, int p) {
    return 'Practised $rounds · $p% right';
  }

  @override
  String get journeyPlayStation => 'Play this lesson';

  @override
  String get journeyNoGames => 'No game plays this lesson\'s practice yet.';

  @override
  String journeyRaceLast(String s) {
    return 'Last time: $s';
  }

  @override
  String get journeyMissions => 'Your missions';

  @override
  String journeyMissionsOf(String name) {
    return '$name\'s missions';
  }

  @override
  String journeyMissionsOn(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n missions', one: '1 mission');
    return '$_temp0';
  }

  @override
  String journeyOpenMissions(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n missions waiting', one: '1 mission waiting');
    return '$_temp0';
  }

  @override
  String get journeyNoMissions => 'No missions right now. Enjoy your day!';

  @override
  String get journeyReview => 'Review';

  @override
  String get journeyLate => 'Late';

  @override
  String get journeyChestTitle => 'This week\'s chest';

  @override
  String journeyChestProgress(int done, int total) {
    return '$done of $total of the week\'s missions';
  }

  @override
  String journeyChestHint(int n) {
    return 'Attend every lesson of the week and hand in its homework: the chest opens with $n stars';
  }

  @override
  String get journeyChestMissed => 'You missed this one. A new chest is waiting this week!';

  @override
  String journeyChestWaiting(String name) {
    return 'The chest is ready to open on $name\'s account';
  }

  @override
  String journeyChestGot(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '+$n stars on your journey!', one: '+1 star on your journey!');
    return '$_temp0';
  }

  @override
  String get journeyYourStars => 'Your stars';

  @override
  String journeyStarsTo(int n, String rank) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n stars to $rank', one: '1 star to $rank');
    return '$_temp0';
  }

  @override
  String journeyStarsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n stars', one: '1 star', zero: 'No stars yet');
    return '$_temp0';
  }

  @override
  String journeyStarsOf(String name) {
    return '$name\'s stars';
  }

  @override
  String get journeyLastLesson => 'Your last lesson';

  @override
  String journeyLastLessonOf(String name) {
    return '$name\'s last lesson';
  }

  @override
  String journeyLessonStars(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n stars from your last lesson!',
      one: '1 star from your last lesson',
      zero: 'No stars from your last lesson',
    );
    return '$_temp0';
  }

  @override
  String journeyLessonCanBring(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'Brings up to $n stars', one: 'Brings 1 star');
    return '$_temp0';
  }

  @override
  String get journeyEggReady => 'Your egg is ready!';

  @override
  String get journeyEggHint => 'Hatch it and see who comes out';

  @override
  String get journeyEggHatch => 'Hatch it';

  @override
  String get journeyEggNone => 'No egg is ready yet. Keep collecting stars!';

  @override
  String get journeyNoCourses => 'No courses running yet.';

  @override
  String get journeyNoCurriculum => 'This course has no curriculum yet. Its missions show here.';

  @override
  String get journeyCourses => 'Lesson materials and reports';

  @override
  String get homeworkPracticeTitle => 'Practice in the games';

  @override
  String get homeworkPracticeNotYet => 'Its round is not played yet.';

  @override
  String get homeworkByPlaying => 'This homework is handed in by playing its round.';

  @override
  String get homeworkNoGame => 'No game for this homework right now.';
}
