import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// How the academy takes a free trial asked for from its page (`GET site`,
/// `trials`): the family picks its own time, or the academy books it after
/// the request.
class TrialSettings extends Equatable {
  const TrialSettings({
    required this.familyBooks,
    required this.responseHours,
    required this.noticeHours,
    required this.helpChooseSubject,
    this.helpChooseSubjectId,
  });

  factory TrialSettings.fromJson(Map<String, dynamic>? json) => TrialSettings(
    familyBooks: (json?['booking'] as String? ?? 'staff') == 'family',
    responseHours: (json?['response_hours'] as num?)?.toInt() ?? 4,
    noticeHours: (json?['notice_hours'] as num?)?.toInt() ?? 3,
    helpChooseSubject: json?['help_choose_subject'] as bool? ?? false,
    helpChooseSubjectId: json?['help_choose_subject_id'] as String?,
  );

  /// The family picks its own time (`site/trial-slots`, a code, `site/trials`).
  final bool familyBooks;

  /// The academy answers a request within this many hours.
  final int responseHours;

  /// The soonest a family may book, in hours from now.
  final int noticeHours;

  /// "Help me choose" starts with the academy's own trial subject (else its staff choose with the family).
  final bool helpChooseSubject;

  /// That subject, whose times a family asking for help may book from (null: the academy's staff choose with them).
  final String? helpChooseSubjectId;

  @override
  List<Object?> get props => [familyBooks, responseHours, noticeHours, helpChooseSubject, helpChooseSubjectId];
}

/// A subject the academy teaches, as its page lists it.
class TrialSubject extends Equatable {
  const TrialSubject({required this.id, required this.name, this.levels = const [], this.categories = const []});

  factory TrialSubject.fromJson(Map<String, dynamic> json) => TrialSubject(
    id: json['id'] as String,
    name: json['name'] as String? ?? '',
    levels: [for (final l in json['levels'] as List<dynamic>? ?? const []) '$l'],
    categories: [for (final c in json['categories'] as List<dynamic>? ?? const []) '$c'],
  );

  final String id;
  final String name;
  final List<String> levels;
  final List<String> categories;

  @override
  List<Object?> get props => [id, name, levels, categories];
}

/// The academy's page as the trial needs it (`GET site`): its name and code,
/// its subjects, how it takes trials, its WhatsApp number, and the form's
/// token for a request sent without booking.
class AcademyPage extends Equatable {
  const AcademyPage({
    required this.slug,
    required this.name,
    required this.subjects,
    required this.trials,
    required this.formToken,
    required this.loadedAt,
    this.whatsapp,
  });

  factory AcademyPage.fromJson(String slug, Map<String, dynamic> json, DateTime loadedAt) {
    final data = json['data'] as Map<String, dynamic>? ?? const {};
    final academy = data['academy'] as Map<String, dynamic>? ?? const {};
    return AcademyPage(
      slug: academy['code'] as String? ?? slug,
      name: academy['name'] as String? ?? slug,
      subjects: [for (final s in data['subjects'] as List<dynamic>? ?? const []) TrialSubject.fromJson(s as Map<String, dynamic>)],
      trials: TrialSettings.fromJson(data['trials'] as Map<String, dynamic>?),
      formToken: data['form_token'] as String? ?? '',
      whatsapp: data['whatsapp'] as String?,
      loadedAt: loadedAt,
    );
  }

  /// The academy's code (`X-Academy`).
  final String slug;
  final String name;
  final List<TrialSubject> subjects;
  final TrialSettings trials;

  /// Sent back with a request; refused (silently) when under three seconds old or over six hours.
  final String formToken;
  final DateTime loadedAt;

  /// The academy's WhatsApp number (E.164), or null.
  final String? whatsapp;

  @override
  List<Object?> get props => [slug, name, subjects, trials, formToken, loadedAt, whatsapp];
}

/// A time a free trial may start at (`GET site/trial-slots`), on the family's clock as the API wrote it.
class TrialSlot extends Equatable {
  const TrialSlot(this.startsAt);

  final LocalTime startsAt;

  /// `2026-11-03` on the family's calendar.
  String get day => startsAt.localDate;

  /// ISO weekday (1 Monday … 7 Sunday) of that calendar day.
  int get weekday => DateTime.utc(int.parse(day.substring(0, 4)), int.parse(day.substring(5, 7)), int.parse(day.substring(8, 10))).weekday;

  @override
  List<Object?> get props => [startsAt];
}

/// The times on offer, soonest first, by day.
class SlotDay extends Equatable {
  const SlotDay(this.day, this.slots);

  final String day;
  final List<TrialSlot> slots;

  LocalTime get first => slots.first.startsAt;

  static List<SlotDay> group(Iterable<TrialSlot> slots) {
    final sorted = [...slots]..sort((a, b) => a.startsAt.utc.compareTo(b.startsAt.utc));
    final days = <String, List<TrialSlot>>{};
    for (final s in sorted) {
      (days[s.day] ??= []).add(s);
    }
    return [for (final MapEntry(:key, :value) in days.entries) SlotDay(key, value)];
  }

  @override
  List<Object?> get props => [day, slots];
}

/// When in the day the family would rather have the lesson.
enum DayPart {
  any(null, null),
  morning(6, 12),
  afternoon(12, 17),
  evening(17, 23);

  const DayPart(this.from, this.to);

  /// Hours on the family's clock (`preferred_from`, `preferred_to`).
  final int? from;
  final int? to;

  bool fits(int hour) => from == null || (hour >= from! && hour < to!);
}

/// Who the trial is for.
enum Learner { me, child }

/// The teacher the family would rather have.
enum TeacherGender {
  any(null),
  female('female'),
  male('male');

  const TeacherGender(this.api);

  final String? api;
}

/// What the family has told us so far; kept whole while they move between steps.
class TrialDraft extends Equatable {
  const TrialDraft({
    this.learner,
    this.name = '',
    this.childName = '',
    this.childAge,
    this.subjectId,
    this.helpChoose = false,
    this.days = const {},
    this.dayPart = DayPart.any,
    this.teacherGender = TeacherGender.any,
    this.phone = '',
    this.email = '',
  });

  final Learner? learner;

  /// The name of whoever we talk to (the parent for a child).
  final String name;
  final String childName;

  /// The child's age in years (3 to 17).
  final int? childAge;
  final String? subjectId;

  /// "Help me choose": no subject; the academy chooses with the family.
  final bool helpChoose;

  /// ISO weekdays that suit them.
  final Set<int> days;
  final DayPart dayPart;
  final TeacherGender teacherGender;

  /// The number with its country code (+966…).
  final String phone;
  final String email;

  bool get forChild => learner == Learner.child;

  /// Who the lesson is for, by name.
  String get learnerName => forChild ? childName.trim() : name.trim();

  /// The child's birth year the API takes, from the age (the year they turn it).
  int? birthYear(int thisYear) => forChild && childAge != null ? thisYear - childAge! : null;

  bool get hasWishes => days.isNotEmpty || dayPart != DayPart.any;

  /// A time that suits the days and hours they gave (any, when they gave none).
  bool suits(TrialSlot slot) => (days.isEmpty || days.contains(slot.weekday)) && dayPart.fits(slot.startsAt.hour);

  TrialDraft copyWith({
    Learner? learner,
    String? name,
    String? childName,
    int? childAge,
    String? Function()? subjectId,
    bool? helpChoose,
    Set<int>? days,
    DayPart? dayPart,
    TeacherGender? teacherGender,
    String? phone,
    String? email,
  }) => TrialDraft(
    learner: learner ?? this.learner,
    name: name ?? this.name,
    childName: childName ?? this.childName,
    childAge: childAge ?? this.childAge,
    subjectId: subjectId != null ? subjectId() : this.subjectId,
    helpChoose: helpChoose ?? this.helpChoose,
    days: days ?? this.days,
    dayPart: dayPart ?? this.dayPart,
    teacherGender: teacherGender ?? this.teacherGender,
    phone: phone ?? this.phone,
    email: email ?? this.email,
  );

  /// Another child of the same family: who we talk to and how, kept; the rest anew.
  TrialDraft anotherChild() => TrialDraft(learner: Learner.child, name: name, phone: phone, email: email);

  /// The request as the page's form sends it (`site/requests`, and with the code `site/trials`).
  Map<String, Object?> toJson({required int thisYear, String? timezone}) => {
    'name': name.trim(),
    'phone': phone,
    if (email.trim().isNotEmpty) 'email': email.trim(),
    'timezone': ?timezone,
    if (helpChoose) 'help_choose': true else 'subject_id': subjectId,
    'for_child': forChild,
    if (forChild) 'child_name': childName.trim(),
    if (forChild) 'child_birth_year': ?birthYear(thisYear),
    if (days.isNotEmpty) 'preferred_days': (days.toList()..sort()),
    'preferred_from': ?dayPart.from,
    'preferred_to': ?dayPart.to,
    'teacher_gender': ?teacherGender.api,
  };

  @override
  List<Object?> get props => [learner, name, childName, childAge, subjectId, helpChoose, days, dayPart, teacherGender, phone, email];
}

/// A code on its way to the family's number (`POST site/trials/code`).
class TrialCode extends Equatable {
  const TrialCode({
    required this.challengeId,
    required this.channel,
    required this.sentTo,
    required this.resendAfter,
    required this.at,
    this.expiresAt,
  });

  factory TrialCode.fromJson(Map<String, dynamic> json, DateTime at) {
    final data = json['data'] as Map<String, dynamic>? ?? json;
    return TrialCode(
      challengeId: data['challenge_id'] as String,
      channel: data['channel'] as String? ?? 'whatsapp',
      sentTo: data['sent_to'] as String? ?? '',
      resendAfter: (data['resend_after'] as num?)?.toInt() ?? 60,
      expiresAt: LocalTime.fromJson(data['expires_at'])?.utc,
      at: at,
    );
  }

  final String challengeId;

  /// whatsapp or sms.
  final String channel;

  /// The number, masked.
  final String sentTo;
  final int resendAfter;
  final DateTime? expiresAt;
  final DateTime at;

  /// Codes may be tried this many times before the API locks the code.
  static const maxTries = 5;

  @override
  List<Object?> get props => [challengeId, channel, sentTo, resendAfter, expiresAt, at];
}

/// The trial booked (`POST site/trials`): when, in which subject, with whom and for how long.
class BookedTrial extends Equatable {
  const BookedTrial({required this.startsAt, required this.subject, this.teacher, this.minutes});

  factory BookedTrial.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? const {};
    final teacher = data['teacher'] as String?;
    final minutes = (data['minutes'] as num?)?.toInt();
    return BookedTrial(
      startsAt: LocalTime.fromJson(data['starts_at']),
      subject: data['subject'] as String? ?? '',
      teacher: teacher == null || teacher.isEmpty ? null : teacher,
      minutes: minutes == null || minutes <= 0 ? null : minutes,
    );
  }

  final LocalTime? startsAt;
  final String subject;

  /// The teacher's first name, when the API says it.
  final String? teacher;

  /// How long the lesson is, when the API says it.
  final int? minutes;

  @override
  List<Object?> get props => [startsAt, subject, teacher, minutes];
}
