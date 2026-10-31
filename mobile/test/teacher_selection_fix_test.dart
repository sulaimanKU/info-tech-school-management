import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/administration/catalog_screen.dart';
import 'package:school/features/administration/teacher_assignment_batch_screen.dart';
import 'package:school/features/administration/teaching_assignments_screen.dart';
import 'package:school/shared/widgets/academic_filters.dart';

import 'final_ui_polish_test.dart' show host, ok;
import 'teacher_assignment_batch_test.dart'
    show batchAuth, choices, fill, reveal;

const legacyTeacher = <String, dynamic>{
  'id': null,
  'user_id': 'user-fixture',
  'staff_profile_id': null,
  'name': 'Existing Teacher Without Employment',
  'account_code': 'ACC-000042',
  'employee_number': null,
  'role': 'teacher',
  'is_active': true,
  'profile_link_required': true,
};

void main() {
  testWidgets(
    'selector renders and selects account-only teacher by user identity',
    (tester) async {
      final requests = <Uri>[];
      final auth = batchAuth((r) async {
        requests.add(r.url);
        return ok({
          'data': r.url.path.endsWith('/teachers') ? [legacyTeacher] : [],
          'last_page': 1,
        });
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeachingAssignmentsScreen(auth: auth)));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('filter-teacher')));
      await tester.pumpAndSettle();
      expect(find.text('ACC-000042'), findsOneWidget);
      expect(find.text('Teacher'), findsOneWidget);
      expect(find.text('Active'), findsOneWidget);
      await tester.tap(find.text(legacyTeacher['name'] as String));
      await tester.pumpAndSettle();
      expect(requests.last.queryParameters['teacher_user_id'], 'user-fixture');
      expect(
        requests.last.queryParameters.containsKey('staff_profile_id'),
        isFalse,
      );
      expect(find.text('Existing assignments'), findsOneWidget);
      expect(find.text('No teaching assignments yet'), findsOneWidget);
      expect(find.textContaining('user-fixture'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('teacher search has a distinct no-match state', (tester) async {
    final requests = <Uri>[];
    final auth = batchAuth((r) async {
      requests.add(r.url);
      return ok({
        'data': r.url.queryParameters.containsKey('search')
            ? []
            : [legacyTeacher],
        'last_page': 1,
      });
    }, permissions: ['assignments.manage']);
    addTearDown(auth.dispose);
    await tester.pumpWidget(host(TeacherPickerScreen(auth: auth)));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('teacher-search')),
      'Absent teacher',
    );
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    expect(requests.last.queryParameters['search'], 'Absent teacher');
    expect(find.text('No teachers match your search.'), findsOneWidget);
    expect(find.text('No teachers available'), findsNothing);
  });

  testWidgets(
    'empty teacher database explains account setup instead of generic emptiness',
    (tester) async {
      final auth = batchAuth(
        (r) async => ok({'data': [], 'last_page': 1}),
        permissions: ['school.manage'],
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeacherPickerScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(find.text('No teachers available'), findsOneWidget);
      expect(
        find.text(
          'Create or activate a teacher staff account before creating teaching assignments.',
        ),
        findsOneWidget,
      );
      expect(find.text('No records found.'), findsNothing);
    },
  );

  testWidgets('teacher API failure is retryable and never an empty result', (
    tester,
  ) async {
    var attempts = 0;
    final auth = batchAuth((r) async {
      attempts++;
      return attempts == 1
          ? http.Response('{}', 500)
          : ok({
              'data': [legacyTeacher],
              'last_page': 1,
            });
    }, permissions: ['assignments.manage']);
    addTearDown(auth.dispose);
    await tester.pumpWidget(host(TeacherPickerScreen(auth: auth)));
    await tester.pumpAndSettle();
    expect(find.text('No teachers available'), findsNothing);
    await tester.tap(find.widgetWithIcon(FilledButton, Icons.refresh_rounded));
    await tester.pumpAndSettle();
    expect(find.text(legacyTeacher['name'] as String), findsOneWidget);
    expect(attempts, 2);
  });

  testWidgets('teacher selector appends actual Laravel paginator pages', (
    tester,
  ) async {
    final requests = <Uri>[];
    final auth = batchAuth((r) async {
      requests.add(r.url);
      return ok({
        'data': [
          {
            ...legacyTeacher,
            'name': r.url.queryParameters['page'] == '2'
                ? 'Teacher Two'
                : 'Teacher One',
          },
        ],
        'last_page': 2,
      });
    }, permissions: ['assignments.manage']);
    addTearDown(auth.dispose);
    await tester.pumpWidget(host(TeacherPickerScreen(auth: auth)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Load more'));
    await tester.pumpAndSettle();
    expect(requests.last.queryParameters['page'], '2');
    expect(find.text('Teacher One'), findsOneWidget);
    expect(find.text('Teacher Two'), findsOneWidget);
  });

  testWidgets(
    'School Setup no longer exposes the duplicate teacher assignment editor',
    (tester) async {
      final auth = batchAuth(
        (r) async => ok({'items': []}),
        permissions: ['school.manage', 'staff.manage'],
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(CatalogScreen(auth: auth)));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<String>).first);
      await tester.pumpAndSettle();
      expect(find.text('Teacher assignments'), findsNothing);
      expect(find.text('Teaching assignments'), findsNothing);
      expect(find.text('Subjects'), findsWidgets);
    },
  );

  testWidgets('creation prompts exclude Any and reset dependent choices', (
    tester,
  ) async {
    Map<String, String> values = {};
    final auth = batchAuth(
      (r) async => ok(choices(r.url)),
      permissions: ['assignments.manage'],
    );
    addTearDown(auth.dispose);
    await tester.pumpWidget(
      host(
        AcademicSelection(
          auth: auth,
          creation: true,
          subject: true,
          initial: const {'academic_session_id': 'session-fixture'},
          onChanged: (v) => values = v,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Any'), findsNothing);
    expect(find.text('Select class'), findsOneWidget);
    expect(find.text('Select section'), findsOneWidget);
    tester
        .widget<ScopedChoice>(find.byKey(const ValueKey('filter-class_id')))
        .onChanged('class-fixture');
    await tester.pumpAndSettle();
    tester
        .widget<ScopedChoice>(find.byKey(const ValueKey('filter-section_id')))
        .onChanged('section-a');
    await tester.pumpAndSettle();
    tester
        .widget<ScopedChoice>(find.byKey(const ValueKey('filter-subject_id')))
        .onChanged('subject-math');
    await tester.pumpAndSettle();
    tester
        .widget<ScopedChoice>(find.byKey(const ValueKey('filter-class_id')))
        .onChanged('different-class');
    await tester.pumpAndSettle();
    expect(values.containsKey('section_id'), isFalse);
    expect(values.containsKey('subject_id'), isFalse);
    expect(find.text('Any'), findsNothing);
  });

  testWidgets(
    'administrator confirms real employment date before account-only assignment save',
    (tester) async {
      Map<String, dynamic>? saved;
      Map<String, dynamic>? linkedTeacher;
      final auth = batchAuth((r) async {
        if (r.method == 'POST') {
          saved = jsonDecode(r.body) as Map<String, dynamic>;
          return ok({
            'count': 1,
            'staff_profile_id': 'created-profile-fixture',
          });
        }
        return ok(choices(r.url));
      }, permissions: ['school.manage', 'staff.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(
          TeacherAssignmentBatchScreen(
            auth: auth,
            teacher: legacyTeacher,
            onSaved: (updated) => linkedTeacher = updated,
          ),
        ),
      );
      await tester.pumpAndSettle();
      await fill(tester, 0, 'Section A');
      await reveal(tester, find.byKey(const Key('save-assignments')));
      await tester.tap(find.byKey(const Key('save-assignments')));
      await tester.pumpAndSettle();
      expect(saved, isNull);
      expect(find.textContaining('Confirm the staff code'), findsOneWidget);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, 1400));
      await tester.pumpAndSettle();
      await reveal(tester, find.byKey(const Key('teacher-joining-date')));
      await tester.tap(find.byKey(const Key('teacher-joining-date')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await reveal(tester, find.byKey(const Key('save-assignments')));
      await tester.tap(find.byKey(const Key('save-assignments')));
      await tester.pumpAndSettle();
      expect(saved?['teacher_user_id'], 'user-fixture');
      expect(saved?.containsKey('staff_profile_id'), isFalse);
      expect(
        (saved?['employment'] as Map?)?['joined_on'],
        matches(r'^\d{4}-\d{2}-\d{2}$'),
      );
      expect((saved?['employment'] as Map?)?['employee_number'], 'ACC-000042');
      expect(linkedTeacher?['staff_profile_id'], 'created-profile-fixture');
      expect(linkedTeacher?['profile_link_required'], false);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'coordinator selecting account-only teacher cannot edit employment',
    (tester) async {
      var writes = 0;
      final auth = batchAuth((r) async {
        if (r.method == 'POST') writes++;
        return ok(choices(r.url));
      }, permissions: ['assignments.manage']);
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(TeacherAssignmentBatchScreen(auth: auth, teacher: legacyTeacher)),
      );
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('teacher-joining-date')), findsNothing);
      expect(
        find.textContaining('administrator with staff management permission'),
        findsOneWidget,
      );
      await reveal(tester, find.byKey(const Key('save-assignments')));
      expect(
        tester
            .widget<FilledButton>(find.byKey(const Key('save-assignments')))
            .onPressed,
        isNull,
      );
      expect(writes, 0);
    },
  );

  testWidgets(
    'direct teacher selector denies unauthorized users without fetching accounts',
    (tester) async {
      var requests = 0;
      final auth = batchAuth((r) async {
        requests++;
        return ok({'data': []});
      }, permissions: []);
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeacherPickerScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(requests, 0);
      expect(find.text('No teachers available'), findsNothing);
    },
  );
}
