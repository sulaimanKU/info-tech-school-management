import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:school/features/finance/financial_forms.dart';
import 'package:school/features/finance/student_fee_screen.dart';
import 'package:school/features/students/student_form.dart';
import 'package:school/shared/forms/domain_form.dart';

import 'final_ui_polish_test.dart' show authFor, host, ok;

import 'dart:convert';

void main() {
  testWidgets(
    'monthly fee action previews before posting and confirms explicit generation',
    (tester) async {
      final requests = <Map<String, dynamic>>[];
      final auth = authFor((r) async {
        if (r.method == 'POST') {
          final body = jsonDecode(r.body) as Map<String, dynamic>;
          requests.add(body);
          return ok({
            'student_count': 1,
            'missing_months': 8,
            'unbilled_minor': 800000,
            'periods': [
              {
                'registration_number': 'INF-001',
                'period': '2026-03',
                'status': 'not_billed',
                'invoice_id': null,
                'net_minor': 100000,
              },
            ],
            'generated_ids': body['preview'] == true ? [] : ['invoice-id'],
          });
        }
        return ok({
          'items': [
            {'id': 'session-id', 'name': 'Fictional session'},
          ],
        });
      });
      addTearDown(auth.dispose);
      auth.user = {
        ...auth.user!,
        'permissions': ['finance.read', 'finance.write'],
      };
      await tester.pumpWidget(
        host(
          Builder(
            builder: (context) => TextButton(
              onPressed: () => generateMonthlyFees(
                context,
                auth,
                studentId: 'student-id',
                studentLabel: 'INF-001',
              ),
              child: const Text('Missing fees'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Missing fees'));
      await tester.pumpAndSettle();
      expect(requests, isEmpty);
      await tester.ensureVisible(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pump(const Duration(milliseconds: 800));
      expect(requests.length, 1);
      expect(requests.single['preview'], true);
      expect(find.textContaining('Missing months: 8'), findsOneWidget);
      await tester.tap(
        find.widgetWithText(FilledButton, 'Generate missing fees'),
      );
      await tester.pumpAndSettle();
      expect(requests.length, 2);
      expect(requests.last['preview'], false);
      expect(requests.last['student_id'], 'student-id');
    },
  );

  for (final parent in [false, true]) {
    testWidgets(
      'monthly history and missing fees in dark narrow view; parent=$parent',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(320, 900));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final auth = authFor(
          (r) async => ok({
            'student': {
              'full_name': 'Fictional Student',
              'registration_number': 'INF-001',
              'admitted_on': '2026-03-01',
              'session_name': 'Fictional session',
            },
            'period': '2026-10',
            'missing_months': 8,
            'unbilled_minor': 800000,
            'balance_minor': 0,
            'items': [],
            'payments': [],
            'discounts': [],
            'fee_schedule': [],
            'monthly_periods': [
              for (var month = 3; month <= 10; month++)
                {
                  'period': '2026-${month.toString().padLeft(2, '0')}',
                  'session_name': 'Fictional session',
                  'gross_minor': 100000,
                  'discount_minor': 0,
                  'net_minor': 100000,
                  'paid_minor': 0,
                  'balance_minor': null,
                  'invoice_id': null,
                  'status': 'not_billed',
                },
            ],
          }),
        );
        addTearDown(auth.dispose);
        auth.user = {
          ...auth.user!,
          'permissions': parent ? [] : ['finance.read', 'finance.write'],
          'roles': [
            {
              'code': parent ? 'parent_guardian' : 'accountant_clerk',
              'name': 'Test role',
            },
          ],
        };
        await tester.pumpWidget(
          host(
            StudentFeeScreen(auth: auth, studentId: 'student-id'),
            dark: true,
          ),
        );
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(
          find.textContaining('8 applicable months'),
          250,
        );
        expect(find.textContaining('8 applicable months'), findsOneWidget);
        if (!parent) {
          await tester.scrollUntilVisible(
            find.widgetWithText(FilledButton, 'Generate missing fees'),
            150,
          );
        }
        expect(
          find.widgetWithText(FilledButton, 'Generate missing fees'),
          parent ? findsNothing : findsOneWidget,
        );
        await tester.scrollUntilVisible(
          find.text('2026-03 | Fictional session'),
          200,
        );
        expect(find.textContaining('Not billed'), findsWidgets);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'admission configuration permission does not reveal payment collection fields',
    (tester) async {
      final auth = authFor((r) async {
        if (r.url.path.endsWith('/sections')) {
          return ok({
            'items': [
              {
                'id': 'section-id',
                'name': 'A',
                'class_id': 'class-id',
                'academic_session_id': 'session-id',
              },
            ],
          });
        }
        return ok({
          'items': [
            {
              'id': r.url.path.endsWith('/classes') ? 'class-id' : 'session-id',
              'name': 'Fictional',
            },
          ],
        });
      });
      addTearDown(auth.dispose);
      auth.user = {
        ...auth.user!,
        'permissions': ['students.manage', 'finance.configure', 'finance.read'],
      };
      await tester.pumpWidget(
        host(
          Builder(
            builder: (context) => TextButton(
              onPressed: () => openStudentForm(context, auth),
              child: const Text('Admission'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Admission'));
      await tester.pumpAndSettle();
      final form = tester.widget<DomainForm>(find.byType(DomainForm));
      expect(form.fields.any((f) => f.name == '_tuition_override'), true);
      expect(form.fields.any((f) => f.name == '_received'), false);
      expect(form.fields.any((f) => f.name == '_admission_fee'), false);
    },
  );
}
