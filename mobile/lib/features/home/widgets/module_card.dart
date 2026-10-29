import 'package:flutter/material.dart';

import '../../../design/app_theme.dart';
import '../../../design/semantic_tokens.dart';
import '../../../shared/widgets/polished_components.dart';
import '../../reports/report_screen.dart';
import '../../auth/auth_controller.dart';
import '../../students/student_list_screen.dart';
import '../../administration/settings_hub_screen.dart';
import '../../academics/teacher_workspace_screen.dart';
import '../../academics/attendance_screen.dart';
import '../../academics/academic_hub_screen.dart';
import '../../finance/finance_hub_screen.dart';
import '../../administration/staff_screen.dart';
import '../../administration/rbac_screen.dart';
import '../../../app/module_access.dart';
import '../../communication/communication_hub_screen.dart';
import '../../../l10n/strings.dart';
import '../../../shared/widgets/surface_card.dart';

class ModuleDefinition {
  const ModuleDefinition(this.key, this.icon);
  final String key;
  final IconData icon;
  static const futureModules = [
    ModuleDefinition('myClasses', Icons.class_outlined),
    ModuleDefinition('students', Icons.school_outlined),
    ModuleDefinition('attendance', Icons.fact_check_outlined),
    ModuleDefinition('academics', Icons.auto_stories_outlined),
    ModuleDefinition('finance', Icons.account_balance_wallet_outlined),
    ModuleDefinition('staffModule', Icons.groups_outlined),
    ModuleDefinition('reports', Icons.bar_chart_rounded),
    ModuleDefinition('messages', Icons.forum_outlined),
    ModuleDefinition('settingsModule', Icons.tune_rounded),
    ModuleDefinition('accessControl', Icons.admin_panel_settings_outlined),
  ];
}

class ModuleCard extends StatelessWidget {
  const ModuleCard({super.key, required this.module, this.onTap});
  final ModuleDefinition module;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final theme = Theme.of(context);
    return Semantics(
      container: true,
      label:
          '${s.text(module.key)}. ${s.text(onTap == null ? 'comingLater' : 'openModule')}',
      button: onTap != null,
      child: ExcludeSemantics(
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: SurfaceCard(
            accent: AppPalette.of(context).module(module.key),
            padding: const EdgeInsets.all(AppSpace.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(AppSpace.md),
                  decoration: BoxDecoration(
                    color: AppPalette.of(context)
                        .module(module.key)
                        .withValues(alpha: .08),
                    borderRadius: BorderRadius.circular(AppRadius.input),
                  ),
                  child: Icon(
                    module.icon,
                    color: AppPalette.of(context).module(module.key),
                    size: 26,
                  ),
                ),
                const SizedBox(height: AppSpace.lg),
                Text(s.text(module.key), style: theme.textTheme.titleMedium),
                const SizedBox(height: AppSpace.sm),
                Text(
                  s.text('${module.key}Description'),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: AppSpace.lg),
                AppBadge(
                  label: s.text(onTap == null ? 'comingLater' : 'openModule'),
                  icon: onTap == null
                      ? Icons.schedule_rounded
                      : Icons.arrow_forward,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class FutureModuleGrid extends StatelessWidget {
  const FutureModuleGrid({super.key, this.auth});
  final AuthController? auth;

  VoidCallback? _action(BuildContext context, ModuleDefinition m) {
    if (auth == null || !ModuleAccess.allowed(auth!, m.key)) return null;
    Widget? target;
    if (m.key == 'myClasses') target = TeacherWorkspaceScreen(auth: auth!);
    if (m.key == 'students') target = StudentListScreen(auth: auth!);
    if (m.key == 'attendance' && auth!.canAcademics) {
      target = AttendanceScreen(auth: auth!);
    }
    if (m.key == 'academics' && auth!.canAcademics) {
      target = AcademicHubScreen(auth: auth!);
    }
    if (m.key == 'finance' &&
        (auth!.can('finance.read') || auth!.can('payroll.read'))) {
      target = FinanceHubScreen(auth: auth!);
    }
    if (m.key == 'staffModule' && auth!.can('staff.manage')) {
      target = StaffScreen(auth: auth!);
    }
    if (m.key == 'messages') target = CommunicationHubScreen(auth: auth!);
    if (m.key == 'reports' &&
        (auth!.can('reports.export') || auth!.canAcademics)) {
      target = ReportScreen(auth: auth!);
    }
    if (m.key == 'settingsModule' && auth!.can('school.manage')) {
      target = SettingsHubScreen(auth: auth!);
    }
    if (m.key == 'accessControl') target = RbacScreen(auth: auth!);
    return target == null
        ? null
        : () => Navigator.push(
            context,
            MaterialPageRoute<void>(builder: (_) => target!),
          );
  }

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final effectiveWidth =
          constraints.maxWidth /
          MediaQuery.textScalerOf(context).scale(1).clamp(1, 2);
      final columns = effectiveWidth >= 1080
          ? 4
          : effectiveWidth >= 720
          ? 3
          : effectiveWidth >= 340
          ? 2
          : 1;
      const gap = AppSpace.lg;
      final width = (constraints.maxWidth - gap * (columns - 1)) / columns;
      return Wrap(
        spacing: gap,
        runSpacing: gap,
        children: ModuleDefinition.futureModules
            .where((m) => auth == null || _action(context, m) != null)
            .map(
              (m) => SizedBox(
                width: width,
                child: Reveal(
                  child: ModuleCard(module: m, onTap: _action(context, m)),
                ),
              ),
            )
            .toList(),
      );
    },
  );
}
