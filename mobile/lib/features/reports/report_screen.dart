import 'package:flutter/material.dart';

import '../../core/operation_id.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../../shared/widgets/academic_filters.dart';
import '../auth/auth_controller.dart';
import '../files/private_file_screen.dart';
import '../imports/import_screen.dart';
import '../imports/biometric_screen.dart';
import '../students/student_list_screen.dart';
import '../academics/exam_schedule_screen.dart';

Future<void> openSchoolReport(
  BuildContext context,
  AuthController auth,
  String type,
  Map<String, String> query,
) async {
  final uri = Uri(
    path: 'reports/$type',
    queryParameters: {...query, 'format': 'pdf'},
  );
  await Navigator.push(
    context,
    MaterialPageRoute<void>(
      builder: (_) => PrivateFileScreen(
        auth: auth,
        id: operationId(),
        mime: 'application/pdf',
        path: uri.toString(),
      ),
    ),
  );
}

class ReportScreen extends StatelessWidget {
  const ReportScreen({super.key, required this.auth, this.studentId});
  final AuthController auth;
  final String? studentId;
  Future<void> studentReport(BuildContext context, String type) async {
    String? id = studentId;
    if (id == null) {
      final selected = await Navigator.push<Map<String, dynamic>>(
        context,
        MaterialPageRoute(
          builder: (_) => StudentListScreen(auth: auth, selection: true),
        ),
      );
      id = selected?['id'] as String?;
    }
    if (id == null || !context.mounted) return;
    final query = {'student_id': id};
    if (type == 'attendance' || type == 'annual_fee') {
      final now = DateTime.now();
      await Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (_) => DomainForm(
            title: 'report_$type',
            fields: [
              FormFieldSpec(
                type == 'attendance' ? 'month' : 'year',
                type == 'attendance' ? 'month' : 'year',
                initial: type == 'attendance'
                    ? '${now.year}-${now.month.toString().padLeft(2, '0')}'
                    : now.year.toString(),
              ),
            ],
            onSubmit: (v) async {
              await openSchoolReport(context, auth, type, {
                ...query,
                for (final e in v.entries) e.key: e.value.toString(),
              });
            },
          ),
        ),
      );
    } else {
      await openSchoolReport(context, auth, type, query);
    }
  }

  Future<void> filteredReport(BuildContext context, String type) async {
    final spec = type == 'registration'
        ? const FilterSpec(
            academic: true,
            dates: false,
            statuses: ['active', 'withdrawn', 'transferred'],
          )
        : type == 'communication'
        ? FilterSpec(
            assignees: !auth.isParent,
            academic: !auth.isParent,
            statuses: const ['submitted', 'in_progress', 'resolved', 'closed'],
          )
        : const FilterSpec();
    final filters = await showFilterSheet(context, auth, spec, {});
    if (filters != null && context.mounted) {
      await openSchoolReport(context, auth, type, {
        ...filters,
        'student_id': ?studentId,
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('reports'))),
      body: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        children: [
          SectionHeader(
            title: s.text('reports'),
            description: s.text('reportIntro'),
          ),
          for (final type in [
            'student_id',
            if (auth.isParent || auth.can('students.manage')) 'admission',
            if (auth.isParent || auth.canAcademics) 'attendance',
            if (auth.isParent || auth.canAcademics) 'academic',
            if (auth.isParent || auth.can('finance.read')) 'annual_fee',
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpace.md),
              child: SurfaceCard(
                padding: EdgeInsets.zero,
                child: ListTile(
                  leading: Icon(switch (type) {
                    'student_id' => Icons.badge_outlined,
                    'admission' => Icons.assignment_ind_outlined,
                    'attendance' => Icons.fact_check_outlined,
                    'academic' => Icons.school_outlined,
                    _ => Icons.receipt_long_outlined,
                  }),
                  title: Text(s.text('report_$type')),
                  subtitle: Text(s.text('report_${type}Description')),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => studentReport(context, type),
                ),
              ),
            ),
          if (auth.isParent || auth.canAcademics)
            ListTile(
              leading: const Icon(Icons.event_note),
              title: Text(s.text('examSchedules')),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) =>
                      ExamScheduleScreen(auth: auth, studentId: studentId),
                ),
              ),
            ),
          if (auth.can('reports.export'))
            ListTile(
              leading: const Icon(Icons.list_alt),
              title: Text(s.text('report_registration')),
              onTap: () => filteredReport(context, 'registration'),
            ),
          if (auth.can('reports.export') &&
              auth.can('finance.read') &&
              auth.can('students.read.all'))
            ListTile(
              leading: const Icon(Icons.account_balance),
              title: Text(s.text('report_financial')),
              onTap: () => filteredReport(context, 'financial'),
            ),
          if (auth.isParent || auth.can('communication.manage'))
            ListTile(
              leading: const Icon(Icons.forum_outlined),
              title: Text(s.text('report_communication')),
              onTap: () => filteredReport(context, 'communication'),
            ),
          if (auth.can('imports.manage'))
            ListTile(
              leading: const Icon(Icons.fingerprint),
              title: Text(s.text('biometricMapping')),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => BiometricScreen(auth: auth),
                ),
              ),
            ),
          if (auth.can('imports.manage'))
            ListTile(
              leading: const Icon(Icons.upload_file),
              title: Text(s.text('importData')),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => ImportScreen(auth: auth),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
