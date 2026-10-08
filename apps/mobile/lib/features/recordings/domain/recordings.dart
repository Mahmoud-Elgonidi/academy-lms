import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';
import '../../consent/domain/consent.dart';

/// Someone's consent to lesson recordings, as the person signed in sees it
/// (`GET recordings/consents`): whether it stands and who gave it, whether
/// they may change it, else who decides, and the words to agree to.
class RecordingConsent extends Equatable {
  const RecordingConsent({
    required this.personId,
    required this.name,
    required this.self,
    required this.agreed,
    required this.canChange,
    required this.text,
    this.by,
    this.decides,
  });

  factory RecordingConsent.fromJson(Map<String, dynamic> json) {
    final person = json['person'] as Map<String, dynamic>;
    return RecordingConsent(
      personId: person['id'] as String,
      name: person['name'] as String,
      self: json['self'] as bool? ?? false,
      agreed: json['state'] == 'agreed',
      canChange: json['can_change'] as bool? ?? false,
      by: json['by'] as String?,
      decides: json['decides'] as String?,
      text: ConsentText.fromJson(json['text'] as Map<String, dynamic>),
    );
  }

  final String personId;
  final String name;
  final bool self;
  final bool agreed;
  final bool canChange;

  /// Who agreed, in words; null when nobody has.
  final String? by;

  /// Who decides, in words, when the person signed in cannot.
  final String? decides;
  final ConsentText text;

  @override
  List<Object?> get props => [personId, agreed, canChange, by, decides, text];
}

class RecordingConsents extends Equatable {
  const RecordingConsents({required this.recording, required this.people});

  factory RecordingConsents.fromJson(Map<String, dynamic> json) => RecordingConsents(
    recording: json['recording'] as String? ?? 'off',
    people: [for (final p in json['people'] as List<dynamic>) RecordingConsent.fromJson(p as Map<String, dynamic>)],
  );

  /// off | manual | auto
  final String recording;
  final List<RecordingConsent> people;

  bool get off => recording == 'off';

  @override
  List<Object?> get props => [recording, people];
}

/// A ready recording of a lesson (`GET family/recordings`, `GET teacher/recordings`).
class LessonRecording extends Equatable {
  const LessonRecording({
    required this.id,
    required this.at,
    required this.teacher,
    required this.media,
    required this.durationSec,
    this.student,
    this.expiresAt,
  });

  factory LessonRecording.fromJson(Map<String, dynamic> json) => LessonRecording(
    id: json['id'] as String,
    at: LocalTime.fromJson(json['at'])!,
    teacher: json['teacher'] as String,
    student: json['student'] as String?,
    media: json['media'] as String,
    durationSec: (json['duration_sec'] as num).toInt(),
    expiresAt: LocalTime.fromJson(json['expires_at']),
  );

  final String id;
  final LocalTime at;
  final String teacher;
  final String? student;

  /// video | audio
  final String media;
  final int durationSec;
  final LocalTime? expiresAt;

  bool get audio => media == 'audio';

  /// "25:00", or "1:02:05" past an hour.
  String get length {
    final h = durationSec ~/ 3600;
    final m = (durationSec % 3600) ~/ 60;
    final s = (durationSec % 60).toString().padLeft(2, '0');
    return h > 0 ? '$h:${m.toString().padLeft(2, '0')}:$s' : '$m:$s';
  }

  @override
  List<Object?> get props => [id, durationSec, expiresAt];
}

class RecordingShelf extends Equatable {
  const RecordingShelf({required this.shared, required this.items});

  factory RecordingShelf.fromJson(Map<String, dynamic> json) => RecordingShelf(
    shared: json['shared'] as bool? ?? false,
    items: [for (final r in json['data'] as List<dynamic>) LessonRecording.fromJson(r as Map<String, dynamic>)],
  );

  /// Whether the academy shows recordings here at all.
  final bool shared;
  final List<LessonRecording> items;

  @override
  List<Object?> get props => [shared, items];
}

/// What the recordings screen shows: the consents and the recordings.
class RecordingsScreen extends Equatable {
  const RecordingsScreen({required this.consents, required this.shelf});

  final RecordingConsents consents;
  final RecordingShelf shelf;

  @override
  List<Object?> get props => [consents, shelf];
}

/// What a lesson's screen shows about recording (with `POST lessons/{id}/join`).
class RecordingState extends Equatable {
  const RecordingState({this.mode = 'off', this.controls = false, this.running = false, this.missing = const []});

  factory RecordingState.fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return const RecordingState();
    return RecordingState(
      mode: json['mode'] as String? ?? 'off',
      controls: json['controls'] as bool? ?? false,
      running: json['running'] as bool? ?? false,
      missing: [for (final n in json['missing'] as List<dynamic>? ?? const []) n as String],
    );
  }

  /// off | manual | auto
  final String mode;

  /// Whether this person may start and stop it (the lesson's teacher).
  final bool controls;
  final bool running;

  /// Who has not agreed to recording yet (names).
  final List<String> missing;

  @override
  List<Object?> get props => [mode, controls, running, missing];
}
