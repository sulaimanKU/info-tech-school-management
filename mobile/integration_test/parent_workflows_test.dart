import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:school/app/school_app.dart';
import 'package:school/app/app_preferences.dart';
import 'package:school/core/api_client.dart';
import 'package:school/core/operation_id.dart';
import 'package:school/features/auth/auth_controller.dart';

import '../test/support/auth_fixtures.dart' show MemoryStore;

import 'package:school/features/parents/parent_home_screen.dart';
import 'package:school/features/parents/child_workspace_screen.dart';
import 'package:school/features/parents/parent_academics_screen.dart';
import 'package:school/features/parents/parent_attendance_screen.dart';
import 'package:school/features/academics/diary_screen.dart';
import 'package:school/features/academics/student_academic_screen.dart';
import 'package:school/features/communication/ticket_screen.dart';
import 'package:school/features/communication/ticket_composer.dart';
import 'package:school/features/communication/announcement_screen.dart';
import 'package:school/features/finance/student_fee_screen.dart';
import 'package:school/shared/widgets/data_state.dart';

import 'final_dashboard_workflows_test.dart'
    show ValidationSession, ValidationLocal, fixturePassword;

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'parent OTP multiple-child workspace coordinator and session workflows',
    (tester) async {
      const store = ValidationSession();
      const local = ValidationLocal();
      await store.clear();
      final prefs = AppPreferences(local);
      await prefs.save(locale: const Locale('en'), mode: ThemeMode.light);
      final auth = AuthController(ApiClient(), store, localStore: local);
      await auth.initialize();
      Future<void> waitFor(Finder finder) async {
        for (var i = 0; i < 160 && finder.evaluate().isEmpty; i++) {
          await tester.pump(const Duration(milliseconds: 150));
        }
        expect(finder, findsWidgets);
        await tester.pumpAndSettle();
      }

      Future<void> loaded() async {
        for (
          var i = 0;
          i < 160 && find.byType(LoadingState).evaluate().isNotEmpty;
          i++
        ) {
          await tester.pump(const Duration(milliseconds: 150));
        }
        expect(find.byType(LoadingState), findsNothing);
        await tester.pumpAndSettle();
      }

      Future<void> shot(String name) async {
        await loaded();
        await binding.takeScreenshot(name);
      }

      await tester.pumpWidget(SchoolApp(auth: auth, preferences: prefs));
      await tester.pumpAndSettle();
      await binding.convertFlutterSurfaceToImage();
      await tester.ensureVisible(find.text('Parent login'));
      await tester.tap(find.text('Parent login'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('parent-cnic')),
        '0000000000001',
      );
      await tester.ensureVisible(find.text('Request code'));
      await tester.tap(find.text('Request code'));
      await waitFor(find.byKey(const Key('parent-otp')));
      await tester.ensureVisible(find.byKey(const Key('parent-otp')));
      await tester.tap(find.byKey(const Key('parent-otp')));
      for (var i = 0; i < 160; i++) {
        if (tester
                .widget<TextFormField>(find.byKey(const Key('parent-otp')))
                .controller!
                .text
                .length ==
            6) {
          break;
        }
        await tester.pump(const Duration(milliseconds: 500));
      }
      expect(
        tester
            .widget<TextFormField>(find.byKey(const Key('parent-otp')))
            .controller!
            .text
            .length,
        6,
      );
      // Persist only the existing secure bearer session.
      final remember = tester.widget<CheckboxListTile>(
        find.byType(CheckboxListTile),
      );
      if (remember.value != true) {
        await tester.ensureVisible(find.byType(CheckboxListTile));
        await tester.tap(find.byType(CheckboxListTile));
      }
      await tester.ensureVisible(find.text('Verify and sign in'));
      await tester.tap(find.text('Verify and sign in'));
      await waitFor(find.byType(ParentHomeScreen));
      await loaded();
      expect(auth.isParent, isTrue);
      await shot('parent-home-two-children');
      final data = await auth.call('parent/home');
      final children = (data['children'] as List)
          .map((c) => (c as Map).cast<String, dynamic>())
          .toList();
      expect(children.length, 2);
      final alpha = children.firstWhere(
        (c) => c['full_name'] == 'Fictional Child Alpha',
      );
      final beta = children.firstWhere(
        (c) => c['full_name'] == 'Fictional Child Beta',
      );
      final admin = AuthController(
        ApiClient(),
        MemoryStore(),
        localStore: local,
      );
      await admin.login(
        'md@fictional.example.test',
        fixturePassword,
        rememberMe: false,
      );
      final directory = await admin.call('students');
      final unrelated = (directory['data'] as List).firstWhere(
        (s) => s['full_name'] == 'Fictional Unrelated Child',
      )['id'];
      for (final path in [
        'parent/children/$unrelated/overview',
        'students/$unrelated/assessments',
        'students/$unrelated/finance',
        'diary?student_id=$unrelated',
        'tickets?student_id=$unrelated',
      ]) {
        await expectLater(
          auth.call(path),
          throwsA(isA<ApiFailure>().having((e) => e.code, 'code', 'notFound')),
        );
      }
      final notice = await admin.call(
        'announcements',
        body: {
          'title': 'Fictional parent notice',
          'body': 'Fictional family announcement',
          'kind': 'notice',
          'audience': 'all_parents',
          'status': 'published',
          'version': 0,
        },
      );
      expect(notice['id'], isNotNull);
      Navigator.of(tester.element(find.byType(ParentHomeScreen))).push(
        MaterialPageRoute<void>(
          builder: (_) => ChildWorkspaceScreen(
            auth: auth,
            children: children,
            initialId: alpha['id'] as String,
          ),
        ),
      );
      await tester.pumpAndSettle();
      await loaded();
      await shot('child-alpha-overview');
      Future<void> nav(int index) async {
        await tester.tap(find.byType(NavigationDestination).at(index));
        await tester.pumpAndSettle();
        await loaded();
      }

      Future<void> select(String name) async {
        await tester.tap(find.byType(DropdownButtonFormField<String>).first);
        await tester.pumpAndSettle();
        await tester.tap(find.text(name).last);
        await tester.pumpAndSettle();
        await loaded();
      }

      await nav(1);
      expect(find.byType(ParentAcademicsScreen), findsOneWidget);
      expect(find.text('80%'), findsOneWidget);
      await shot('published-academics');
      await tester.tap(find.byType(DropdownButtonFormField<String>).last);
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining('Fictional September ·').last);
      await tester.pumpAndSettle();
      await loaded();
      expect(find.text('60%'), findsOneWidget);
      await shot('historical-assessment');
      await select('Fictional Child Beta');
      expect(find.text('60%'), findsOneWidget);
      expect(find.text(alpha['registration_number'] as String), findsNothing);
      await shot('child-beta-academics');
      Future<void> more(IconData icon) async {
        await nav(4);
        await Scrollable.ensureVisible(
          tester.element(find.byIcon(icon).first),
          alignment: .5,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byIcon(icon).first);
        await tester.pumpAndSettle();
        await loaded();
      }

      await more(Icons.fact_check_outlined);
      expect(find.byType(ParentAttendanceScreen), findsOneWidget);
      final betaAttendance = await auth.call(
        'students/${beta['id']}/attendance?month=2026-10',
      );
      expect(betaAttendance['counts']['absent'], 1);
      await shot('child-beta-attendance');
      await select('Fictional Child Alpha');
      final alphaAttendance = await auth.call(
        'students/${alpha['id']}/attendance?month=2026-10',
      );
      expect(alphaAttendance['counts']['present'], 1);
      await shot('child-alpha-attendance');
      await more(Icons.receipt_long_outlined);
      expect(find.byType(StudentFeeScreen), findsOneWidget);
      expect(find.text('FICTIONAL-PARENT-1'), findsOneWidget);
      await shot('child-alpha-fees');
      await select('Fictional Child Beta');
      expect(find.text('FICTIONAL-PARENT-2'), findsOneWidget);
      expect(find.text('FICTIONAL-PARENT-1'), findsNothing);
      await shot('child-beta-fees');
      await nav(2);
      expect(find.byType(DiaryScreen), findsOneWidget);
      expect(find.text('Fictional Beta homework'), findsOneWidget);
      await select('Fictional Child Alpha');
      expect(find.text('Fictional Alpha homework'), findsOneWidget);
      expect(find.text('Fictional Beta homework'), findsNothing);
      await shot('child-alpha-homework');
      await more(Icons.favorite_outline);
      expect(find.byType(StudentAcademicScreen), findsOneWidget);
      expect(find.text('Fictional Alpha encouragement'), findsOneWidget);
      await select('Fictional Child Beta');
      expect(find.text('Fictional Beta encouragement'), findsOneWidget);
      expect(find.text('Fictional Alpha encouragement'), findsNothing);
      await shot('child-beta-behavior');
      await more(Icons.campaign_outlined);
      expect(find.byType(AnnouncementScreen), findsOneWidget);
      expect(find.text('Fictional parent notice'), findsOneWidget);
      await shot('parent-announcements');
      await nav(3);
      expect(find.byType(TicketScreen), findsOneWidget);
      await tester.tap(find.byType(FloatingActionButton));
      await waitFor(find.byType(TicketComposer));
      await tester.enterText(
        find.byType(TextFormField).at(0),
        'Fictional parent progress query',
      );
      await tester.enterText(
        find.byType(TextFormField).at(1),
        'Please discuss fictional progress.',
      );
      await tester.ensureVisible(find.text('Send'));
      await tester.tap(find.text('Send'));
      for (
        var i = 0;
        i < 100 && find.byType(TicketComposer).evaluate().isNotEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 150));
      }
      expect(find.byType(TicketComposer), findsNothing);
      await waitFor(find.byType(TicketScreen));
      await loaded();
      final tickets = await auth.call('tickets?student_id=${beta['id']}');
      final ticket = (tickets['data'] as List).single;
      final coordinator = AuthController(
        ApiClient(),
        MemoryStore(),
        localStore: local,
      );
      await coordinator.login(
        'coordinator@fictional.example.test',
        fixturePassword,
        rememberMe: false,
      );
      await coordinator.call(
        'tickets/${ticket['id']}/messages',
        body: {
          'message': 'Fictional coordinator follow-up',
          'is_internal': false,
          'attachments': [],
          'client_id': operationId(),
        },
      );
      await tester.tap(find.textContaining('Fictional parent progress query'));
      await waitFor(find.byType(TicketDetailScreen));
      await loaded();
      expect(find.text('Fictional coordinator follow-up'), findsOneWidget);
      await shot('parent-coordinator-follow-up');
      Navigator.of(tester.element(find.byType(TicketDetailScreen))).pop();
      await tester.pumpAndSettle();
      await select('Fictional Child Alpha');
      expect(
        find.textContaining('Fictional parent progress query'),
        findsNothing,
      );
      await shot('child-alpha-own-query-list');
      final pdf = await auth.download(
        'reports/academic?student_id=${alpha['id']}&exam_key=latest',
      );
      expect(String.fromCharCodes(pdf.take(4)), '%PDF');
      await prefs.save(locale: const Locale('ur'), mode: ThemeMode.dark);
      final readPrefs = AppPreferences(local);
      await readPrefs.load();
      expect(readPrefs.locale, const Locale('ur'));
      expect(readPrefs.mode, ThemeMode.dark);
      final restored = AuthController(ApiClient(), store, localStore: local);
      await restored.initialize();
      expect(restored.isParent, isTrue);
      expect(restored.status, AuthStatus.signedIn);
      await tester.pumpWidget(
        SchoolApp(key: UniqueKey(), auth: restored, preferences: readPrefs),
      );
      await tester.pumpAndSettle();
      await loaded();
      await shot('urdu-dark-restored-parent');
      expect(
        Directionality.of(tester.element(find.byType(ParentHomeScreen))),
        TextDirection.rtl,
      );
      await restored.logout();
      expect(await store.read(), isNull);
      expect(restored.status, AuthStatus.signedOut);
      await tester.pumpAndSettle();
      await shot('parent-logout-urdu');
      await local.remove('preferences');
      await tester.pumpWidget(const SizedBox());
      expect(tester.takeException(), isNull);
    },
  );
}
