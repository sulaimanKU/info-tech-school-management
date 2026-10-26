import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../../core/api_client.dart';
import '../../core/local_store.dart';
import '../../core/operation_id.dart';
import '../auth/auth_controller.dart';

class DraftQueue extends ChangeNotifier {
  DraftQueue(this.ownerId, this.store);
  final String ownerId;
  final LocalStore store;
  final List<Map<String, dynamic>> items = [];
  bool syncing = false;
  bool disposed = false;
  void announce() {
    if (!disposed) notifyListeners();
  }

  @override
  void dispose() {
    disposed = true;
    super.dispose();
  }

  String get key => 'drafts:$ownerId';
  Future<void> load() async {
    final raw = await store.read(key);
    items.clear();
    if (raw != null) {
      items.addAll(
        (jsonDecode(raw) as List).map(
          (v) => (v as Map).cast<String, dynamic>(),
        ),
      );
    }
    announce();
  }

  Future<void> _persist() async {
    await store.write(key, jsonEncode(items));
    announce();
  }

  Future<String> put({
    required String kind,
    required String path,
    required Map<String, dynamic> payload,
    required bool pending,
    String? id,
    String method = 'POST',
  }) async {
    if (!['attendance', 'diary'].contains(kind)) {
      throw ArgumentError('Only safe academic drafts can be queued.');
    }
    final existing = items.where((v) => v['id'] == id).firstOrNull;
    final operation = existing != null && existing['state'] != 'saved'
        ? existing['id'] as String
        : operationId();
    items.removeWhere((v) => v['id'] == operation);
    final body = jsonDecode(jsonEncode(payload)) as Map<String, dynamic>;
    if (kind == 'attendance') body['request_id'] = operation;
    if (kind == 'diary') body['client_id'] = body['client_id'] ?? operation;
    items.add({
      'id': operation,
      'kind': kind,
      'path': path,
      'method': method,
      'payload': body,
      'state': pending ? 'pendingSync' : 'draft',
      'attempts': 0,
      'updated_at': DateTime.now().toUtc().toIso8601String(),
    });
    await _persist();
    return operation;
  }

  Future<void> sync(AuthController auth, {String? only}) async {
    if (syncing) return;
    if (auth.user?['id'] != ownerId) {
      throw StateError('Draft owner differs from signed-in user.');
    }
    syncing = true;
    announce();
    try {
      for (final item in items) {
        if (auth.user?['id'] != ownerId) break;
        if (only != null && item['id'] != only) continue;
        if (!['pendingSync', 'retry'].contains(item['state'])) continue;
        try {
          final result = await auth.call(
            item['path'] as String,
            method: item['method'] as String,
            body: (item['payload'] as Map).cast<String, dynamic>(),
          );
          item['state'] = 'saved';
          item['result'] = result;
          item.remove('error');
        } on ApiFailure catch (e) {
          item['state'] = e.code == 'conflict' ? 'syncConflict' : 'retry';
          item['error'] = e.code;
        } catch (_) {
          item['state'] = 'retry';
          item['error'] = 'network';
        }
        item['attempts'] = (item['attempts'] as int) + 1;
        await _persist();
        if (item['error'] == 'unauthorized') break;
      }
    } finally {
      syncing = false;
      announce();
    }
  }

  Future<void> discard(String id) async {
    items.removeWhere((v) => v['id'] == id);
    await _persist();
  }
}

class ReadCache {
  const ReadCache(this.store, this.ownerId);
  final LocalStore store;
  final String ownerId;
  Future<void> write(String key, Map<String, dynamic> data) => store.write(
    'cache:$ownerId:$key',
    jsonEncode({
      'saved_at': DateTime.now().toUtc().toIso8601String(),
      'data': data,
    }),
  );
  Future<Map<String, dynamic>?> read(String key) async {
    final raw = await store.read('cache:$ownerId:$key');
    if (raw == null) return null;
    final data = jsonDecode(raw) as Map<String, dynamic>;
    if (DateTime.now().toUtc().difference(
          DateTime.parse(data['saved_at'] as String),
        ) >
        const Duration(days: 2)) {
      return null;
    }
    return (data['data'] as Map).cast<String, dynamic>();
  }
}
