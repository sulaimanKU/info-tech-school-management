import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_form.dart';

Future<void> announcementForm(
  BuildContext context,
  AuthController auth, {
  Map<String, dynamic>? existing,
}) async {
  final sections = await sectionOptions(auth);
  final parents = await auth.call('communication/parents');
  final classes = auth.can('students.read.all')
      ? await auth.call('catalog/classes')
      : {'items': []};
  if (!context.mounted) return;
  final s = Strings.of(context);
  await Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: 'announcement',
        fields: [
          FormFieldSpec('title', 'title', initial: existing?['title']),
          FormFieldSpec(
            'body',
            'announcementBody',
            kind: FieldKind.multiline,
            maxLength: 10000,
            initial: existing?['body'],
          ),
          FormFieldSpec(
            'kind',
            'category',
            kind: FieldKind.choice,
            initial: existing?['kind'],
            options: {
              for (final k in [
                'notice',
                'holiday',
                'exam',
                'result',
                'ptm',
                'fee_reminder',
                'event',
                'emergency',
                'homework',
                'admission',
              ])
                k: s.text(k),
            },
          ),
          FormFieldSpec(
            'audience',
            'audience',
            kind: FieldKind.choice,
            initial: existing?['audience'],
            options: {
              for (final k in [
                if (auth.can('students.read.all'))
                  ...'all_users,all_parents,role,teachers,staffAudience'.split(
                    ',',
                  ),
                'class',
                'section',
                'parent',
              ])
                k == 'staffAudience' ? 'staff' : k: s.text(k),
            },
          ),
          FormFieldSpec(
            'section_id',
            'classSection',
            kind: FieldKind.choice,
            required: false,
            initial: existing?['section_id'],
            options: sections,
          ),
          FormFieldSpec(
            'class_id',
            'class',
            kind: FieldKind.choice,
            required: false,
            initial: existing?['class_id'],
            options: {
              for (final row in classes['items'] as List)
                row['id'] as String: row['name'] as String,
            },
          ),
          if (auth.can('students.read.all'))
            FormFieldSpec(
              'target_role_code',
              'role',
              kind: FieldKind.choice,
              required: false,
              initial: existing?['target_role_code'],
              options: {
                for (final k in [
                  'super_admin',
                  'school_administrator',
                  'coordinator',
                  'accountant_clerk',
                  'teacher',
                  'parent_guardian',
                ])
                  k: s.text(k),
              },
            ),
          FormFieldSpec(
            'target_user_id',
            'individualParent',
            kind: FieldKind.choice,
            required: false,
            initial: existing?['target_user_id'],
            options: {
              for (final p in parents['items'] as List)
                (p['user_id'] as String): p['full_name'] as String,
            },
          ),
          FormFieldSpec(
            'status',
            'status',
            kind: FieldKind.choice,
            initial: existing?['status'] ?? 'draft',
            options: {
              for (final k in ['draft', 'published', 'archived']) k: s.text(k),
            },
          ),
          FormFieldSpec(
            'publish_at',
            'publicationDate',
            kind: FieldKind.date,
            required: false,
            initial: existing?['publish_at']?.toString().substring(0, 10),
          ),
          FormFieldSpec(
            'expires_at',
            'expiryDate',
            kind: FieldKind.date,
            required: false,
            initial: existing?['expires_at']?.toString().substring(0, 10),
          ),
        ],
        onSubmit: (v) async {
          await auth.call(
            existing == null
                ? 'announcements'
                : 'announcements/${existing['id']}',
            method: existing == null ? 'POST' : 'PUT',
            body: {...v, 'version': existing?['version'] ?? 0},
          );
        },
      ),
    ),
  );
}

class AnnouncementScreen extends StatelessWidget {
  const AnnouncementScreen({super.key, required this.auth, this.studentId});
  final String? studentId;
  final AuthController auth;
  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: Uri(
      path: 'announcements',
      queryParameters: {'student_id': ?studentId},
    ).toString(),
    title: 'announcements',
    recordTitle: (r) => r['title'] as String,
    recordSubtitle: (r) =>
        '${Strings.of(context).text(r['kind'] as String)} · ${r['publish_at']?.toString().substring(0, 10) ?? ''}',
    onCreate: auth.can('announcements.manage')
        ? (c) => announcementForm(c, auth)
        : null,
    onOpen: (c, r) async {
      await Navigator.push<void>(
        c,
        MaterialPageRoute(
          builder: (_) =>
              AnnouncementDetailScreen(auth: auth, id: r['id'] as String),
        ),
      );
    },
  );
}

class AnnouncementDetailScreen extends StatefulWidget {
  const AnnouncementDetailScreen({
    super.key,
    required this.auth,
    required this.id,
  });
  final AuthController auth;
  final String id;
  @override
  State<AnnouncementDetailScreen> createState() =>
      _AnnouncementDetailScreenState();
}

class _AnnouncementDetailScreenState extends State<AnnouncementDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call('announcements/${widget.id}');
      if (mounted) setState(() => data = value);
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.text('announcement')),
        actions: [
          if (widget.auth.can('announcements.manage') &&
              data?['can_manage'] == true)
            IconButton(
              tooltip: s.text('edit'),
              onPressed: data == null
                  ? null
                  : () async {
                      await announcementForm(
                        context,
                        widget.auth,
                        existing: data,
                      );
                      load();
                    },
              icon: const Icon(Icons.edit_outlined),
            ),
        ],
      ),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpace.lg),
              child: SurfaceCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    AppBadge(label: s.text(data!['kind'] as String)),
                    const SizedBox(height: AppSpace.lg),
                    Text(
                      data!['title'] as String,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: AppSpace.lg),
                    SelectableText(data!['body'] as String),
                    const SizedBox(height: AppSpace.xl),
                    Text(
                      data!['publish_at']?.toString().substring(0, 10) ?? '',
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
