import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:school/features/administration/catalog_screen.dart';
import 'package:school/features/administration/teacher_assignment_batch_screen.dart';
import 'package:school/features/administration/teaching_assignments_screen.dart';

import 'final_ui_polish_test.dart' show host, ok;
import 'teacher_assignment_batch_test.dart'
    show batchAuth, teacher, choose, reveal;

Map<String, dynamic> options(Uri uri) => {
  'current_academic_session_id': 'session',
  'sessions': [
    {'id': 'session', 'name': 'Academic 2026'},
  ],
  'classes': [
    {'id': 'nursery', 'name': 'Nursery'},
    {'id': 'prep', 'name': 'Prep'},
  ],
  'sections': [
    {
      'id': uri.queryParameters['class_id'] == 'prep' ? 'prep-a' : 'nursery-a',
      'name': 'A',
    },
  ],
  'subjects': [
    {'id': 'english', 'code': 'ENG-01', 'name': 'English'},
    {'id': 'maths', 'code': 'MATH-01', 'name': 'Maths'},
  ],
};

Future<void> fillRow(
  WidgetTester tester,
  int row,
  String className,
  String subject,
) async {
  await choose(tester, row, 'class_id', className);
  await choose(tester, row, 'section_id', 'A');
  await choose(tester, row, 'subject_id', subject);
}

Future<void> addRow(WidgetTester tester) async {
  await reveal(tester, find.byKey(const Key('add-assignment')));
  await tester.tap(find.byKey(const Key('add-assignment')));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'School Setup distinguishes duplicate subject names by existing code',
    (tester) async {
      final auth = batchAuth(
        (r) async => ok({
          'items': r.url.path.endsWith('/subjects')
              ? [
                  {'id': 'english-1', 'code': 'ENG-01', 'name': 'Language'},
                  {'id': 'english-2', 'code': 'ENG-02', 'name': 'Language'},
                ]
              : [],
        }),
        permissions: ['school.manage'],
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(CatalogScreen(auth: auth)));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Subjects').last);
      await tester.pumpAndSettle();
      expect(find.text('ENG-01 — Language'), findsOneWidget);
      expect(find.text('ENG-02 — Language'), findsOneWidget);
    },
  );

  testWidgets(
    'one save submits many subjects and classes with one separate section Form Master',
    (tester) async {
      var writes = 0;
      Map<String, dynamic>? saved;
      final auth = batchAuth((r) async {
        if (r.method == 'POST') {
          writes++;
          saved = jsonDecode(r.body) as Map<String, dynamic>;
          return ok({'count': 4});
        }
        return ok(options(r.url));
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(TeacherAssignmentBatchScreen(auth: auth, teacher: teacher)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Any'), findsNothing);
      expect(find.text('Select class'), findsOneWidget);
      await fillRow(tester, 0, 'Nursery', 'ENG-01 — English');
      await addRow(tester);
      await fillRow(tester, 1, 'Nursery', 'MATH-01 — Maths');
      await addRow(tester);
      await fillRow(tester, 2, 'Prep', 'ENG-01 — English');
      await addRow(tester);
      await reveal(tester, find.byKey(const ValueKey('remove-draft-3')));
      await tester.tap(find.byKey(const ValueKey('remove-draft-3')));
      await tester.pumpAndSettle();
      final master = find.byKey(const ValueKey('form-master-nursery-a'));
      await reveal(tester, master);
      expect(master, findsOneWidget);
      await tester.tap(master);
      await tester.pumpAndSettle();
      await reveal(tester, find.byKey(const Key('save-assignments')));
      await tester.tap(find.byKey(const Key('save-assignments')));
      await tester.pumpAndSettle();
      final rows = saved!['assignments'] as List;
      expect(writes, 1);
      expect(saved?['staff_profile_id'], 'profile-fixture');
      expect(saved?['academic_session_id'], 'session');
      expect(rows.length, 4);
      expect(
        rows
            .where((r) => r['subject_id'] != null)
            .map((r) => '${r['section_id']}/${r['subject_id']}'),
        ['nursery-a/english', 'nursery-a/maths', 'prep-a/english'],
      );
      expect(rows.where((r) => r['is_class_teacher'] == true), [
        {
          'section_id': 'nursery-a',
          'subject_id': null,
          'is_class_teacher': true,
        },
      ]);
      expect(
        rows
            .where((r) => r['subject_id'] != null)
            .every((r) => r['is_class_teacher'] == false),
        isTrue,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'assignment groups show coded subjects and a single section designation',
    (tester) async {
      tester.view.physicalSize = const Size(800, 1400);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final auth = batchAuth(
        (r) async => ok({
          'data': [
            for (final row in [
              {
                'section_id': 'nursery-a',
                'class_name': 'Nursery',
                'subject_id': 'english',
                'subject_code': 'ENG-01',
                'subject_name': 'English',
                'is_class_teacher': false,
              },
              {
                'section_id': 'nursery-a',
                'class_name': 'Nursery',
                'subject_id': 'maths',
                'subject_code': 'MATH-01',
                'subject_name': 'Maths',
                'is_class_teacher': false,
              },
              {
                'section_id': 'nursery-a',
                'class_name': 'Nursery',
                'subject_id': null,
                'subject_code': null,
                'subject_name': null,
                'is_class_teacher': true,
              },
              {
                'section_id': 'prep-a',
                'class_name': 'Prep',
                'subject_id': 'english',
                'subject_code': 'ENG-01',
                'subject_name': 'English',
                'is_class_teacher': false,
              },
            ])
              {
                ...row,
                'section_name': 'A',
                'session_name': 'Academic 2026',
                'staff_profile_id': 'profile-fixture',
                'teacher_name': 'Ali Khan',
                'employee_number': 'STF-0001',
                'is_active': true,
              },
          ],
          'last_page': 1,
        }),
        permissions: ['assignments.manage'],
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeachingAssignmentsScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(find.text('Nursery — A'), findsOneWidget);
      expect(find.text('Prep — A'), findsOneWidget);
      expect(find.text('ENG-01 — English'), findsNWidgets(2));
      expect(find.text('MATH-01 — Maths'), findsOneWidget);
      expect(find.text('Class Teacher / Form Master'), findsOneWidget);
      expect(find.textContaining('profile-fixture'), findsNothing);
    },
  );

  testWidgets(
    'removing the last row for a section also removes its pending Form Master designation',
    (tester) async {
      Map<String, dynamic>? saved;
      final auth = batchAuth((r) async {
        if (r.method == 'POST') {
          saved = jsonDecode(r.body) as Map<String, dynamic>;
          return ok({'count': 1});
        }
        return ok(options(r.url));
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(TeacherAssignmentBatchScreen(auth: auth, teacher: teacher)),
      );
      await tester.pumpAndSettle();
      await fillRow(tester, 0, 'Nursery', 'ENG-01 — English');
      await addRow(tester);
      await fillRow(tester, 1, 'Prep', 'MATH-01 — Maths');
      await reveal(tester, find.byKey(const ValueKey('form-master-prep-a')));
      await tester.tap(find.byKey(const ValueKey('form-master-prep-a')));
      await tester.pumpAndSettle();
      await tester.drag(find.byType(Scrollable).first, const Offset(0, 700));
      await tester.pumpAndSettle();
      await reveal(tester, find.byKey(const ValueKey('remove-draft-1')));
      await tester.tap(find.byKey(const ValueKey('remove-draft-1')));
      await tester.pumpAndSettle();
      await reveal(tester, find.byKey(const Key('save-assignments')));
      await tester.tap(find.byKey(const Key('save-assignments')));
      await tester.pumpAndSettle();
      expect(saved?['assignments'], [
        {
          'section_id': 'nursery-a',
          'subject_id': 'english',
          'is_class_teacher': false,
        },
      ]);
    },
  );
}
