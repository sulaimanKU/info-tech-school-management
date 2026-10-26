import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:school/core/api_client.dart';
import 'package:school/design/app_theme.dart';
import 'package:school/design/semantic_tokens.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/academics/attendance_screen.dart';
import 'package:school/features/academics/academic_hub_screen.dart';
import 'package:school/features/finance/finance_hub_screen.dart';
import 'package:school/features/communication/communication_hub_screen.dart';
import 'package:school/features/reports/report_screen.dart';
import 'package:school/features/parents/child_workspace_screen.dart';
import 'package:school/shared/widgets/conversation_message.dart';
import 'package:school/shared/widgets/data_state.dart';
import 'package:school/shared/widgets/import_review.dart';
import 'package:school/shared/widgets/polished_components.dart';
import 'package:school/shared/widgets/records_screen.dart';

import 'support/auth_fixtures.dart';
import 'mobile_foundation_test.dart' show MemoryLocalStore;

Widget host(
  Widget child, {
  bool urdu = false,
  bool dark = false,
  double scale = 1,
  bool reduced = false,
}) => MaterialApp(
  locale: Locale(urdu ? 'ur' : 'en'),
  supportedLocales: const [Locale('en'), Locale('ur')],
  localizationsDelegates: GlobalMaterialLocalizations.delegates,
  theme: AppTheme.build(dark ? Brightness.dark : Brightness.light),
  builder: (context, child) => MediaQuery(
    data: MediaQuery.of(context).copyWith(
      textScaler: TextScaler.linear(scale),
      disableAnimations: reduced,
    ),
    child: child!,
  ),
  home: Scaffold(body: child),
);
AuthController authFor(Future<http.Response> Function(http.Request) handler) {
  final auth = AuthController(
    ApiClient(baseUrl: 'http://localhost/api/v1', client: MockClient(handler)),
    MemoryStore(),
    localStore: MemoryLocalStore(),
  );
  auth.user = {
    ...testUser,
    'permissions': ['academics.write'],
  };
  auth.status = AuthStatus.signedIn;
  return auth;
}

http.Response ok(Map<String, dynamic> value) => http.Response(
  jsonEncode({'data': value}),
  200,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

void main() {
  for (final dark in [false, true]) {
    testWidgets(
      'semantic status and text contrast in ${dark ? 'dark' : 'light'} theme',
      (tester) async {
        await tester.pumpWidget(
          host(
            Builder(
              builder: (context) {
                final p = AppPalette.of(context);
                double contrast(Color a, Color b) {
                  final x = a.computeLuminance(), y = b.computeLuminance();
                  return ((x > y ? x : y) + .05) / ((x < y ? x : y) + .05);
                }

                expect(contrast(p.text, p.surface), greaterThanOrEqualTo(4.5));
                expect(
                  contrast(p.supporting, p.surface),
                  greaterThanOrEqualTo(4.5),
                );
                for (final tone in StatusTone.values) {
                  expect(
                    contrast(p.tone(tone), p.surface),
                    greaterThanOrEqualTo(4.5),
                  );
                }
                return const SizedBox();
              },
            ),
            dark: dark,
          ),
        );
      },
    );
  }
  testWidgets(
    'internal notes and public sender types are explicit, including Urdu',
    (tester) async {
      await tester.pumpWidget(
        host(
          SingleChildScrollView(
            child: Column(
              children: [
                for (final kind in ['parent', 'staff', 'internal'])
                  ConversationMessage(
                    message: {
                      'sender_name': 'Fictional sender',
                      'sender_kind': kind == 'parent' ? 'parent' : 'staff',
                      'is_internal': kind == 'internal',
                      'body': 'Fictional message',
                      'created_at': '2026-10-22T10:00:00Z',
                    },
                  ),
              ],
            ),
          ),
        ),
      );
      expect(find.text('Parent message'), findsOneWidget);
      expect(find.text('Staff reply'), findsOneWidget);
      expect(
        find.text('Internal note · visible to staff only'),
        findsOneWidget,
      );
      expect(find.byIcon(Icons.lock_outline_rounded), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'import review shows actual counts and issues without inventing row totals',
    (tester) async {
      await tester.pumpWidget(
        host(
          const SingleChildScrollView(
            child: Column(
              children: [
                ImportProgress(status: 'invalid'),
                ImportReview(
                  report: {
                    'valid': false,
                    'row_count': 7,
                    'errors': ['rows.2.device_code', 'rows.5.external_id'],
                    'warnings': ['Review duplicate identifiers'],
                  },
                ),
              ],
            ),
          ),
        ),
      );
      expect(find.text('7'), findsOneWidget);
      expect(find.text('rows.2.device_code'), findsOneWidget);
      expect(find.text('rows.5.external_id'), findsOneWidget);
      expect(find.text('Review duplicate identifiers'), findsOneWidget);
      expect(find.text('3 · Review'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  for (final size in [const Size(320, 640), const Size(844, 390)]) {
    testWidgets(
      'Urdu states, finance and import reflow at $size with large text',
      (tester) async {
        tester.view.physicalSize = size;
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          host(
            const SingleChildScrollView(
              child: Column(
                children: [
                  ValueRow(
                    label: 'بقایاجات کی تفصیل',
                    value: 'PKR 1,234,567.89',
                    emphasis: true,
                  ),
                  ImportProgress(status: 'validated'),
                  ImportReview(report: {'valid': true, 'row_count': 42}),
                  DataState(empty: true),
                  DataState(error: 'network'),
                ],
              ),
            ),
            urdu: true,
            dark: true,
            scale: 2,
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets('loading, empty and error states fit short viewports', (
    tester,
  ) async {
    for (final state in [
      const DataState(),
      const DataState(empty: true),
      DataState(error: 'forbidden', onRetry: () {}),
    ]) {
      await tester.pumpWidget(
        host(SizedBox(height: 100, child: state), scale: 2),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    }
  });
  testWidgets('reduced motion renders finite entrance immediately', (
    tester,
  ) async {
    await tester.pumpWidget(
      host(const Reveal(child: Text('Ready')), reduced: true),
    );
    await tester.pump();
    expect(tester.widget<Opacity>(find.byType(Opacity).first).opacity, 1);
    expect(tester.binding.hasScheduledFrame, false);
  });
  testWidgets(
    'record search is scoped to loaded records and preserves status',
    (tester) async {
      final auth = authFor(
        (r) async => ok({
          'items': [
            {'name': 'Fictional Alpha', 'status': 'paid'},
            {'name': 'Fictional Beta', 'status': 'pending'},
          ],
        }),
      );
      await tester.pumpWidget(
        host(
          RecordsScreen(
            auth: auth,
            path: 'fixture',
            title: 'students',
            recordTitle: (r) => r['name'] as String,
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Beta');
      await tester.pumpAndSettle();
      expect(find.text('Fictional Alpha'), findsNothing);
      expect(find.text('Fictional Beta'), findsOneWidget);
      await tester.tap(find.byTooltip('Clear search'));
      await tester.pumpAndSettle();
      expect(find.text('Fictional Alpha'), findsOneWidget);
    },
  );
  testWidgets('attendance confirmation can cancel without writing', (
    tester,
  ) async {
    var writes = 0;
    final auth = authFor((r) async {
      if (r.method != 'GET') {
        writes++;
        return ok({'version': 1});
      }
      if (r.url.path.endsWith('sections')) {
        return ok({
          'items': [
            {
              'id': 's',
              'class_id': 'c',
              'academic_session_id': 'a',
              'name': 'A',
            },
          ],
        });
      }
      if (r.url.path.endsWith('classes')) {
        return ok({
          'items': [
            {'id': 'c', 'name': 'Grade 1'},
          ],
        });
      }
      if (r.url.path.endsWith('sessions')) {
        return ok({
          'items': [
            {'id': 'a', 'name': '2026'},
          ],
        });
      }
      return ok({
        'students': [
          {
            'id': 'one',
            'roll_number': 1,
            'full_name': 'Fictional child',
            'registration_number': 'TEST-1',
          },
        ],
        'records': [],
      });
    });
    await tester.pumpWidget(host(AttendanceScreen(auth: auth)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mark all present'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Submit'));
    await tester.tap(find.text('Submit'));
    await tester.pumpAndSettle();
    expect(find.text('Submit attendance?'), findsOneWidget);
    expect(writes, 0);
    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(writes, 0);
    expect(
      find.text('Fictional child'),
      findsNothing,
    ); // roster includes roll-number prefix
    expect(find.textContaining('Fictional child'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    auth.dispose();
  });
  testWidgets('child switch updates identity and module destinations', (
    tester,
  ) async {
    final auth = authFor((r) async => ok({}));
    final children = [
      for (final id in ['a', 'b'])
        {
          'id': id,
          'full_name': 'Fictional child $id',
          'registration_number': 'REG-$id',
        },
    ];
    await tester.pumpWidget(
      host(
        ChildWorkspaceScreen(auth: auth, children: children, initialId: 'a'),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fictional child b').last);
    await tester.pumpAndSettle();
    expect(find.text('REG-b'), findsOneWidget);
    expect(find.text('REG-a'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  for (final dark in [false, true]) {
    testWidgets(
      'role hubs reflow in Urdu with large text in ${dark ? 'dark' : 'light'}',
      (tester) async {
        tester.view.physicalSize = const Size(320, 640);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final auth = authFor((r) async => ok({'items': []}));
        for (final screen in [
          AcademicHubScreen(auth: auth),
          FinanceHubScreen(auth: auth),
          CommunicationHubScreen(auth: auth),
          ReportScreen(auth: auth),
        ]) {
          await tester.pumpWidget(
            host(screen, urdu: true, dark: dark, scale: 1.8),
          );
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          await tester.pumpWidget(const SizedBox());
        }
      },
    );
  }
}
