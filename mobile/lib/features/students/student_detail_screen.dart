import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../../shared/forms/profile_photo_picker.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import 'student_form.dart';
import 'guardian_screen.dart';
import '../academics/student_academic_screen.dart';
import '../academics/diary_screen.dart';
import '../files/private_file_screen.dart';
import '../finance/student_fee_screen.dart';

class StudentDetailScreen extends StatefulWidget {
  const StudentDetailScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<StudentDetailScreen> createState() => _StudentDetailScreenState();
}

class _StudentDetailScreenState extends State<StudentDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call('students/${widget.id}');
      if (mounted) {
        setState(() {
          data = value;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> transition() async {
    Map<String, String> options;
    try {
      options = await sectionOptions(widget.auth);
    } catch (_) {
      return;
    }
    if (!mounted) return;
    final s = Strings.of(context);
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'enrollmentAction',
          fields: [
            FormFieldSpec(
              'type',
              'action',
              kind: FieldKind.choice,
              options: {
                for (final key in [
                  'withdrawal',
                  'transfer',
                  'readmission',
                  'promotion',
                  'section_change',
                ])
                  key: s.text(key),
              },
            ),
            FormFieldSpec(
              'effective_on',
              'effectiveDate',
              kind: FieldKind.date,
              initial: DateTime.now().toIso8601String().substring(0, 10),
            ),
            FormFieldSpec(
              'section_id',
              'targetSection',
              kind: FieldKind.choice,
              required: false,
              options: options,
            ),
            const FormFieldSpec(
              'roll_number',
              'rollNumber',
              kind: FieldKind.integer,
              required: false,
            ),
            const FormFieldSpec(
              'reason',
              'reason',
              kind: FieldKind.multiline,
              required: false,
              maxLength: 2000,
            ),
          ],
          onSubmit: (v) async {
            if (v['section_id'] == null) v.remove('section_id');
            if (v['roll_number'] == null) v.remove('roll_number');
            await widget.auth.call(
              'students/${widget.id}/transitions',
              body: v,
            );
          },
        ),
      ),
    );
    if (saved == true) load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.text('studentProfile')),
        actions: [
          if (widget.auth.can('students.manage'))
            IconButton(
              tooltip: s.text('editStudent'),
              onPressed: data == null
                  ? null
                  : () async {
                      if (await openStudentForm(
                            context,
                            widget.auth,
                            existing: data,
                          ) ==
                          true) {
                        load();
                      }
                    },
              icon: const Icon(Icons.edit_outlined),
            ),
        ],
      ),
      body: error != null
          ? DataState(error: error, onRetry: load)
          : data == null
          ? const DataState()
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  SurfaceCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Center(
                          child: AuthorizedPhoto(
                            auth: widget.auth,
                            fileId: data!['photo_file_id'] as String?,
                            name: data!['full_name'] as String,
                            size: 104,
                          ),
                        ),
                        const SizedBox(height: AppSpace.lg),
                        Text(
                          data!['full_name'] as String,
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: AppSpace.sm),
                        Text(data!['registration_number'] as String),
                        const SizedBox(height: AppSpace.lg),
                        AppBadge(
                          label: s.text(data!['status'] as String),
                          status: data!['status'] as String,
                        ),
                        for (final entry in {
                          'date_of_birth': 'dateOfBirth',
                          'admitted_on': 'admissionDate',
                          'father_name': 'fatherName',
                          'emergency_contact': 'emergencyContact',
                          'address': 'address',
                          'previous_school': 'previousSchool',
                        }.entries)
                          if (data![entry.key] != null)
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(s.text(entry.value)),
                              subtitle: Text(data![entry.key].toString()),
                            ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.xl),
                  Text(
                    s.text('enrollmentHistory'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: AppSpace.lg),
                  for (final e in data!['enrollments'] as List)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpace.md),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${e['class_name']} · ${e['section_name']}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(
                              '${e['session_name']} · ${s.text('rollNumber')} ${e['roll_number']}',
                            ),
                            Text(
                              '${e['starts_on']} — ${e['ends_on'] ?? s.text('current')}',
                            ),
                            const SizedBox(height: AppSpace.sm),
                            AppBadge(
                              label: s.text(e['status'] as String),
                              status: e['status'] as String,
                            ),
                          ],
                        ),
                      ),
                    ),

                  Wrap(
                    spacing: AppSpace.sm,
                    runSpacing: AppSpace.sm,
                    children: [
                      if (widget.auth.isParent ||
                          widget.auth.can('finance.read'))
                        OutlinedButton(
                          onPressed: () => Navigator.push(
                            context,
                            MaterialPageRoute<void>(
                              builder: (_) => StudentFeeScreen(
                                auth: widget.auth,
                                studentId: widget.id,
                              ),
                            ),
                          ),
                          child: Text(s.text('finance')),
                        ),
                      if (widget.auth.isParent || widget.auth.canAcademics)
                        for (final kind in [
                          'attendance',
                          'assessments',
                          'behavior',
                        ])
                          OutlinedButton(
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute<void>(
                                builder: (_) => StudentAcademicScreen(
                                  auth: widget.auth,
                                  studentId: widget.id,
                                  kind: kind,
                                ),
                              ),
                            ),
                            child: Text(s.text(kind)),
                          ),
                      if (widget.auth.isParent || widget.auth.canAcademics)
                        OutlinedButton(
                          onPressed: () => Navigator.push(
                            context,
                            MaterialPageRoute<void>(
                              builder: (_) => DiaryScreen(
                                auth: widget.auth,
                                studentId: widget.id,
                              ),
                            ),
                          ),
                          child: Text(s.text('diary')),
                        ),
                      if (data!['photo_file_id'] != null)
                        OutlinedButton.icon(
                          onPressed: () => Navigator.push(
                            context,
                            MaterialPageRoute<void>(
                              builder: (_) => PrivateFileScreen(
                                auth: widget.auth,
                                id: data!['photo_file_id'] as String,
                                mime: 'image/jpeg',
                              ),
                            ),
                          ),
                          icon: const Icon(Icons.photo_outlined),
                          label: Text(s.text('studentPhoto')),
                        ),
                      if (widget.auth.can('students.manage'))
                        OutlinedButton.icon(
                          onPressed: () async {
                            try {
                              await Navigator.push(
                                context,
                                MaterialPageRoute<void>(
                                  builder: (_) => DomainForm(
                                    title: 'profilePhoto',
                                    fields: [
                                      FormFieldSpec(
                                        '_photo',
                                        'profilePhoto',
                                        kind: FieldKind.photo,
                                        required: false,
                                        photoAuth: widget.auth,
                                        initial: data!['photo_file_id'],
                                      ),
                                    ],
                                    onSubmit: (v) => saveProfilePhoto(
                                      widget.auth,
                                      v['_photo'] as ProfilePhotoDraft?,
                                      widget.id,
                                    ),
                                  ),
                                ),
                              );
                              load();
                            } on ApiFailure catch (e) {
                              if (mounted) setState(() => error = e.code);
                            }
                          },
                          icon: const Icon(Icons.add_a_photo_outlined),
                          label: Text(s.text('uploadPhoto')),
                        ),
                    ],
                  ),
                  const SizedBox(height: AppSpace.lg),
                  if (widget.auth.can('guardians.manage'))
                    OutlinedButton.icon(
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => GuardianScreen(
                            auth: widget.auth,
                            studentId: widget.id,
                          ),
                        ),
                      ),
                      icon: const Icon(Icons.family_restroom),
                      label: Text(s.text('guardians')),
                    ),
                  if (widget.auth.can('students.manage'))
                    OutlinedButton.icon(
                      onPressed: transition,
                      icon: const Icon(Icons.move_up),
                      label: Text(s.text('enrollmentAction')),
                    ),
                ],
              ),
            ),
    );
  }
}
