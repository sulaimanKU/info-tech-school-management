import 'package:flutter/material.dart';

import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../auth/auth_controller.dart';
import 'staff_assignment_screen.dart';

class TeachingAssignmentsScreen extends StatelessWidget {
  const TeachingAssignmentsScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> create(BuildContext context) async {
    final teacher = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(builder: (_) => TeacherPickerScreen(auth: auth)),
    );
    if (teacher == null || !context.mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute<bool>(
        builder: (_) => StaffAssignmentScreen(
          auth: auth,
          profileId: teacher['id'] as String,
          coordinator: false,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!(auth.can('school.manage') || auth.can('assignments.manage'))) {
      return const Scaffold(body: DataState(error: 'forbidden'));
    }
    return RecordsScreen(
      auth: auth,
      path: 'teaching-assignments',
      title: 'teachingAssignments',
      recordTitle: (r) => '${r['employee_number']} | ${r['teacher_name']}',
      recordSubtitle: (r) =>
          '${r['session_name']} | ${r['class_name']} ${r['section_name']} | ${r['subject_name'] ?? Strings.of(context).text('classTeacher')}\n${Strings.of(context).text(r['is_active'] == true ? 'active' : 'inactive')}',
      onCreate: create,
      onOpen: (c, r) async {
        await Navigator.push(
          c,
          MaterialPageRoute<bool>(
            builder: (_) => StaffAssignmentScreen(
              auth: auth,
              profileId: r['staff_profile_id'] as String,
              coordinator: false,
              existing: r,
            ),
          ),
        );
      },
    );
  }
}

class TeacherPickerScreen extends StatelessWidget {
  const TeacherPickerScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'teaching-assignments/teachers',
    title: 'chooseTeacher',
    recordTitle: (r) => '${r['employee_number']} | ${r['name']}',
    recordSubtitle: (r) => r['account_code']?.toString() ?? '',
    onOpen: (c, r) async => Navigator.pop(c, r),
  );
}
