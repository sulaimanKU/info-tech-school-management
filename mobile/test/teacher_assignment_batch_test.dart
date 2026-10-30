import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/administration/teacher_assignment_batch_screen.dart';
import 'package:school/features/administration/teaching_assignments_screen.dart';
import 'package:school/features/administration/staff_assignment_screen.dart';

import 'final_ui_polish_test.dart' show authFor, host, ok;

AuthController batchAuth(
  Future<http.Response> Function(http.Request) handler, {
  required List<String> permissions,
}) {
  final auth = authFor(handler);
  auth.user = {...auth.user!, 'permissions': permissions};
  return auth;
}

const teacher = <String, dynamic>{
  'id': 'profile-fixture',
  'name': 'Ali Khan',
  'employee_number': 'STF-0001',
  'account_code': 'ACC-000001',
};

Map<String, dynamic> choices(Uri uri) => {
  'current_academic_session_id': 'session-fixture',
  'sessions': [
    {'id': 'session-fixture', 'name': '2026'},
  ],
  'classes': [
    {'id': 'class-fixture', 'name': 'Grade 5'},
  ],
  'sections': [
    {'id': 'section-a', 'name': 'Section A'},
    {'id': 'section-b', 'name': 'Section B'},
  ],
  'subjects': [
    {'id': 'subject-math', 'name': 'Mathematics'},
  ],
};

Future<void> reveal(WidgetTester tester, Finder target) async {
  FocusManager.instance.primaryFocus?.unfocus();
  await tester.pumpAndSettle();
  if (target.evaluate().isEmpty) {
    await tester.scrollUntilVisible(
      target,
      180,
      scrollable: find.byType(Scrollable).first,
    );
  }
  await tester.ensureVisible(target);
  await tester.pumpAndSettle();
}

Future<void> choose(
  WidgetTester tester,
  int row,
  String field,
  String value,
) async {
  final scoped = find.descendant(
    of: find.byKey(ValueKey('draft-fields-$row')),
    matching: find.byKey(ValueKey('filter-$field')),
  );
  await reveal(tester, scoped);
  await tester.tap(scoped);
  await tester.pumpAndSettle();
  await tester.tap(find.text(value).last);
  await tester.pumpAndSettle();
}

Future<void> fill(WidgetTester tester, int row, String section) async {
  await choose(tester, row, 'class_id', 'Grade 5');
  await choose(tester, row, 'section_id', section);
  await choose(tester, row, 'subject_id', 'Mathematics');
}

void main() {
  testWidgets(
    'removing an existing assignment confirms then deactivates only its row',
    (tester) async {
      final writes = <String>[];
      final auth = batchAuth((r) async {
        if (r.method == 'POST') {
          writes.add('${r.method} ${r.url.path}');
          return ok({'deactivated': true});
        }
        return ok(choices(r.url));
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(
          StaffAssignmentScreen(
            auth: auth,
            profileId: 'profile-fixture',
            coordinator: false,
            existing: {
              'id': 'assignment-fixture',
              'academic_session_id': 'session-fixture',
              'class_id': 'class-fixture',
              'section_id': 'section-a',
              'subject_id': 'subject-math',
              'is_active': true,
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      final remove = find.widgetWithText(TextButton, 'Remove assignment');
      await reveal(tester, remove);
      await tester.tap(remove);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(writes, isEmpty);
      await tester.tap(remove);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(writes, [
        'POST /api/v1/catalog/teacher-assignments/assignment-fixture/deactivate',
      ]);
      expect(tester.takeException(), isNull);
    },
  );

  for (final dark in [false, true]) {
    testWidgets(
      'assignment form wraps long teacher names at compact size ${dark ? 'dark' : 'light'}',
      (tester) async {
        tester.view.physicalSize = const Size(320, 640);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final auth = batchAuth(
          (r) async => ok(choices(r.url)),
          permissions: ['assignments.manage'],
        );
        addTearDown(auth.dispose);
        await tester.pumpWidget(
          host(
            TeacherAssignmentBatchScreen(
              auth: auth,
              teacher: {
                ...teacher,
                'name':
                    'Ali Khan with a long academic designation and campus name',
              },
            ),
            dark: dark,
            scale: 1.5,
          ),
        );
        await tester.pumpAndSettle();
        await reveal(tester, find.byKey(const Key('save-assignments')));
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'multiple rows for one teacher save atomically with dependent choices',
    (tester) async {
      Map<String, dynamic>? saved;
      final references = <Uri>[];
      final auth = batchAuth((r) async {
        if (r.method == 'POST') {
          saved = jsonDecode(r.body) as Map<String, dynamic>;
          return ok({
            'count': 2,
            'ids': ['first', 'second'],
          });
        }
        references.add(r.url);
        return ok(choices(r.url));
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(TeacherAssignmentBatchScreen(auth: auth, teacher: teacher)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Ali Khan'), findsOneWidget);
      expect(find.textContaining('ACC-000001'), findsOneWidget);
      expect(find.textContaining('profile-fixture'), findsNothing);
      await fill(tester, 0, 'Section A');
      await reveal(tester, find.byKey(const Key('add-assignment')));
      await tester.tap(find.byKey(const Key('add-assignment')));
      await tester.pumpAndSettle();
      await fill(tester, 1, 'Section B');
      await reveal(tester, find.byKey(const Key('save-assignments')));
      await tester.tap(find.byKey(const Key('save-assignments')));
      await tester.pumpAndSettle();
      expect(saved?['staff_profile_id'], 'profile-fixture');
      expect(saved?['academic_session_id'], 'session-fixture');
      final rows = saved!['assignments'] as List;
      expect(rows.map((r) => r['section_id']), ['section-a', 'section-b']);
      expect(rows.every((r) => r['subject_id'] == 'subject-math'), isTrue);
      expect(
        references.any((u) => u.queryParameters['class_id'] == 'class-fixture'),
        isTrue,
      );
      expect(
        references.any((u) => u.queryParameters['section_id'] == 'section-b'),
        isTrue,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'duplicate drafts never post and removal changes only draft rows',
    (tester) async {
      var writes = 0;
      final auth = batchAuth((r) async {
        if (r.method == 'POST') writes++;
        return ok(choices(r.url));
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(TeacherAssignmentBatchScreen(auth: auth, teacher: teacher)),
      );
      await tester.pumpAndSettle();
      await fill(tester, 0, 'Section A');
      await reveal(tester, find.byKey(const Key('add-assignment')));
      await tester.tap(find.byKey(const Key('add-assignment')));
      await tester.pumpAndSettle();
      await fill(tester, 1, 'Section A');
      await reveal(tester, find.byKey(const Key('save-assignments')));
      await tester.tap(find.byKey(const Key('save-assignments')));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('already in the assignment list'),
        findsOneWidget,
      );
      expect(writes, 0);
      await reveal(tester, find.byKey(const ValueKey('remove-draft-1')));
      await tester.tap(find.byKey(const ValueKey('remove-draft-1')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('draft-1')), findsNothing);
      expect(find.byKey(const ValueKey('draft-0')), findsOneWidget);
      expect(writes, 0);
    },
  );

  testWidgets('batch API validation failure retains drafts for correction', (
    tester,
  ) async {
    final auth = batchAuth((r) async {
      if (r.method == 'POST') {
        return http.Response(
          jsonEncode({
            'error': {'code': 'validation', 'fields': {}},
          }),
          422,
        );
      }
      return ok(choices(r.url));
    }, permissions: ['school.manage']);
    addTearDown(auth.dispose);
    await tester.pumpWidget(
      host(TeacherAssignmentBatchScreen(auth: auth, teacher: teacher)),
    );
    await tester.pumpAndSettle();
    await fill(tester, 0, 'Section A');
    await reveal(tester, find.byKey(const Key('save-assignments')));
    await tester.tap(find.byKey(const Key('save-assignments')));
    await tester.pumpAndSettle();
    expect(
      find.text('Check the entered details and try again.'),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('draft-0')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'teacher filter is submitted as profile scope with existing academic filters',
    (tester) async {
      final requests = <Uri>[];
      final auth = batchAuth((r) async {
        requests.add(r.url);
        if (r.url.path.endsWith('/teachers')) {
          return ok({
            'data': [teacher],
            'last_page': 1,
          });
        }
        return ok({'data': [], 'last_page': 1});
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeachingAssignmentsScreen(auth: auth)));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('filter-teacher')));
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining('STF-0001 | Ali Khan'));
      await tester.pumpAndSettle();
      expect(
        requests.last.queryParameters['staff_profile_id'],
        'profile-fixture',
      );
      expect(find.text('Ali Khan'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
