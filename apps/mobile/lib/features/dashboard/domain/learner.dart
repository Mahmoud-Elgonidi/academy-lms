/// A learner the person follows (themself, or a guardian's children).
class Learner {
  const Learner({required this.id, required this.name, required this.isSelf, required this.stage});

  factory Learner.fromJson(Map<String, dynamic> json) => Learner(
    id: json['id'] as String,
    name: json['name'] as String,
    isSelf: json['is_self'] == true,
    stage: json['stage'] as String? ?? 'classic',
  );

  final String id;
  final String name;
  final bool isSelf;
  final String stage;

  String get firstName => name.split(' ').first;
}

/// A child the guardian just added: who, their look, and the learner to start a course for.
class AddedChild {
  const AddedChild({required this.id, required this.name, this.stage = 'kids', this.message = ''});

  factory AddedChild.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? const {};
    final start = data['start'] as Map<String, dynamic>? ?? const {};
    return AddedChild(
      id: start['learner_id'] as String? ?? data['id'] as String? ?? '',
      name: data['name'] as String? ?? '',
      stage: data['stage'] as String? ?? 'kids',
      message: json['message'] as String? ?? '',
    );
  }

  /// The learner's id (the start flow's `learner`).
  final String id;
  final String name;
  final String stage;

  /// The API's sentence ("أُضيف … اختاروا له الآن الكورس").
  final String message;
}
