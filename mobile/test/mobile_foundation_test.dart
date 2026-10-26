import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/app/app_preferences.dart';
import 'package:school/app/school_app.dart';
import 'package:school/core/local_store.dart';
import 'package:school/features/auth/auth_controller.dart';

import 'support/auth_fixtures.dart';

class MemoryLocalStore implements LocalStore {
  final values = <String, String>{};
  @override
  Future<String?> read(String key) async => values[key];
  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> remove(String key) async {
    values.remove(key);
  }
}

void main() {
  test('theme and language survive a new preference controller', () async {
    final store = MemoryLocalStore();
    await AppPreferences(store)
        .save(locale: const Locale('ur'), mode: ThemeMode.dark);
    final restored = AppPreferences(store);
    await restored.load();
    expect(restored.locale, const Locale('ur'));
    expect(restored.mode, ThemeMode.dark);
  });
  test('session expiry on an operational request signs out', () async {
    final store = MemoryStore()..token = 'expired';
    final auth = createAuth(
      store,
      handler: (_) async => http.Response('{}', 401),
    );
    expect(() => auth.call('students'), throwsA(isA<Exception>()));
    await Future<void>.delayed(Duration.zero);
    expect(auth.status, AuthStatus.signedOut);
    expect(store.token, isNull);
  });
  testWidgets('parent can request and verify OTP through a separate flow', (
    tester,
  ) async {
    final store = MemoryStore();
    final paths = <String>[];
    final auth = createAuth(
      store,
      handler: (r) async {
        paths.add(r.url.path);
        if (r.url.path.endsWith('parent/home')) {
          return http.Response(
            jsonEncode({
              'data': {
                'children': [],
                'guardian': {
                  'full_name': 'Fictional Parent',
                  'cnic_masked': '*********0001',
                },
              },
            }),
            200,
          );
        }
        if (r.url.path.endsWith('request')) {
          expect(jsonDecode(r.body)['cnic'], '0000000000001');
          return http.Response(
            jsonEncode({
              'data': {
                'challenge_id': 'test-challenge',
                'resend_after': 60,
                'expires_in': 300,
              },
            }),
            202,
          );
        }
        return http.Response(
          jsonEncode({
            'data': {
              'token': 'parent-token',
              'user': {
                'id': 'parent-test',
                'name': 'Fictional Parent',
                'roles': [
                  {'code': 'parent_guardian', 'name': 'Parent / Guardian'},
                ],
                'permissions': [],
              },
            },
          }),
          200,
        );
      },
    );
    await auth.initialize();
    await tester.pumpWidget(SchoolApp(auth: auth));
    await tester.ensureVisible(find.text('Parent login'));
    await tester.tap(find.text('Parent login'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('parent-cnic')));
    await tester.enterText(
      find.byKey(const Key('parent-cnic')),
      '0000000000001',
    );
    await tester.ensureVisible(find.text('Request code'));
    await tester.tap(find.text('Request code'));
    await tester.pumpAndSettle();
    expect(find.textContaining('If your account is eligible'), findsOneWidget);
    await tester.ensureVisible(find.byKey(const Key('parent-otp')));
    await tester.enterText(find.byKey(const Key('parent-otp')), '123456');
    await tester.ensureVisible(find.text('Verify and sign in'));
    await tester.tap(find.text('Verify and sign in'));
    await tester.pumpAndSettle();
    expect(auth.isParent, isTrue);
    expect(store.token, isNull);
    expect(paths, [
      '/api/v1/parent-auth/request',
      '/api/v1/parent-auth/verify',
      '/api/v1/parent/home',
    ]);
    expect(find.text('Fictional Parent'), findsWidgets);
    await tester.pumpWidget(const SizedBox()); // Disposes countdown timer.
  });
}
