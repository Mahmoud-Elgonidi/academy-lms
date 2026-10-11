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
