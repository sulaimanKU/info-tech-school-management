import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/home/home_screen.dart';
import 'package:school/features/home/widgets/dashboard_overview.dart';
import 'package:school/features/home/widgets/quick_actions.dart';
import 'package:school/shared/widgets/data_state.dart';
import 'package:school/shared/widgets/summary_card.dart';

import 'final_ui_polish_test.dart' show host, ok;
import 'rbac_filters_test.dart' show fixture;

Map<String, dynamic> summary() => {
  'as_of': '2026-10-22T12:00:00+05:00',
  'scope': 'school',
  'unread_notifications': 2,
  'students': {'total': 17, 'active': 16},
  'staff': {'active': 5, 'teachers': 3},
  'open_requests': 4,
  'school': {
    'classes': 11,
    'sections': 2,
    'subjects': 3,
    'sessions': [
      {'name': 'Fictional 2026'},
    ],
  },
  'academic': {
    'homework_today': 2,
    'pending_assessments': 1,
    'assessment_state': 'submitted',
  },
  'attendance': {
    'date': '2026-10-22',
    'expected': 17,
    'recorded': 10,
    'present': 8,
    'absent': 1,
    'late': 1,
    'excused': 0,
    'not_marked': 7,
    'coverage_percentage': 58.8,
  },
  'finance': {
    'outstanding_minor': 123456789,
    'overdue_minor': 10000,
    'receipts_month_minor': 250000,
    'students_with_balance': 3,
    'period': '2026-10',
  },
  'announcements': [
    {
      'id': 'fictional',
      'title': 'Fictional campus notice',
      'kind': 'notice',
      'publish_at': '2026-10-22',
    },
  ],
};
void main() {
  testWidgets(
    'Super Admin dashboard renders response values and authorized quick actions',
    (tester) async {
      var calls = 0;
      final auth = fixture(
        'super_admin',
        [],
        handler: (r) async {
          expect(r.url.path, '/api/v1/dashboard-summary');
          calls++;
          return ok(summary());
        },
      );
      await tester.pumpWidget(
        host(
          SingleChildScrollView(
            child: Column(
              children: [
                DashboardOverview(auth: auth),
                QuickActions(auth: auth, onReturn: () {}),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('kpi-students')), findsOneWidget);
      expect(find.text('17'), findsOneWidget);
      expect(find.text('PKR 1234567.89'), findsOneWidget);
      expect(find.text('Fictional campus notice'), findsOneWidget);
      expect(find.byKey(const Key('quick-addStudent')), findsOneWidget);
      expect(find.text('Add student'), findsOneWidget);
      expect(find.text('addStudent'), findsNothing);
      expect(find.byKey(const Key('quick-staffManagement')), findsOneWidget);
      expect(calls, 1);
      auth.dispose();
    },
  );
  testWidgets(
    'teacher dashboard never renders finance or staff affordances from unexpected fields',
    (tester) async {
      final auth = fixture('teacher', [
        'students.read',
        'academics.read',
        'academics.write',
      ], handler: (r) async => ok({...summary(), 'scope': 'assigned'}));
      await tester.pumpWidget(
        host(
          SingleChildScrollView(
            child: Column(
              children: [
                DashboardOverview(auth: auth),
                QuickActions(auth: auth, onReturn: () {}),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('kpi-finance')), findsNothing);
      expect(find.byKey(const Key('kpi-staff')), findsNothing);
      expect(find.byKey(const Key('kpi-requests')), findsNothing);
      expect(find.byKey(const Key('quick-staffManagement')), findsNothing);
      expect(find.byKey(const Key('quick-finance')), findsNothing);
      expect(find.byKey(const Key('quick-teacherDiary')), findsOneWidget);
      auth.dispose();
    },
  );
  testWidgets('dashboard loading and retry do not invent zero statistics', (
    tester,
  ) async {
    final pending = Completer<http.Response>();
    var calls = 0;
    final auth = fixture(
      'super_admin',
      [],
      handler: (r) async {
        calls++;
        return calls == 1 ? pending.future : ok(summary());
      },
    );
    await tester.pumpWidget(
      host(SingleChildScrollView(child: DashboardOverview(auth: auth))),
    );
    await tester.pump();
    expect(find.byType(LoadingState), findsOneWidget);
    expect(find.byType(SummaryCard), findsNothing);
    pending.complete(http.Response('{}', 503));
    await tester.pumpAndSettle();
    expect(find.byType(SummaryCard), findsNothing);
    await tester.tap(find.byKey(const Key('dashboard-retry')));
    await tester.pumpAndSettle();
    expect(find.byType(SummaryCard), findsNWidgets(4));
    expect(calls, 2);
    auth.dispose();
  });
  testWidgets(
    'empty and malformed responses remain explicit and do not become fabricated counts',
    (tester) async {
      final auth = fixture(
        'teacher',
        [],
        handler: (r) async => ok({
          'as_of': '2026-10-22',
          'scope': 'assigned',
          'unread_notifications': 0,
          'announcements': [],
        }),
      );
      await tester.pumpWidget(
        host(SingleChildScrollView(child: DashboardOverview(auth: auth))),
      );
      await tester.pumpAndSettle();
      expect(
        find.text('No recent announcements for your workspace.'),
        findsOneWidget,
      );
      expect(find.byKey(const Key('kpi-students')), findsNothing);
      auth.dispose();
    },
  );
  testWidgets('theme and language rebuilds do not refetch the dashboard', (
    tester,
  ) async {
    var calls = 0;
    final auth = fixture(
      'super_admin',
      [],
      handler: (r) async {
        calls++;
        return ok(summary());
      },
    );
    await tester.pumpWidget(
      host(SingleChildScrollView(child: DashboardOverview(auth: auth))),
    );
    await tester.pumpAndSettle();
    await tester.pumpWidget(
      host(
        SingleChildScrollView(child: DashboardOverview(auth: auth)),
        urdu: true,
        dark: true,
      ),
    );
    await tester.pumpAndSettle();
    expect(calls, 1);
    expect(tester.takeException(), isNull);
    auth.dispose();
  });
  for (final width in [320.0, 390.0, 768.0, 1440.0]) {
    testWidgets('populated dashboard, shell and RTL scale safely at $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final auth = fixture(
        'super_admin',
        [],
        handler: (r) async => ok(summary()),
      );
      await tester.pumpWidget(
        host(HomeScreen(auth: auth), urdu: true, dark: true, scale: 1.6),
      );
      await tester.pumpAndSettle();
      expect(find.byType(SummaryCard), findsNWidgets(4));
      expect(tester.takeException(), isNull);
      expect(
        width >= 900 ? find.byType(NavigationRail) : find.byType(NavigationBar),
        findsOneWidget,
      );
      auth.dispose();
    });
  }
}
