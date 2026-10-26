import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/parents/child_workspace_screen.dart';
import 'package:school/features/parents/parent_home_screen.dart';
import 'package:school/features/parents/parent_resource.dart';
import 'package:school/features/parents/parent_academics_screen.dart';
import 'package:school/features/parents/parent_attendance_screen.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/shared/widgets/data_state.dart';

import 'final_ui_polish_test.dart' show host, authFor, ok;

const children = [
  {
    'id': 'alpha',
    'full_name': 'Fictional Alpha',
    'registration_number': 'REG-A',
    'fee_status': 'no_vouchers',
    'balance_minor': 0,
  },
  {
    'id': 'beta',
    'full_name': 'Fictional Beta',
    'registration_number': 'REG-B',
    'fee_status': 'no_vouchers',
    'balance_minor': 0,
  },
];
AuthController parent(Future<http.Response> Function(http.Request) handler) {
  final auth = authFor(handler);
  auth.user = {
    ...auth.user!,
    'roles': [
      {'code': 'parent_guardian', 'name': 'Parent'},
    ],
    'permissions': <String>[],
  };
  return auth;
}

Map<String, dynamic> period(String key, String title) => {
  'key': key,
  'title': title,
  'type': 'monthly_test',
  'date': '2026-10-01',
  'percentage': 80,
  'obtained_marks': 120,
  'maximum_marks': 150,
  'grade': 'B',
  'session_name': 'Fictional 2026',
  'class_name': 'Grade 1',
  'section_name': 'A',
  'subjects': 2,
};
Map<String, dynamic> academic(String key) => {
  'periods': [period('new', 'October'), period('old', 'September')],
  'selected_period': period(key, key == 'old' ? 'September' : 'October'),
  'items': [
    {
      'subject_name': 'Fictional English',
      'obtained_marks': 40,
      'maximum_marks': 50,
      'percentage': 80,
      'grade': 'B',
      'title': key == 'old' ? 'Old assessment' : 'New assessment',
      'date': '2026-10-01',
      'remarks': 'Keep reading',
      'teacher_name': 'Fictional Teacher',
    },
  ],
  'trend': [],
};
Map<String, dynamic> attendance() => {
  'attendance_percentage': 75,
  'recorded_days': 4,
  'school_days': 5,
  'provisional': true,
  'counts': {'present': 2, 'absent': 1, 'late': 1, 'excused': 0},
  'items': [
    {'date': '2026-10-01', 'status': 'present'},
  ],
  'holidays': [],
};

void main() {
  testWidgets(
    'late response from previous child cannot replace the selected child',
    (tester) async {
      final delayed = Completer<http.Response>();
      final auth = parent(
        (r) async => r.url.path.endsWith('/alpha')
            ? delayed.future
            : ok({'name': 'Beta private data'}),
      );
      Widget resource(String id) => host(
        ParentResource(
          auth: auth,
          path: 'child/$id',
          builder: (c, d) => Text(d['name'] as String),
        ),
      );
      await tester.pumpWidget(resource('alpha'));
      expect(find.byType(LoadingState), findsOneWidget);
      await tester.pumpWidget(resource('beta'));
      await tester.pumpAndSettle();
      expect(find.text('Beta private data'), findsOneWidget);
      delayed.complete(ok({'name': 'Alpha old data'}));
      await tester.pumpAndSettle();
      expect(find.text('Alpha old data'), findsNothing);
      expect(find.text('Beta private data'), findsOneWidget);
    },
  );
  testWidgets('parent resource network error supports retry', (tester) async {
    var attempts = 0;
    final auth = parent((r) async {
      if (attempts++ == 0) throw http.ClientException('offline');
      return ok({'name': 'Recovered child'});
    });
    await tester.pumpWidget(
      host(
        ParentResource(
          auth: auth,
          path: 'child/alpha',
          builder: (c, d) => Text(d['name'] as String),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.wifi_off_rounded), findsOneWidget);
    await tester.tap(find.byIcon(Icons.refresh_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Recovered child'), findsOneWidget);
  });
  testWidgets(
    'published assessment selector changes API key and subject data',
    (tester) async {
      final paths = <Uri>[];
      final auth = parent((r) async {
        paths.add(r.url);
        return ok(
          academic(r.url.queryParameters['exam_key'] == 'old' ? 'old' : 'new'),
        );
      });
      await tester.pumpWidget(
        host(ParentAcademicsScreen(auth: auth, studentId: 'alpha')),
      );
      await tester.pumpAndSettle();
      expect(find.text('New assessment · 2026-10-01'), findsOneWidget);
      expect(find.text('Keep reading'), findsOneWidget);
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining('September ·').last);
      await tester.pumpAndSettle();
      expect(paths.last.queryParameters['exam_key'], 'old');
      expect(find.text('Old assessment · 2026-10-01'), findsOneWidget);
      expect(find.text('New assessment · 2026-10-01'), findsNothing);
    },
  );
  testWidgets('attendance month selector makes a new scoped request', (
    tester,
  ) async {
    final paths = <Uri>[];
    final auth = parent((r) async {
      paths.add(r.url);
      return ok(attendance());
    });
    await tester.pumpWidget(
      host(ParentAttendanceScreen(auth: auth, studentId: 'beta')),
    );
    await tester.pumpAndSettle();
    final current = paths.last.queryParameters['month'];
    expect(find.text('75%'), findsOneWidget);
    await tester.tap(find.byKey(const Key('previous-attendance-month')));
    await tester.pumpAndSettle();
    expect(paths.last.path, endsWith('/students/beta/attendance'));
    expect(paths.last.queryParameters['month'], isNot(current));
  });
  testWidgets(
    'all child modules issue new scoped requests after child switch',
    (tester) async {
      final paths = <Uri>[];
      final auth = parent((r) async {
        paths.add(r.url);
        final p = r.url.path;
        if (p.endsWith('/assessments')) return ok({'periods': [], 'items': []});
        if (p.endsWith('/attendance')) return ok(attendance());
        if (p.endsWith('/finance')) {
          return ok({
            'balance_minor': 0,
            'arrears_minor': 0,
            'items': [],
            'payments': [],
            'discounts': [],
          });
        }
        if (p.endsWith('/behavior')) return ok({'items': []});
        if (p.endsWith('/overview')) return ok({});
        return ok({'data': [], 'last_page': 1});
      });
      await tester.pumpWidget(
        host(
          ChildWorkspaceScreen(
            auth: auth,
            children: children,
            initialId: 'alpha',
          ),
        ),
      );
      await tester.pumpAndSettle();
      Future<void> select(String name) async {
        await tester.tap(find.byType(DropdownButtonFormField<String>).first);
        await tester.pumpAndSettle();
        await tester.tap(find.text(name).last);
        await tester.pumpAndSettle();
      }

      Future<void> nav(String text) async {
        await tester.tap(
          find.descendant(
            of: find.byType(NavigationBar),
            matching: find.text(text),
          ),
        );
        await tester.pumpAndSettle();
      }

      for (final module in [
        ('Overview', '/overview'),
        ('Academics', '/assessments'),
        ('Diary / Homework', '/diary'),
        ('Contact coordinator', '/tickets'),
        ('More', ''),
      ]) {
        if (module.$1 == 'More') continue;
        // Use navigation index: translated labels are checked independently by localization tests.
        final index = [
          'Overview',
          'Academics',
          'Diary / Homework',
          'Contact coordinator',
        ].indexOf(module.$1);
        await tester.tap(find.byType(NavigationDestination).at(index));
        await tester.pumpAndSettle();
        await select('Fictional Beta');
        expect(find.text('REG-A'), findsNothing);
        expect(find.text('REG-B'), findsOneWidget);
        final request = paths.last;
        expect(request.path, endsWith(module.$2));
        expect(
          request.path.contains('beta') ||
              request.queryParameters['student_id'] == 'beta',
          isTrue,
        );
        await select('Fictional Alpha');
      }
      for (final entry in [
        ('Attendance', '/attendance'),
        ('Fees & Finance', '/finance'),
        ('Behavior / Conduct', '/behavior'),
        ('Announcements', '/announcements'),
      ]) {
        await nav('More');
        final icon = switch (entry.$2) {
          '/attendance' => Icons.fact_check_outlined,
          '/finance' => Icons.receipt_long_outlined,
          '/behavior' => Icons.favorite_outline,
          _ => Icons.campaign_outlined,
        };
        await Scrollable.ensureVisible(
          tester.element(find.byIcon(icon).first),
          alignment: 0.5,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byIcon(icon).first);
        await tester.pumpAndSettle();
        await select('Fictional Beta');
        final request = paths.last;
        expect(request.path, endsWith(entry.$2));
        expect(
          request.path.contains('beta') ||
              request.queryParameters['student_id'] == 'beta',
          isTrue,
        );
        expect(find.text('REG-A'), findsNothing);
        await select('Fictional Alpha');
      }
      expect(tester.takeException(), isNull);
    },
  );
  for (final width in [320.0, 390.0, 768.0, 1440.0]) {
    testWidgets('parent workspace Urdu dark layout at $width', (tester) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final auth = parent(
        (r) async => ok({
          'child': children.first,
          'homework_count': 0,
          'recent_homework': [],
          'announcements': [],
        }),
      );
      await tester.pumpWidget(
        host(
          ChildWorkspaceScreen(
            auth: auth,
            children: children,
            initialId: 'alpha',
          ),
          urdu: true,
          dark: true,
          scale: 1.3,
        ),
      );
      await tester.pumpAndSettle();
      expect(
        Directionality.of(tester.element(find.byType(NavigationBar))),
        TextDirection.rtl,
      );
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets(
    'parent home handles no verified children without invented values',
    (tester) async {
      final auth = parent((r) async => ok({'children': []}));
      await tester.pumpWidget(host(ParentHomeScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(find.textContaining('No verified'), findsOneWidget);
      expect(find.text('0%'), findsNothing);
    },
  );

  testWidgets(
    'single-child workspace shows real profile and safe fallback selection',
    (tester) async {
      final auth = parent((r) async => ok({}));
      await tester.pumpWidget(
        host(
          ChildWorkspaceScreen(
            auth: auth,
            children: [children.first],
            initialId: 'missing',
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('REG-A'), findsOneWidget);
      expect(find.text('REG-B'), findsNothing);
      expect(find.text('FA'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  for (final width in [320.0, 390.0, 768.0, 1440.0]) {
    testWidgets('published academics Urdu light scaled layout at $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final auth = parent((r) async => ok(academic('new')));
      await tester.pumpWidget(
        host(
          ParentAcademicsScreen(auth: auth, studentId: 'alpha'),
          urdu: true,
          scale: 1.6,
        ),
      );
      await tester.pumpAndSettle();
      await tester.drag(find.byType(ListView).first, const Offset(0, -700));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }
}
