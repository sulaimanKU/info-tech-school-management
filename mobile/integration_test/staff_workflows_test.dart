import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:image/image.dart' as img;
import 'package:school/app/school_app.dart';
import 'package:school/app/app_preferences.dart';
import 'package:school/core/api_client.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/account/staff_account_screen.dart';
import 'package:school/features/account/staff_profile_editor.dart';
import 'package:school/features/account/change_password_screen.dart';
import 'package:school/shared/forms/profile_photo_picker.dart';
import 'package:school/shared/widgets/authorized_photo.dart';
import 'package:school/shared/widgets/data_state.dart';

import '../test/support/auth_fixtures.dart' show MemoryStore;
import 'final_dashboard_workflows_test.dart'
    show ValidationLocal, fixturePassword;

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets(
    'five own staff accounts edit private photo salary password and RTL workflows',
    (tester) async {
      const local = ValidationLocal();
      final prefs = AppPreferences(local);
      await prefs.save(locale: const Locale('en'), mode: ThemeMode.light);
      Future<void> reveal(Finder finder) async {
        await Scrollable.ensureVisible(tester.element(finder), alignment: 0.5);
        await tester.pumpAndSettle();
      }

      Future<void> loaded() async {
        for (
          var i = 0;
          i < 160 && find.byType(LoadingState).evaluate().isNotEmpty;
          i++
        ) {
          await tester.pump(const Duration(milliseconds: 100));
        }
        expect(find.byType(LoadingState), findsNothing);
        await tester.pumpAndSettle();
      }

      Future<void> shot(String name) async {
        await loaded();
        await binding.takeScreenshot(name);
      }

      Future<void> openProfile() async {
        await tester.tap(find.byKey(const Key('account-menu')));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Profile & Account'));
        await tester.pumpAndSettle();
        await loaded();
        expect(find.byType(StaffAccountScreen), findsOneWidget);
      }

      AuthController? teacher;
      String? otherItem;
      String? otherUser;
      for (final label in [
        'md',
        'administrator',
        'coordinator',
        'accountant',
        'teacher',
      ]) {
        final auth = AuthController(
          ApiClient(),
          MemoryStore(),
          localStore: local,
        );
        await auth.login(
          '$label@fictional.example.test',
          fixturePassword,
          rememberMe: false,
        );
        expect(auth.status, AuthStatus.signedIn);
        await tester.pumpWidget(
          SchoolApp(key: UniqueKey(), auth: auth, preferences: prefs),
        );
        await tester.pumpAndSettle();
        await loaded();
        if (label == 'md') await binding.convertFlutterSurfaceToImage();
        await openProfile();
        final data = await auth.call('account/profile');
        expect(data['account']['id'], auth.user!['id']);
        expect(data['staff_linked'], isTrue);
        expect(find.text(auth.user!['email'] as String), findsOneWidget);
        await shot('staff-$label-profile');
        if (label == 'md') {
          otherUser = auth.user!['id'] as String;
          final slips = await auth.call('my-staff-profile');
          otherItem = (slips['items'] as List).single['id'] as String;
        }
        if (label == 'teacher') {
          teacher = auth;
          break;
        }
        await auth.logout();
        await tester.pumpAndSettle();
        expect(find.byType(StaffAccountScreen), findsNothing);
      }
      final auth = teacher!;
      await expectLater(
        auth.call('account/profile?user_id=$otherUser'),
        throwsA(isA<ApiFailure>().having((e) => e.code, 'code', 'validation')),
      );
      await expectLater(
        auth.download('account/salary-slips/$otherItem/document'),
        throwsA(isA<ApiFailure>().having((e) => e.code, 'code', 'notFound')),
      );
      await reveal(find.byKey(const Key('edit-own-profile')));
      await tester.tap(find.byKey(const Key('edit-own-profile')));
      await tester.pumpAndSettle();
      expect(find.byType(StaffProfileEditor), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('own-name')),
        'Fictional Updated Teacher',
      );
      await tester.enterText(
        find.byKey(const Key('own-mobile')),
        '+923000000009',
      );
      final picker = tester.widget<ProfilePhotoPicker>(
        find.byType(ProfilePhotoPicker),
      );
      final image = img.Image(width: 80, height: 80);
      img.fill(image, color: img.ColorRgb8(40, 100, 140));
      picker.onChanged(ProfilePhotoDraft(bytes: img.encodePng(image)));
      await tester.pumpAndSettle();
      await shot('staff-photo-preview');
      await reveal(find.byKey(const Key('save-own-profile')));
      await tester.tap(find.byKey(const Key('save-own-profile')));
      for (
        var i = 0;
        i < 160 && find.byType(StaffProfileEditor).evaluate().isNotEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 150));
      }
      expect(find.byType(StaffProfileEditor), findsNothing);
      await loaded();
      expect(auth.user!['name'], 'Fictional Updated Teacher');
      final updated = await auth.call('account/profile');
      expect(updated['account']['mobile'], '+923000000009');
      expect(updated['account']['photo_file_id'], isNotNull);
      for (
        var i = 0;
        i < 80 &&
            find
                .descendant(
                  of: find.byType(AuthorizedPhoto),
                  matching: find.byType(Image),
                )
                .evaluate()
                .isEmpty;
        i++
      ) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(
        find.descendant(
          of: find.byType(AuthorizedPhoto),
          matching: find.byType(Image),
        ),
        findsOneWidget,
      );
      await shot('staff-updated-private-photo');
      await reveal(find.byKey(const Key('own-salary-slips')));
      await tester.tap(find.byKey(const Key('own-salary-slips')));
      await tester.pumpAndSettle();
      await loaded();
      expect(find.byType(StaffSalaryScreen), findsOneWidget);
      expect(find.text('2026-09'), findsOneWidget);
      await shot('staff-own-paid-salary');
      final ownSlips = await auth.call('my-staff-profile');
      final ownItem = (ownSlips['items'] as List).single['id'];
      final bytes = await auth.download(
        'account/salary-slips/$ownItem/document',
      );
      expect(String.fromCharCodes(bytes.take(4)), '%PDF');
      Navigator.of(tester.element(find.byType(StaffSalaryScreen))).pop();
      await tester.pumpAndSettle();
      await reveal(find.byKey(const Key('change-own-password')));
      await tester.tap(find.byKey(const Key('change-own-password')));
      await tester.pumpAndSettle();
      await reveal(find.byKey(const Key('save-own-password')));
      await tester.tap(find.byKey(const Key('save-own-password')));
      await tester.pumpAndSettle();
      expect(find.byType(ChangePasswordScreen), findsOneWidget);
      for (final field in [
        ('accountCurrentPassword', fixturePassword),
        ('accountNewPassword', 'Fictional-updated-only-2026'),
        ('accountConfirmPassword', 'Fictional-updated-only-2026'),
      ]) {
        await tester.enterText(find.byKey(Key(field.$1)), field.$2);
      }
      await reveal(find.byKey(const Key('save-own-password')));
      await tester.tap(find.byKey(const Key('save-own-password')));
      for (var i = 0; i < 160 && auth.status != AuthStatus.signedOut; i++) {
        await tester.pump(const Duration(milliseconds: 150));
      }
      expect(auth.status, AuthStatus.signedOut);
      await tester.pumpAndSettle();
      expect(find.byType(StaffAccountScreen), findsNothing);
      await shot('staff-password-changed-sign-in');
      await auth.login(
        'teacher@fictional.example.test',
        'Fictional-updated-only-2026',
        rememberMe: false,
      );
      expect(auth.status, AuthStatus.signedIn);
      await tester.pumpAndSettle();
      await loaded();
      await openProfile();
      await prefs.save(locale: const Locale('ur'), mode: ThemeMode.dark);
      await tester.pumpWidget(
        SchoolApp(key: UniqueKey(), auth: auth, preferences: prefs),
      );
      await tester.pumpAndSettle();
      await loaded();
      Navigator.of(tester.element(find.byType(Scaffold).first)).push(
        MaterialPageRoute<void>(builder: (_) => StaffAccountScreen(auth: auth)),
      );
      await tester.pumpAndSettle();
      await loaded();
      expect(
        Directionality.of(tester.element(find.byType(StaffAccountScreen))),
        TextDirection.rtl,
      );
      await shot('staff-urdu-dark-profile');
      await auth.logout();
      await tester.pumpAndSettle();
      expect(find.byType(StaffAccountScreen), findsNothing);
      await local.remove('preferences');
      await tester.pumpWidget(const SizedBox());
      expect(tester.takeException(), isNull);
    },
  );
}
