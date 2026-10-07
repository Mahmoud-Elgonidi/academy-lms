import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'account.dart';

/// Where secrets live: the Keychain / Keystore on a phone. Tests use [MemorySecrets].
abstract interface class Secrets {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

class DeviceSecrets implements Secrets {
  const DeviceSecrets([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) => _storage.write(key: key, value: value);

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

class MemorySecrets implements Secrets {
  final Map<String, String> values = {};

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async => values[key] = value;

  @override
  Future<void> delete(String key) async => values.remove(key);
}

/// The accounts on this device and which one is open, kept with their tokens
/// in secure storage.
class AccountStore {
  AccountStore(this._secrets);

  static const _accountsKey = 'lms.accounts';
  static const _currentKey = 'lms.current';

  final Secrets _secrets;

  Future<(List<Account>, String?)> load() async {
    final raw = await _secrets.read(_accountsKey);
    final current = await _secrets.read(_currentKey);
    if (raw == null) return (<Account>[], null);
    try {
      final list = (jsonDecode(raw) as List<dynamic>).map((a) => Account.fromJson(a as Map<String, dynamic>)).toList();
      return (list, current);
    } on FormatException {
      return (<Account>[], null);
    }
  }

  Future<void> save(List<Account> accounts, String? current) async {
    await _secrets.write(_accountsKey, jsonEncode(accounts.map((a) => a.toJson()).toList()));
    if (current == null) {
      await _secrets.delete(_currentKey);
    } else {
      await _secrets.write(_currentKey, current);
    }
  }
}
