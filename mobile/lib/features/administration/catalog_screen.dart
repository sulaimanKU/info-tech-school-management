import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_form.dart';

class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  String selected = 'sessions';
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> records = [];
  final lookups = <String, Map<String, String>>{};
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> accountStatus(Map<String, dynamic> user) async {
    final s = Strings.of(context);
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(
          s.text(
            user['is_active'] == true ? 'suspendAccount' : 'reactivateAccount',
          ),
        ),
        content: Text(s.text('accountStatusConfirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(s.text('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: Text(s.text('confirm')),
          ),
        ],
      ),
    );
    if (yes != true) return;
    try {
      await widget.auth.call(
        'staff-accounts/${user['id']}/status',
        method: 'PATCH',
        body: {'is_active': user['is_active'] != true},
      );
      await load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  String path(String key) =>
      key == 'staff-accounts' ? 'staff-accounts' : 'catalog/$key';
  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.auth.call(path(selected));
      for (final key in ['sessions', 'classes', 'subjects', 'sections']) {
        final value = await widget.auth.call('catalog/$key');
        lookups[key] = {
          for (final v in value['items'] as List)
            (v['id'] as String): (v['name'] ?? v['code']).toString(),
        };
      }
      if (widget.auth.can('staff.manage')) {
        for (final key in ['staff-accounts', 'staff']) {
          final value = await widget.auth.call(path(key));
          lookups[key] = {
            for (final v in value['items'] as List)
              (v['id'] as String):
                  '${v['employee_number'] ?? v['account_code'] ?? ''} | ${v['name'] ?? v['designation'] ?? ''}',
          };
        }
      }
      if (mounted) {
        setState(
          () => records = (data['items'] as List)
              .map((v) => (v as Map).cast<String, dynamic>())
              .toList(),
        );
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> create([Map<String, dynamic>? existing]) async {
    final s = Strings.of(context);
    final fields = <FormFieldSpec>[];
    FormFieldSpec choice(String field, String label, String source) =>
        FormFieldSpec(
          field,
          label,
          kind: FieldKind.choice,
          options: lookups[source] ?? {},
        );
    switch (selected) {
      case 'sessions':
        fields.addAll([
          const FormFieldSpec('name', 'name'),
          const FormFieldSpec('starts_on', 'startDate', kind: FieldKind.date),
          const FormFieldSpec('ends_on', 'endDate', kind: FieldKind.date),
        ]);
      case 'classes':
        fields.addAll([
          const FormFieldSpec('name', 'name'),
          const FormFieldSpec(
            'sort_order',
            'classOrder',
            kind: FieldKind.integer,
          ),
        ]);
      case 'sections':
        fields.addAll([
          const FormFieldSpec('name', 'name'),
          choice('class_id', 'class', 'classes'),
          choice('academic_session_id', 'academicSession', 'sessions'),
        ]);
      case 'subjects':
        fields.addAll([
          const FormFieldSpec('name', 'name'),
          const FormFieldSpec('code', 'subjectCode'),
        ]);
      case 'class-subjects':
        fields.addAll([
          choice('class_id', 'class', 'classes'),
          choice('subject_id', 'subject', 'subjects'),
          choice('academic_session_id', 'academicSession', 'sessions'),
        ]);
      case 'staff':
        fields.addAll([
          choice('user_id', 'staffAccount', 'staff-accounts'),
          const FormFieldSpec('employee_number', 'employeeNumber'),
          const FormFieldSpec('joined_on', 'joiningDate', kind: FieldKind.date),
          const FormFieldSpec('designation', 'designation'),
        ]);
      case 'teacher-assignments':
        final sections = await sectionOptions(widget.auth);
        if (!mounted) return;
        fields.addAll([
          choice('staff_profile_id', 'staffProfile', 'staff'),
          FormFieldSpec(
            'section_id',
            'classSection',
            kind: FieldKind.choice,
            options: sections,
          ),
          FormFieldSpec(
            'subject_id',
            'subject',
            kind: FieldKind.choice,
            options: lookups['subjects'] ?? {},
            required: false,
          ),
          const FormFieldSpec(
            'is_class_teacher',
            'classTeacher',
            kind: FieldKind.boolean,
            initial: false,
          ),
        ]);
      case 'fee-categories':
        fields.add(const FormFieldSpec('name', 'name'));
      case 'staff-accounts':
        fields.addAll([
          const FormFieldSpec('name', 'fullName'),
          const FormFieldSpec('email', 'email'),
          const FormFieldSpec(
            'password',
            'temporaryPassword',
            kind: FieldKind.password,
            maxLength: 1024,
          ),
          FormFieldSpec(
            'role',
            'role',
            kind: FieldKind.choice,
            options: {
              for (final role in [
                'teacher',
                'coordinator',
                'accountant_clerk',
                if (widget.auth.isSuperAdmin) 'school_administrator',
                if (widget.auth.isSuperAdmin) 'super_admin',
              ])
                role: s.text(role),
            },
          ),
        ]);
    }
    if (!mounted) return;
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          academicAuth: selected == 'teacher-assignments' ? widget.auth : null,
          title: 'addRecord',
          fields: [
            for (final f in fields)
              FormFieldSpec(
                f.name,
                f.label,
                kind: f.kind,
                required: f.required,
                options: f.options,
                initial: existing?[f.name] ?? f.initial,
                maxLength: f.maxLength,
              ),
          ],
          onSubmit: (v) async {
            await widget.auth.call(
              existing == null
                  ? path(selected)
                  : '${path(selected)}/${existing['id']}',
              method: existing == null ? 'POST' : 'PATCH',
              body: existing == null
                  ? v
                  : {
                      for (final e in v.entries)
                        if (e.value != existing[e.key]) e.key: e.value,
                    },
            );
          },
        ),
      ),
    );
    if (result == true) load();
  }

  String title(Map<String, dynamic> v) =>
      v['name']?.toString() ??
      v['employee_number']?.toString() ??
      '${lookups['sections']?[v['section_id']] ?? lookups['classes']?[v['class_id']] ?? ''} · ${lookups['subjects']?[v['subject_id']] ?? ''}';
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final choices = [
      'sessions',
      'classes',
      'sections',
      'subjects',
      'class-subjects',
      'fee-categories',
      if (widget.auth.can('staff.manage'))
        ...'staff,staff-accounts,teacher-assignments'.split(','),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(s.text('schoolSetup'))),
      floatingActionButton: FloatingActionButton(
        onPressed: () => create(),
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpace.lg),
            child: DropdownButtonFormField<String>(
              initialValue: selected,
              isExpanded: true,
              decoration: InputDecoration(labelText: s.text('manage')),
              items: choices
                  .map(
                    (c) => DropdownMenuItem(value: c, child: Text(s.text(c))),
                  )
                  .toList(),
              onChanged: loading
                  ? null
                  : (v) {
                      selected = v!;
                      load();
                    },
            ),
          ),
          Expanded(
            child: loading
                ? const DataState()
                : error != null
                ? DataState(error: error, onRetry: load)
                : RefreshIndicator(
                    onRefresh: load,
                    child: ListView(
                      padding: const EdgeInsets.all(AppSpace.lg),
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        if (records.isEmpty) const DataState(empty: true),
                        for (final v in records)
                          Padding(
                            padding: const EdgeInsets.only(bottom: AppSpace.md),
                            child: SurfaceCard(
                              padding: EdgeInsets.zero,
                              child: ListTile(
                                onTap:
                                    [
                                      'sessions',
                                      'classes',
                                      'sections',
                                      'subjects',
                                      'staff',
                                      'fee-categories',
                                    ].contains(selected)
                                    ? () => create(v)
                                    : null,
                                title: Text(title(v)),
                                subtitle: Text(
                                  [
                                    if (v['starts_on'] != null)
                                      '${v['starts_on']} — ${v['ends_on']}',
                                    if (v['designation'] != null)
                                      v['designation'],
                                    if (v['email'] != null) v['email'],
                                    if (v['is_active'] == false)
                                      s.text('inactive'),
                                  ].join(' · '),
                                ),
                                trailing: selected == 'staff-accounts'
                                    ? IconButton(
                                        tooltip: s.text(
                                          v['is_active'] == true
                                              ? 'suspendAccount'
                                              : 'reactivateAccount',
                                        ),
                                        onPressed: () => accountStatus(v),
                                        icon: Icon(
                                          v['is_active'] == true
                                              ? Icons.lock_outline
                                              : Icons.lock_open,
                                        ),
                                      )
                                    : [
                                            'staff',
                                            'teacher-assignments',
                                          ].contains(selected) &&
                                          v['is_active'] == true
                                    ? IconButton(
                                        tooltip: s.text('deactivate'),
                                        icon: const Icon(
                                          Icons.pause_circle_outline,
                                        ),
                                        onPressed: () async {
                                          final ok = await showDialog<bool>(
                                            context: context,
                                            builder: (c) => AlertDialog(
                                              title: Text(s.text('deactivate')),
                                              content: Text(
                                                s.text('deactivateConfirm'),
                                              ),
                                              actions: [
                                                TextButton(
                                                  onPressed: () =>
                                                      Navigator.pop(c, false),
                                                  child: Text(s.text('cancel')),
                                                ),
                                                FilledButton(
                                                  onPressed: () =>
                                                      Navigator.pop(c, true),
                                                  child: Text(
                                                    s.text('confirm'),
                                                  ),
                                                ),
                                              ],
                                            ),
                                          );
                                          if (ok == true) {
                                            try {
                                              await widget.auth.call(
                                                'catalog/$selected/${v['id']}/deactivate',
                                                body: {},
                                              );
                                              load();
                                            } on ApiFailure catch (e) {
                                              if (mounted) {
                                                setState(() => error = e.code);
                                              }
                                            }
                                          }
                                        },
                                      )
                                    : null,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
