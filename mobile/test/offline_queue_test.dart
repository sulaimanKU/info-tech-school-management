import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/offline/draft_queue.dart';

import 'support/auth_fixtures.dart';
import 'mobile_foundation_test.dart' show MemoryLocalStore;

void main() {
  test('offline pending operation is retained and later acknowledged with same request ID', () async {
    final local = MemoryLocalStore();
    final queue = DraftQueue('test-user', local);
    await queue.load();
    final id = await queue.put(
      kind: 'attendance',
      path: 'attendance/register',
      payload: {'version': 0, 'records': []},
      pending: true,
    );
    final auth = createAuth(
      MemoryStore(),
      handler: (_) async => throw http.ClientException('offline'),
    );
    auth.user = testUser;
    await queue.sync(auth);
    expect(queue.items.single['state'], 'retry');
    expect(queue.items.single['payload']['request_id'], id);
    final restored = DraftQueue('test-user', local);
    await restored.load();
    expect(restored.items.single['id'], id);
    final online = createAuth(
      MemoryStore(),
      handler: (_) async =>
          http.Response('{"data":{"id":"record","version":1}}', 200),
    );
    online.user = testUser;
    await restored.sync(online);
    expect(restored.items.single['state'], 'saved');
  });
  test(
    'conflicts require review and finance cannot enter draft queue',
    () async {
      final queue = DraftQueue('test-user', MemoryLocalStore());
      await queue.put(
        kind: 'diary',
        path: 'diary',
        payload: {'version': 1},
        pending: true,
      );
      final auth = createAuth(
        MemoryStore(),
        handler: (_) async => http.Response('{}', 409),
      );
      auth.user = testUser;
      await queue.sync(auth);
      expect(queue.items.single['state'], 'syncConflict');
      expect(
        () => queue.put(
          kind: 'payment',
          path: 'payments',
          payload: {},
          pending: true,
        ),
        throwsArgumentError,
      );
      final wrong = createAuth(MemoryStore())..user = {'id': 'different-user'};
      expect(() => queue.sync(wrong), throwsStateError);
    },
  );
}
