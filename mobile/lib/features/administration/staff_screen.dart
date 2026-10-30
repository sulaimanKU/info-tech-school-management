import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../auth/auth_controller.dart';
import 'staff_assignment_screen.dart';
import 'teacher_assignment_batch_screen.dart';

Map<String, String> staffRoles(BuildContext context, AuthController auth) => {
  for (final role in [
    if (auth.isSuperAdmin) 'super_admin',
    if (auth.isSuperAdmin) 'school_administrator',
    'coordinator',
    'accountant_clerk',
    'teacher',
  ])
    role: Strings.of(context).text(role),
};

class StaffScreen extends StatelessWidget {
  const StaffScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> create(BuildContext context) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'staffManagement',
          fields: [
            const FormFieldSpec('name', 'fullName'),
            const FormFieldSpec('email', 'email'),
            FormFieldSpec(
              'role',
              'role',
              kind: FieldKind.choice,
              options: staffRoles(context, auth),
            ),
            const FormFieldSpec(
              'password',
              'temporaryPassword',
              kind: FieldKind.password,
              maxLength: 1024,
            ),
            const FormFieldSpec(
              'password_confirmation',
              'confirmPassword',
              kind: FieldKind.password,
              maxLength: 1024,
            ),
            const FormFieldSpec(
              'is_active',
              'active',
              kind: FieldKind.boolean,
              initial: true,
            ),
          ],
          onSubmit: (v) => auth.call('staff-accounts', body: v),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!auth.can('staff.manage')) {
      return const Scaffold(body: DataState(error: 'forbidden'));
    }
    final s = Strings.of(context);
    return RecordsScreen(
      auth: auth,
      path: 'staff-accounts',
      title: 'staffManagement',
      recordTitle: (v) => '${v['account_code'] ?? ''} | ${v['name']}',
      recordSubtitle: (v) =>
          '${v['email']} · ${(v['roles'] as List).map((r) => s.text(r['code'] as String)).join(', ')} · ${s.text(v['is_active'] == true ? 'active' : 'inactive')}\n${(v['assignments'] as List? ?? []).where((a) => a['is_active'] == true).map((a) => '${a['class_name']} · ${a['section_name']} · ${a['subject_name'] ?? ''}').join('\n')}',
      onCreate: create,
      onOpen: (c, v) => Navigator.push(
        c,
        MaterialPageRoute<void>(
          builder: (_) => StaffDetailScreen(auth: auth, id: v['id'] as String),
        ),
      ),
    );
  }
}

class StaffDetailScreen extends StatefulWidget {
  const StaffDetailScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<StaffDetailScreen> createState() => _StaffDetailScreenState();
}

class _StaffDetailScreenState extends State<StaffDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final d = await widget.auth.call('staff-accounts/${widget.id}');
      if (mounted) {
        setState(() {
          data = d;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  bool get editable =>
      widget.auth.isSuperAdmin ||
      !(data?['roles'] as List? ?? []).any(
        (r) => ['super_admin', 'school_administrator'].contains(r['code']),
      );
  Future<void> editAccount() async {
    final role = (data!['roles'] as List).first['code'] as String;
    final s = Strings.of(context);
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'editStaff',
          fields: [
            FormFieldSpec('name', 'fullName', initial: data!['name']),
            FormFieldSpec('email', 'email', initial: data!['email']),
            FormFieldSpec(
              'mobile',
              'mobile',
              initial: data!['mobile'],
              required: false,
              maxLength: 30,
            ),
            FormFieldSpec(
              'address',
              'address',
              initial: data!['address'],
              required: false,
              kind: FieldKind.multiline,
              maxLength: 1000,
            ),
            FormFieldSpec(
              'role',
              'role',
              kind: FieldKind.choice,
              initial: role,
              options: staffRoles(context, widget.auth),
            ),
            const FormFieldSpec(
              'password',
              'New password (4+ characters; leave blank to keep)',
              kind: FieldKind.password,
              required: false,
              maxLength: 1024,
            ),
            const FormFieldSpec(
              'password_confirmation',
              'confirmPassword',
              kind: FieldKind.password,
              required: false,
              maxLength: 1024,
            ),
          ],
          onSubmit: (v) async {
            if ((v['password'] as String? ?? '').isEmpty) {
              v.remove('password');
              v.remove('password_confirmation');
            }
            if (v['role'] != role &&
                !await confirmAction(
                  context,
                  title: s.text('role'),
                  message: s.text('roleChangeNotice'),
                  action: s.text('confirm'),
                )) {
              throw const ApiFailure('operationCancelled');
            }
            await widget.auth.call(
              'staff-accounts/${widget.id}',
              method: 'PATCH',
              body: v,
            );
          },
        ),
      ),
    );
    if (mounted) load();
  }

  Future<void> status() async {
    final s = Strings.of(context);
    if (!await confirmAction(
      context,
      title: s.text(
        data!['is_active'] == true ? 'suspendAccount' : 'reactivateAccount',
      ),
      message: s.text('accountStatusConfirm'),
      action: s.text('confirm'),
    )) {
      return;
    }
    try {
      await widget.auth.call(
        'staff-accounts/${widget.id}/status',
        method: 'PATCH',
        body: {'is_active': data!['is_active'] != true},
      );
      if (mounted) load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> profile() async {
    final existing = data!['profile'] as Map?;
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'staffProfile',
          fields: [
            FormFieldSpec(
              'employee_number',
              'employeeNumber',
              initial: existing?['employee_number'],
            ),
            FormFieldSpec(
              'designation',
              'designation',
              initial: existing?['designation'],
            ),
            FormFieldSpec(
              'joined_on',
              'joiningDate',
              kind: FieldKind.date,
              initial: existing?['joined_on'],
            ),
            if (existing != null)
              FormFieldSpec(
                'is_active',
                'active',
                kind: FieldKind.boolean,
                initial: existing['is_active'] == true,
              ),
          ],
          onSubmit: (v) => existing == null
              ? widget.auth.call(
                  'catalog/staff',
                  body: {...v, 'user_id': widget.id},
                )
              : widget.auth.call(
                  'staff-accounts/${widget.id}',
                  method: 'PATCH',
                  body: {'employment': v},
                ),
        ),
      ),
    );
    if (mounted) load();
  }

  Future<void> assignment({Map<String, dynamic>? existing}) async {
    final role = (data!['roles'] as List).first['code'] as String;
    if (existing == null && role == 'teacher') {
      await Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (_) => TeacherAssignmentBatchScreen(
            auth: widget.auth,
            teacher: {
              'id': data!['profile']['id'],
              'name': data!['name'],
              'employee_number': data!['profile']['employee_number'],
              'account_code': data!['account_code'],
            },
          ),
        ),
      );
      if (mounted) load();
      return;
    }
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => StaffAssignmentScreen(
          auth: widget.auth,
          profileId: data!['profile']['id'] as String,
          coordinator:
              existing?['kind'] == 'coordinator' ||
              (existing == null && role == 'coordinator'),
          existing: existing,
        ),
      ),
    );
    if (mounted) load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final roles = data?['roles'] as List? ?? [];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('staffManagement'))),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : PageBody(
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  SurfaceCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        AuthorizedPhoto(
                          auth: widget.auth,
                          fileId: widget.id == widget.auth.user?['id']
                              ? data!['photo_file_id'] as String?
                              : null,
                          name: data!['name'] as String,
                          size: 72,
                        ),
                        const SizedBox(height: AppSpace.md),
                        Text(
                          data!['name'] as String,
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        Text(
                          data!['email'] as String,
                          textDirection: TextDirection.ltr,
                        ),
                        if (data!['account_code'] != null)
                          SelectableText(data!['account_code'] as String),
                        const SizedBox(height: AppSpace.md),
                        Wrap(
                          spacing: AppSpace.sm,
                          runSpacing: AppSpace.sm,
                          children: [
                            for (final r in roles)
                              AppBadge(label: s.text(r['code'] as String)),
                            AppBadge(
                              label: s.text(
                                data!['is_active'] == true
                                    ? 'active'
                                    : 'inactive',
                              ),
                              status: data!['is_active'] == true
                                  ? 'active'
                                  : 'inactive',
                            ),
                          ],
                        ),
                        if (data!['profile'] != null) ...[
                          const SizedBox(height: AppSpace.md),
                          Text(
                            '${data!['profile']['employee_number']} · ${data!['profile']['designation']}',
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.lg),
                  SectionHeader(title: s.text('contactSection')),
                  SurfaceCard(
                    child: Column(
                      children: [
                        ValueRow(
                          label: s.text('mobile'),
                          value:
                              data!['mobile']?.toString() ??
                              s.text('notRecorded'),
                        ),
                        ValueRow(
                          label: s.text('address'),
                          value:
                              data!['address']?.toString() ??
                              s.text('notRecorded'),
                        ),
                        if (data!['profile'] != null)
                          ValueRow(
                            label: s.text('joiningDate'),
                            value:
                                data!['profile']['joined_on']?.toString() ??
                                s.text('notRecorded'),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.lg),
                  if (error != null)
                    StatusBanner(
                      message: s.text(error!),
                      kind: StatusKind.error,
                    ),
                  if (editable)
                    Wrap(
                      spacing: AppSpace.sm,
                      runSpacing: AppSpace.sm,
                      children: [
                        OutlinedButton.icon(
                          onPressed: editAccount,
                          icon: const Icon(Icons.edit_outlined),
                          label: Text(s.text('editStaff')),
                        ),
                        OutlinedButton.icon(
                          key: const Key('edit-staff-employment'),
                          onPressed: profile,
                          icon: const Icon(Icons.badge_outlined),
                          label: Text(
                            s.text(
                              data!['profile'] == null
                                  ? 'staffProfile'
                                  : 'editEmployment',
                            ),
                          ),
                        ),
                        if (widget.id != widget.auth.user?['id'])
                          OutlinedButton.icon(
                            onPressed: status,
                            icon: const Icon(Icons.lock_outline),
                            label: Text(
                              s.text(
                                data!['is_active'] == true
                                    ? 'suspendAccount'
                                    : 'reactivateAccount',
                              ),
                            ),
                          ),
                      ],
                    ),
                  const SizedBox(height: AppSpace.xl),
                  SectionHeader(title: s.text('staffAssignments')),
                  if (widget.auth.can('school.manage') &&
                      roles.any(
                        (r) => ['teacher', 'coordinator'].contains(r['code']),
                      ))
                    FilledButton.icon(
                      onPressed: data!['profile'] == null
                          ? profile
                          : () => assignment(),
                      icon: const Icon(Icons.add),
                      label: Text(
                        s.text(
                          data!['profile'] == null
                              ? 'staffProfile'
                              : 'staffAssignments',
                        ),
                      ),
                    ),
                  for (final raw in data!['assignments'] as List)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpace.md),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${raw['class_name']} · ${raw['section_name']}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(
                              '${raw['session_name']} · ${raw['subject_name'] ?? s.text('coordinator')}',
                            ),
                            AppBadge(
                              label: s.text(
                                raw['is_active'] == true
                                    ? 'active'
                                    : 'inactive',
                              ),
                              status: raw['is_active'] == true
                                  ? 'active'
                                  : 'inactive',
                            ),
                            if (widget.auth.can('school.manage'))
                              TextButton.icon(
                                onPressed: () => assignment(
                                  existing: (raw as Map)
                                      .cast<String, dynamic>(),
                                ),
                                icon: const Icon(Icons.edit_outlined),
                                label: Text(s.text('edit')),
                              ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}
