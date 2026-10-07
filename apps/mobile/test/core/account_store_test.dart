import 'package:academy_app/app/theme/branding.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/core/auth/account_store.dart';
import 'package:academy_app/features/auth/domain/sign_in_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

void main() {
  test('keeps several accounts and the open one in secure storage', () async {
    final secrets = MemorySecrets();
    final store = AccountStore(secrets);
    final teacher = guardian.copyWith(stage: 'classic');
    final other = Account(
      academySlug: 'rawda',
      academyNameAr: 'الروضة',
      academyNameEn: 'Rawda',
      userId: 'u2',
      userName: 'هالة',
      roles: const ['teacher'],
      token: 't2',
      expiresAt: DateTime.utc(2026, 11, 6),
    );
    await store.save([teacher, other], other.id);
    final (accounts, current) = await store.load();
    expect(accounts, [teacher, other]);
    expect(current, 'rawda:u2');
    expect(accounts.last.expiresAt, DateTime.utc(2026, 11, 6));
    expect(accounts.last.isTeacher, isTrue);
    expect(accounts.first.isGuardian && accounts.first.hasAppArea, isTrue);
  });

  test('a broken store reads as empty', () async {
    final secrets = MemorySecrets()..values['lms.accounts'] = 'not json';
    expect((await AccountStore(secrets).load()).$1, isEmpty);
  });

  test('a sign-in answer becomes an account (expiry as an instant or a string)', () {
    final user = {
      'id': 'u1',
      'name': 'رامي',
      'roles': ['guardian'],
      'academy': {'slug': 'demo', 'name_ar': 'النور', 'name_en': 'Noor'},
    };
    final a = accountFromToken({'token': 't', 'expires_at': time('2026-11-06T09:00:00Z', '2026-11-06T12:00:00+03:00'), 'user': user});
    expect(a.id, 'demo:u1');
    expect(a.expiresAt, DateTime.utc(2026, 11, 6, 9));
    final b = accountFromToken({'token': 't', 'expires_at': '2026-11-06T09:00:00Z', 'user': user});
    expect(b.expiresAt, DateTime.utc(2026, 11, 6, 9));
    expect(a.expired(DateTime.utc(2026, 12)), isTrue);
  });

  test('branding reads the academy colours, keeping defaults for gaps', () {
    final b = Branding.fromJson(branding()['data']! as Map<String, dynamic>);
    expect(b.primary, const Color(0xFF1D5FB8));
    expect(Branding.hex(b.primary), '#1D5FB8');
    expect(Branding.fromJson(const {}).primary, Branding.fallback.primary);
    expect(Branding.parseColour('nope'), isNull);
  });
}
