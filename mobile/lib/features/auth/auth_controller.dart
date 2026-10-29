import 'package:flutter/foundation.dart';

import '../../core/api_client.dart';
import '../../core/local_store.dart';
import 'session_store.dart';

enum AuthStatus { initializing, signedOut, signedIn, reconnect }

class AuthController extends ChangeNotifier {
  AuthController(this.api, this.store, {LocalStore? localStore})
    : localStore = localStore ?? const SecureLocalStore();
  final LocalStore localStore;
  final ApiClient api;
  final SessionStore store;
  AuthStatus status = AuthStatus.initializing;
  bool busy = false;
  String? error;
  String? _token;
  Map<String, dynamic>? user;

  Future<void> initialize() async {
    status = AuthStatus.initializing;
    error = null;
    notifyListeners();
    try {
      _token = await store.read();
      if (_token == null) {
        status = AuthStatus.signedOut;
      } else {
        user = await api.request('auth/me', token: _token);
        status = AuthStatus.signedIn;
      }
    } on ApiFailure catch (e) {
      if (e.code == 'unauthorized') {
        try {
          await _clear();
        } catch (_) {
          error = 'storage';
          status = AuthStatus.reconnect;
        }
      } else {
        error = e.code;
        status = AuthStatus.reconnect;
      }
    } catch (_) {
      error = 'storage';
      status = AuthStatus.reconnect;
    }
    notifyListeners();
  }

  Future<void> login(
    String email,
    String password, {
    bool rememberMe = true,
  }) async {
    if (busy) return;
    busy = true;
    error = null;
    notifyListeners();
    try {
      final result = await api.request(
        'auth/login',
        body: {'email': email.trim().toLowerCase(), 'password': password},
      );
      final token = result['token'] as String;
      try {
        if (rememberMe) {
          await store.write(token);
        } else {
          await store.clear();
        }
      } catch (_) {
        // Best effort revoke if Android secure storage cannot retain the session.
        try {
          await api.request('auth/logout', token: token, body: {});
        } catch (_) {}
        rethrow;
      }
      _token = token;
      user = result['user'] as Map<String, dynamic>;
      status = AuthStatus.signedIn;
    } on ApiFailure catch (e) {
      error = e.code;
    } catch (_) {
      error = 'storage';
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  bool get isParent =>
      (user?['roles'] as List? ?? []).any(
        (r) => r['code'] == 'parent_guardian',
      ) &&
      !(user?['roles'] as List? ?? []).any(
        (r) => r['code'] != 'parent_guardian',
      );
  bool get isTeacher =>
      (user?['roles'] as List? ?? []).any((r) => r['code'] == 'teacher');
  bool get isSuperAdmin =>
      (user?['roles'] as List? ?? []).any((r) => r['code'] == 'super_admin');
  bool get canAcademics =>
      can('academics.read') ||
      can('academics.write') ||
      can('academics.review');
  Future<void> refreshProfile() async {
    if (status != AuthStatus.signedIn) return;
    try {
      user = await call('auth/me');
      notifyListeners();
    } on ApiFailure {
      /* Network loss keeps the existing secure session/offline drafts. */
    }
  }

  bool can(String permission) =>
      isSuperAdmin ||
      (user?['permissions'] as List? ?? []).contains(permission);
  Future<Map<String, dynamic>> call(
    String path, {
    Map<String, dynamic>? body,
    String? method,
  }) async {
    try {
      return await api.request(path, token: _token, body: body, method: method);
    } on ApiFailure catch (e) {
      if (e.code == 'unauthorized') {
        await _clear();
        notifyListeners();
      }
      rethrow;
    }
  }

  Future<Uint8List> download(String path) async {
    try {
      return await api.download(path, token: _token ?? '');
    } on ApiFailure catch (e) {
      if (e.code == 'unauthorized') {
        await _clear();
        notifyListeners();
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> upload(
    String path,
    String filePath,
    Map<String, String> fields,
  ) async {
    try {
      return await api.upload(
        path,
        token: _token ?? '',
        filePath: filePath,
        fields: fields,
      );
    } on ApiFailure catch (e) {
      if (e.code == 'unauthorized') {
        await _clear();
        notifyListeners();
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> requestParentCode(String cnic) =>
      api.request('parent-auth/request', body: {'cnic': cnic});
  Future<void> verifyParentCode(
    String challenge,
    String otp, {
    required bool rememberMe,
  }) async {
    if (busy) return;
    busy = true;
    error = null;
    notifyListeners();
    try {
      final data = await api.request(
        'parent-auth/verify',
        body: {'challenge_id': challenge, 'otp': otp},
      );
      final token = data['token'] as String;
      try {
        if (rememberMe) {
          await store.write(token);
        } else {
          await store.clear();
        }
      } catch (_) {
        try {
          await api.request('auth/logout', token: token, body: {});
        } catch (_) {}
        rethrow;
      }
      _token = token;
      user = data['user'] as Map<String, dynamic>;
      status = AuthStatus.signedIn;
    } on ApiFailure catch (e) {
      error = e.code;
    } catch (_) {
      error = 'storage';
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    if (busy) return;
    busy = true;
    error = null;
    notifyListeners();
    try {
      try {
        await api.request('auth/logout', token: _token, body: {});
      } on ApiFailure catch (e) {
        if (e.code != 'unauthorized') rethrow;
      }
      await _clear();
    } on ApiFailure catch (e) {
      error = e.code;
    } catch (_) {
      error = 'storage';
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> _clear() async {
    await store.clear();
    _token = null;
    user = null;
    status = AuthStatus.signedOut;
  }
}
