import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// The wording a guardian agrees to for one child, in their language; its
/// [version] goes back with "I agree".
class ConsentText extends Equatable {
  const ConsentText({required this.version, required this.title, required this.intro, required this.sections, required this.agree});

  factory ConsentText.fromJson(Map<String, dynamic> json) => ConsentText(
    version: (json['version'] as num).toInt(),
    title: json['title'] as String,
    intro: json['intro'] as String,
    sections: [
      for (final s in json['sections'] as List<dynamic>) (title: (s as Map<String, dynamic>)['title'] as String, body: s['body'] as String),
    ],
    agree: json['agree'] as String,
  );

  final int version;
  final String title;
  final String intro;
  final List<({String title, String body})> sections;
  final String agree;

  @override
  List<Object?> get props => [version, title, intro, agree];
}

/// One of the guardian's children who needs their consent, or has it
/// (`GET family/consents`).
class ChildConsent extends Equatable {
  const ChildConsent({
    required this.studentId,
    required this.name,
    required this.waiting,
    required this.text,
    this.grantedAt,
    this.by,
    this.canWithdraw = false,
  });

  factory ChildConsent.fromJson(Map<String, dynamic> json) {
    final student = json['student'] as Map<String, dynamic>;
    return ChildConsent(
      studentId: student['id'] as String,
      name: student['name'] as String,
      waiting: json['state'] == 'waiting',
      grantedAt: LocalTime.fromJson(json['granted_at']),
      by: json['by'] as String?,
      canWithdraw: json['can_withdraw'] as bool? ?? false,
      text: ConsentText.fromJson(json['text'] as Map<String, dynamic>),
    );
  }

  final String studentId;
  final String name;

  /// The child cannot sign in or join by their own link until a guardian agrees.
  final bool waiting;
  final LocalTime? grantedAt;

  /// Who agreed, in words ("You agreed on 5 October 2026").
  final String? by;
  final bool canWithdraw;
  final ConsentText text;

  @override
  List<Object?> get props => [studentId, waiting, by, canWithdraw];
}

class Consents extends Equatable {
  const Consents({required this.age, required this.children});

  factory Consents.fromJson(Map<String, dynamic> json) => Consents(
    age: (json['age'] as num).toInt(),
    children: [for (final c in json['children'] as List<dynamic>) ChildConsent.fromJson(c as Map<String, dynamic>)],
  );

  /// The academy asks guardians for children under this age.
  final int age;
  final List<ChildConsent> children;

  List<ChildConsent> get waiting => children.where((c) => c.waiting).toList();

  @override
  List<Object?> get props => [age, children];
}
