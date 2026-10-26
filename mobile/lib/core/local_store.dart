import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class LocalStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> remove(String key);
}

class SecureLocalStore implements LocalStore {
  const SecureLocalStore();
  static const _storage = FlutterSecureStorage();
  @override
  Future<String?> read(String key) => _storage.read(key: 'school:$key');
  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: 'school:$key', value: value);
  @override
  Future<void> remove(String key) => _storage.delete(key: 'school:$key');
}
