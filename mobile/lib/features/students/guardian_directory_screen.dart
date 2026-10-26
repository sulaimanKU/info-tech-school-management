import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../../shared/forms/profile_photo_picker.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import 'student_list_screen.dart';

class GuardianDirectoryScreen extends StatelessWidget {
  const GuardianDirectoryScreen({
    super.key,
    required this.auth,
    this.studentId,
  });
  final AuthController auth;
  final String? studentId;
  Future<void> create(BuildContext context) async {
    String? savedId;
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'addGuardian',
          fields: [
            FormFieldSpec(
              '_photo',
              'profilePhoto',
              kind: FieldKind.photo,
              required: false,
              photoAuth: auth,
              section: 'profileSection',
            ),
            FormFieldSpec('full_name', 'fullName'),
            FormFieldSpec('cnic', 'cnic', maxLength: 20),
            FormFieldSpec('mobile', 'verifiedMobile', maxLength: 15),
            FormFieldSpec(
              'mobile_verified',
              'mobileVerifiedByAdmin',
              kind: FieldKind.boolean,
              initial: false,
            ),
          ],
          onSubmit: (v) async {
            final photo = v.remove('_photo') as ProfilePhotoDraft?;
            if (savedId == null) {
              final result = await auth.call('guardians', body: v);
              savedId = result['id'] as String;
            }
            await saveProfilePhoto(auth, photo, savedId!, guardian: true);
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!auth.can('guardians.manage')) {
      return const Scaffold(body: DataState(error: 'forbidden'));
    }
    final s = Strings.of(context);
    return RecordsScreen(
      auth: auth,
      path: 'guardians',
      title: 'guardians',
      recordLeading: (v) => AuthorizedPhoto(
        auth: auth,
        fileId: v['photo_file_id'] as String?,
        name: v['full_name'] as String?,
        size: 48,
      ),
      recordTitle: (v) => v['full_name'] as String,
      recordSubtitle: (v) =>
          '${v['cnic_masked']} Â· ${v['mobile_masked']} Â· ${s.text(v['is_active'] == true ? 'active' : 'inactive')}\n${(v['children'] as List? ?? []).map((c) => '${c['full_name']} Â· ${s.text(c['relationship'] as String)}').join('\n')}',
      onCreate: create,
      onOpen: (c, v) => Navigator.push(
        c,
        MaterialPageRoute<void>(
          builder: (_) => GuardianDetailScreen(
            auth: auth,
            id: v['id'] as String,
            studentId: studentId,
          ),
        ),
      ),
    );
  }
}

class GuardianDetailScreen extends StatefulWidget {
  const GuardianDetailScreen({
    super.key,
    required this.auth,
    required this.id,
    this.studentId,
  });
  final AuthController auth;
  final String id;
  final String? studentId;
  @override
  State<GuardianDetailScreen> createState() => _GuardianDetailScreenState();
}

class _GuardianDetailScreenState extends State<GuardianDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final d = await widget.auth.call('guardians/${widget.id}');
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

  Future<void> editContact() async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'editGuardian',
          fields: [
            FormFieldSpec(
              '_photo',
              'profilePhoto',
              kind: FieldKind.photo,
              required: false,
              photoAuth: widget.auth,
              initial: data!['photo_file_id'],
              section: 'profileSection',
            ),
            FormFieldSpec('full_name', 'fullName', initial: data!['full_name']),
            const FormFieldSpec(
              'mobile',
              'verifiedMobile',
              maxLength: 15,
              required: false,
            ),
            const FormFieldSpec(
              'mobile_verified',
              'mobileVerifiedByAdmin',
              kind: FieldKind.boolean,
              initial: false,
              required: false,
            ),
            FormFieldSpec(
              'is_active',
              'active',
              kind: FieldKind.boolean,
              initial: data!['is_active'],
            ),
          ],
          onSubmit: (v) async {
            final photo = v.remove('_photo') as ProfilePhotoDraft?;
            final phone = v['mobile'] as String?;
            await widget.auth.call(
              'guardians/${widget.id}',
              method: 'PATCH',
              body: {
                'full_name': v['full_name'],
                'is_active': v['is_active'],
                if (phone != null && phone.isNotEmpty) 'mobile': phone,
                if (phone != null && phone.isNotEmpty)
                  'mobile_verified': v['mobile_verified'] == true,
              },
            );
            await saveProfilePhoto(
              widget.auth,
              photo,
              widget.id,
              guardian: true,
            );
          },
        ),
      ),
    );
    if (mounted) load();
  }

  Future<void> link({Map<String, dynamic>? child}) async {
    String? id = child?['student_id'] as String? ?? widget.studentId;
    if (id == null) {
      final selected = await Navigator.push<Map<String, dynamic>>(
        context,
        MaterialPageRoute(
          builder: (_) => StudentListScreen(auth: widget.auth, selection: true),
        ),
      );
      id = selected?['id'] as String?;
    }
    if (id == null || !mounted) return;
    final student = id;
    final s = Strings.of(context);
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'linkGuardian',
          fields: [
            FormFieldSpec(
              'relationship',
              'relationship',
              kind: FieldKind.choice,
              initial: child?['relationship'],
              options: {
                for (final k in ['father', 'mother', 'guardian', 'other'])
                  k: s.text(k),
              },
            ),
            FormFieldSpec(
              'is_primary',
              'primaryGuardian',
              kind: FieldKind.boolean,
              initial: child?['is_primary'] ?? false,
            ),
            FormFieldSpec(
              'is_active',
              'linkActive',
              kind: FieldKind.boolean,
              initial: child?['is_active'] ?? true,
            ),
          ],
          onSubmit: (v) => widget.auth.call(
            'guardians/${widget.id}/links',
            method: 'PUT',
            body: {...v, 'student_id': student},
          ),
        ),
      ),
    );
    if (mounted) load();
  }

  Future<void> unlink(Map<String, dynamic> child) async {
    final s = Strings.of(context);
    if (!await confirmAction(
      context,
      title: s.text('unlinkGuardian'),
      message: s.text('unlinkGuardianBody'),
      action: s.text('confirm'),
    )) {
      return;
    }
    try {
      await widget.auth.call(
        'guardians/${widget.id}/links/${child['student_id']}',
        method: 'DELETE',
      );
      if (mounted) load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('guardianDetails'))),
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
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        Text(
                          data!['cnic_masked'] as String,
                          textDirection: TextDirection.ltr,
                        ),
                        Text(
                          data!['mobile_masked'] as String,
                          textDirection: TextDirection.ltr,
                        ),
                        AppBadge(
                          label: s.text(
                            data!['is_active'] == true ? 'active' : 'inactive',
                          ),
                          status: data!['is_active'] == true
                              ? 'active'
                              : 'inactive',
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.lg),
                  StatusBanner(message: s.text('guardianVerificationNotice')),
                  if (error != null)
                    StatusBanner(
                      message: s.text(error!),
                      kind: StatusKind.error,
                    ),
                  const SizedBox(height: AppSpace.md),
                  Wrap(
                    spacing: AppSpace.sm,
                    runSpacing: AppSpace.sm,
                    children: [
                      OutlinedButton.icon(
                        onPressed: editContact,
                        icon: const Icon(Icons.edit_outlined),
                        label: Text(s.text('editGuardian')),
                      ),
                      FilledButton.icon(
                        onPressed: () => link(),
                        icon: const Icon(Icons.link),
                        label: Text(s.text('linkGuardian')),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpace.xl),
                  SectionHeader(title: s.text('myChildren')),
                  for (final raw in data!['children'] as List)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpace.md),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              raw['full_name'] as String,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(
                              '${raw['registration_number']} Â· ${s.text(raw['relationship'] as String)}',
                            ),
                            Wrap(
                              spacing: AppSpace.sm,
                              runSpacing: AppSpace.sm,
                              children: [
                                AppBadge(
                                  label: s.text(
                                    raw['verified_at'] == null
                                        ? 'unverifiedLink'
                                        : 'verifiedLink',
                                  ),
                                  status: raw['verified_at'] == null
                                      ? 'pending'
                                      : 'approved',
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
                              ],
                            ),
                            Wrap(
                              spacing: AppSpace.sm,
                              runSpacing: AppSpace.sm,
                              children: [
                                TextButton(
                                  onPressed: () => link(
                                    child: (raw as Map).cast<String, dynamic>(),
                                  ),
                                  child: Text(s.text('edit')),
                                ),
                                if (raw['is_active'] == true)
                                  TextButton(
                                    onPressed: () => unlink(
                                      (raw as Map).cast<String, dynamic>(),
                                    ),
                                    child: Text(s.text('unlinkGuardian')),
                                  ),
                              ],
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
