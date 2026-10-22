import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/theme/stage.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/core/auth/account_store.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:academy_app/core/network/api_problem.dart';
import 'package:academy_app/core/prefs/app_prefs.dart';
import 'package:academy_app/core/time/clock_style.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../helpers.dart';

Future<void> _settle() => Future<void>.delayed(const Duration(milliseconds: 250));

const _teacher = Account(
  academySlug: 'rawda',
  academyNameAr: 'الروضة',
  academyNameEn: 'Rawda',
  userId: 'u-teacher',
  userName: 'هالة منصور',
  roles: ['teacher'],
  token: 'token-teacher',
);

void main() {
  test('opens the saved account, drops expired ones, and takes the academy look', () async {
    final expired = _teacher.copyWith();
    final app = await TestApp.create(
      accounts: [
        Account(
          academySlug: 'old',
          academyNameAr: 'قديمة',
          academyNameEn: 'Old',
          userId: 'x',
          userName: 'x',
          roles: const ['guardian'],
          token: 't',
          expiresAt: DateTime.utc(2020),
        ),
        guardian,
        expired,
      ],
      current: 'old:x',
    );
    familyRoutes(app.api);
    await app.session.start();
    await _settle();
    final s = app.session.state;
    expect(s.status, SessionStatus.signedIn);
    expect(s.accounts.map((a) => a.id), ['demo:u-guardian', 'rawda:u-teacher']);
    expect(s.current?.id, guardian.id);
    expect(s.branding.primary, const Color(0xFF1D5FB8));
    expect(Area.of(s), Area.family);
    expect((await AccountStore(app.secrets).load()).$1, hasLength(2));
  });

  test('a student opens in their stage from the first frame', () async {
    const kid = Account(
      academySlug: 'demo',
      academyNameAr: 'النور',
      academyNameEn: 'Noor',
      userId: 'u-kid',
      userName: 'يوسف',
      roles: ['student'],
      token: 'token-kid',
    );
    final app = await TestApp.create();
    familyRoutes(app.api);
    app.api.on('GET', 'family/learners', {'data': <Object>[], 'viewer_stage': 'kids'});
    await app.session.start();
    final opened = <SessionState>[];
    final sub = app.session.stream.listen(opened.add);
    await app.session.add(kid);
    await _settle();
    await sub.cancel();
    final first = opened.firstWhere((s) => s.status == SessionStatus.signedIn);
    expect(first.stage, Stage.kids);
    expect(Area.of(first), Area.kids);
  });

  test('switching, signing out (with the right token), and a refused token', () async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('POST', 'auth/logout', null, status: 204);
    await app.session.start();
    await app.session.add(_teacher);
    await _settle();
    expect(app.session.state.current?.id, _teacher.id);
    expect(Area.of(app.session.state), Area.teacher);

    await app.session.open(guardian.id);
    expect(app.session.state.current?.id, guardian.id);

    await app.session.signOut(_teacher.id);
    await _settle();
    final logout = app.api.sent('POST', 'auth/logout').single;
    expect(logout.headers['Authorization'], 'Bearer token-teacher');
    expect(logout.headers['X-Academy'], 'rawda');
    expect(app.session.state.accounts.map((a) => a.id), [guardian.id]);
    expect(app.session.state.current?.id, guardian.id);

    app.api.on('GET', 'me', {'type': 'unauthenticated', 'title': 'x', 'status': 401}, status: 401);
    await app.session.refresh();
    await _settle();
    expect(app.session.state.status, SessionStatus.signedOut);
    expect(app.session.state.notice, SessionNotice.expired);
  });

  test('the clock: offered when the phone differs, kept when asked', () async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'me', {
      'data': {'name': 'رامي', 'timezone': 'America/New_York', 'display_timezone': 'Asia/Riyadh', 'timezone_mismatch': true},
    });
    await app.session.start();
    await _settle();
    expect(app.session.state.offerClock, isTrue);
    await app.session.keepProfileClock();
    expect(app.session.state.offerClock, isFalse);
    expect(app.holder.timezone, 'America/New_York');
  });

  test('how a clock is written: the academy\'s until the person chooses, at once, remembered, and back when refused', () async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'me', me(academyTimeFormat: 'h24'))
      ..onWith('PATCH', 'me', (r) {
        final choice = (r.data as Map<String, dynamic>)['time_format'] as String?;
        return (200, {'data': meJson(timeFormat: choice, academyTimeFormat: 'h24')});
      });
    expect(app.session.state.timeFormat, TimeFormat.h12);
    await app.session.start();
    await _settle();
    var s = app.session.state;
    expect(s.timeFormat, TimeFormat.h24);
    expect(s.me?.timeFormatChoice, isNull);
    expect(s.me?.academyTimeFormat, TimeFormat.h24);
    // The next opening on this phone starts with it, before the profile loads.
    expect(await AppPrefs(SharedPreferencesAsync()).timeFormat(guardian.id), TimeFormat.h24);

    final seen = <SessionState>[];
    final sub = app.session.stream.listen(seen.add);
    await app.session.setTimeFormat(TimeFormat.h12);
    expect(seen.first.timeFormat, TimeFormat.h12, reason: 'shown before the API answers');
    expect(app.api.sent('PATCH', 'me').single.data, {'time_format': 'h12'});
    s = app.session.state;
    expect(s.timeFormat, TimeFormat.h12);
    expect(s.me?.timeFormatChoice, TimeFormat.h12);
    expect(await AppPrefs(SharedPreferencesAsync()).timeFormat(guardian.id), TimeFormat.h12);

    // Back to the academy's way: null is sent, not left out.
    await app.session.setTimeFormat(null);
    expect(app.api.sent('PATCH', 'me').last.data, {'time_format': null});
    expect(app.session.state.timeFormat, TimeFormat.h24);
    expect(app.session.state.me?.timeFormatChoice, isNull);

    app.api.on('PATCH', 'me', {'type': 'validation', 'title': 'x', 'status': 422}, status: 422);
    await expectLater(app.session.setTimeFormat(TimeFormat.h12), throwsA(isA<ApiProblem>()));
    expect(app.session.state.timeFormat, TimeFormat.h24);
    expect(app.session.state.me?.timeFormatChoice, isNull);
    await sub.cancel();

    // Signed out: 12 hours, and the phone forgets the account's way.
    await app.session.signOut();
    await _settle();
    expect(app.session.state.timeFormat, TimeFormat.h12);
    expect(await AppPrefs(SharedPreferencesAsync()).timeFormat(guardian.id), isNull);
  });
}
