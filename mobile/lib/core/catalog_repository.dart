import 'api_client.dart';
import '../features/auth/auth_controller.dart';
import '../features/offline/draft_queue.dart';

Future<Map<String, dynamic>> cachedCatalog(
  AuthController auth,
  String catalog,
) async {
  final owner = auth.user?['id'] as String?;
  final cache = owner == null ? null : ReadCache(auth.localStore, owner);
  try {
    final data = await auth.call('catalog/$catalog');
    try {
      await cache?.write('catalog:$catalog', data);
    } catch (_) {}
    return data;
  } on ApiFailure catch (e) {
    if (e.code == 'network') {
      final data = await cache?.read('catalog:$catalog');
      if (data != null) return data;
    }
    rethrow;
  }
}
