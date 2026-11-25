/// Whom the academy teaches (`audience` in `GET branding`, `academy.audience`
/// in `GET me`): families (children with their guardians, and adults), or
/// adults alone (professionals and their trainers: no guardians, no
/// children, no kids' looks, and the training words in place of a school's).
enum Audience {
  families,
  adults;

  /// As the API writes it; anything else (an older server) is families, as before.
  static Audience parse(Object? value) => value == 'adults' ? adults : families;

  String get value => name;
}
