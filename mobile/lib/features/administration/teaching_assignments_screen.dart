import 'package:flutter/material.dart';

import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../auth/auth_controller.dart';
import 'staff_assignment_screen.dart';
import 'teacher_assignment_batch_screen.dart';

class TeachingAssignmentsScreen extends StatefulWidget {
  const TeachingAssignmentsScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<TeachingAssignmentsScreen> createState() =>
      _TeachingAssignmentsScreenState();
}

class _TeachingAssignmentsScreenState extends State<TeachingAssignmentsScreen> {
  Map<String, dynamic>? teacherFilter;
  AuthController get auth => widget.auth;

  Future<Map<String, dynamic>?> chooseTeacher(BuildContext context) =>
      Navigator.push<Map<String, dynamic>>(
        context,
        MaterialPageRoute(builder: (_) => TeacherPickerScreen(auth: auth)),
      );

  Future<void> create(BuildContext context) async {
    final teacher = teacherFilter ?? await chooseTeacher(context);
    if (teacher == null || !context.mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute<bool>(
        builder: (_) =>
            TeacherAssignmentBatchScreen(auth: auth, teacher: teacher),
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
      path: Uri(
        path: 'teaching-assignments',
        queryParameters: {
          if (teacherFilter != null)
            'staff_profile_id': teacherFilter!['id'] as String,
        },
      ).toString(),
      title: 'teachingAssignments',
      recordTitle: (r) => '${r['employee_number']} | ${r['teacher_name']}',
      recordSubtitle: (r) =>
          '${r['session_name']} | ${r['class_name']} ${r['section_name']} | ${r['subject_name'] ?? Strings.of(context).text('classTeacher')}\n${Strings.of(context).text(r['is_active'] == true ? 'active' : 'inactive')}',
      onCreate: create,
      summary: (_) => Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          OutlinedButton.icon(
            key: const Key('filter-teacher'),
            onPressed: () async {
              final value = await chooseTeacher(context);
              if (value != null && mounted) {
                setState(() => teacherFilter = value);
              }
            },
            icon: const Icon(Icons.person_search_outlined),
            label: Text(
              teacherFilter?['name'] as String? ??
                  Strings.of(context).text('chooseTeacher'),
            ),
          ),
          if (teacherFilter != null)
            TextButton.icon(
              onPressed: () => setState(() => teacherFilter = null),
              icon: const Icon(Icons.close),
              label: Text(Strings.of(context).text('clearFilter')),
            ),
        ],
      ),
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
