import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:school/core/api_client.dart';
import 'package:school/shared/forms/domain_form.dart';

import 'final_ui_polish_test.dart' show host;

void main() {
  testWidgets(
    'server field validation appears by the input and clears when corrected',
    (tester) async {
      await tester.pumpWidget(
        host(
          DomainForm(
            title: 'staffManagement',
            fields: const [
              FormFieldSpec(
                'email',
                'email',
                initial: 'fictional@example.test',
              ),
            ],
            onSubmit: (_) async {
              throw const ApiFailure(
                'validation',
                fields: {
                  'email': ['This fictional email is already registered.'],
                },
              );
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        tester.widget<TextField>(find.byType(TextField)).keyboardType,
        TextInputType.emailAddress,
      );
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      expect(
        find.text('This fictional email is already registered.'),
        findsOneWidget,
      );
      await tester.enterText(
        find.byType(TextFormField),
        'other-fictional@example.test',
      );
      await tester.pumpAndSettle();
      expect(
        find.text('This fictional email is already registered.'),
        findsNothing,
      );
    },
  );
  testWidgets(
    'phone fields use the phone keyboard and password fields remain obscured',
    (tester) async {
      await tester.pumpWidget(
        host(
          DomainForm(
            title: 'staffManagement',
            fields: const [
              FormFieldSpec('mobile', 'verifiedMobile'),
              FormFieldSpec('password', 'password', kind: FieldKind.password),
            ],
            onSubmit: (_) async {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      final fields = tester
          .widgetList<TextField>(find.byType(TextField))
          .toList();
      expect(fields[0].keyboardType, TextInputType.phone);
      expect(fields[1].obscureText, true);
      expect(tester.takeException(), isNull);
    },
  );
}
