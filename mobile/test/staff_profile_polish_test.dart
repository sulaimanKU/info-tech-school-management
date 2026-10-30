import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:school/features/account/staff_account_screen.dart';
import 'package:school/features/administration/staff_screen.dart';

import 'final_ui_polish_test.dart' show authFor, host, ok;
import 'staff_account_test.dart' show accountData, failure;

List<Map<String, dynamic>> teachingRows() => [
  {
    'id': 'opaque-assignment-a',
    'class_name': 'Grade 5',
    'section_name': 'Section A',
    'subject_name': 'Mathematics',
    'session_name': 'Academic 2026',
    'kind': 'teacher',
    'is_active': true,
  },
  {
    'id': 'opaque-assignment-b',
    'class_name': 'Grade 5',
    'section_name': 'Section B',
    'subject_name': 'Mathematics',
    'session_name': 'Academic 2026',
    'kind': 'teacher',
    'is_active': true,
  },
  {
    'id': 'opaque-assignment-c',
    'class_name': 'Grade 6',
    'section_name': 'Section A',
    'subject_name': 'Computer Science',
    'session_name': 'Academic 2025',
    'kind': 'teacher',
    'is_active': false,
  },
];

void main() {
  for (final dark in [false, true]) {
    testWidgets(
      'staff profile shows contact and every readable assignment in ${dark ? 'dark' : 'light'} mode',
      (tester) async {
        final data = accountData();
        data['account'] = {
          ...(data['account'] as Map).cast<String, dynamic>(),
          'account_code': 'ACC-00123',
          'mobile': '+923000000001',
          'address': 'Fictional staff contact address',
        };
        data['assignments'] = teachingRows();
        final auth = authFor((r) async => ok(data));
        await tester.pumpWidget(
          host(StaffAccountScreen(auth: auth), dark: dark),
        );
        await tester.pumpAndSettle();
        expect(find.text('ACC-00123'), findsWidgets);
        expect(find.text('+923000000001'), findsOneWidget);
        expect(find.text('Fictional staff contact address'), findsOneWidget);
        expect(find.text('Grade 5 · Section A'), findsOneWidget);
        expect(find.text('Grade 5 · Section B'), findsOneWidget);
        expect(find.text('Grade 6 · Section A'), findsOneWidget);
        expect(find.text('Mathematics'), findsNWidgets(2));
        expect(find.text('Computer Science'), findsOneWidget);
        expect(find.text('Academic 2025'), findsOneWidget);
        expect(find.text('opaque-assignment-a'), findsNothing);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('linked teacher profile explains an empty teaching list', (
    tester,
  ) async {
    final data = accountData()..['assignments'] = [];
    final auth = authFor((r) async => ok(data));
    await tester.pumpWidget(host(StaffAccountScreen(auth: auth)));
    await tester.pumpAndSettle();
    expect(find.textContaining('No teaching assignments'), findsOneWidget);
    expect(
      find.textContaining('employment profile is not linked'),
      findsNothing,
    );
  });

  testWidgets(
    'admin employment editor updates existing profile instead of creating a second one',
    (tester) async {
      Map<String, dynamic>? saved;
      final source = accountData();
      final account = (source['account'] as Map).cast<String, dynamic>();
      var designation = 'Fictional teacher';
      final calls = <String>[];
      final auth = authFor((r) async {
        calls.add('${r.method} ${r.url.path}');
        if (r.method == 'PATCH') {
          saved = jsonDecode(r.body) as Map<String, dynamic>;
          designation = (saved!['employment'] as Map)['designation'] as String;
          return ok({'saved': true});
        }
        return ok({
          ...account,
          'profile': {...source['profile'] as Map, 'designation': designation},
          'assignments': teachingRows(),
        });
      });
      auth.user!['permissions'] = ['staff.manage', 'school.manage'];
      await tester.pumpWidget(
        host(StaffDetailScreen(auth: auth, id: 'own-user')),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.byKey(const Key('edit-staff-employment')),
        250,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.byKey(const Key('edit-staff-employment')));
      await tester.pumpAndSettle();
      final field = find.widgetWithText(TextFormField, 'Designation');
      await tester.enterText(field, 'Updated teaching designation');
      FocusManager.instance.primaryFocus?.unfocus();
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Save').last);
      await tester.tap(find.text('Save').last);
      await tester.pumpAndSettle();
      expect(saved?.keys, ['employment']);
      expect(
        (saved?['employment'] as Map?)?['designation'],
        'Updated teaching designation',
      );
      expect(calls.where((c) => c.startsWith('POST')), isEmpty);
      await tester.drag(find.byType(ListView), const Offset(0, 1600));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Updated teaching designation'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'photo failure is recoverable and does not replace the profile with an exception',
    (tester) async {
      final data = accountData();
      (data['account'] as Map)['photo_file_id'] = 'private-photo-fixture';
      var downloads = 0;
      final auth = authFor((r) async {
        if (r.url.path.contains('/files/')) {
          downloads++;
          return failure(503);
        }
        return ok(data);
      });
      await tester.pumpWidget(host(StaffAccountScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(find.text('Fictional Staff'), findsWidgets);
      final retry = find.byTooltip('Photo unavailable · tap to retry');
      expect(retry, findsOneWidget);
      await tester.tap(retry);
      await tester.pumpAndSettle();
      expect(downloads, 2);
      expect(tester.takeException(), isNull);
    },
  );
}
