import 'package:equatable/equatable.dart';

/// The teacher's card on the academy's page, as `GET teacher/card` answers:
/// what it says, the teacher's own switch, and where it stands ([state]: on,
/// off, hidden by the academy, none yet). Whether the academy took it off is
/// said in [stateLabel], never why.
class TeacherCard extends Equatable {
  const TeacherCard({
    required this.displayName,
    required this.bio,
    required this.photoUrl,
    required this.shown,
    required this.state,
    required this.stateLabel,
    required this.subjects,
  });

  factory TeacherCard.fromJson(Map<String, dynamic> j) => TeacherCard(
    displayName: j['display_name'] as String? ?? '',
    bio: j['bio'] as String?,
    photoUrl: j['photo_url'] as String?,
    shown: j['shown'] as bool? ?? false,
    state: j['state'] as String? ?? 'none',
    stateLabel: j['state_label'] as String? ?? '',
    subjects: [
      for (final s in (j['subjects'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>())
        (name: s['name'] as String? ?? '', from: s['level_from'] as String?, to: s['level_to'] as String?),
    ],
  );

  final String displayName;
  final String? bio;
  final String? photoUrl;
  final bool shown;

  /// on | off | hidden | none
  final String state;
  final String stateLabel;

  /// From the teacher's profile at the academy; the levels are null at the start or the end.
  final List<({String name, String? from, String? to})> subjects;

  bool get hidden => state == 'hidden';

  @override
  List<Object?> get props => [displayName, bio, photoUrl, shown, state, stateLabel, subjects];
}
