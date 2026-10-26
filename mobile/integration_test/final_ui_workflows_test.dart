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
import 'package:school/features/parents/child_workspace_screen.dart';
import 'package:school/features/parents/parent_home_screen.dart';
import 'package:school/features/finance/finance_hub_screen.dart';
import 'package:school/features/finance/invoice_screen.dart';
import 'package:school/features/reports/report_screen.dart';
import 'package:school/features/academics/diary_screen.dart';
import 'package:school/features/imports/import_screen.dart';
import 'package:school/shared/widgets/data_state.dart';

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
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  Future<void> shot(WidgetTester tester, String name) async {
    await tester.pumpAndSettle();
    if (name != 'loading-state') {
      for (
        var i = 0;
        i < 80 && find.byType(LoadingState).evaluate().isNotEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
    }
    await tester.pumpAndSettle();
    await binding.takeScreenshot(name);
  }

  testWidgets('final UI visual workflows with fictional isolated data', (
    tester,
  ) async {
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
    await binding.convertFlutterSurfaceToImage();
    await tester.pumpAndSettle();
    await shot(tester, 'login');
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
    await shot(tester, 'super-admin-dashboard');
    Future<void> open(Widget screen) async {
      final context = tester.element(find.byType(ModuleCard).first);
      Navigator.of(context)
          .push(MaterialPageRoute<void>(builder: (_) => screen));
      await tester.pumpAndSettle();
    }

    Future<void> back() async {
      Navigator.of(tester.element(find.byType(Scaffold).last)).pop();
      await tester.pumpAndSettle();
    }

    final students = await auth.call('students');
    final mdChild = (students['data'] as List).firstWhere(
      (v) => v['full_name'] == 'Fictional Child Alpha',
    );
    final sessions = await auth.call('catalog/sessions');
    final session = (sessions['items'] as List).firstWhere(
      (v) => v['name'] == '2026-27',
    );
    final now = DateTime.now();
    final existingInvoices = await auth.call('finance/invoices');
    final existingRows = existingInvoices['data'] as List;
    final invoice = existingRows.isNotEmpty
        ? (existingRows.first as Map).cast<String, dynamic>()
        : await auth.call(
            'finance/invoices',
            body: {
              'student_id': mdChild['id'],
              'academic_session_id': session['id'],
              'kind': 'tuition',
              'period': now.toIso8601String().substring(0, 7),
              'issued_on': now.toIso8601String().substring(0, 10),
              'due_on': now
                  .add(const Duration(days: 10))
                  .toIso8601String()
                  .substring(0, 10),
              'request_id': operationId(),
              'charges': [],
            },
          );
    await open(FinanceHubScreen(auth: auth));
    await shot(tester, 'finance');
    await back();
    await open(InvoiceDetailScreen(auth: auth, id: invoice['id'] as String));
    await shot(tester, 'fee-voucher');
    await back();
    await open(ReportScreen(auth: auth));
    await shot(tester, 'reports');
    await back();
    await open(ImportScreen(auth: auth));
    await shot(tester, 'imports-empty');
    await back();
    await open(const Scaffold(body: DataState()));
    await shot(tester, 'loading-state');
    await back();
    await open(const Scaffold(body: DataState(error: 'network')));
    await shot(tester, 'error-state');
    await back();

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
    await shot(tester, 'teacher-dashboard');
    expect(await store.read(), isNull);
    final availableSections = await auth.call('catalog/sections');
    final sectionId =
        (availableSections['items'] as List).first['id'] as String;
    String? attendanceDate;
    for (var day = 0; day < 28; day++) {
      final date = DateTime.now()
          .subtract(Duration(days: day))
          .toIso8601String()
          .substring(0, 10);
      final register = await auth.call(
        'attendance/register?section_id=$sectionId&date=$date',
      );
      if (register['register']?['status'] != 'submitted') {
        attendanceDate = date;
        break;
      }
    }
    expect(attendanceDate, isNotNull);
    final ctx = tester.element(find.byType(ModuleCard).first);
    Navigator.of(ctx).push(
      MaterialPageRoute<void>(
        builder: (_) => AttendanceScreen(
          auth: auth,
          initialSection: sectionId,
          initialDate: attendanceDate,
        ),
      ),
    );
    await tester.pumpAndSettle();
    for (
      var i = 0;
      i < 80 && find.textContaining('Fictional Child Alpha').evaluate().isEmpty;
      i++
    ) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    expect(find.textContaining('Fictional Child Alpha'), findsOneWidget);
    expect(find.text('Fictional Unrelated Child'), findsNothing);
    await shot(tester, 'attendance');
    await tester.tap(find.text('Mark all present'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Submit'));
    await tester.tap(find.text('Submit'));
    await tester.pumpAndSettle();
    expect(find.text('Submit attendance?'), findsOneWidget);
    await shot(tester, 'attendance-confirmation');
    await tester.tap(find.byKey(const Key('confirm-action')));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(CustomScrollView), const Offset(0, 1600));
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
    await shot(tester, 'diary-editor');
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
    await shot(tester, 'parent-login');
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
    await shot(tester, 'parent-dashboard');
    for (
      var i = 0;
      i < 80 && find.textContaining('Fictional Child Alpha').evaluate().isEmpty;
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
    Navigator.of(tester.element(find.byType(ParentHomeScreen))).push(
      MaterialPageRoute<void>(
        builder: (_) => ChildWorkspaceScreen(
          auth: auth,
          children: (home['children'] as List)
              .map((v) => (v as Map).cast<String, dynamic>())
              .toList(),
          initialId: child,
        ),
      ),
    );
    await tester.pumpAndSettle();
    await shot(tester, 'child-workspace');
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fictional Child Beta').last);
    await tester.pumpAndSettle();
    await shot(tester, 'child-switch-beta');
    Navigator.of(tester.element(find.byType(ChildWorkspaceScreen))).pop();
    await tester.pumpAndSettle();
    Navigator.of(tester.element(find.byType(ParentHomeScreen))).push(
      MaterialPageRoute<void>(
        builder: (_) => DiaryScreen(auth: auth, studentId: child),
      ),
    );
    await tester.pumpAndSettle();
    await shot(tester, 'parent-homework');
    Navigator.of(tester.element(find.byType(DiaryScreen))).pop();
    await tester.pumpAndSettle();

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
          find.text('Fictional guardian asks for guidance').evaluate().isEmpty;
      i++
    ) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    expect(find.text('Fictional guardian asks for guidance'), findsOneWidget);
    await auth.call(
      'tickets/${ticket['id']}/messages',
      body: {
        'message': 'Fictional coordinator response',
        'is_internal': false,
        'attachments': [],
        'client_id': operationId(),
      },
    );
    await auth.call(
      'tickets/${ticket['id']}/messages',
      body: {
        'message': 'Fictional private staff note',
        'is_internal': true,
        'attachments': [],
        'client_id': operationId(),
      },
    );
    await tester.drag(find.byType(ListView).last, const Offset(0, 400));
    await tester.pumpAndSettle();
    // Reopen the detail to load the server-filtered, typed conversation.
    Navigator.of(tester.element(find.byType(TicketDetailScreen))).pop();
    await tester.pumpAndSettle();
    Navigator.of(tester.element(find.byType(ModuleCard).first)).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            TicketDetailScreen(auth: auth, id: ticket['id'] as String),
      ),
    );
    await tester.pumpAndSettle();
    await shot(tester, 'communication');
    await tester.drag(find.byType(ListView).last, const Offset(0, -450));
    await tester.pumpAndSettle();
    await shot(tester, 'communication-internal-note');

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

    await shot(tester, 'urdu-dark-login');
    await auth.login(
      'md@fictional.example.test',
      fixturePassword,
      rememberMe: false,
    );
    await tester.pumpAndSettle();
    await shot(tester, 'urdu-dark-dashboard');
    await tester.tap(find.byKey(const Key('language-toggle')));
    await tester.pumpAndSettle();
    await shot(tester, 'dark-dashboard');
    await tester.tap(find.byKey(const Key('theme-menu')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Light theme'));
    await tester.pumpAndSettle();
    expect(
      Theme.of(tester.element(find.byType(ModuleCard).first)).brightness,
      Brightness.light,
    );
    await shot(tester, 'light-dashboard');
    await auth.logout();
    await tester.pumpAndSettle();
    await prefs.save(locale: const Locale('en'), mode: ThemeMode.system);
    await loaded.load();
    expect(loaded.mode, ThemeMode.system);
    await local.remove('preferences');
    await tester.pumpWidget(const SizedBox());
  });
}
