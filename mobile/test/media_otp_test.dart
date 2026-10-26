import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/auth/widgets/parent_login_form.dart';
import 'package:school/shared/forms/domain_form.dart';
import 'package:school/shared/forms/profile_photo_picker.dart';
import 'package:school/shared/widgets/authorized_photo.dart';

import 'support/auth_fixtures.dart';
import 'final_ui_polish_test.dart' show host;

final png = base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=',
);
http.Response ok(Map<String, dynamic> data, [int status = 200]) =>
    http.Response(jsonEncode({'data': data}), status);
void main() {
  testWidgets('missing avatar uses initials and makes no download', (
    tester,
  ) async {
    var calls = 0;
    final auth = createAuth(
      MemoryStore(),
      handler: (_) async {
        calls++;
        return ok({});
      },
    );
    await tester.pumpWidget(
      host(AuthorizedPhoto(auth: auth, name: 'Fictional Child')),
    );
    expect(find.text('FC'), findsOneWidget);
    expect(calls, 0);
  });
  testWidgets(
    'private photo loads once per file and offers retry after failure',
    (tester) async {
      var calls = 0;
      var fail = true;
      final auth = createAuth(
        MemoryStore(),
        handler: (r) async {
          calls++;
          expect(r.url.path, endsWith('files/one'));
          return fail
              ? http.Response('{}', 404)
              : http.Response.bytes(png, 200);
        },
      );
      await tester.pumpWidget(host(AuthorizedPhoto(auth: auth, fileId: 'one')));
      await tester.pumpAndSettle();
      expect(
        find.byTooltip('Photo unavailable \u00b7 tap to retry'),
        findsOneWidget,
      );
      expect(calls, 1);
      fail = false;
      await tester.tap(find.byType(IconButton));
      await tester.pumpAndSettle();
      expect(calls, 2);
      // The old sample fixture is decoder-invalid; image errors must still render a fallback safely.
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'photo selection previews and removal preserves optional form state',
    (tester) async {
      final auth = createAuth(MemoryStore());
      var draft = ProfilePhotoDraft();
      await tester.pumpWidget(
        host(
          StatefulBuilder(
            builder: (c, setState) => ProfilePhotoPicker(
              auth: auth,
              value: draft,
              onChanged: (v) => setState(() => draft = v),
              pickImage: () async => png,
            ),
          ),
        ),
      );
      await tester.tap(find.byKey(const Key('choose-profile-photo')));
      await tester.pumpAndSettle();
      expect(draft.bytes, isNotNull);
      expect(find.byKey(const Key('remove-profile-photo')), findsOneWidget);
      await tester.tap(find.byKey(const Key('remove-profile-photo')));
      await tester.pumpAndSettle();
      expect(draft.bytes, isNull);
      expect(draft.removed, true);
    },
  );
  testWidgets('optional photo field submits without a selection', (
    tester,
  ) async {
    ProfilePhotoDraft? saved;
    final auth = createAuth(MemoryStore());
    await tester.pumpWidget(
      host(
        DomainForm(
          title: 'addGuardian',
          fields: [
            FormFieldSpec(
              '_photo',
              'profilePhoto',
              kind: FieldKind.photo,
              required: false,
              photoAuth: auth,
            ),
          ],
          onSubmit: (v) async {
            saved = v['_photo'] as ProfilePhotoDraft;
          },
        ),
      ),
    );
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(saved, isNotNull);
    expect(saved!.bytes, isNull);
  });
  testWidgets('Urdu dark photo controls fit a small phone with text scaling', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      host(
        SingleChildScrollView(
          child: ProfilePhotoPicker(
            auth: createAuth(MemoryStore()),
            value: ProfilePhotoDraft(existingId: 'one'),
            onChanged: (_) {},
          ),
        ),
        urdu: true,
        dark: true,
        scale: 1.6,
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
  testWidgets('OTP resend works with empty OTP and identity can be corrected', (
    tester,
  ) async {
    var requests = 0;
    final auth = createAuth(
      MemoryStore(),
      handler: (r) async {
        requests++;
        expect(jsonDecode(r.body)['cnic'], '0000000000001');
        return ok({
          'challenge_id': 'fictional-$requests',
          'expires_in': 5,
          'resend_after': 1,
        }, 202);
      },
    );
    await tester.pumpWidget(
      host(SingleChildScrollView(child: ParentLoginForm(auth: auth))),
    );
    await tester.enterText(
      find.byKey(const Key('parent-cnic')),
      '0000000000001',
    );
    await tester.ensureVisible(find.text('Request code'));
    await tester.tap(find.text('Request code'));
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 2));
    await tester.ensureVisible(find.text('Resend code'));
    await tester.tap(find.text('Resend code'));
    await tester.pumpAndSettle();
    expect(requests, 2);
    await tester.ensureVisible(
      find.byKey(const Key('correct-parent-identity')),
    );
    await tester.tap(find.byKey(const Key('correct-parent-identity')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('parent-otp')), findsNothing);
    expect(
      tester
          .widget<TextFormField>(find.byKey(const Key('parent-cnic')))
          .enabled,
      true,
    );
  });
  testWidgets('expired OTP prevents verification and explains renewal', (
    tester,
  ) async {
    var verifications = 0;
    final auth = createAuth(
      MemoryStore(),
      handler: (r) async {
        if (r.url.path.endsWith('verify')) verifications++;
        return ok({
          'challenge_id': 'fictional',
          'expires_in': 1,
          'resend_after': 1,
        }, 202);
      },
    );
    await tester.pumpWidget(
      host(SingleChildScrollView(child: ParentLoginForm(auth: auth))),
    );
    await tester.enterText(
      find.byKey(const Key('parent-cnic')),
      '0000000000001',
    );
    await tester.ensureVisible(find.text('Request code'));
    await tester.tap(find.text('Request code'));
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 2));
    await tester.ensureVisible(find.byKey(const Key('parent-otp')));
    await tester.enterText(find.byKey(const Key('parent-otp')), '000000');
    await tester.ensureVisible(find.text('Verify and sign in'));
    await tester.tap(find.text('Verify and sign in'));
    await tester.pumpAndSettle();
    expect(verifications, 0);
    expect(
      find.text('This code has expired. Request a new code.'),
      findsWidgets,
    );
  });
  testWidgets('invalid OTP remains on challenge with clear error', (
    tester,
  ) async {
    final auth = createAuth(
      MemoryStore(),
      handler: (r) async => r.url.path.endsWith('verify')
          ? http.Response('{}', 401)
          : ok({
              'challenge_id': 'fictional',
              'expires_in': 300,
              'resend_after': 60,
            }, 202),
    );
    await tester.pumpWidget(
      host(
        AnimatedBuilder(
          animation: auth,
          builder: (_, _) =>
              SingleChildScrollView(child: ParentLoginForm(auth: auth)),
        ),
      ),
    );
    await tester.enterText(
      find.byKey(const Key('parent-cnic')),
      '0000000000001',
    );
    await tester.ensureVisible(find.text('Request code'));
    await tester.tap(find.text('Request code'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('parent-otp')));
    await tester.enterText(find.byKey(const Key('parent-otp')), '000000');
    await tester.ensureVisible(find.text('Verify and sign in'));
    await tester.tap(find.text('Verify and sign in'));
    await tester.pumpAndSettle();
    expect(
      find.textContaining('The code is invalid, expired or already used.'),
      findsOneWidget,
    );
    expect(auth.status, isNot(AuthStatus.signedIn));
  });
  test('parent remembered token restores through normal auth me', () async {
    final store = MemoryStore();
    final user = {
      ...testUser,
      'roles': [
        {'code': 'parent_guardian', 'name': 'Parent'},
      ],
      'permissions': [],
    };
    final auth = createAuth(
      store,
      handler: (r) async => ok(
        r.url.path.endsWith('verify')
            ? {'token': 'fictional-parent-token', 'user': user}
            : user,
      ),
    );
    await auth.verifyParentCode('fictional', '000000', rememberMe: true);
    expect(store.token, 'fictional-parent-token');
    final restored = createAuth(store, handler: (_) async => ok(user));
    await restored.initialize();
    expect(restored.isParent, true);
    expect(restored.status, AuthStatus.signedIn);
  });
}
