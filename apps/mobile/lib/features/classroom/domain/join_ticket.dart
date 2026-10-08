import '../../recordings/domain/recordings.dart';

/// A seat in a lesson's room (`POST lessons/{id}/join`): a LiveKit room with
/// its token, or an outside link (Zoom, Meet) to open; and what the screen
/// shows about recording.
class JoinTicket {
  const JoinTicket({
    required this.provider,
    required this.url,
    this.token,
    this.room,
    this.name,
    this.role,
    this.canPublish = true,
    this.recording = const RecordingState(),
  });

  factory JoinTicket.fromJson(Map<String, dynamic> json, {Object? recording}) => JoinTicket(
    provider: json['provider'] as String,
    url: json['url'] as String,
    token: json['token'] as String?,
    room: json['room'] as String?,
    name: json['name'] as String?,
    role: json['role'] as String?,
    canPublish: json['can_publish'] != false,
    recording: RecordingState.fromJson(recording),
  );

  final String provider;
  final String url;
  final String? token;
  final String? room;
  final String? name;
  final String? role;
  final bool canPublish;
  final RecordingState recording;

  bool get isLiveKit => provider == 'livekit' && token != null;
}
