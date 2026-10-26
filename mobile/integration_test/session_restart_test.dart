import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:school/app/school_app.dart';
import 'package:school/core/api_client.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/shared/widgets/summary_card.dart';

import 'final_ui_workflows_test.dart'
    show ValidationSession, ValidationLocal, fixturePassword;

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  const prepare = bool.fromEnvironment('PREPARE_SESSION');
  testWidgets(
    prepare
        ? 'persist fictional session before process stop'
        : 'restore secure session after real process restart',
    (tester) async {
      const store = ValidationSession();
      final auth = AuthController(
        ApiClient(),
        store,
        localStore: const ValidationLocal(),
      );
      if (prepare) {
        await store.clear();
        await auth.login(
          'md@fictional.example.test',
          fixturePassword,
          rememberMe: true,
        );
        expect(auth.status, AuthStatus.signedIn);
        final saved = await store.read();
        expect(saved?.length, 64);
        expect(saved, isNot(contains(fixturePassword)));
        await tester.pumpWidget(const SizedBox());
        return;
      }
      expect(await store.read(), isNotNull);
      await auth.initialize();
      expect(auth.status, AuthStatus.signedIn);
      expect(auth.isSuperAdmin, true);
      await tester.pumpWidget(SchoolApp(auth: auth));
      await tester.pumpAndSettle();
      for (
        var i = 0;
        i < 100 && find.byType(SummaryCard).evaluate().isEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(find.byType(SummaryCard), findsNWidgets(4));
      await binding.convertFlutterSurfaceToImage();
      await tester.pumpAndSettle();
      await binding.takeScreenshot('restored-session');
      await auth.logout();
      expect(await store.read(), isNull);
      expect(auth.status, AuthStatus.signedOut);
      await tester.pumpAndSettle();
      await tester.pumpWidget(const SizedBox());
    },
  );
}
