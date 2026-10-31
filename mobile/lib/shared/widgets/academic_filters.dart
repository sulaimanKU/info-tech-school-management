import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../features/auth/auth_controller.dart';
import '../../l10n/strings.dart';
import 'surface_card.dart';

class ScopedChoice extends StatelessWidget {
  const ScopedChoice({
    super.key,
    required this.label,
    required this.options,
    required this.value,
    required this.onChanged,
    this.enabled = true,
    this.allowAny = true,
    this.prompt,
  });
  final String label;
  final Map<String, String> options;
  final String? value;
  final ValueChanged<String?> onChanged;
  final bool enabled, allowAny;
  final String? prompt;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (options.length <= 20) {
      return DropdownButtonFormField<String>(
        key: ValueKey('$label:$value:${options.keys.join()}'),
        initialValue: options.containsKey(value) ? value : null,
        isExpanded: true,
        decoration: InputDecoration(labelText: s.text(label)),
        hint: Text(s.text(prompt ?? 'anyValue')),
        items: [
          if (allowAny)
            DropdownMenuItem(value: '', child: Text(s.text('anyValue'))),
          for (final e in options.entries)
            DropdownMenuItem(
              value: e.key,
              child: Text(e.value, overflow: TextOverflow.ellipsis),
            ),
        ],
        onChanged: enabled ? (v) => onChanged(v == '' ? null : v) : null,
      );
    }
    return InkWell(
      onTap: !enabled
          ? null
          : () async {
              final selected = await showDialog<String>(
                context: context,
                builder: (c) => _ChoiceDialog(
                  label: label,
                  options: options,
                  allowAny: allowAny,
                ),
              );
              if (selected != null) {
                onChanged(selected.isEmpty ? null : selected);
              }
            },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: s.text(label),
          suffixIcon: const Icon(Icons.search),
        ),
        child: Text(options[value] ?? s.text(prompt ?? 'anyValue')),
      ),
    );
  }
}

class _ChoiceDialog extends StatefulWidget {
  const _ChoiceDialog({
    required this.label,
    required this.options,
    required this.allowAny,
  });
  final String label;
  final Map<String, String> options;
  final bool allowAny;
  @override
  State<_ChoiceDialog> createState() => _ChoiceDialogState();
}

class _ChoiceDialogState extends State<_ChoiceDialog> {
  String query = '';
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final rows = widget.options.entries
        .where((e) => e.value.toLowerCase().contains(query.toLowerCase()))
        .toList();
    return AlertDialog(
      title: Text(s.text(widget.label)),
      content: SizedBox(
        width: 480,
        height: 360,
        child: Column(
          children: [
            TextField(
              decoration: InputDecoration(
                labelText: s.text('search'),
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (v) => setState(() => query = v),
            ),
            Expanded(
              child: ListView.builder(
                itemCount: rows.length,
                itemBuilder: (c, i) => ListTile(
                  title: Text(rows[i].value),
                  onTap: () => Navigator.pop(c, rows[i].key),
                ),
              ),
            ),
          ],
        ),
      ),
      actions: [
        if (widget.allowAny)
          TextButton(
            onPressed: () => Navigator.pop(context, ''),
            child: Text(s.text('anyValue')),
          ),
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(s.text('cancel')),
        ),
      ],
    );
  }
}

/// All reference choices are supplied by server-authorized scope, never all students.
class AcademicSelection extends StatefulWidget {
  const AcademicSelection({
    super.key,
    required this.auth,
    required this.initial,
    required this.onChanged,
    this.subject = false,
    this.preferCurrentSession = false,
    this.showSession = true,
    this.enabled = true,
    this.creation = false,
  });
  final AuthController auth;
  final Map<String, String> initial;
  final ValueChanged<Map<String, String>> onChanged;
  final bool subject, preferCurrentSession, showSession, enabled, creation;
  @override
  State<AcademicSelection> createState() => _AcademicSelectionState();
}

class _AcademicSelectionState extends State<AcademicSelection> {
  late Map<String, String> values;
  Map<String, dynamic>? data;
  String? error;
  int generation = 0;
  bool appliedDefault = false;
  @override
  void initState() {
    super.initState();
    values = Map.of(widget.initial);
    load();
  }

  Future<void> load() async {
    final version = ++generation;
    try {
      final result = await widget.auth.call(
        Uri(
          path: 'academic-filter-options',
          queryParameters: values,
        ).toString(),
      );
      if (mounted && version == generation && !appliedDefault) {
        appliedDefault = true;
        final current = result['current_academic_session_id'] as String?;
        if (widget.preferCurrentSession &&
            values.isEmpty &&
            current != null &&
            (result['sessions'] as List).any((row) => row['id'] == current)) {
          values['academic_session_id'] = current;
          widget.onChanged(Map.of(values));
          await load();
          return;
        }
      }
      if (mounted && version == generation) {
        setState(() {
          data = result;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted && version == generation) setState(() => error = e.code);
    }
  }

  void changed(String key, String? value) {
    setState(() {
      if (value == null) {
        values.remove(key);
      } else {
        values[key] = value;
      }
      if (key == 'academic_session_id') {
        values.remove('class_id');
        values.remove('section_id');
        values.remove('subject_id');
      }
      if (key == 'class_id') {
        values.remove('section_id');
        values.remove('subject_id');
      }
      if (key == 'section_id') values.remove('subject_id');
      data = null;
    });
    widget.onChanged(Map.of(values));
    load();
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (error != null) ...[
        StatusBanner(
          message: Strings.of(context).text(error!),
          kind: StatusKind.error,
        ),
        TextButton(
          onPressed: load,
          child: Text(Strings.of(context).text('retry')),
        ),
      ],
      for (final field in [
        if (widget.showSession)
          ('academic_session_id', 'academicSession', 'sessions'),
        ('class_id', 'class', 'classes'),
        ('section_id', 'section', 'sections'),
        if (widget.subject) ('subject_id', 'subject', 'subjects'),
      ])
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpace.lg),
          child: ScopedChoice(
            key: ValueKey('filter-${field.$1}'),
            label: field.$2,
            enabled:
                widget.enabled &&
                data != null &&
                (!widget.creation ||
                    switch (field.$1) {
                      'class_id' => values.containsKey('academic_session_id'),
                      'section_id' => values.containsKey('class_id'),
                      'subject_id' => values.containsKey('section_id'),
                      _ => true,
                    }),
            allowAny: !widget.creation,
            prompt: widget.creation
                ? switch (field.$1) {
                    'academic_session_id' => 'selectAcademicSession',
                    'class_id' => 'selectClass',
                    'section_id' => 'selectSection',
                    _ => 'selectSubject',
                  }
                : null,
            options: {
              for (final row in data?[field.$3] as List? ?? [])
                row['id'] as String: row['name'] as String,
            },
            value: values[field.$1],
            onChanged: (v) => changed(field.$1, v),
          ),
        ),
    ],
  );
}

class FilterSpec {
  const FilterSpec({
    this.academic = false,
    this.search = false,
    this.assignees = false,
    this.subject = false,
    this.statuses = const [],
    this.period = false,
    this.dates = true,
    this.extra = const {},
  });
  final bool academic, subject, period, dates, search, assignees;
  final List<String> statuses;
  final Map<String, List<String>> extra;
  static FilterSpec? forPath(String path, bool parent) =>
      switch (Uri.parse(path).path) {
        'teaching-assignments' => const FilterSpec(
          statuses: ['active', 'inactive'],
          search: true,
          academic: true,
          subject: true,
          dates: false,
        ),
        'teaching-assignments/teachers' => const FilterSpec(
          search: true,
          dates: false,
        ),
        final String p when p.startsWith('my-teaching/sections/') =>
          const FilterSpec(search: true, dates: false),
        'students' => const FilterSpec(
          academic: true,
          dates: false,
          statuses: ['active', 'withdrawn', 'transferred'],
          extra: {
            'enrollment_status': [
              'active',
              'completed',
              'withdrawn',
              'transferred',
            ],
          },
        ),
        'diary' => FilterSpec(
          search: true,
          academic: !parent,
          subject: !parent,
          statuses: parent ? const [] : const ['draft', 'published'],
        ),
        'assessments' => const FilterSpec(
          academic: true,
          subject: true,
          statuses: ['draft', 'submitted', 'approved', 'published'],
          extra: {
            'type': [
              'monthly_test',
              'unit_test',
              'midterm',
              'final',
              'oral',
              'assignment',
              'practical',
            ],
          },
        ),
        'finance/invoices' => const FilterSpec(
          academic: true,
          period: true,
          statuses: [
            'unpaid',
            'paid',
            'partially_paid',
            'overdue',
            'waived_adjusted',
          ],
        ),
        'finance/payments' => const FilterSpec(
          academic: true,
          statuses: ['pending', 'verified', 'canceled', 'refunded'],
        ),
        'tickets' => FilterSpec(
          assignees: !parent,
          academic: !parent,
          statuses: const [
            'submitted',
            'acknowledged',
            'in_progress',
            'awaiting_parent',
            'escalated',
            'resolved',
            'closed',
          ],
        ),
        'announcements' => FilterSpec(
          statuses: parent
              ? const ['published']
              : const ['draft', 'published', 'archived'],
          extra: const {
            'read': ['read', 'unread'],
          },
        ),
        'guardians' => const FilterSpec(
          dates: false,
          statuses: ['active', 'inactive'],
        ),
        'staff-accounts' => const FilterSpec(
          dates: false,
          statuses: ['active', 'inactive'],
          extra: {
            'role': [
              'super_admin',
              'school_administrator',
              'coordinator',
              'accountant_clerk',
              'teacher',
            ],
          },
        ),
        _ => null,
      };
}

Future<Map<String, String>?> showFilterSheet(
  BuildContext context,
  AuthController auth,
  FilterSpec spec,
  Map<String, String> initial,
) => showModalBottomSheet<Map<String, String>>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (_) => _FilterSheet(auth: auth, spec: spec, initial: initial),
);

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({
    required this.auth,
    required this.spec,
    required this.initial,
  });
  final AuthController auth;
  final FilterSpec spec;
  final Map<String, String> initial;
  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late Map<String, String> values;
  int revision = 0;
  Map<String, String> assignees = {};
  Future<void> loadAssignees() async {
    try {
      final d = await widget.auth.call('communication/staff');
      if (mounted) {
        setState(
          () => assignees = {
            for (final row in d['items'] as List)
              if (row['can_coordinate'] == true)
                row['id'] as String: row['name'] as String,
          },
        );
      }
    } on ApiFailure catch (_) {
      /* Existing ticket list remains usable without optional assignee choices. */
    }
  }

  @override
  void initState() {
    super.initState();
    values = Map.of(widget.initial);
    if (widget.spec.assignees && widget.auth.can('communication.manage')) {
      loadAssignees();
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return FractionallySizedBox(
      heightFactor: .9,
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: ListView(
          padding: const EdgeInsets.all(AppSpace.xl),
          children: [
            Text(
              s.text('filters'),
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: AppSpace.xl),
            if (widget.spec.search)
              TextFormField(
                key: ValueKey('search-$revision'),
                initialValue: values['search'],
                decoration: InputDecoration(
                  labelText: s.text('search'),
                  prefixIcon: const Icon(Icons.search),
                ),
                onChanged: (v) {
                  if (v.trim().isEmpty) {
                    values.remove('search');
                  } else {
                    values['search'] = v.trim();
                  }
                },
              ),
            if (widget.spec.assignees &&
                widget.auth.can('communication.manage')) ...[
              ScopedChoice(
                label: 'assignCoordinator',
                options: assignees,
                value: values['assigned_to'],
                onChanged: (v) => setState(() {
                  if (v == null) {
                    values.remove('assigned_to');
                  } else {
                    values['assigned_to'] = v;
                  }
                }),
              ),
              const SizedBox(height: AppSpace.lg),
            ],
            if (widget.spec.academic && !widget.auth.isParent)
              AcademicSelection(
                key: ValueKey(revision),
                auth: widget.auth,
                initial: {
                  for (final e in values.entries)
                    if ([
                      'academic_session_id',
                      'class_id',
                      'section_id',
                      'subject_id',
                    ].contains(e.key))
                      e.key: e.value,
                },
                subject: widget.spec.subject,
                onChanged: (v) => setState(() {
                  for (final k in [
                    'academic_session_id',
                    'class_id',
                    'section_id',
                    'subject_id',
                  ]) {
                    values.remove(k);
                  }
                  values.addAll(v);
                }),
              ),
            if (widget.spec.statuses.isNotEmpty) ...[
              ScopedChoice(
                label: 'status',
                value: values['status'],
                options: {for (final x in widget.spec.statuses) x: s.text(x)},
                onChanged: (v) => setState(
                  () => v == null
                      ? values.remove('status')
                      : values['status'] = v,
                ),
              ),
              const SizedBox(height: AppSpace.lg),
            ],
            for (final field in widget.spec.extra.entries) ...[
              ScopedChoice(
                label: field.key,
                value: values[field.key],
                options: {for (final x in field.value) x: s.text(x)},
                onChanged: (v) => setState(
                  () => v == null
                      ? values.remove(field.key)
                      : values[field.key] = v,
                ),
              ),
              const SizedBox(height: AppSpace.lg),
            ],
            if (widget.spec.period)
              TextFormField(
                key: ValueKey('period-$revision'),
                initialValue: values['period'],
                textDirection: TextDirection.ltr,
                decoration: InputDecoration(
                  labelText: s.text('period'),
                  hintText: 'YYYY-MM',
                ),
                onChanged: (v) {
                  if (v.trim().isEmpty) {
                    values.remove('period');
                  } else {
                    values['period'] = v.trim();
                  }
                },
              ),
            if (widget.spec.dates)
              for (final field in ['from', 'to'])
                Padding(
                  padding: const EdgeInsets.only(top: AppSpace.lg),
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      final date = await showDatePicker(
                        context: context,
                        initialDate:
                            DateTime.tryParse(values[field] ?? '') ??
                            DateTime.now(),
                        firstDate: DateTime(2000),
                        lastDate: DateTime(2200),
                      );
                      if (date != null && mounted) {
                        setState(
                          () => values[field] = date
                              .toIso8601String()
                              .substring(0, 10),
                        );
                      }
                    },
                    icon: const Icon(Icons.calendar_today_outlined),
                    label: Text(
                      '${s.text(field)}: ${values[field] ?? s.text('anyValue')}',
                    ),
                  ),
                ),
            const SizedBox(height: AppSpace.xl),
            FilledButton(
              key: const Key('apply-filters'),
              onPressed: () => Navigator.pop(context, values),
              child: Text(s.text('applyFilters')),
            ),
            TextButton(
              key: const Key('reset-filters'),
              onPressed: () => setState(() {
                values.clear();
                revision++;
              }),
              child: Text(s.text('resetFilters')),
            ),
          ],
        ),
      ),
    );
  }
}
