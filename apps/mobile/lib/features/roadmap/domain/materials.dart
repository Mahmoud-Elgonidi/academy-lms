/// A lesson's file or link as the API sends it: a file's url is signed for
/// twelve hours and opens without signing in (in the browser or the
/// phone's own viewer), a link's is where the academy points.
class LessonMaterial {
  const LessonMaterial({required this.id, required this.type, required this.title, required this.url, this.teachersOnly = false});

  factory LessonMaterial.fromJson(Map<String, dynamic> json) => LessonMaterial(
    id: json['id'] as String,
    type: json['type'] as String? ?? 'link',
    title: json['title'] as String? ?? '',
    url: json['url'] as String? ?? '',
    teachersOnly: json['teachers_only'] == true,
  );

  static List<LessonMaterial> listOf(Object? json) => [
    if (json is List)
      for (final m in json) LessonMaterial.fromJson(m as Map<String, dynamic>),
  ];

  final String id;

  /// pdf, audio, video, image or link.
  final String type;
  final String title;
  final String url;

  /// For teachers only (a guide, the answers); a family never gets one.
  final bool teachersOnly;
}
