import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/app/school_app.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/home/widgets/module_card.dart';
import 'package:school/l10n/strings.dart';
import 'package:school/shared/widgets/school_brand.dart';

import 'support/auth_fixtures.dart';

Future<void> fillLogin(WidgetTester tester) async {
  await tester.ensureVisible(find.byKey(const Key('email-field')));
  await tester.enterText(
    find.byKey(const Key('email-field')),
    'staff@example.test',
  );
  await tester.ensureVisible(find.byKey(const Key('password-field')));
  await tester.enterText(
    find.byKey(const Key('password-field')),
    'test-password',
  );
}

void main() {
  test(
    'remembered session stores only token and restores through API',
    () async {
      final store = MemoryStore();
      final auth = createAuth(store);
      await auth.initialize();
      await auth.login(
        'staff@example.test',
        'private-password',
        rememberMe: true,
      );
      expect(store.writtenValues, ['test-token']);
      final restored = createAuth(store);
      await restored.initialize();
      expect(restored.status, AuthStatus.signedIn);
      expect(restored.user?['name'], 'Test Staff');
    },
  );

  test('unchecked remember me keeps token in memory and clears old persisted token', () async {
    final store = MemoryStore()..token = 'old-token';
    final auth = createAuth(store);
    await auth.login(
      'staff@example.test',
      'private-password',
      rememberMe: false,
    );
    expect(auth.status, AuthStatus.signedIn);
    expect(store.token, isNull);
    expect(store.writes, 0);
    final restarted = createAuth(store);
    await restarted.initialize();
    expect(restarted.status, AuthStatus.signedOut);
    await auth.logout();
    expect(auth.status, AuthStatus.signedOut);
  });

  testWidgets('password visibility, field validation and honest reset help', (
    tester,
  ) async {
    final store = MemoryStore();
    final auth = createAuth(store);
    await auth.initialize();
    await tester.pumpWidget(SchoolApp(auth: auth));
    await tester.ensureVisible(find.byKey(const Key('sign-in')));
    await tester.tap(find.byKey(const Key('sign-in')));
    await tester.pumpAndSettle();
    expect(find.text('Enter a valid email address.'), findsOneWidget);
    expect(store.writes, 0);
    await tester.ensureVisible(find.byKey(const Key('password-visibility')));
    final editable = find.descendant(
      of: find.byKey(const Key('password-field')),
      matching: find.byType(EditableText),
    );
    expect(tester.widget<EditableText>(editable).obscureText, isTrue);
    await tester.tap(find.byKey(const Key('password-visibility')));
    await tester.pump();
    expect(tester.widget<EditableText>(editable).obscureText, isFalse);
    await tester.ensureVisible(find.byKey(const Key('forgot-password')));
    await tester.tap(find.byKey(const Key('forgot-password')));
    await tester.pumpAndSettle();
    expect(
      find.textContaining('Password reset is not available'),
      findsOneWidget,
    );
    await tester.tap(find.text('Got it'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'remember checkbox controls secure session persistence from the form',
    (tester) async {
      final store = MemoryStore();
      final auth = createAuth(store);
      await auth.initialize();
      await tester.pumpWidget(SchoolApp(auth: auth));
      await fillLogin(tester);
      await tester.ensureVisible(find.byKey(const Key('remember-me')));
      await tester.tap(find.byKey(const Key('remember-me')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('sign-in')));
      await tester.tap(find.byKey(const Key('sign-in')));
      await tester.pumpAndSettle();
      expect(store.writtenValues, ['test-token']);
      expect(find.text('Test Staff'), findsOneWidget);
    },
  );

  testWidgets(
    'sign in is disabled during request and network failure is visible',
    (tester) async {
      final pending = Completer<http.Response>();
      final auth = createAuth(MemoryStore(), handler: (_) => pending.future);
      await auth.initialize();
      await tester.pumpWidget(SchoolApp(auth: auth));
      await fillLogin(tester);
      await tester.ensureVisible(find.byKey(const Key('sign-in')));
      await tester.tap(find.byKey(const Key('sign-in')));
      await tester.pump();
      expect(
        tester.widget<FilledButton>(find.byKey(const Key('sign-in'))).onPressed,
        isNull,
      );
      expect(find.text('Signing in…'), findsOneWidget);
      pending.completeError(http.ClientException('offline'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Cannot reach the server'), findsOneWidget);
      expect(
        tester.widget<FilledButton>(find.byKey(const Key('sign-in'))).onPressed,
        isNotNull,
      );
      expect(
        tester
            .widget<TextFormField>(find.byKey(const Key('password-field')))
            .controller!
            .text,
        isEmpty,
      );
    },
  );

  testWidgets('account details are real and logout can be cancelled', (
    tester,
  ) async {
    final store = MemoryStore();
    final auth = createAuth(store);
    await auth.login('staff@example.test', 'test-password');
    await tester.pumpWidget(SchoolApp(auth: auth));
    // Teachers also have the assignment-scoped My classes workspace.
    expect(find.byType(ModuleCard), findsNWidgets(6));
    expect(find.text('Coming later'), findsNothing);
    expect(find.text('Fees & Finance'), findsNothing);
    await tester.tap(find.byKey(const Key('account-menu')));
    await tester.pumpAndSettle();
    expect(find.text('staff@example.test'), findsOneWidget);
    expect(
      find.descendant(
        of: find.byType(BottomSheet),
        matching: find.text('Test Staff'),
      ),
      findsOneWidget,
    );
    await tester.tap(find.byTooltip('Close'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('home-logout')));
    await tester.tap(find.byKey(const Key('home-logout')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('cancel-logout')));
    await tester.pumpAndSettle();
    expect(auth.status, AuthStatus.signedIn);
    expect(store.token, 'test-token');
  });

  for (final size in [
    const Size(320, 640),
    const Size(390, 844),
    const Size(768, 1024),
    const Size(1440, 900),
  ]) {
    testWidgets(
      'login and dashboard adapt at ${size.width} with Urdu and keyboard',
      (tester) async {
        tester.view.physicalSize = size;
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final auth = createAuth(MemoryStore());
        await auth.initialize();
        await tester.pumpWidget(SchoolApp(auth: auth));
        await tester.pumpAndSettle();
        expect(find.byType(CampusHero), findsOneWidget);
        await tester.tap(find.byKey(const Key('language-toggle')));
        await tester.pumpAndSettle();
        expect(
          Directionality.of(tester.element(find.text('عملے کا لاگ اِن'))),
          TextDirection.rtl,
        );
        tester.view.viewInsets = const FakeViewPadding(bottom: 280);
        addTearDown(tester.view.resetViewInsets);
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.byKey(const Key('sign-in')));
        await tester.pumpAndSettle();
        expect(
          tester.getRect(find.byKey(const Key('sign-in'))).bottom,
          lessThanOrEqualTo(size.height - 280),
        );
        expect(tester.takeException(), isNull);
        tester.view.resetViewInsets();
        await auth.login('staff@example.test', 'test-password');
        await tester.pumpAndSettle();
        expect(find.byType(ModuleCard), findsNWidgets(6));
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'system appearance follows platform brightness and can be overridden',
    (tester) async {
      tester.platformDispatcher.platformBrightnessTestValue = Brightness.dark;
      addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);
      final auth = createAuth(MemoryStore());
      await auth.initialize();
      await tester.pumpWidget(SchoolApp(auth: auth));
      await tester.pumpAndSettle();
      expect(
        Theme.of(tester.element(find.text('Staff sign in'))).brightness,
        Brightness.dark,
      );
      await tester.tap(find.byKey(const Key('theme-menu')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Light theme'));
      await tester.pumpAndSettle();
      expect(
        Theme.of(tester.element(find.text('Staff sign in'))).brightness,
        Brightness.light,
      );
    },
  );

  test('every English string has a nonempty Urdu translation', () {
    expect(Strings.ur.keys.toSet(), Strings.en.keys.toSet());
    expect(Strings.ur.values.every((v) => v.trim().isNotEmpty), isTrue);
  });
}
