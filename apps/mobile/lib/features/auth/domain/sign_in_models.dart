import 'package:equatable/equatable.dart';

import '../../../core/auth/account.dart';

/// A code on its way (`POST accounts/code`): where it went, masked, and
/// when another may be asked for.
class SentCode extends Equatable {
  const SentCode({
    required this.challengeId,
    required this.channel,
    required this.sentTo,
    required this.resendAfter,
    required this.channels,
    required this.at,
  });

  factory SentCode.fromJson(Map<String, dynamic> json, DateTime at) => SentCode(
    challengeId: json['challenge_id'] as String,
    channel: json['channel'] as String,
    sentTo: json['sent_to'] as String,
    resendAfter: (json['resend_after'] as num?)?.toInt() ?? 60,
    channels: (json['channels'] as List<dynamic>? ?? const []).cast<String>(),
    at: at,
  );

  final String challengeId;

  /// whatsapp, sms or email.
  final String channel;
  final String sentTo;
  final int resendAfter;

  /// Channels a new code can go by.
  final List<String> channels;
  final DateTime at;

  DateTime get resendAt => at.add(Duration(seconds: resendAfter));

  bool get canSwitchToSms => channel == 'whatsapp' && channels.contains('sms');

  @override
  List<Object?> get props => [challengeId, channel, sentTo, resendAfter, at];
}

/// One account the code found, in any academy (`DiscoveredAccountResource`).
class FoundAccount extends Equatable {
  const FoundAccount({
    required this.academySlug,
    required this.academyNameAr,
    required this.academyNameEn,
    required this.userId,
    required this.userName,
    required this.roles,
    this.colour,
    this.logoUrl,
  });

  factory FoundAccount.fromJson(Map<String, dynamic> json) {
    final academy = json['academy'] as Map<String, dynamic>;
    final user = json['user'] as Map<String, dynamic>;
    return FoundAccount(
      academySlug: academy['slug'] as String,
      academyNameAr: academy['name_ar'] as String,
      academyNameEn: academy['name_en'] as String,
      colour: academy['colour'] as String?,
      logoUrl: academy['logo_url'] as String?,
      userId: user['id'] as String,
      userName: user['name'] as String,
      roles: (user['roles'] as List<dynamic>).cast<String>(),
    );
  }

  final String academySlug;
  final String academyNameAr;
  final String academyNameEn;
  final String? colour;
  final String? logoUrl;
  final String userId;
  final String userName;
  final List<String> roles;

  String get id => '$academySlug:$userId';

  /// Staff-only accounts work on the website, not in the app.
  bool get hasAppArea => roles.any((r) => r == 'teacher' || r == 'student' || r == 'guardian');

  String academyName(String locale) => locale == 'ar' ? academyNameAr : academyNameEn;

  @override
  List<Object?> get props => [id];
}

/// What a verified code opens: a short-lived token to sign in to each account.
class Discovery extends Equatable {
  const Discovery({required this.token, required this.accounts});

  factory Discovery.fromJson(Map<String, dynamic> json) => Discovery(
    token: json['discovery_token'] as String,
    accounts: (json['accounts'] as List<dynamic>).map((a) => FoundAccount.fromJson(a as Map<String, dynamic>)).toList(),
  );

  final String token;
  final List<FoundAccount> accounts;

  @override
  List<Object?> get props => [token, accounts];
}

/// A sign-in waiting on the authenticator app's code (two-step sign-in is
/// on for that account): the token from the API, the academy it is in, and,
/// when it came from a code, the found account and the chosen ones still to
/// sign in to after it.
class TwoFactorStep extends Equatable {
  const TwoFactorStep({required this.token, required this.academySlug, this.found, this.queue = const [], this.signed = const []});

  final String token;
  final String academySlug;
  final FoundAccount? found;
  final List<FoundAccount> queue;
  final List<Account> signed;

  @override
  List<Object?> get props => [token, academySlug, found, queue, signed.length];
}

/// The account a token response opens (`{token, expires_at, user}`); a
/// discovered account brings its academy's colour and logo.
Account accountFromToken(Map<String, dynamic> json, {FoundAccount? found}) {
  final user = json['user'] as Map<String, dynamic>;
  final academy = user['academy'] as Map<String, dynamic>?;
  final expires = json['expires_at'];
  final expiresAt = switch (expires) {
    final Map<String, dynamic> t => DateTime.tryParse(t['utc'] as String? ?? ''),
    final String s => DateTime.tryParse(s),
    _ => null,
  };
  return Account(
    academySlug: academy?['slug'] as String? ?? found!.academySlug,
    academyNameAr: academy?['name_ar'] as String? ?? found!.academyNameAr,
    academyNameEn: academy?['name_en'] as String? ?? found!.academyNameEn,
    academyColour: found?.colour,
    logoUrl: found?.logoUrl,
    userId: user['id'] as String,
    userName: user['name'] as String,
    roles: (user['roles'] as List<dynamic>).map((r) => '$r').toList(),
    token: json['token'] as String,
    expiresAt: expiresAt,
  );
}
