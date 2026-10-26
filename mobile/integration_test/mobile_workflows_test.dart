import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:school/app/school_app.dart';
import 'package:school/app/app_preferences.dart';
import 'package:school/core/api_client.dart';
import 'package:school/core/local_store.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/auth/session_store.dart';
import 'package:school/features/academics/attendance_screen.dart';
import 'package:school/features/academics/diary_editor.dart';
import 'package:school/features/communication/ticket_screen.dart';
import 'package:school/features/home/widgets/module_card.dart';
import 'package:school/core/operation_id.dart';

// Fictional credentials only. Seeder refuses every database except infotech_school_test.
const fixturePassword = 'Fictional-validation-only-2026';

class ValidationSession implements SessionStore {
  const ValidationSession();
  static const storage = FlutterSecureStorage();
  @override
  Future<String?> read() => storage.read(key: 'validation:session');
  @override
  Future<void> write(String value) =>
      storage.write(key: 'validation:session', value: value);
  @override
  Future<void> clear() => storage.delete(key: 'validation:session');
}

class ValidationLocal implements LocalStore {
  const ValidationLocal();
  static const storage = FlutterSecureStorage();
  @override
  Future<String?> read(String key) => storage.read(key: 'validation:$key');
  @override
  Future<void> write(String key, String value) =>
      storage.write(key: 'validation:$key', value: value);
  @override
  Future<void> remove(String key) => storage.delete(key: 'validation:$key');
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'real staff, parent OTP, teacher attendance/diary, coordinator and preferences',
    (tester) async {
      const store = ValidationSession();
      const local = ValidationLocal();
      await store.clear();
      await local.remove('preferences');
      final auth = AuthController(ApiClient(), store, localStore: local);
      await auth.initialize();
      final prefs = AppPreferences(local);
      await prefs.save(locale: const Locale('en'), mode: ThemeMode.light);
      await tester.pumpWidget(
        SchoolApp(key: UniqueKey(), auth: auth, preferences: prefs),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('email-field')));
      await tester.enterText(
        find.byKey(const Key('email-field')),
        'md@fictional.example.test',
      );
      await tester.enterText(
        find.byKey(const Key('password-field')),
        fixturePassword,
      );
      await tester.ensureVisible(find.byKey(const Key('remember-me')));
      await tester.tap(find.byKey(const Key('remember-me')));
      await tester.ensureVisible(find.byKey(const Key('sign-in')));
      await tester.tap(find.byKey(const Key('sign-in')));
      await tester.pumpAndSettle();
      expect(auth.status, AuthStatus.signedIn);
      expect(find.byType(ModuleCard), findsNWidgets(8));
      expect(auth.can('finance.approve'), true);
      final stored = await store.read();
      expect(stored, isNotNull);
      expect(stored, isNot(contains(fixturePassword)));
      final restored = AuthController(ApiClient(), store, localStore: local);
      await restored.initialize();
      expect(restored.status, AuthStatus.signedIn);
      await auth.logout();
      await tester.pumpAndSettle();
      await auth.login(
        'teacher@fictional.example.test',
        fixturePassword,
        rememberMe: false,
      );
      await tester.pumpAndSettle();
      expect(auth.can('finance.read'), false);
      expect(await store.read(), isNull);
      final ctx = tester.element(find.byType(ModuleCard).first);
      Navigator.of(ctx).push(
        MaterialPageRoute<void>(builder: (_) => AttendanceScreen(auth: auth)),
      );
      await tester.pumpAndSettle();
      for (
        var i = 0;
        i < 80 &&
            find.textContaining('Fictional Child Alpha').evaluate().isEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(find.textContaining('Fictional Child Alpha'), findsOneWidget);
      expect(find.text('Fictional Unrelated Child'), findsNothing);
      await tester.tap(find.text('Mark all present'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Submit'));
      await tester.tap(find.text('Submit'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('confirm-action')));
      await tester.pumpAndSettle();
      for (var i = 0; i < 80 && find.text('Synced').evaluate().isEmpty; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(find.text('Synced'), findsWidgets);
      Navigator.of(tester.element(find.byType(AttendanceScreen))).pop();
      await tester.pumpAndSettle();
      Navigator.of(tester.element(find.byType(ModuleCard).first)).push(
        MaterialPageRoute<void>(
          builder: (_) => DiaryEditor(auth: auth, clientId: operationId()),
        ),
      );
      await tester.pumpAndSettle();
      final inputs = find.byType(TextFormField);
      for (var i = 0; i < 80 && inputs.evaluate().length < 2; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(inputs.evaluate().length, greaterThanOrEqualTo(2));
      await tester.enterText(inputs.at(0), 'Fictional integration lesson');
      await tester.enterText(inputs.at(1), 'Fictional reading activity');
      await tester.ensureVisible(find.text('Publish'));
      await tester.tap(find.text('Publish'));
      await tester.pumpAndSettle();
      for (
        var i = 0;
        i < 80 && find.byType(DiaryEditor).evaluate().isNotEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(find.byType(DiaryEditor), findsNothing);
      final diary = await auth.call('diary');
      expect(
        (diary['data'] as List).any(
          (v) => v['topic'] == 'Fictional integration lesson',
        ),
        true,
      );
      // An expiry/logout closes detail routes as well as the root shell.
      await auth.logout();
      await tester.pumpAndSettle();
      expect(find.byType(DiaryEditor), findsNothing);
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
      await tester.ensureVisible(find.byKey(const Key('parent-otp')));
      await tester.tap(find.byKey(const Key('parent-otp')));
      // The host-only local simulator enters the code using ADB, never an HTTP endpoint or log.
      var entered = false;
      for (var i = 0; i < 60; i++) {
        await tester.pump(const Duration(seconds: 1));
        final field = tester.widget<TextFormField>(
          find.byKey(const Key('parent-otp')),
        );
        if (field.controller?.text.length == 6) {
          entered = true;
          break;
        }
      }
      expect(
        entered,
        true,
        reason: 'Run deployment/inject-validation-otp.ps1 alongside this test.',
      );
      await tester.ensureVisible(find.text('Verify and sign in'));
      await tester.tap(find.text('Verify and sign in'));
      await tester.pumpAndSettle();
      expect(auth.isParent, true);
      for (
        var i = 0;
        i < 80 &&
            find.textContaining('Fictional Child Alpha').evaluate().isEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(find.textContaining('Fictional Child Alpha'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Fictional Child Beta'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Fictional Child Beta'), findsOneWidget);
      expect(find.text('Fictional Unrelated Child'), findsNothing);
      final home = await auth.call('parent/home');
      final child = (home['children'] as List).first['id'] as String;
      final ticket = await auth.call(
        'tickets',
        body: {
          'student_id': child,
          'category': 'academic_progress',
          'subject': 'Fictional integration query',
          'message': 'Fictional guardian asks for guidance',
          'attachments': [],
          'client_id': operationId(),
        },
      );
      await auth.logout();
      await tester.pumpAndSettle();
      await auth.login(
        'coordinator@fictional.example.test',
        fixturePassword,
        rememberMe: false,
      );
      await tester.pumpAndSettle();
      expect(auth.can('communication.manage'), true);
      final inbox = await auth.call('tickets');
      expect((inbox['data'] as List).any((v) => v['id'] == ticket['id']), true);
      Navigator.of(tester.element(find.byType(ModuleCard).first)).push(
        MaterialPageRoute<void>(
          builder: (_) =>
              TicketDetailScreen(auth: auth, id: ticket['id'] as String),
        ),
      );
      await tester.pumpAndSettle();
      for (
        var i = 0;
        i < 80 &&
            find
                .text('Fictional guardian asks for guidance')
                .evaluate()
                .isEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(find.text('Fictional guardian asks for guidance'), findsOneWidget);
      await auth.logout();
      await tester.pumpAndSettle();
      await prefs.save(locale: const Locale('ur'), mode: ThemeMode.dark);
      final loaded = AppPreferences(local);
      await loaded.load();
      expect(loaded.locale, const Locale('ur'));
      expect(loaded.mode, ThemeMode.dark);
      await tester.pumpWidget(
        SchoolApp(key: UniqueKey(), auth: auth, preferences: loaded),
      );
      await tester.pumpAndSettle();
      expect(
        Directionality.of(
          tester.element(find.byKey(const Key('language-toggle'))),
        ),
        TextDirection.rtl,
      );
      expect(
        Theme.of(tester.element(find.byKey(const Key('language-toggle'))))
            .brightness,
        Brightness.dark,
      );
      await prefs.save(locale: const Locale('en'), mode: ThemeMode.system);
      await loaded.load();
      expect(loaded.mode, ThemeMode.system);
      await local.remove('preferences');
      await tester.pumpWidget(const SizedBox());
    },
  );
}
