import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:school/app/app_preferences.dart';
import 'package:school/app/school_app.dart';
import 'package:school/core/local_store.dart';
import 'package:school/design/semantic_tokens.dart';
import 'package:school/features/auth/login_screen.dart';
import 'package:school/features/home/home_screen.dart';
import 'package:school/shared/widgets/branded_startup.dart';
import 'package:school/shared/widgets/data_state.dart';
import 'package:school/shared/widgets/summary_card.dart';
import 'package:school/shared/widgets/surface_card.dart';

import 'final_ui_polish_test.dart' show host;
import 'support/auth_fixtures.dart';

class DelayedSessionStore extends MemoryStore {
  final ready = Completer<String?>();
  @override
  Future<String?> read() => ready.future;
}

class DelayedPreferences implements LocalStore {
  final ready = Completer<String?>();
  @override
  Future<String?> read(String key) => ready.future;
  @override
  Future<void> write(String key, String value) async {}
  @override
  Future<void> remove(String key) async {}
}

void main() {
  testWidgets(
    'unauthenticated startup exits as soon as secure storage resolves',
    (tester) async {
      final store = DelayedSessionStore();
      final auth = createAuth(store);
      await tester.pumpWidget(SchoolApp(auth: auth, initialize: true));
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.byType(BrandedStartup), findsOneWidget);
      expect(find.byType(LoginScreen), findsNothing);
      store.ready.complete(null);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.byType(BrandedStartup), findsNothing);
      expect(find.byType(LoginScreen), findsOneWidget);
      expect(tester.takeException(), isNull);
      auth.dispose();
    },
  );

  testWidgets(
    'authenticated startup never flashes login while preferences resolve',
    (tester) async {
      final store = DelayedSessionStore();
      final preferences = DelayedPreferences();
      final auth = createAuth(
        store,
        handler: (r) async => http.Response(
          jsonEncode({
            'data': r.url.path.endsWith('/auth/me')
                ? testUser
                : {'as_of': '2026-10-30', 'unread_notifications': 0},
          }),
          200,
        ),
      );
      await tester.pumpWidget(
        SchoolApp(
          auth: auth,
          preferences: AppPreferences(preferences),
          initialize: true,
        ),
      );
      store.ready.complete('test-token');
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.byType(BrandedStartup), findsOneWidget);
      expect(find.byType(LoginScreen), findsNothing);
      preferences.ready.complete('{"locale":"ur","theme":"dark"}');
      await tester.pumpAndSettle();
      expect(find.byType(HomeScreen), findsOneWidget);
      expect(find.byType(LoginScreen), findsNothing);
      expect(find.byType(BrandedStartup), findsNothing);
      expect(
        Theme.of(tester.element(find.byType(HomeScreen))).brightness,
        Brightness.dark,
      );
      // Normal authenticated API refreshes do not replay the startup screen.
      await auth.refreshProfile();
      await tester.pump();
      expect(find.byType(BrandedStartup), findsNothing);
      expect(tester.takeException(), isNull);
      auth.dispose();
    },
  );

  for (final dark in [false, true]) {
    for (final size in [const Size(320, 640), const Size(640, 320)]) {
      testWidgets(
        'startup is responsive at $size with dark=$dark and enlarged text',
        (tester) async {
          tester.view.physicalSize = size;
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          await tester.pumpWidget(
            host(const BrandedStartup(), dark: dark, scale: 1.4, reduced: true),
          );
          await tester.pump();
          expect(find.text('TreadExellence'), findsOneWidget);
          expect(
            find.text('INFO-TECH High School & Educational Campus'),
            findsOneWidget,
          );
          expect(
            tester
                .widget<LinearProgressIndicator>(
                  find.byType(LinearProgressIndicator),
                )
                .value,
            .5,
          );
          expect(tester.takeException(), isNull);
        },
      );
    }

    testWidgets(
      'semantic colors and readable status cues work with dark=$dark',
      (tester) async {
        late AppPalette palette;
        await tester.pumpWidget(
          host(
            Builder(
              builder: (context) {
                palette = AppPalette.of(context);
                return SingleChildScrollView(
                  child: Column(
                    children: [
                      const SummaryCard(
                        label: 'Outstanding',
                        value: 'PKR 250.00',
                        icon: Icons.payments_outlined,
                        tone: StatusTone.warning,
                      ),
                      for (final status in [
                        'active',
                        'inactive',
                        'present',
                        'absent',
                        'excused',
                        'late',
                        'paid',
                        'partial',
                        'unpaid',
                        'published',
                        'draft',
                      ])
                        AppBadge(label: status, status: status),
                    ],
                  ),
                );
              },
            ),
            dark: dark,
          ),
        );
        await tester.pumpAndSettle();
        expect(palette.module('finance'), isNot(palette.module('students')));
        expect(palette.module('reports'), isNot(palette.module('staffModule')));
        expect(AppStatus.tone('unpaid'), StatusTone.error);
        expect(AppStatus.tone('partial'), StatusTone.warning);
        expect(AppStatus.tone('excused'), StatusTone.info);
        for (final status in [
          'active',
          'inactive',
          'present',
          'absent',
          'excused',
          'late',
          'paid',
          'partial',
          'unpaid',
          'published',
          'draft',
        ]) {
          final color = palette.tone(AppStatus.tone(status));
          final background = Color.alphaBlend(
            color.withValues(alpha: .1),
            palette.surface,
          );
          final light = color.computeLuminance();
          final surface = background.computeLuminance();
          final contrast =
              (light > surface ? light + .05 : surface + .05) /
              (light > surface ? surface + .05 : light + .05);
          expect(contrast, greaterThanOrEqualTo(4.5), reason: status);
          expect(find.text(status), findsOneWidget);
        }
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('safe error and contextual empty state keep useful retry', (
    tester,
  ) async {
    var retried = false;
    await tester.pumpWidget(
      host(
        SingleChildScrollView(
          child: Column(
            children: [
              const DataState(
                empty: true,
                emptyMessage: 'noTeachingAssignments',
                emptyIcon: Icons.class_outlined,
              ),
              DataState(
                error: 'Exception: private internal details',
                onRetry: () => retried = true,
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.textContaining('No active teaching assignments'),
      findsOneWidget,
    );
    expect(find.textContaining('private internal details'), findsNothing);
    final retry = find.widgetWithIcon(FilledButton, Icons.refresh_rounded);
    await tester.ensureVisible(retry);
    await tester.tap(retry);
    expect(retried, isTrue);
    expect(find.byType(BrandedStartup), findsNothing);
  });
}
