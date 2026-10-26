import 'package:flutter/material.dart';

import '../../../design/app_theme.dart';
import '../../../design/semantic_tokens.dart';
import '../../students/guardian_directory_screen.dart';
import '../../../l10n/strings.dart';
import '../../../core/api_client.dart';
import '../../auth/auth_controller.dart';
import '../../students/student_form.dart';
import '../../administration/staff_screen.dart';
import '../../academics/attendance_screen.dart';
import '../../academics/diary_editor.dart';
import '../../finance/finance_hub_screen.dart';
import '../../communication/announcement_screen.dart';
import '../../communication/ticket_screen.dart';
import '../../reports/report_screen.dart';

class QuickActions extends StatelessWidget {
  const QuickActions({super.key, required this.auth, required this.onReturn});
  final AuthController auth;
  final VoidCallback onReturn;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    Future<void> open(Widget screen) async {
      await Navigator.push(
        context,
        MaterialPageRoute<void>(builder: (_) => screen),
      );
      onReturn();
    }

    final actions =
        <({String key, IconData icon, Future<void> Function() run})>[
          if (auth.can('students.manage'))
            (
              key: 'addStudent',
              icon: Icons.person_add_alt_1_outlined,
              run: () async {
                await openStudentForm(context, auth);
                onReturn();
              },
            ),
          if (auth.can('guardians.manage'))
            (
              key: 'addGuardian',
              icon: Icons.family_restroom_outlined,
              run: () async {
                await GuardianDirectoryScreen(auth: auth).create(context);
                onReturn();
              },
            ),
          if (auth.can('staff.manage'))
            (
              key: 'staffManagement',
              icon: Icons.manage_accounts_outlined,
              run: () => open(StaffScreen(auth: auth)),
            ),
          if (auth.canAcademics)
            (
              key: 'attendance',
              icon: Icons.fact_check_outlined,
              run: () => open(AttendanceScreen(auth: auth)),
            ),
          if (auth.can('finance.read'))
            (
              key: 'finance',
              icon: Icons.account_balance_wallet_outlined,
              run: () => open(FinanceHubScreen(auth: auth)),
            ),
          if (auth.can('announcements.manage'))
            (
              key: 'announcement',
              icon: Icons.campaign_outlined,
              run: () async {
                await announcementForm(context, auth);
                onReturn();
              },
            ),
          if (!auth.can('staff.manage') && auth.can('academics.write'))
            (
              key: 'teacherDiary',
              icon: Icons.edit_note,
              run: () => open(DiaryEditor(auth: auth)),
            ),
          if (!auth.can('staff.manage') && auth.can('communication.manage'))
            (
              key: 'contactCoordinator',
              icon: Icons.support_agent_outlined,
              run: () => open(TicketScreen(auth: auth)),
            ),
          if (auth.can('reports.export') || auth.canAcademics)
            (
              key: 'reports',
              icon: Icons.bar_chart_rounded,
              run: () => open(ReportScreen(auth: auth)),
            ),
        ];
    return Wrap(
      spacing: AppSpace.sm,
      runSpacing: AppSpace.sm,
      children: [
        for (final action in actions)
          OutlinedButton.icon(
            key: ValueKey('quick-${action.key}'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppPalette.of(context).module(action.key),
              backgroundColor: AppPalette.of(context)
                  .module(action.key)
                  .withValues(alpha: .07),
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpace.lg,
                vertical: AppSpace.lg,
              ),
              side: BorderSide(
                color: AppPalette.of(context)
                    .module(action.key)
                    .withValues(alpha: .25),
              ),
            ),
            onPressed: () async {
              try {
                await action.run();
              } on ApiFailure catch (e) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context)
                      .showSnackBar(SnackBar(content: Text(s.text(e.code))));
                }
              }
            },
            icon: Icon(action.icon, size: 20),
            label: Text(s.text(action.key)),
          ),
      ],
    );
  }
}
