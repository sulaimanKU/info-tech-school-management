import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
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
  int reloadToken = 0;
  AuthController get auth => widget.auth;

  Future<Map<String, dynamic>?> chooseTeacher(BuildContext context) =>
      Navigator.push<Map<String, dynamic>>(
        context,
        MaterialPageRoute(builder: (_) => TeacherPickerScreen(auth: auth)),
      );

  Future<void> create(BuildContext context) async {
    final teacher = teacherFilter ?? await chooseTeacher(context);
    if (teacher == null || !context.mounted) return;
    setState(() => teacherFilter = teacher);
    await Navigator.push(
      context,
      MaterialPageRoute<bool>(
        builder: (_) => TeacherAssignmentBatchScreen(
          auth: auth,
          teacher: teacher,
          onSaved: (updated) {
            if (mounted) setState(() => teacherFilter = updated);
          },
        ),
      ),
    );
    if (mounted) setState(() => reloadToken++);
  }

  @override
  Widget build(BuildContext context) {
    if (!(auth.can('school.manage') || auth.can('assignments.manage'))) {
      return const Scaffold(body: DataState(error: 'forbidden'));
    }
    return RecordsScreen(
      reloadToken: reloadToken,
      auth: auth,
      path: Uri(
        path: 'teaching-assignments',
        queryParameters: {
          if (teacherFilter?['user_id'] != null)
            'teacher_user_id': teacherFilter!['user_id'] as String
          else if (teacherFilter?['id'] != null)
            'staff_profile_id': teacherFilter!['id'] as String,
        },
      ).toString(),
      title: 'teachingAssignments',
      recordTitle: (r) => [
        r['employee_number'] ?? r['account_code'],
        r['teacher_name'],
      ].whereType<String>().join(' | '),
      recordSubtitle: (r) =>
          '${r['session_name']} | ${r['class_name']} ${r['section_name']} | ${r['subject_name'] ?? Strings.of(context).text('classTeacher')}\n${Strings.of(context).text(r['is_active'] == true ? 'active' : 'inactive')}',
      onCreate: create,
      emptyBuilder: (c, searched) => DataState(
        empty: true,
        emptyMessage: searched
            ? 'noAssignmentRecords'
            : 'noTeachingAssignmentsYet',
        emptyIcon: Icons.assignment_outlined,
      ),
      summary: (_) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: AppSpace.sm,
            runSpacing: AppSpace.sm,
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
                  Strings.of(context).text(
                    teacherFilter == null ? 'chooseTeacher' : 'changeTeacher',
                  ),
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
          if (teacherFilter != null) ...[
            const SizedBox(height: AppSpace.md),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const CircleAvatar(child: Icon(Icons.person_outline)),
              title: Text(teacherFilter!['name'] as String),
              subtitle: Text(
                [
                  teacherFilter!['employee_number'],
                  teacherFilter!['account_code'],
                ].whereType<String>().join(' · '),
              ),
            ),
            OutlinedButton.icon(
              key: const Key('add-teacher-assignment'),
              onPressed: () async {
                await create(context);
              },
              icon: const Icon(Icons.add),
              label: Text(Strings.of(context).text('addAnotherAssignment')),
            ),
          ],
          const SizedBox(height: AppSpace.lg),
          Text(
            Strings.of(context).text('existingAssignments'),
            style: Theme.of(context).textTheme.titleMedium,
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

class TeacherPickerScreen extends StatefulWidget {
  const TeacherPickerScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<TeacherPickerScreen> createState() => _TeacherPickerScreenState();
}

class _TeacherPickerScreenState extends State<TeacherPickerScreen> {
  final search = TextEditingController();
  final teachers = <Map<String, dynamic>>[];
  Timer? debounce;
  String query = '';
  String? error;
  bool loading = true, loadingMore = false;
  int page = 1, lastPage = 1, generation = 0;
  bool get allowed =>
      widget.auth.can('school.manage') || widget.auth.can('assignments.manage');

  @override
  void initState() {
    super.initState();
    if (allowed) load();
  }

  @override
  void dispose() {
    debounce?.cancel();
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool more = false}) async {
    if (more && loadingMore) return;
    final request = ++generation;
    final requestedPage = more ? page + 1 : 1;
    setState(() {
      loading = !more;
      loadingMore = more;
      error = null;
    });
    try {
      final data = await widget.auth.call(
        Uri(
          path: 'teaching-assignments/teachers',
          queryParameters: {
            if (query.isNotEmpty) 'search': query,
            'page': '$requestedPage',
          },
        ).toString(),
      );
      if (!mounted || request != generation) return;
      setState(() {
        if (!more) teachers.clear();
        teachers.addAll(
          (data['data'] as List).map((v) => (v as Map).cast<String, dynamic>()),
        );
        page = requestedPage;
        lastPage = data['last_page'] as int? ?? 1;
      });
    } on ApiFailure catch (e) {
      if (mounted && request == generation) setState(() => error = e.code);
    } finally {
      if (mounted && request == generation) {
        setState(() {
          loading = false;
          loadingMore = false;
        });
      }
    }
  }

  Widget empty(BuildContext context) {
    final s = Strings.of(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpace.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.person_search_outlined, size: 48),
            const SizedBox(height: AppSpace.lg),
            Text(
              s.text(
                query.isEmpty
                    ? 'noTeachersAvailable'
                    : 'noTeacherSearchResults',
              ),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            if (query.isEmpty) ...[
              const SizedBox(height: AppSpace.md),
              Text(
                s.text('noTeachersAvailableHelp'),
                textAlign: TextAlign.center,
              ),
            ],
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (!allowed) return const Scaffold(body: DataState(error: 'forbidden'));
    return Scaffold(
      appBar: AppBar(
        title: Text(s.text('chooseTeacher')),
        actions: [
          IconButton(
            tooltip: s.text('refresh'),
            onPressed: load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpace.lg),
            child: TextField(
              key: const Key('teacher-search'),
              controller: search,
              decoration: InputDecoration(
                labelText: s.text('search'),
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (value) {
                query = value.trim();
                debounce?.cancel();
                debounce = Timer(const Duration(milliseconds: 350), load);
              },
            ),
          ),
          Expanded(
            child: loading
                ? const DataState()
                : error != null
                ? DataState(error: error, onRetry: load)
                : teachers.isEmpty
                ? empty(context)
                : RefreshIndicator(
                    onRefresh: load,
                    child: ListView.builder(
                      padding: const EdgeInsets.all(AppSpace.lg),
                      itemCount: teachers.length + (page < lastPage ? 1 : 0),
                      itemBuilder: (c, index) {
                        if (index == teachers.length) {
                          return TextButton.icon(
                            onPressed: loadingMore
                                ? null
                                : () => load(more: true),
                            icon: const Icon(Icons.expand_more),
                            label: Text(s.text('loadMore')),
                          );
                        }
                        final teacher = teachers[index];
                        return Padding(
                          padding: const EdgeInsets.only(bottom: AppSpace.md),
                          child: SurfaceCard(
                            padding: EdgeInsets.zero,
                            child: ListTile(
                              leading: const CircleAvatar(
                                child: Icon(Icons.person_outline),
                              ),
                              title: Text(
                                [
                                  teacher['employee_number'],
                                  teacher['name'],
                                ].whereType<String>().join(' | '),
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (teacher['account_code'] != null)
                                    Text(teacher['account_code'] as String),
                                  Wrap(
                                    spacing: AppSpace.sm,
                                    runSpacing: AppSpace.xs,
                                    children: [
                                      AppBadge(
                                        label: s.text('teacher'),
                                        icon: Icons.school_outlined,
                                      ),
                                      AppBadge(
                                        label: s.text('active'),
                                        status: 'active',
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                              trailing: const Icon(Icons.chevron_right),
                              onTap: () => Navigator.pop(context, teacher),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
