import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/polished_components.dart';
import '../academics/academic_settings_screen.dart';
import '../auth/auth_controller.dart';
import '../finance/finance_configuration_screen.dart';
import 'catalog_screen.dart';
import 'school_profile_screen.dart';
import 'teaching_assignments_screen.dart';
import 'staff_screen.dart';
import 'rbac_screen.dart';

class SettingsHubScreen extends StatelessWidget {
  const SettingsHubScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (!auth.can('school.manage')) {
      return const Scaffold(body: DataState(error: 'forbidden'));
    }
    final entries = [
      (
        'schoolProfile',
        Icons.business_outlined,
        SchoolProfileScreen(auth: auth, editable: true),
      ),
      ('schoolSetup', Icons.account_tree_outlined, CatalogScreen(auth: auth)),
      (
        'academicSettings',
        Icons.school_outlined,
        AcademicSettingsScreen(auth: auth),
      ),
      (
        'teachingAssignments',
        Icons.assignment_ind_outlined,
        TeachingAssignmentsScreen(auth: auth),
      ),
      if (auth.can('staff.manage'))
        ('staffManagement', Icons.groups_outlined, StaffScreen(auth: auth)),
      if (auth.can('finance.read'))
        (
          'financeConfiguration',
          Icons.payments_outlined,
          FinanceConfigurationScreen(auth: auth),
        ),
      if (auth.can('rbac.manage'))
        ('accessControl', Icons.security_outlined, RbacScreen(auth: auth)),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('settingsModule'))),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
          for (final e in entries)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.md),
              child: ActionTile(
                icon: e.$2,
                title: s.text(e.$1),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(builder: (_) => e.$3),
                ),
              ),
            ),
          Text(s.text('preferencesLocation')),
        ],
      ),
    );
  }
}
