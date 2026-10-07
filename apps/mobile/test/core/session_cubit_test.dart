import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/theme/stage.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/core/auth/account_store.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

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
}
