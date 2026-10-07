/// The look of a student's own area, as on the web (People's `LearnerStage`):
/// kids (playful, round, few tabs), middle (levels and badges), teen (a plan
/// and a goal). Adults, guardians and teachers are [classic], in the
/// academy's own look.
enum Stage {
  kids,
  middle,
  teen,
  classic;

  static Stage parse(String? value) => Stage.values.firstWhere((s) => s.name == value, orElse: () => Stage.classic);
}
