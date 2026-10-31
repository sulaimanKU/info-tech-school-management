import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:school/core/api_client.dart';
import 'package:school/features/academics/academic_settings_screen.dart';
import 'package:school/features/academics/attendance_screen.dart';
import 'package:school/features/academics/assessment_form.dart';
import 'package:school/features/academics/diary_editor.dart';
import 'package:school/features/academics/teacher_workspace_screen.dart';
import 'package:school/features/administration/school_profile_screen.dart';
import 'package:school/features/administration/teaching_assignments_screen.dart';
import 'package:school/shared/forms/domain_form.dart';

import 'final_ui_polish_test.dart' show authFor, host, ok;

final section = <String, dynamic>{
  'section_id': 'section-fixture',
  'class_id': 'class-fixture',
  'academic_session_id': 'session-fixture',
  'class_name': 'Grade 5',
  'section_name': 'Section A',
  'session_name': 'Fictional session',
  'student_count': 1,
  'attendance_status': 'pending',
  'subjects': [
    {'id': 'subject-fixture', 'name': 'Fictional Mathematics'},
  ],
};
Map<String, dynamic> references(String path) => {
  'items': switch (path.split('/').last) {
    'sections' => [
      {
        'id': 'section-fixture',
        'name': 'Section A',
        'class_id': 'class-fixture',
        'academic_session_id': 'session-fixture',
      },
    ],
    'classes' => [
      {'id': 'class-fixture', 'name': 'Grade 5'},
    ],
    _ => [
      {'id': 'session-fixture', 'name': 'Fictional session'},
    ],
  },
};
Map<String, dynamic> choices() => {
  'sessions': [
    {'id': 'session-fixture', 'name': 'Fictional session'},
  ],
  'classes': [
    {'id': 'class-fixture', 'name': 'Grade 5'},
  ],
  'sections': [
    {
      'id': 'section-fixture',
      'name': 'Section A',
      'class_id': 'class-fixture',
      'academic_session_id': 'session-fixture',
    },
  ],
  'subjects': [
    {'id': 'subject-fixture', 'name': 'Fictional Mathematics'},
  ],
};

Future<void> reveal(WidgetTester tester, Finder target) async {
  FocusManager.instance.primaryFocus?.unfocus();
  await tester.pumpAndSettle();
  for (
    var attempt = 0;
    attempt < 10 && target.hitTestable().evaluate().isEmpty;
    attempt++
  ) {
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -220));
    await tester.pumpAndSettle();
  }
  expect(target.hitTestable(), findsOneWidget);
}

void main() {
  testWidgets(
    'teacher classes open scoped students with readable codes and academic actions',
    (tester) async {
      final requests = <Uri>[];
      final auth = authFor((r) async {
        requests.add(r.url);
        if (r.url.path.endsWith('/my-teaching')) {
          return ok({
            'student_count': 1,
            'pending_attendance_sections': 1,
            'pending_assessments': 0,
            'sections': [section],
            'assessments': [],
            'upcoming_exams': [],
            'recent_diary': [],
          });
        }
        if (r.url.path.contains('/my-teaching/sections/')) {
          return ok({
            'data': [
              {
                'id': 'student-fixture',
                'full_name': 'Fictional Child',
                'registration_number': 'INF-SG-26-0001',
                'roll_number': 1,
                'photo_file_id': null,
              },
            ],
            'last_page': 1,
          });
        }
        throw StateError('Unexpected endpoint ${r.url.path}');
      });
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeacherWorkspaceScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(find.text('Grade 5 | Section A'), findsOneWidget);
      expect(find.text('Fictional Mathematics'), findsOneWidget);
      await tester.tap(find.text('Open class'));
      await tester.pumpAndSettle();
      expect(find.text('1. Fictional Child'), findsOneWidget);
      expect(find.text('INF-SG-26-0001'), findsOneWidget);
      expect(find.textContaining('student-fixture'), findsNothing);
      expect(
        requests.last.path,
        endsWith('/my-teaching/sections/section-fixture/students'),
      );
      await tester.enterText(find.byType(TextField).first, 'Fictional');
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pumpAndSettle();
      expect(requests.last.queryParameters['search'], 'Fictional');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'empty teacher assignments explain setup and expose no unrelated students',
    (tester) async {
      final auth = authFor(
        (r) async => ok({
          'student_count': 0,
          'pending_attendance_sections': 0,
          'pending_assessments': 0,
          'sections': [],
          'assessments': [],
          'upcoming_exams': [],
          'recent_diary': [],
        }),
      );
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeacherWorkspaceScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('No active teaching assignments'),
        findsOneWidget,
      );
      expect(find.text('Open class'), findsNothing);
    },
  );

  testWidgets(
    'direct teacher access to assignment management stops before any API request',
    (tester) async {
      var requests = 0;
      final auth = authFor((r) async {
        requests++;
        return ok({});
      });
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeachingAssignmentsScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(requests, 0);
      expect(find.byType(FloatingActionButton), findsNothing);
    },
  );

  testWidgets('school profile edits save then reload the persisted values', (
    tester,
  ) async {
    final calls = <String>[];
    var values = <String, dynamic>{
      'school_name': 'Fictional School',
      'campus': 'North',
      'location': 'Fictional City',
      'currency': 'PKR',
      'timezone': 'Asia/Karachi',
      'current_academic_session_id': null,
    };
    final auth = authFor((r) async {
      calls.add('${r.method} ${r.url.path}');
      if (r.url.path.contains('/catalog/')) return ok(references(r.url.path));
      if (r.method == 'PUT') {
        values = {...values, ...jsonDecode(r.body) as Map<String, dynamic>};
        return ok({'saved': true});
      }
      return ok(values);
    });
    auth.user = {
      ...auth.user!,
      'roles': [
        {'code': 'super_admin'},
      ],
    };
    addTearDown(auth.dispose);
    await tester.pumpWidget(
      host(SchoolProfileScreen(auth: auth, editable: true)),
    );
    await tester.pumpAndSettle();
    await reveal(tester, find.text('Edit school information'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Edit school information'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.descendant(
        of: find.byKey(const ValueKey('school_name')),
        matching: find.byType(TextFormField),
      ),
      'Fictional Renamed School',
    );
    await reveal(tester, find.widgetWithText(FilledButton, 'Save'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Save'));
    await tester.pumpAndSettle();
    expect(values['school_name'], 'Fictional Renamed School');
    expect(calls.where((v) => v.startsWith('PUT ')).length, 1);
    expect(calls.last, 'GET /api/v1/school-settings');
    await tester.drag(find.byType(Scrollable).first, const Offset(0, 1400));
    await tester.pumpAndSettle();
    expect(
      find.byWidgetPredicate(
        (w) => w is SelectableText && w.data == 'Fictional Renamed School',
      ),
      findsOneWidget,
    );
  });

  testWidgets(
    'academic settings use one atomic save and reload instead of a second grading write',
    (tester) async {
      final writes = <Map<String, dynamic>>[];
      final auth = authFor((r) async {
        if (r.url.path.contains('/catalog/')) return ok(references(r.url.path));
        if (r.method == 'PUT') {
          writes.add(jsonDecode(r.body) as Map<String, dynamic>);
          return ok({'saved': true});
        }
        return ok({
          'weekdays': [1, 2, 3, 4, 5],
          'class_position_enabled': false,
          'bands': [
            {'minimum_percent': 0, 'grade': 'F'},
          ],
          'school_days': [],
        });
      });
      auth.user = {
        ...auth.user!,
        'roles': [
          {'code': 'super_admin'},
        ],
      };
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(AcademicSettingsScreen(auth: auth)));
      await tester.pumpAndSettle();
      await reveal(tester, find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(writes.length, 1);
      expect(writes.single['bands'], [
        {'minimum_percent': 0, 'grade': 'F'},
      ]);
      expect(writes.single['academic_session_id'], 'session-fixture');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'submitted teacher attendance is read only and displays status counts',
    (tester) async {
      final auth = authFor((r) async {
        if (r.url.path.contains('/catalog/')) return ok(references(r.url.path));
        if (r.url.path.endsWith('/attendance/register')) {
          return ok({
            'is_school_day': true,
            'register': {'version': 1, 'status': 'submitted'},
            'students': [
              {
                'id': 's1',
                'full_name': 'Fictional Child',
                'registration_number': 'INF-SG-26-0001',
                'roll_number': 1,
              },
            ],
            'records': [
              {'student_id': 's1', 'status': 'present'},
            ],
          });
        }
        throw StateError('Unexpected endpoint');
      });
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(AttendanceScreen(auth: auth, initialSection: 'section-fixture')),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Present: 1'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Submit'), findsNothing);
      expect(find.text('Save draft'), findsNothing);
    },
  );

  testWidgets(
    'homework editor loads academic roster rather than future attendance endpoint',
    (tester) async {
      final paths = <String>[];
      final auth = authFor((r) async {
        paths.add(r.url.path);
        if (r.url.path.contains('/catalog/')) return ok(references(r.url.path));
        if (r.url.path.endsWith('/academic-filter-options')) {
          return ok(choices());
        }
        if (r.url.path.endsWith('/academic-roster')) {
          return ok({'students': []});
        }
        throw StateError('Unexpected endpoint ${r.url.path}');
      });
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(
          DiaryEditor(
            auth: auth,
            initialSection: 'section-fixture',
            initialSubject: 'subject-fixture',
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(paths, contains('/api/v1/academic-roster'));
      expect(paths.any((p) => p.contains('attendance/register')), false);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'managed assessment form chooses teacher from the selected subject assignment',
    (tester) async {
      Map<String, dynamic>? submitted;
      final auth = authFor((r) async {
        if (r.url.path.endsWith('/academic-filter-options')) {
          return ok(choices());
        }
        if (r.url.path.endsWith('/teaching-assignments')) {
          expect(r.url.queryParameters['section_id'], 'section-fixture');
          expect(r.url.queryParameters['subject_id'], 'subject-fixture');
          return ok({
            'data': [
              {
                'teacher_user_id': 'teacher-fixture',
                'employee_number': 'STF-0001',
                'teacher_name': 'Fictional Teacher',
              },
            ],
          });
        }
        if (r.url.path.endsWith('/assessments')) {
          submitted = jsonDecode(r.body) as Map<String, dynamic>;
          return ok({'id': 'assessment-fixture'});
        }
        throw StateError('Unexpected endpoint');
      });
      auth.user = {
        ...auth.user!,
        'roles': [
          {'code': 'super_admin'},
        ],
      };
      addTearDown(auth.dispose);
      await tester.pumpWidget(
        host(AssessmentForm(auth: auth, initialSection: 'section-fixture')),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<String>).at(3));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Fictional Mathematics').last);
      await tester.pumpAndSettle();
      expect(find.text('STF-0001 | Fictional Teacher'), findsOneWidget);
      await tester.enterText(
        find.byType(TextFormField).first,
        'Fictional Test',
      );
      await reveal(tester, find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(submitted?['teacher_user_id'], 'teacher-fixture');
      expect(submitted?['subject_id'], 'subject-fixture');
    },
  );

  testWidgets(
    'shared forms preserve exact passwords and submit real boolean values',
    (tester) async {
      Map<String, dynamic>? saved;
      await tester.pumpWidget(
        host(
          DomainForm(
            title: 'Account fixture',
            fields: const [
              FormFieldSpec(
                'password',
                'Password fixture',
                kind: FieldKind.password,
              ),
              FormFieldSpec(
                'is_active',
                'Active fixture',
                kind: FieldKind.boolean,
                initial: false,
              ),
            ],
            onSubmit: (v) async {
              saved = v;
            },
          ),
        ),
      );
      await tester.enterText(find.byType(TextFormField), ' 12 ');
      await tester.tap(find.byType(CheckboxListTile));
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(saved?['password'], ' 12 ');
      expect(saved?['is_active'], true);
    },
  );

  testWidgets(
    'coordinator edits an authorized teaching assignment without staff-account access',
    (tester) async {
      Map<String, dynamic>? saved;
      final auth = authFor((r) async {
        if (r.url.path.endsWith('/academic-filter-options')) {
          return ok(choices());
        }
        if (r.method == 'PATCH') {
          saved = jsonDecode(r.body) as Map<String, dynamic>;
          return ok({'saved': true});
        }
        return ok({
          'data': [
            {
              'id': 'assignment-fixture',
              'staff_profile_id': 'profile-fixture',
              'teacher_name': 'Fictional Teacher',
              'employee_number': 'STF-0001',
              'class_name': 'Grade 5',
              'section_name': 'Section A',
              'session_name': 'Fictional session',
              'academic_session_id': 'session-fixture',
              'class_id': 'class-fixture',
              'section_id': 'section-fixture',
              'subject_id': 'subject-fixture',
              'subject_name': 'Fictional Mathematics',
              'is_class_teacher': false,
              'is_active': true,
              'starts_on': null,
              'ends_on': null,
            },
          ],
          'last_page': 1,
        });
      });
      auth.user = {
        ...auth.user!,
        'roles': [
          {'code': 'coordinator'},
        ],
        'permissions': ['academics.read', 'assignments.manage'],
      };
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(TeachingAssignmentsScreen(auth: auth)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Fictional Mathematics'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(SwitchListTile).last);
      await tester.pumpAndSettle();
      await tester.tap(find.byType(SwitchListTile).last);
      await reveal(tester, find.widgetWithText(FilledButton, 'Save'));
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(saved?['section_id'], 'section-fixture');
      expect(saved?['staff_profile_id'], 'profile-fixture');
      expect(saved?['is_active'], false);
    },
  );

  testWidgets(
    'parent school-information view never offers editable administration controls',
    (tester) async {
      final paths = <String>[];
      final auth = authFor((r) async {
        paths.add(r.url.path);
        return ok({
          'school_name': 'Fictional School',
          'campus': 'North',
          'location': 'Fictional City',
          'currency': 'PKR',
          'timezone': 'Asia/Karachi',
        });
      });
      auth.user = {
        ...auth.user!,
        'roles': [
          {'code': 'parent_guardian'},
        ],
        'permissions': [],
      };
      addTearDown(auth.dispose);
      await tester.pumpWidget(host(SchoolProfileScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(paths, ['/api/v1/school-information']);
      expect(find.byType(FilledButton), findsNothing);
      expect(find.text('SMS status'), findsNothing);
    },
  );

  test('API errors distinguish authorization, missing records, validation, conflicts and server faults', () async {
    for (final e in {
      401: 'unauthorized',
      403: 'forbidden',
      404: 'notFound',
      409: 'conflict',
      422: 'validation',
      500: 'server',
    }.entries) {
      final api = ApiClient(
        baseUrl: 'http://localhost/api/v1',
        client: MockClient(
          (r) async => http.Response(
            jsonEncode({
              'error': {
                'message': 'Internal information must not become UI text',
                'fields': {},
              },
            }),
            e.key,
          ),
        ),
      );
      await expectLater(
        api.request('test'),
        throwsA(isA<ApiFailure>().having((f) => f.code, 'safe code', e.value)),
      );
    }
  });
}
