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
  String get webOnlyTitle => 'This is a staff account';

  @override
  String get webOnlyBody => 'Staff accounts work on the website. Open the academy\'s back office on a computer.';

  @override
  String get navHome => 'Home';

  @override
  String get navLessons => 'Lessons';

  @override
  String get navProgress => 'Progress';

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
  String get navBadges => 'Badges';

  @override
  String get navPlan => 'Plan';

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
  String progressOf(String name) {
    return '$name\'s progress';
  }

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
  String get handInOnWeb =>
      'This homework takes a recording or a file. Handing those in is coming to the app soon; use the website until then.';

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
  String get invoiceNote => 'Open any invoice to see and print it, with its tax invoice QR code.';

  @override
  String get cantPayOnline => 'Online payment isn\'t available for this invoice. Contact the academy.';

  @override
  String get pickGateway => 'Pay with?';

  @override
  String get joining => 'Joining…';

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
  String lessonUntil(String time) {
    return 'Until $time';
  }

  @override
  String get meTitle => 'Me';

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
    return '$h h';
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
  String get writeOnWeb => 'Write reports and mark homework on the teacher site until they arrive here.';

  @override
  String reportDueBy(String when) {
    return 'Before $when';
  }

  @override
  String weekSummary(int n, String h) {
    return '$n lessons · $h h';
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
  String lessonsHours(int n, String h) {
    return '$n lessons · $h h';
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
  String get kidsHello => 'Hi, champ';

  @override
  String kidsJourney(String program) {
    return 'My journey: $program';
  }

  @override
  String kidsStations(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'You finished $n stops!',
      one: 'You finished a stop!',
      zero: 'Your first stop is waiting!',
    );
    return '$_temp0';
  }

  @override
  String kidsNext(String title) {
    return 'Next: $title';
  }

  @override
  String get youAreHere => 'You\'re here!';

  @override
  String levelRank(int index, String rank) {
    return 'Level $index · $rank';
  }

  @override
  String pointsTo(String rank) {
    return 'Points to $rank';
  }

  @override
  String pointsLeft(int n) {
    return '$n points to go.';
  }

  @override
  String get topRank => 'You reached the top rank!';

  @override
  String worldStage(int index, String title) {
    return 'World $index · $title';
  }

  @override
  String stageOf(int i, int n) {
    return 'Stage $i of $n';
  }

  @override
  String streak(int n) {
    return '$n in a row';
  }

  @override
  String badgesCount(int earned, int total) {
    return '$earned of $total';
  }

  @override
  String planFor(String name) {
    return 'Your plan, $name';
  }

  @override
  String get goal => 'Your goal';

  @override
  String get finishOn => 'At this pace you finish';

  @override
  String get pace => 'Your pace';

  @override
  String perWeek(int n) {
    return '$n lessons a week';
  }

  @override
  String get milestones => 'Milestones';

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
  String get stars => 'Stars';

  @override
  String get points => 'points';
}
