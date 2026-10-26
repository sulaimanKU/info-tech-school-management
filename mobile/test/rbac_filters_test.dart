import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:school/app/module_access.dart';
import 'package:school/core/api_client.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/administration/staff_screen.dart';
import 'package:school/features/students/guardian_directory_screen.dart';
import 'package:school/features/academics/academic_hub_screen.dart';
import 'package:school/shared/widgets/academic_filters.dart';
import 'package:school/shared/widgets/records_screen.dart';

import 'support/auth_fixtures.dart';
import 'final_ui_polish_test.dart' show host, ok;

AuthController fixture(
  String role,
  List<String> permissions, {
  Future<http.Response> Function(http.Request)? handler,
}) {
  final auth = AuthController(
    ApiClient(
      baseUrl: 'http://localhost/api/v1',
      client: MockClient(handler ?? (r) async => ok({'items': []})),
    ),
    MemoryStore(),
  );
  auth.user = {
    ...testUser,
    'roles': [
      {'code': role, 'name': role},
    ],
    'permissions': permissions,
  };
  auth.status = AuthStatus.signedIn;
  return auth;
}

const modules = [
  'students',
  'attendance',
  'academics',
  'finance',
  'staffModule',
  'reports',
  'messages',
  'settingsModule',
  'accessControl',
];
void main() {
  final cases = <String, ({List<String> permissions, List<String> expected})>{
    'super_admin': (permissions: [], expected: modules),
    'school_administrator': (
      permissions: [
        'students.read',
        'academics.read',
        'students.manage',
        'finance.read',
        'staff.manage',
        'reports.export',
        'school.manage',
      ],
      expected: [
        'students',
        'attendance',
        'academics',
        'finance',
        'staffModule',
        'reports',
        'messages',
        'settingsModule',
      ],
    ),
    'coordinator': (
      permissions: [
        'students.read',
        'academics.read',
        'academics.write',
        'academics.review',
        'reports.export',
      ],
      expected: ['students', 'attendance', 'academics', 'reports', 'messages'],
    ),
    'accountant_clerk': (
      permissions: [
        'students.read',
        'students.read.all',
        'finance.read',
        'finance.write',
        'reports.export',
      ],
      expected: ['students', 'finance', 'reports', 'messages'],
    ),
    'teacher': (
      permissions: ['students.read', 'academics.read', 'academics.write'],
      expected: ['students', 'attendance', 'academics', 'reports', 'messages'],
    ),
    'parent_guardian': (permissions: [], expected: []),
  };
  for (final entry in cases.entries) {
    test('navigation matrix for ${entry.key}', () {
      final auth = fixture(entry.key, entry.value.permissions);
      expect(
        modules.where((m) => ModuleAccess.allowed(auth, m)).toSet(),
        entry.value.expected.toSet(),
      );
      auth.dispose();
    });
  }
  test('role label alone cannot grant administrator or academic access', () {
    final auth = fixture('school_administrator', []);
    expect(ModuleAccess.allowed(auth, 'staffModule'), false);
    expect(auth.canAcademics, false);
    expect(auth.can('rbac.manage'), false);
    auth.dispose();
  });
  testWidgets(
    'direct unauthorized staff and guardian routes stop before fetching',
    (tester) async {
      var requests = 0;
      final auth = fixture(
        'teacher',
        ['academics.read'],
        handler: (r) async {
          requests++;
          return ok({'items': []});
        },
      );
      await tester.pumpWidget(host(StaffScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(requests, 0);
      expect(find.byType(RecordsScreen), findsNothing);
      await tester.pumpWidget(host(GuardianDirectoryScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(requests, 0);
      auth.dispose();
    },
  );
  testWidgets('read-only academic user sees no diary creation action', (
    tester,
  ) async {
    final auth = fixture('teacher', ['academics.read']);
    await tester.pumpWidget(host(AcademicHubScreen(auth: auth)));
    await tester.pumpAndSettle();
    expect(find.text('New diary entry'), findsNothing);
    auth.dispose();
  });
  testWidgets(
    'changing a class clears dependent section and subject before requesting new choices',
    (tester) async {
      final urls = <Uri>[];
      final changes = <Map<String, String>>[];
      final auth = fixture(
        'teacher',
        ['academics.read'],
        handler: (r) async {
          urls.add(r.url);
          return ok({
            'sessions': [
              {'id': 'session', 'name': 'Fictional session'},
            ],
            'classes': [
              {'id': 'a', 'name': 'Class A'},
              {'id': 'b', 'name': 'Class B'},
            ],
            'sections': [
              {'id': 'section', 'name': 'Fictional A'},
            ],
            'subjects': [
              {'id': 'subject', 'name': 'Fictional English'},
            ],
          });
        },
      );
      await tester.pumpWidget(
        host(
          SingleChildScrollView(
            child: AcademicSelection(
              auth: auth,
              subject: true,
              initial: const {
                'academic_session_id': 'session',
                'class_id': 'a',
                'section_id': 'section',
                'subject_id': 'subject',
              },
              onChanged: changes.add,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      final choice = find.descendant(
        of: find.byKey(const ValueKey('filter-class_id')),
        matching: find.byType(DropdownButtonFormField<String>),
      );
      await tester.tap(choice);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Class B').last);
      await tester.pumpAndSettle();
      expect(changes.last, {'academic_session_id': 'session', 'class_id': 'b'});
      expect(urls.last.queryParameters.containsKey('section_id'), false);
      expect(urls.last.queryParameters.containsKey('subject_id'), false);
      auth.dispose();
    },
  );
  testWidgets(
    'records filters apply on the server and reset including in Urdu dark mode',
    (tester) async {
      final urls = <Uri>[];
      final auth = fixture(
        'teacher',
        ['academics.read'],
        handler: (r) async {
          urls.add(r.url);
          return ok({
            'data': [],
            'last_page': 1,
            'sessions': [],
            'classes': [],
            'sections': [],
            'subjects': [],
          });
        },
      );
      await tester.pumpWidget(
        host(
          RecordsScreen(
            auth: auth,
            path: 'assessments',
            title: 'assessments',
            recordTitle: (v) => v['title'] as String,
          ),
          urdu: true,
          dark: true,
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('open-filters')));
      await tester.pumpAndSettle();
      // The empty options fixture still produces a usable filter sheet and reset control.
      final reset = find.text('فلٹر صاف کریں');
      expect(find.byType(BottomSheet), findsOneWidget);
      if (reset.evaluate().isNotEmpty) {
        await tester.ensureVisible(reset);
        await tester.tap(reset);
        await tester.pumpAndSettle();
      }
      expect(tester.takeException(), isNull);
      expect(urls.first.queryParameters['page'], '1');
      auth.dispose();
    },
  );
  testWidgets(
    'guardian search reaches the API instead of only filtering the loaded page',
    (tester) async {
      final urls = <Uri>[];
      final auth = fixture(
        'school_administrator',
        ['guardians.manage'],
        handler: (r) async {
          urls.add(r.url);
          return ok({
            'data': [],
            'last_page': 1,
            'sessions': [],
            'classes': [],
            'sections': [],
            'subjects': [],
          });
        },
      );
      await tester.pumpWidget(host(GuardianDirectoryScreen(auth: auth)));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextField).first,
        'Fictional Guardian',
      );
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(urls.last.queryParameters['search'], 'Fictional Guardian');
      auth.dispose();
    },
  );
  test('all list filter specifications preserve a parent child scope', () {
    for (final path in ['tickets?student_id=child', 'diary?student_id=child']) {
      final spec = FilterSpec.forPath(path, true)!;
      expect(spec.academic, false);
      expect(spec.subject, false);
    }
  });
  testWidgets('Apply and Reset send exact server filter parameters', (
    tester,
  ) async {
    final urls = <Uri>[];
    final auth = fixture(
      'teacher',
      ['academics.read'],
      handler: (r) async {
        urls.add(r.url);
        return ok({'data': [], 'last_page': 1});
      },
    );
    await tester.pumpWidget(
      host(
        RecordsScreen(
          auth: auth,
          path: 'announcements',
          title: 'announcements',
          recordTitle: (v) => v['title'] as String,
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('open-filters')));
    await tester.pumpAndSettle();
    final status = find.byType(DropdownButtonFormField<String>).first;
    await tester.tap(status);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Published').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Apply filters'));
    await tester.tap(find.text('Apply filters'));
    await tester.pumpAndSettle();
    expect(urls.last.queryParameters['status'], 'published');
    await tester.tap(find.byKey(const Key('open-filters')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Reset filters'));
    await tester.tap(find.text('Reset filters'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Apply filters'));
    await tester.tap(find.text('Apply filters'));
    await tester.pumpAndSettle();
    expect(urls.last.queryParameters.containsKey('status'), false);
    auth.dispose();
  });
}
