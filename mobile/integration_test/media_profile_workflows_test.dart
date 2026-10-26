import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image/image.dart' as img;
import 'package:integration_test/integration_test.dart';
import 'package:school/app/school_app.dart';
import 'package:school/app/app_preferences.dart';
import 'package:school/core/api_client.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/students/student_detail_screen.dart';
import 'package:school/features/students/student_form.dart';
import 'package:school/features/students/guardian_directory_screen.dart';
import 'package:school/features/home/widgets/module_card.dart';
import 'package:school/shared/forms/domain_form.dart';
import 'package:school/shared/forms/profile_photo_picker.dart';
import 'package:school/shared/widgets/authorized_photo.dart';

import 'final_dashboard_workflows_test.dart'
    show ValidationSession, ValidationLocal;
import 'final_ui_workflows_test.dart' show fixturePassword;

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'private student guardian photos and local parent OTP on Android',
    (tester) async {
      const store = ValidationSession();
      await store.clear();
      const local = ValidationLocal();
      final prefs = AppPreferences(local);
      await prefs.save(locale: const Locale('en'), mode: ThemeMode.light);
      final auth = AuthController(ApiClient(), store, localStore: local);
      await auth.login(
        'md@fictional.example.test',
        fixturePassword,
        rememberMe: false,
      );
      expect(auth.status, AuthStatus.signedIn);
      Future<void> home() async {
        await tester.pumpWidget(
          SchoolApp(key: UniqueKey(), auth: auth, preferences: prefs),
        );
        await tester.pumpAndSettle();
      }

      Future<void> shot(String name) async {
        await tester.pumpAndSettle();
        await binding.takeScreenshot(name);
      }

      Future<void> waitForPhoto() async {
        final image = find.descendant(
          of: find.byType(AuthorizedPhoto),
          matching: find.byType(Image),
        );
        for (var i = 0; i < 80 && image.evaluate().isEmpty; i++) {
          await tester.pump(const Duration(milliseconds: 100));
        }
        expect(image, findsOneWidget);
      }

      Future<void> open(Widget screen) async {
        Navigator.of(tester.element(find.byType(ModuleCard).first))
            .push(MaterialPageRoute<void>(builder: (_) => screen));
        await tester.pumpAndSettle();
      }

      await home();
      await binding.convertFlutterSurfaceToImage();
      await shot('super-admin-color-dashboard');
      final initial = await auth.call('students');
      final existing = (initial['data'] as List).first;
      final existingDetail = await auth.call('students/${existing['id']}');
      final section =
          (existingDetail['enrollments'] as List).first['section_id'];
      final now = DateTime.now().toIso8601String().substring(0, 10);
      final input = {
        'full_name': 'Fictional Media Student',
        'gender': 'female',
        'date_of_birth': '2020-01-01',
        'admitted_on': now,
        'section_id': section,
        'roll_number': 98,
      };
      // Invoke the actual create-form save callback, including the optional-photo draft.
      unawaited(
        openStudentForm(tester.element(find.byType(ModuleCard).first), auth),
      );
      await tester.pumpAndSettle();
      for (
        var i = 0;
        i < 30 && find.byType(DomainForm).evaluate().isEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      final form = tester.widget<DomainForm>(find.byType(DomainForm));
      await shot('student-create-optional-photo');
      final previous = await auth.call(
        'students?search=Fictional%20Media%20Student',
      );
      if ((previous['data'] as List).isEmpty) {
        await form.onSubmit({...input, '_photo': ProfilePhotoDraft()});
      } else {
        await auth.call(
          'students/${(previous['data'] as List).single['id']}/photo',
          method: 'DELETE',
        );
      }
      Navigator.of(tester.element(find.byType(DomainForm))).pop(true);
      await tester.pumpAndSettle();
      final created = await auth.call(
        'students?search=Fictional%20Media%20Student',
      );
      final student = (created['data'] as List).single['id'] as String;
      final without = await auth.call('students/$student');
      expect(without['photo_file_id'], isNull);
      final image = img.Image(width: 960, height: 960);
      img.fill(image, color: img.ColorRgb8(36, 77, 177));
      img.fillRect(
        image,
        x1: 220,
        y1: 180,
        x2: 740,
        y2: 780,
        color: img.ColorRgb8(32, 157, 145),
      );
      final bytes = Uint8List.fromList(img.encodeJpg(image, quality: 85));
      await saveProfilePhoto(auth, ProfilePhotoDraft(bytes: bytes), student);
      var detail = await auth.call('students/$student');
      final first = detail['photo_file_id'] as String;
      expect(img.decodeImage(await auth.download('files/$first')), isNotNull);
      await open(StudentDetailScreen(auth: auth, id: student));
      await waitForPhoto();
      await shot('student-private-photo');
      expect(find.byType(AuthorizedPhoto), findsOneWidget);
      expect(
        find.descendant(
          of: find.byType(AuthorizedPhoto),
          matching: find.byType(Image),
        ),
        findsOneWidget,
      );
      await home();
      await saveProfilePhoto(auth, ProfilePhotoDraft(bytes: bytes), student);
      detail = await auth.call('students/$student');
      expect(detail['photo_file_id'], isNot(first));
      try {
        await auth.download('files/$first');
        fail('Superseded image should be inaccessible.');
      } on ApiFailure catch (e) {
        expect(e.code, 'notFound');
      }
      await saveProfilePhoto(
        auth,
        ProfilePhotoDraft(
          existingId: detail['photo_file_id'] as String,
          removed: true,
        ),
        student,
      );
      expect((await auth.call('students/$student'))['photo_file_id'], isNull);
      await saveProfilePhoto(auth, ProfilePhotoDraft(bytes: bytes), student);
      final guardian = await auth.call(
        'guardians',
        body: {
          'full_name': 'Fictional Validation Guardian',
          'cnic': '0000000000042',
          'mobile': '+923000000042',
          'mobile_verified': true,
        },
      );
      final gid = guardian['id'] as String;
      expect((await auth.call('guardians/$gid'))['photo_file_id'], isNull);
      await saveProfilePhoto(
        auth,
        ProfilePhotoDraft(bytes: bytes),
        gid,
        guardian: true,
      );
      for (final id in [student, existing['id']]) {
        await auth.call(
          'guardians/$gid/links',
          method: 'PUT',
          body: {
            'student_id': id,
            'relationship': 'guardian',
            'is_primary': false,
          },
        );
      }
      await open(GuardianDetailScreen(auth: auth, id: gid));
      await waitForPhoto();
      await shot('guardian-private-photo');
      expect(
        find.descendant(
          of: find.byType(AuthorizedPhoto),
          matching: find.byType(Image),
        ),
        findsOneWidget,
      );
      await home();
      await auth.logout();
      await home();
      await shot('staff-color-login');
      await tester.ensureVisible(find.text('Parent login'));
      await tester.tap(find.text('Parent login'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('parent-cnic')));
      await tester.enterText(
        find.byKey(const Key('parent-cnic')),
        '0000000000042',
      );
      await shot('parent-color-login');
      await tester.ensureVisible(find.text('Request code'));
      await tester.tap(find.text('Request code'));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('parent-otp')), findsOneWidget);
      await tester.ensureVisible(find.byKey(const Key('parent-otp')));
      await tester.tap(find.byKey(const Key('parent-otp')));
      await shot('parent-otp-step');
      for (var i = 0; i < 100; i++) {
        final field = tester.widget<TextFormField>(
          find.byKey(const Key('parent-otp')),
        );
        if (field.controller!.text.length == 6) break;
        await tester.pump(const Duration(milliseconds: 500));
      }
      expect(
        tester
            .widget<TextFormField>(find.byKey(const Key('parent-otp')))
            .controller!
            .text
            .length,
        6,
      );
      await tester.ensureVisible(find.byType(CheckboxListTile));
      await tester.tap(find.byType(CheckboxListTile));
      await tester.ensureVisible(find.text('Verify and sign in'));
      await tester.tap(find.text('Verify and sign in'));
      await tester.pumpAndSettle();
      expect(auth.isParent, true);
      expect(auth.status, AuthStatus.signedIn);
      final parentHome = await auth.call('parent/home');
      expect(parentHome['children'].length, 2);
      expect(parentHome['guardian']['photo_file_id'], isNotNull);
      await shot('parent-private-photo-home');
      final restored = AuthController(ApiClient(), store, localStore: local);
      await restored.initialize();
      expect(restored.isParent, true);
      expect(restored.status, AuthStatus.signedIn);
      await restored.logout();
      expect(await store.read(), isNull);
      await prefs.save(locale: const Locale('ur'), mode: ThemeMode.dark);
      await auth.logout();
      await home();
      await shot('urdu-dark-auth');
      expect(
        Directionality.of(
          tester.element(find.byKey(const Key('language-toggle'))),
        ),
        TextDirection.rtl,
      );
      expect(tester.takeException(), isNull);
      await local.remove('preferences');
      await tester.pumpWidget(const SizedBox());
    },
  );
}
