import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class SchoolProfileScreen extends StatefulWidget {
  const SchoolProfileScreen({
    super.key,
    required this.auth,
    this.editable = false,
  });
  final AuthController auth;
  final bool editable;
  @override
  State<SchoolProfileScreen> createState() => _SchoolProfileScreenState();
}

class _SchoolProfileScreenState extends State<SchoolProfileScreen> {
  Map<String, dynamic>? data;
  String? error;
  bool loading = false;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (loading) return;
    setState(() => loading = true);
    try {
      final result = await widget.auth.call(
        widget.editable && widget.auth.can('settings.read')
            ? 'school-settings'
            : 'school-information',
      );
      if (mounted) {
        setState(() {
          data = result;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> edit() async {
    try {
      final sessions = await widget.auth.call('catalog/sessions');
      if (!mounted) return;
      final result = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => DomainForm(
            title: 'schoolProfile',
            fields: [
              for (final field in [
                ('school_name', 'schoolName'),
                ('campus', 'campus'),
                ('location', 'location'),
                ('address', 'address'),
                ('phone', 'schoolPhone'),
                ('email', 'email'),
                ('website', 'website'),
              ])
                FormFieldSpec(
                  field.$1,
                  field.$2,
                  initial: data![field.$1],
                  required: [
                    'school_name',
                    'campus',
                    'location',
                  ].contains(field.$1),
                  maxLength: field.$1 == 'address'
                      ? 1000
                      : field.$1 == 'school_name'
                      ? 200
                      : field.$1 == 'website'
                      ? 500
                      : 254,
                ),
              FormFieldSpec(
                'current_academic_session_id',
                'currentAcademicSession',
                kind: FieldKind.choice,
                required: false,
                initial: data!['current_academic_session_id'],
                options: {
                  for (final s in sessions['items'] as List)
                    s['id'] as String: s['name'] as String,
                },
              ),
            ],
            onSubmit: (v) async {
              await widget.auth.call('school-settings', method: 'PUT', body: v);
            },
          ),
        ),
      );
      if (result == true) await load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('schoolProfile'))),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  if (error != null)
                    StatusBanner(
                      message: s.text(error!),
                      kind: StatusKind.error,
                    ),
                  for (final field in [
                    ('school_name', 'schoolName'),
                    ('campus', 'campus'),
                    ('location', 'location'),
                    ('address', 'address'),
                    ('phone', 'schoolPhone'),
                    ('email', 'email'),
                    ('website', 'website'),
                    (
                      'effective_academic_session_name',
                      'currentAcademicSession',
                    ),
                    ('timezone', 'schoolTimezone'),
                    ('currency', 'schoolCurrency'),
                  ])
                    ListTile(
                      title: Text(s.text(field.$2)),
                      subtitle: SelectableText(
                        data![field.$1]?.toString() ?? '-',
                      ),
                    ),
                  StatusBanner(message: s.text('schoolProfilePolicy')),
                  if (data!['integration_status'] case final Map status) ...[
                    ListTile(
                      title: Text(s.text('smsStatus')),
                      subtitle: Text(
                        '${status['sms_driver']} | ${s.text(status['sms_configured'] == true ? 'configured' : 'notConfigured')}',
                      ),
                    ),
                    ListTile(
                      title: Text(s.text('pushNotifications')),
                      subtitle: Text(status['push_driver'].toString()),
                    ),
                    Text(s.text('serverCredentialsPolicy')),
                  ],
                  if (widget.editable && widget.auth.can('school.manage'))
                    FilledButton.icon(
                      onPressed: loading ? null : edit,
                      icon: const Icon(Icons.edit_outlined),
                      label: Text(s.text('editSchoolProfile')),
                    ),
                ],
              ),
            ),
    );
  }
}
