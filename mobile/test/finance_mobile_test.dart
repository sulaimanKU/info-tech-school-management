import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:school/app/school_app.dart';
import 'package:school/shared/forms/domain_form.dart';

import 'support/auth_fixtures.dart';

void main() {
  testWidgets(
    'money form submits exact integer minor units, not floating point',
    (tester) async {
      Map<String, dynamic>? submitted;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: FilledButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute<bool>(
                    builder: (_) => DomainForm(
                      title: 'amount',
                      fields: const [
                        FormFieldSpec(
                          'amount_minor',
                          'Amount (PKR)',
                          kind: FieldKind.money,
                        ),
                      ],
                      onSubmit: (v) async {
                        submitted = v;
                      },
                    ),
                  ),
                ),
                child: const Text('Open'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), '500.01');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      expect(submitted?['amount_minor'], 50001);
    },
  );
  testWidgets('financial navigation requires server-issued permission', (
    tester,
  ) async {
    final auth = createAuth(MemoryStore());
    await auth.login('staff@example.test', 'test-only');
    auth.user = {
      ...testUser,
      'permissions': ['finance.read'],
    };
    await tester.pumpWidget(SchoolApp(auth: auth));
    await tester.ensureVisible(find.text('Fees & Finance').last);
    await tester.tap(find.text('Fees & Finance').last);
    await tester.pumpAndSettle();
    expect(find.text('Fee vouchers'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Trial balance'), 200);
    expect(find.text('Trial balance'), findsOneWidget);
    expect(find.text('Payroll'), findsOneWidget);
  });
}
