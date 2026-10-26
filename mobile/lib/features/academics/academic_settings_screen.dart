import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

class AcademicSettingsScreen extends StatefulWidget {
  const AcademicSettingsScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<AcademicSettingsScreen> createState() => _AcademicSettingsScreenState();
}

class _AcademicSettingsScreenState extends State<AcademicSettingsScreen> {
  Map<String, String> sessions = {};
  String? session, error;
  List<int> weekdays = [];
  bool positions = false, loading = true, busy = false;
  final bands =
      <({TextEditingController minimum, TextEditingController grade})>[];
  List<dynamic> days = [];
  @override
  void initState() {
    super.initState();
    initialize();
  }

  @override
  void dispose() {
    clearBands();
    super.dispose();
  }

  void clearBands() {
    for (final b in bands) {
      b.minimum.dispose();
      b.grade.dispose();
    }
    bands.clear();
  }

  Future<void> initialize() async {
    try {
      final data = await widget.auth.call('catalog/sessions');
      sessions = {
        for (final v in data['items'] as List)
          (v['id'] as String): v['name'] as String,
      };
      session = sessions.keys.firstOrNull;
      await load();
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> load() async {
    final data = await widget.auth.call(
      'academic-settings${session == null ? '' : '?session_id=$session'}',
    );
    clearBands();
    weekdays = (data['weekdays'] as List).cast<int>();
    positions = data['class_position_enabled'] == true;
    days = data['school_days'] as List;
    for (final v in data['bands'] as List) {
      bands.add((
        minimum: TextEditingController(text: v['minimum_percent'].toString()),
        grade: TextEditingController(text: v['grade'] as String),
      ));
    }
    if (mounted) setState(() {});
  }

  Future<void> save() async {
    setState(() => busy = true);
    try {
      await widget.auth.call(
        'academic-settings',
        method: 'PUT',
        body: {'weekdays': weekdays, 'class_position_enabled': positions},
      );
      if (session != null) {
        await widget.auth.call(
          'grading-rules',
          method: 'PUT',
          body: {
            'academic_session_id': session,
            'bands': [
              for (final b in bands)
                {
                  'minimum_percent': num.tryParse(b.minimum.text) ?? -1,
                  'grade': b.grade.text.trim(),
                },
            ],
          },
        );
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(Strings.of(context).text('saved'))),
        );
      }
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> calendar() async {
    if (session == null) return;
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'schoolCalendar',
          fields: [
            const FormFieldSpec('date', 'date', kind: FieldKind.date),
            const FormFieldSpec(
              'is_school_day',
              'schoolDay',
              kind: FieldKind.boolean,
              initial: false,
            ),
            const FormFieldSpec('label', 'reason', required: false),
          ],
          onSubmit: (v) async {
            await widget.auth.call(
              'school-days',
              body: {...v, 'academic_session_id': session},
            );
          },
        ),
      ),
    );
    await load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('academicSettings'))),
      body: loading
          ? const DataState()
          : ListView(
              padding: const EdgeInsets.all(AppSpace.lg),
              children: [
                Text(
                  s.text('schoolWeekdays'),
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                Wrap(
                  spacing: AppSpace.sm,
                  children: [
                    for (var day = 1; day <= 7; day++)
                      FilterChip(
                        label: Text(s.text('weekday$day')),
                        selected: weekdays.contains(day),
                        onSelected: busy
                            ? null
                            : (selected) => setState(() {
                                selected
                                    ? weekdays.add(day)
                                    : weekdays.remove(day);
                              }),
                      ),
                  ],
                ),
                SwitchListTile(
                  title: Text(s.text('showClassPosition')),
                  value: positions,
                  onChanged: busy ? null : (v) => setState(() => positions = v),
                ),
                const SizedBox(height: AppSpace.xl),
                DropdownButtonFormField<String>(
                  initialValue: session,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: s.text('academicSession'),
                  ),
                  items: sessions.entries
                      .map(
                        (e) => DropdownMenuItem(
                          value: e.key,
                          child: Text(e.value),
                        ),
                      )
                      .toList(),
                  onChanged: busy
                      ? null
                      : (v) async {
                          session = v;
                          try {
                            await load();
                          } on ApiFailure catch (e) {
                            if (mounted) setState(() => error = e.code);
                          }
                        },
                ),
                const SizedBox(height: AppSpace.xl),
                Text(
                  s.text('gradingCriteria'),
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                StatusBanner(message: s.text('gradingHelp')),
                const SizedBox(height: AppSpace.lg),
                for (final band in bands)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpace.lg),
                    child: Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: band.minimum,
                            enabled: !busy,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: InputDecoration(
                              labelText: s.text('minimumPercent'),
                            ),
                          ),
                        ),
                        const SizedBox(width: AppSpace.md),
                        Expanded(
                          child: TextField(
                            controller: band.grade,
                            enabled: !busy,
                            decoration: InputDecoration(
                              labelText: s.text('grade'),
                            ),
                          ),
                        ),
                        IconButton(
                          tooltip: s.text('remove'),
                          onPressed: busy
                              ? null
                              : () => setState(() {
                                  bands.remove(band);
                                  band.minimum.dispose();
                                  band.grade.dispose();
                                }),
                          icon: const Icon(Icons.remove_circle_outline),
                        ),
                      ],
                    ),
                  ),
                OutlinedButton.icon(
                  onPressed: busy
                      ? null
                      : () => setState(
                          () => bands.add((
                            minimum: TextEditingController(),
                            grade: TextEditingController(),
                          )),
                        ),
                  icon: const Icon(Icons.add),
                  label: Text(s.text('addBand')),
                ),
                if (error != null)
                  StatusBanner(message: s.text(error!), kind: StatusKind.error),
                const SizedBox(height: AppSpace.lg),
                FilledButton(
                  onPressed: busy ? null : save,
                  child: Text(s.text('save')),
                ),
                const SizedBox(height: AppSpace.xl),
                OutlinedButton.icon(
                  onPressed: busy ? null : calendar,
                  icon: const Icon(Icons.calendar_month_outlined),
                  label: Text(s.text('schoolCalendar')),
                ),
                for (final day in days)
                  ListTile(
                    title: Text(day['date'] as String),
                    subtitle: Text(
                      '${s.text(day['is_school_day'] == true ? 'schoolDay' : 'school_holiday')} · ${day['label'] ?? ''}',
                    ),
                  ),
              ],
            ),
    );
  }
}
