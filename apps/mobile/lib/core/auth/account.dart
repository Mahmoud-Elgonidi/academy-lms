import 'package:equatable/equatable.dart';

/// One account on this device: a person in one academy, with the token the
/// API gave it. The same person may have several (a parent at two academies,
/// a teacher who is also a parent).
class Account extends Equatable {
  const Account({
    required this.academySlug,
    required this.academyNameAr,
    required this.academyNameEn,
    required this.userId,
    required this.userName,
    required this.roles,
    required this.token,
    this.academyColour,
    this.logoUrl,
    this.expiresAt,
    this.stage,
  });

  factory Account.fromJson(Map<String, dynamic> json) => Account(
    academySlug: json['academy_slug'] as String,
    academyNameAr: json['academy_name_ar'] as String,
    academyNameEn: json['academy_name_en'] as String,
    academyColour: json['academy_colour'] as String?,
    logoUrl: json['logo_url'] as String?,
    userId: json['user_id'] as String,
    userName: json['user_name'] as String,
    roles: (json['roles'] as List<dynamic>).cast<String>(),
    token: json['token'] as String,
    expiresAt: json['expires_at'] == null ? null : DateTime.parse(json['expires_at'] as String),
    stage: json['stage'] as String?,
  );

  final String academySlug;
  final String academyNameAr;
  final String academyNameEn;
  final String? academyColour;
  final String? logoUrl;
  final String userId;
  final String userName;
  final List<String> roles;
  final String token;
  final DateTime? expiresAt;

  /// The look of the person's own area when they are a student: kids,
  /// middle, teen or classic (`viewer_stage` from `family/learners`).
  /// Guardians and teachers are classic.
  final String? stage;

  /// Unique on the device: the same user id never appears in two academies.
  String get id => '$academySlug:$userId';

  bool get isTeacher => roles.contains('teacher');
  bool get isFamily => roles.contains('student') || roles.contains('guardian');
  bool get isGuardian => roles.contains('guardian');

  /// Staff without a teaching or family role use the web back office.
  bool get hasAppArea => isTeacher || isFamily;

  String academyName(String locale) => locale == 'ar' ? academyNameAr : academyNameEn;

  bool expired(DateTime now) => expiresAt != null && !expiresAt!.isAfter(now);

  Account copyWith({String? academyColour, String? logoUrl, String? stage}) => Account(
    academySlug: academySlug,
    academyNameAr: academyNameAr,
    academyNameEn: academyNameEn,
    academyColour: academyColour ?? this.academyColour,
    logoUrl: logoUrl ?? this.logoUrl,
    userId: userId,
    userName: userName,
    roles: roles,
    token: token,
    expiresAt: expiresAt,
    stage: stage ?? this.stage,
  );

  Map<String, dynamic> toJson() => {
    'academy_slug': academySlug,
    'academy_name_ar': academyNameAr,
    'academy_name_en': academyNameEn,
    'academy_colour': academyColour,
    'logo_url': logoUrl,
    'user_id': userId,
    'user_name': userName,
    'roles': roles,
    'token': token,
    'expires_at': expiresAt?.toIso8601String(),
    'stage': stage,
  };

  @override
  List<Object?> get props => [id, token, roles, userName, academyColour, logoUrl, stage];
}
