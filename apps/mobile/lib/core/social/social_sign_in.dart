import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

/// What the phone's own Google or Apple sign-in gave: an ID token for the
/// API to check, and the raw nonce it was asked with.
class SocialCredential {
  const SocialCredential({required this.provider, required this.idToken, this.nonce});

  /// google | apple
  final String provider;
  final String idToken;
  final String? nonce;
}

/// The phone's Google and Apple sign-in. The API checks the ID token (it only
/// signs in accounts the academy made); this only asks the person.
abstract class SocialSignIn {
  /// Whether this phone can offer the provider (Google: a server client is
  /// configured; Apple: on an iPhone, iPad or Mac).
  bool available(String provider);

  /// The credential, or null when the person cancelled.
  Future<SocialCredential?> signIn(String provider);
}

/// Google through google_sign_in (its server client from
/// `--dart-define=GOOGLE_SERVER_CLIENT_ID`, the iOS client from
/// `GOOGLE_IOS_CLIENT_ID`), Apple through sign_in_with_apple, never on the web.
class NativeSocialSignIn implements SocialSignIn {
  static const _googleServerClient = String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID');
  static const _googleIosClient = String.fromEnvironment('GOOGLE_IOS_CLIENT_ID');

  bool _googleReady = false;

  @override
  bool available(String provider) {
    if (kIsWeb) return false;
    return switch (provider) {
      'google' =>
        _googleServerClient.isNotEmpty && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS),
      'apple' => defaultTargetPlatform == TargetPlatform.iOS || defaultTargetPlatform == TargetPlatform.macOS,
      _ => false,
    };
  }

  @override
  Future<SocialCredential?> signIn(String provider) async {
    final nonce = _nonce();
    if (provider == 'apple') {
      try {
        final credential = await SignInWithApple.getAppleIDCredential(
          scopes: const [AppleIDAuthorizationScopes.email],
          // Apple puts this (the nonce's SHA-256) in the token; the API compares it with the raw one.
          nonce: sha256.convert(utf8.encode(nonce)).toString(),
        );
        final token = credential.identityToken;
        return token == null ? null : SocialCredential(provider: provider, idToken: token, nonce: nonce);
      } on SignInWithAppleAuthorizationException catch (e) {
        if (e.code == AuthorizationErrorCode.canceled) return null;
        rethrow;
      }
    }

    final google = GoogleSignIn.instance;
    if (!_googleReady) {
      await google.initialize(
        clientId: _googleIosClient.isEmpty ? null : _googleIosClient,
        serverClientId: _googleServerClient,
        nonce: nonce,
      );
      _googleReady = true;
    }
    try {
      final account = await google.authenticate();
      final token = account.authentication.idToken;
      // A nonce is set once per process (initialize); the API checks it only when it is sent.
      return token == null ? null : SocialCredential(provider: provider, idToken: token);
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled) return null;
      rethrow;
    }
  }

  static String _nonce() {
    final random = Random.secure();
    return base64Url.encode(List<int>.generate(24, (_) => random.nextInt(256))).replaceAll('=', '');
  }
}
