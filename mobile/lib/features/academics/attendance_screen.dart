import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/polished_components.dart';
import '../../shared/widgets/academic_filters.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_form.dart';
import '../offline/draft_queue.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({
    super.key,
    required this.auth,
    this.initialSection,
    this.initialDate,
  });
  final AuthController auth;
  final String? initialSection, initialDate;
  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  Map<String, String> sections = {};
  String? section, error, draftId;
  String date = DateTime.now().toIso8601String().substring(0, 10);
  List<Map<String, dynamic>> students = [];
  final statuses = <String, String>{};
  int version = 0;
  bool loading = true, busy = false, cached = false, schoolDay = true;
  String registerStatus = 'draft', syncState = 'draft';
  Map<String, String> filters = {};
  String rosterSearch = '', rosterStatus = '';
  Future<void> filter() async {
    final value = await showFilterSheet(
      context,
      widget.auth,
      const FilterSpec(academic: true, dates: false),
      filters,
    );
    if (value == null || !mounted) return;
    try {
      final d = await widget.auth.call(
        Uri(path: 'academic-filter-options', queryParameters: value).toString(),
      );
      if (!mounted) return;
      setState(() {
        filters = value;
        sections = {
          for (final v in d['sections'] as List)
            v['id'] as String: v['name'] as String,
        };
        section = value['section_id'] ?? sections.keys.firstOrNull;
        students = [];
        statuses.clear();
        draftId = null;
      });
      if (section != null) await load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  late final DraftQueue queue;
  late final ReadCache cache;
  @override
  void initState() {
    super.initState();
    final owner = widget.auth.user!['id'] as String;
    queue = DraftQueue(owner, widget.auth.localStore);
    cache = ReadCache(widget.auth.localStore, owner);
    date = widget.initialDate ?? date;
    initialize();
  }

  @override
  void dispose() {
    queue.dispose();
    super.dispose();
  }

  Future<void> initialize() async {
    try {
      await queue.load();
      sections = await sectionOptions(widget.auth);
      section = widget.initialSection ?? sections.keys.firstOrNull;
      if (section != null) await load();
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> load() async {
    if (section == null) return;
    setState(() {
      loading = true;
      error = null;
      cached = false;
    });
    Map<String, dynamic>? data;
    final key = 'attendance:$section:$date';
    try {
      data = await widget.auth.call(
        'attendance/register?section_id=$section&date=$date',
      );
      await cache.write(key, data);
    } on ApiFailure catch (e) {
      if (e.code == 'network') {
        data = await cache.read(key);
        cached = data != null;
      }
      if (data == null) error = e.code;
    }
    if (!mounted) return;
    if (data != null) {
      students = (data['students'] as List)
          .map((v) => (v as Map).cast<String, dynamic>())
          .toList();
      statuses.clear();
      schoolDay = data['is_school_day'] != false;
      for (final row in data['records'] as List) {
        statuses[row['student_id'] as String] = row['status'] as String;
      }
      version = (data['register']?['version'] as num? ?? 0).toInt();
      registerStatus = data['register']?['status'] as String? ?? 'draft';
      if (!schoolDay && (data['records'] as List).isEmpty) {
        for (final student in students) {
          statuses[student['id'] as String] = 'school_holiday';
        }
      }
      final local = queue.items
          .where(
            (v) =>
                v['kind'] == 'attendance' &&
                (v['payload'] as Map)['section_id'] == section &&
                (v['payload'] as Map)['date'] == date &&
                v['state'] != 'saved',
          )
          .lastOrNull;
      draftId = local?['id'] as String?;
      syncState = local?['state'] as String? ?? 'draft';
      if (local != null) {
        for (final row in (local['payload'] as Map)['records'] as List) {
          statuses[row['student_id'] as String] = row['status'] as String;
        }
        version = ((local['payload'] as Map)['version'] as num).toInt();
      }
    }
    setState(() => loading = false);
  }

  Future<void> save(bool submit, {bool localOnly = false}) async {
    if (busy || section == null) return;
    if (submit && students.any((s) => !statuses.containsKey(s['id']))) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(Strings.of(context).text('markAllStudents'))),
      );
      return;
    }
    if (submit &&
        !await confirmAction(
          context,
          title: Strings.of(context).text('confirmAttendance'),
          message: Strings.of(context).text('confirmAttendanceBody'),
          action: Strings.of(context).text('submit'),
        )) {
      return;
    }
    if (!mounted) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      draftId = await queue.put(
        kind: 'attendance',
        path: 'attendance/register',
        pending: !localOnly,
        id: draftId,
        payload: {
          'section_id': section,
          'date': date,
          'status': submit ? 'submitted' : 'draft',
          'version': version,
          'records': [
            for (final s in students)
              if (statuses[s['id']] != null)
                {'student_id': s['id'], 'status': statuses[s['id']]},
          ],
        },
      );
      if (!localOnly) await queue.sync(widget.auth, only: draftId);
      final item = queue.items.firstWhere((v) => v['id'] == draftId);
      syncState = item['state'] as String;
      error = item['error'] as String?;
      if (syncState == 'saved') {
        version = (item['result']['version'] as num).toInt();
        registerStatus = submit ? 'submitted' : 'draft';
        draftId = null;
      }
    } catch (_) {
      error = 'storage';
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> reviewConflict() async {
    try {
      final latest = await widget.auth.call(
        'attendance/register?section_id=$section&date=$date',
      );
      if (!mounted) return;
      final s = Strings.of(context);
      final ok = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: Text(s.text('reviewConflict')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(s.text('keepDraftWarning')),
                for (final row in latest['records'] as List)
                  Text(
                    '${students.where((s) => s['id'] == row['student_id']).firstOrNull?['full_name'] ?? ''}: ${s.text(row['status'] as String)}',
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: Text(s.text('cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(c, true),
              child: Text(s.text('keepMyDraft')),
            ),
          ],
        ),
      );
      if (ok == true && mounted) {
        setState(() {
          version = (latest['register']?['version'] as num? ?? 0).toInt();
          error = null;
          syncState = 'draft';
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final editable =
        widget.auth.can('academics.write') &&
        (registerStatus != 'submitted' ||
            widget.auth.can('attendance.correct'));
    return Scaffold(
      appBar: AppBar(
        title: Text(s.text('attendance')),
        actions: [
          IconButton(
            key: const Key('open-filters'),
            tooltip: s.text('filters'),
            onPressed: busy ? null : filter,
            icon: Badge(
              isLabelVisible: filters.isNotEmpty,
              child: const Icon(Icons.filter_alt_outlined),
            ),
          ),
        ],
      ),
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(AppSpace.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: section,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: s.text('classSection'),
                    ),
                    items: sections.entries
                        .map(
                          (e) => DropdownMenuItem(
                            value: e.key,
                            child: Text(
                              e.value,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: busy
                        ? null
                        : (v) {
                            section = v;
                            draftId = null;
                            load();
                          },
                  ),
                  const SizedBox(height: AppSpace.sm),
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      TextButton.icon(
                        onPressed: busy
                            ? null
                            : () async {
                                final value = await showDatePicker(
                                  context: context,
                                  initialDate: DateTime.parse(date),
                                  firstDate: DateTime(2020),
                                  lastDate: DateTime.now(),
                                );
                                if (value != null) {
                                  date = value.toIso8601String().substring(
                                    0,
                                    10,
                                  );
                                  draftId = null;
                                  load();
                                }
                              },
                        icon: const Icon(Icons.calendar_today_outlined),
                        label: Text(date),
                      ),
                      AppBadge(label: s.text(syncState), status: syncState),
                    ],
                  ),
                  if (syncState == 'syncConflict')
                    OutlinedButton(
                      onPressed: reviewConflict,
                      child: Text(s.text('reviewConflict')),
                    ),
                  if (cached) StatusBanner(message: s.text('offlineCopy')),
                  if (error != null)
                    StatusBanner(
                      message: s.text(error!),
                      kind: StatusKind.error,
                    ),
                  if (!loading)
                    Text(
                      '${s.text('students')}: ${students.length} | ${s.text('present')}: ${statuses.values.where((v) => v == 'present').length} | ${s.text('absent')}: ${statuses.values.where((v) => v == 'absent').length} | ${s.text('excused')}: ${statuses.values.where((v) => v == 'excused').length} | ${s.text('late')}: ${statuses.values.where((v) => v == 'late').length}',
                    ),
                  if (!loading && !schoolDay)
                    StatusBanner(message: s.text('school_holiday')),
                  if (!loading && students.isNotEmpty) ...[
                    TextField(
                      decoration: InputDecoration(
                        labelText: s.text('search'),
                        prefixIcon: const Icon(Icons.search),
                      ),
                      onChanged: (v) =>
                          setState(() => rosterSearch = v.toLowerCase()),
                    ),
                    const SizedBox(height: AppSpace.sm),
                    ScopedChoice(
                      label: 'status',
                      value: rosterStatus.isEmpty ? null : rosterStatus,
                      options: {
                        for (final k in [
                          'present',
                          'absent',
                          'late',
                          'excused',
                          'school_holiday',
                        ])
                          k: s.text(k),
                      },
                      onChanged: (v) => setState(() => rosterStatus = v ?? ''),
                    ),
                  ],
                  if (!loading && editable)
                    TextButton.icon(
                      onPressed: busy
                          ? null
                          : () => setState(() {
                              for (final student in students) {
                                statuses[student['id'] as String] = schoolDay
                                    ? 'present'
                                    : 'school_holiday';
                              }
                            }),
                      icon: const Icon(Icons.done_all),
                      label: Text(
                        s.text(
                          schoolDay ? 'markAllPresent' : 'markSchoolHoliday',
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          if (loading)
            const SliverToBoxAdapter(child: DataState())
          else if (section == null || students.isEmpty)
            const SliverToBoxAdapter(child: DataState(empty: true))
          else
            SliverList.builder(
              itemCount: students.length,
              itemBuilder: (context, i) {
                final student = students[i];
                if (!'${student['full_name']} ${student['registration_number']}'
                        .toLowerCase()
                        .contains(rosterSearch) ||
                    (rosterStatus.isNotEmpty &&
                        statuses[student['id']] != rosterStatus)) {
                  return const SizedBox.shrink();
                }
                return Padding(
                  padding: const EdgeInsetsDirectional.fromSTEB(
                    AppSpace.lg,
                    0,
                    AppSpace.lg,
                    AppSpace.md,
                  ),
                  child: SurfaceCard(
                    padding: const EdgeInsets.all(AppSpace.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          '${student['roll_number']}. ${student['full_name']}',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        Text(
                          student['registration_number'] as String,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        const SizedBox(height: AppSpace.md),
                        DropdownButtonFormField<String>(
                          key: ValueKey(
                            '${student['id']}-${statuses[student['id']]}',
                          ),
                          initialValue: statuses[student['id']],
                          isExpanded: true,
                          hint: Text(s.text('notMarked')),
                          items:
                              [
                                    'present',
                                    'absent',
                                    'late',
                                    'excused',
                                    'school_holiday',
                                  ]
                                  .map(
                                    (v) => DropdownMenuItem(
                                      value: v,
                                      child: Text(
                                        s.text(v),
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  )
                                  .toList(),
                          onChanged: busy || !editable
                              ? null
                              : (v) => setState(
                                  () => statuses[student['id'] as String] = v!,
                                ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          if (editable && !loading && students.isNotEmpty)
            SliverToBoxAdapter(
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.all(AppSpace.lg),
                  child: Wrap(
                    spacing: AppSpace.sm,
                    runSpacing: AppSpace.sm,
                    children: [
                      OutlinedButton(
                        onPressed: busy
                            ? null
                            : () => save(false, localOnly: true),
                        child: Text(s.text('saveLocalDraft')),
                      ),
                      OutlinedButton(
                        onPressed: busy ? null : () => save(false),
                        child: Text(s.text('saveDraft')),
                      ),
                      FilledButton(
                        onPressed: busy ? null : () => save(true),
                        child: Text(s.text('submit')),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
