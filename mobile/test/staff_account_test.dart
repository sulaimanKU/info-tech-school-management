import 'dart:convert';

import 'package:school/l10n/strings.dart';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/account/staff_account_screen.dart';
import 'package:school/features/account/staff_profile_editor.dart';
import 'package:school/features/account/change_password_screen.dart';
import 'package:school/features/auth/auth_controller.dart';

import 'final_ui_polish_test.dart' show host, authFor, ok;

Map<String, dynamic> accountData({
  bool linked = true,
  String role = 'teacher',
}) => {
  'account': {
    'id': 'own-user',
    'name': 'Fictional Staff',
    'email': 'fictional@example.test',
    'mobile': null,
    'address': null,
    'photo_file_id': null,
    'is_active': true,
    'roles': [
      {'code': role, 'name': role},
    ],
  },
  'profile': linked
      ? {
          'id': 'own-staff',
          'employee_number': 'FICTIONAL-EMP-1',
          'designation': 'Fictional teacher',
          'joined_on': '2026-04-01',
          'is_active': true,
        }
      : null,
  'staff_linked': linked,
};
http.Response failure(int status, {Map<String, dynamic> fields = const {}}) =>
    http.Response(
      jsonEncode({
        'error': {'fields': fields},
      }),
      status,
      headers: {'content-type': 'application/json'},
    );
void main() {
  test(
    'staff account field and error labels have English and Urdu translations',
    () {
      for (final key in [
        'mobile',
        'paidOn',
        'fullName',
        'email',
        'address',
        'employeeNumber',
        'designation',
        'joiningDate',
        'status',
        'myStaffProfile',
        'staffNotLinked',
        'accountCurrentPassword',
        'accountNewPassword',
        'accountConfirmPassword',
        'accountError_network',
        'accountError_server',
        'accountError_forbidden',
        'accountError_notFound',
        'accountError_unauthorized',
        'accountError_viewerUnavailable',
      ]) {
        expect(Strings.en[key], isNotNull, reason: key);
        expect(Strings.ur[key], isNotNull, reason: key);
      }
    },
  );

  for (final role in [
    'super_admin',
    'school_administrator',
    'coordinator',
    'accountant_clerk',
    'teacher',
  ]) {
    testWidgets('own profile supports $role with no employment link', (
      tester,
    ) async {
      final auth = authFor(
        (r) async => ok(accountData(linked: false, role: role)),
      );
      await tester.pumpWidget(host(StaffAccountScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(find.text('Fictional Staff'), findsWidgets);
      expect(find.text('fictional@example.test'), findsOneWidget);
      await tester.drag(find.byType(ListView), const Offset(0, -650));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('employment profile is not linked'),
        findsOneWidget,
      );
      expect(
        find.text('This record is not available to your account.'),
        findsNothing,
      );
      expect(tester.takeException(), isNull);
    });
  }
  testWidgets(
    'network failure retries but forbidden and missing do not offer connection retry',
    (tester) async {
      var code = 503;
      final auth = authFor((r) async => failure(code));
      await tester.pumpWidget(
        host(StaffAccountScreen(key: UniqueKey(), auth: auth)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Try again'), findsOneWidget);
      for (final value in [403, 404]) {
        code = value;
        await tester.pumpWidget(
          host(StaffAccountScreen(key: UniqueKey(), auth: auth)),
        );
        await tester.pumpAndSettle();
        expect(find.text('Try again'), findsNothing);
        expect(find.textContaining('administrator'), findsOneWidget);
      }
    },
  );
  testWidgets(
    'safe profile form sends allowed fields and returns refreshed result',
    (tester) async {
      final requests = <Map<String, dynamic>>[];
      final auth = authFor((r) async {
        if (r.method == 'PATCH') {
          requests.add(jsonDecode(r.body) as Map<String, dynamic>);
        }
        return ok({'saved': true});
      });
      await tester.pumpWidget(
        host(
          StaffProfileEditor(
            auth: auth,
            account: (accountData()['account'] as Map).cast<String, dynamic>(),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('own-name')),
        'Fictional Updated',
      );
      await tester.enterText(find.byKey(const Key('own-mobile')), '-------');
      await tester.ensureVisible(find.byKey(const Key('save-own-profile')));
      await tester.tap(find.byKey(const Key('save-own-profile')));
      await tester.pumpAndSettle();
      expect(requests, isEmpty);
      await tester.enterText(
        find.byKey(const Key('own-mobile')),
        '+923000000009',
      );
      await tester.ensureVisible(find.byKey(const Key('save-own-profile')));
      await tester.tap(find.byKey(const Key('save-own-profile')));
      await tester.pumpAndSettle();
      expect(
        requests.single.keys,
        unorderedEquals(['name', 'mobile', 'address']),
      );
      expect(requests.single['name'], 'Fictional Updated');
      expect(requests.single.containsKey('role'), isFalse);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'password fields are obscured and client validation prevents incomplete submission',
    (tester) async {
      var requests = 0;
      final auth = authFor((r) async {
        requests++;
        return ok({});
      });
      await tester.pumpWidget(host(ChangePasswordScreen(auth: auth)));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<TextField>(
              find.descendant(
                of: find.byKey(const Key('accountCurrentPassword')),
                matching: find.byType(TextField),
              ),
            )
            .obscureText,
        isTrue,
      );
      await tester.ensureVisible(find.byKey(const Key('save-own-password')));
      await tester.tap(find.byKey(const Key('save-own-password')));
      await tester.pumpAndSettle();
      expect(requests, 0);
      await tester.enterText(
        find.byKey(const Key('accountCurrentPassword')),
        'Fictional-current',
      );
      await tester.enterText(
        find.byKey(const Key('accountNewPassword')),
        'short',
      );
      await tester.enterText(
        find.byKey(const Key('accountConfirmPassword')),
        'different',
      );
      await tester.ensureVisible(find.byKey(const Key('save-own-password')));
      await tester.tap(find.byKey(const Key('save-own-password')));
      await tester.pumpAndSettle();
      expect(requests, 0);
      expect(find.text('The new passwords do not match.'), findsOneWidget);
    },
  );
  testWidgets('wrong current password uses useful localized feedback', (
    tester,
  ) async {
    final auth = authFor(
      (r) async => failure(
        422,
        fields: {
          'current_password': ['Invalid'],
        },
      ),
    );
    await tester.pumpWidget(host(ChangePasswordScreen(auth: auth)));
    await tester.pumpAndSettle();
    for (final entry in [
      ('accountCurrentPassword', 'wrong-current'),
      ('accountNewPassword', 'Fictional-new-password-only'),
      ('accountConfirmPassword', 'Fictional-new-password-only'),
    ]) {
      await tester.enterText(find.byKey(Key(entry.$1)), entry.$2);
    }
    await tester.ensureVisible(find.byKey(const Key('save-own-password')));
    await tester.tap(find.byKey(const Key('save-own-password')));
    await tester.pumpAndSettle();
    expect(find.text('The current password is incorrect.'), findsOneWidget);
  });
  testWidgets(
    'successful password change submits policy fields then signs out',
    (tester) async {
      final calls = <String>[];
      final auth = authFor((r) async {
        calls.add(r.url.path);
        return ok({'saved': true, 'reauthenticate': true});
      });
      await tester.pumpWidget(host(ChangePasswordScreen(auth: auth)));
      await tester.pumpAndSettle();
      for (final entry in [
        ('accountCurrentPassword', 'Fictional-old-password'),
        ('accountNewPassword', 'Fictional-new-password-only'),
        ('accountConfirmPassword', 'Fictional-new-password-only'),
      ]) {
        await tester.enterText(find.byKey(Key(entry.$1)), entry.$2);
      }
      await tester.ensureVisible(find.byKey(const Key('save-own-password')));
      await tester.tap(find.byKey(const Key('save-own-password')));
      await tester.pumpAndSettle();
      expect(calls, contains('/api/v1/account/password'));
      expect(calls, contains('/api/v1/auth/logout'));
      expect(auth.status, AuthStatus.signedOut);
    },
  );
  testWidgets(
    'salary empty and unlinked are separate from authorization errors',
    (tester) async {
      var linked = true;
      final auth = authFor(
        (r) async => ok({'staff_linked': linked, 'items': []}),
      );
      await tester.pumpWidget(
        host(StaffSalaryScreen(key: UniqueKey(), auth: auth)),
      );
      await tester.pumpAndSettle();
      expect(find.text('No paid salary slips yet'), findsOneWidget);
      linked = false;
      await tester.pumpWidget(
        host(StaffSalaryScreen(key: UniqueKey(), auth: auth)),
      );
      await tester.pumpAndSettle();
      expect(find.text('No paid salary slips yet'), findsNothing);
      expect(
        find.textContaining('employment profile is not linked'),
        findsOneWidget,
      );
    },
  );
  for (final width in [320.0, 390.0, 768.0, 1440.0]) {
    testWidgets('account Urdu RTL dark accessible layout at $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final auth = authFor((r) async => ok(accountData()));
      await tester.pumpWidget(
        host(
          StaffAccountScreen(auth: auth),
          urdu: true,
          dark: true,
          scale: 1.4,
        ),
      );
      await tester.pumpAndSettle();
      expect(
        Directionality.of(tester.element(find.byType(StaffAccountScreen))),
        TextDirection.rtl,
      );
      await tester.drag(find.byType(ListView), const Offset(0, -900));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }
}
