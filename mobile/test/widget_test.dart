import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:school/app/school_app.dart';
import 'package:school/core/api_client.dart';
import 'package:school/features/auth/auth_controller.dart';

import 'support/auth_fixtures.dart';

void main() {
  testWidgets('valid login form reaches role home and signs out', (
    tester,
  ) async {
    final store = MemoryStore();
    final auth = AuthController(
      ApiClient(
        baseUrl: 'http://localhost/api/v1',
        client: MockClient((r) async {
          return http.Response(
            r.url.path.endsWith('login')
                ? '{"data":{"token":"test-token","user":{"name":"Test Staff","roles":[{"code":"teacher","name":"Teacher"}]}}}'
                : '{"data":{"logged_out":true}}',
            200,
          );
        }),
      ),
      store,
    );
    await auth.initialize();
    await tester.pumpWidget(SchoolApp(auth: auth));
    await tester.enterText(
      find.byType(TextFormField).at(0),
      'staff@example.test',
    );
    await tester.enterText(find.byType(TextFormField).at(1), 'test-only');
    await tester.ensureVisible(find.text('Sign in'));
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();
    expect(find.text('Test Staff'), findsOneWidget);
    expect(find.text('Teacher'), findsOneWidget);
    await tester.ensureVisible(find.text('Sign out'));
    await tester.tap(find.byKey(const Key('home-logout')));
    await tester.pumpAndSettle();
    expect(find.text('Sign out of your account?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-logout')));
    await tester.pumpAndSettle();
    expect(find.text('Staff sign in'), findsOneWidget);
    expect(store.token, isNull);
    expect(tester.takeException(), isNull);
  });

  testWidgets('signed out shell supports Urdu RTL and dark theme', (
    tester,
  ) async {
    final auth = AuthController(ApiClient(), MemoryStore());
    await auth.initialize();
    await tester.pumpWidget(SchoolApp(auth: auth));
    expect(find.text('Staff sign in'), findsOneWidget);
    await tester.tap(find.byKey(const Key('language-toggle')));
    await tester.pumpAndSettle();
    expect(find.text('عملے کا لاگ اِن'), findsOneWidget);
    expect(
      Directionality.of(tester.element(find.text('عملے کا لاگ اِن'))),
      TextDirection.rtl,
    );
    await tester.tap(find.byKey(const Key('theme-menu')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('گہری تھیم'));
    await tester.pumpAndSettle();
    expect(
      Theme.of(tester.element(find.text('عملے کا لاگ اِن'))).brightness,
      Brightness.dark,
    );
  });
  test('login stores session, loads user and logout revokes it', () async {
    final store = MemoryStore();
    final paths = <String>[];
    final api = ApiClient(
      baseUrl: 'http://localhost/api/v1',
      client: MockClient((r) async {
        paths.add(r.url.path);
        if (r.url.path.endsWith('login')) {
          expect(jsonDecode(r.body), {
            'email': 'staff@example.test',
            'password': 'test-only',
          });
          expect((jsonDecode(r.body) as Map).containsKey('login'), false);
          return http.Response(
            '{"data":{"token":"test-token","user":{"name":"Test Staff","roles":[]}}}',
            200,
          );
        }
        expect(r.headers['Authorization'], 'Bearer test-token');
        return http.Response('{"data":{"logged_out":true}}', 200);
      }),
    );
    final auth = AuthController(api, store);
    await auth.initialize();
    await auth.login('  STAFF@EXAMPLE.TEST  ', 'test-only');
    expect(auth.status, AuthStatus.signedIn);
    expect(store.token, 'test-token');
    await auth.logout();
    expect(auth.status, AuthStatus.signedOut);
    expect(store.token, isNull);
    expect(paths, ['/api/v1/auth/login', '/api/v1/auth/logout']);
  });
  test(
    'temporary network failure retains stored token and requires retry',
    () async {
      final store = MemoryStore()..token = 'existing';
      final auth = AuthController(
        ApiClient(
          baseUrl: 'http://localhost/api/v1',
          client: MockClient(
            (_) async => throw http.ClientException('offline'),
          ),
        ),
        store,
      );
      await auth.initialize();
      expect(auth.status, AuthStatus.reconnect);
      expect(store.token, 'existing');
    },
  );
  test('expired server session clears local storage', () async {
    final store = MemoryStore()..token = 'expired';
    final auth = AuthController(
      ApiClient(
        baseUrl: 'http://localhost/api/v1',
        client: MockClient((_) async => http.Response('{}', 401)),
      ),
      store,
    );
    await auth.initialize();
    expect(auth.status, AuthStatus.signedOut);
    expect(store.token, isNull);
  });
  test('failed server logout retains session for a revocation retry', () async {
    final store = MemoryStore()..token = 'existing';
    final auth = AuthController(
      ApiClient(
        baseUrl: 'http://localhost/api/v1',
        client: MockClient(
          (r) async => http.Response(
            r.method == 'GET' ? '{"data":{"name":"Test","roles":[]}}' : '{}',
            r.method == 'GET' ? 200 : 500,
          ),
        ),
      ),
      store,
    );
    await auth.initialize();
    await auth.logout();
    expect(auth.status, AuthStatus.signedIn);
    expect(store.token, 'existing');
    expect(auth.error, 'server');
  });
}
