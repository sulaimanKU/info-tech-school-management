import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../shared/widgets/academic_filters.dart';
import '../../core/operation_id.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import '../students/student_form.dart';
import '../files/private_file_service.dart';
import '../offline/draft_queue.dart';

class DiaryEditor extends StatefulWidget {
  const DiaryEditor({
    super.key,
    required this.auth,
    this.existing,
    this.clientId,
    this.draft,
  });
  final AuthController auth;
  final Map<String, dynamic>? existing;
  final String? clientId;
  final Map<String, dynamic>? draft;
  @override
  State<DiaryEditor> createState() => _DiaryEditorState();
}

class _DiaryEditorState extends State<DiaryEditor> {
  final topic = TextEditingController(), homework = TextEditingController();
  final form = GlobalKey<FormState>();
  Map<String, String> sections = {}, subjects = {};
  String? section, subject, error, queueId;
  late String clientId;
  int baseVersion = 0;
  String date = DateTime.now().toIso8601String().substring(0, 10),
      state = 'draft';
  List<String> attachments = [], selected = [];
  List<Map<String, dynamic>> roster = [];
  bool all = true, loading = true, busy = false;
  late final DraftQueue queue;
  @override
  void initState() {
    super.initState();
    queue = DraftQueue(
      widget.auth.user!['id'] as String,
      widget.auth.localStore,
    );
    clientId =
        widget.existing?['client_id'] as String? ??
        widget.draft?['payload']?['client_id'] as String? ??
        widget.clientId ??
        operationId();
    initialize();
  }

  @override
  void dispose() {
    topic.dispose();
    homework.dispose();
    queue.dispose();
    super.dispose();
  }

  Future<void> initialize() async {
    try {
      await queue.load();
      sections = await sectionOptions(widget.auth);
      final old =
          widget.existing ??
          (widget.draft?['payload'] as Map?)?.cast<String, dynamic>();
      queueId = widget.draft?['id'] as String?;
      baseVersion = (old?['version'] as num? ?? 0).toInt();
      state = widget.draft?['state'] as String? ?? 'draft';
      section = old?['section_id'] as String? ?? sections.keys.firstOrNull;
      subject = old?['subject_id'] as String?;
      if (old != null) {
        topic.text = old['topic'] as String;
        homework.text = old['homework'] as String;
        date = old['date'] as String;
        all = old['whole_class'] == true;
        attachments = (old['attachments'] as List)
            .map((f) => f is String ? f : f['id'] as String)
            .toList();
        selected = (old['students'] as List).cast<String>();
      }
      await loadRoster(selectDefault: true);
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> loadRoster({bool selectDefault = false}) async {
    if (section == null) return;
    final choices = await widget.auth.call(
      'academic-filter-options?section_id=$section',
    );
    subjects = {
      for (final row in choices['subjects'] as List)
        row['id'] as String: row['name'] as String,
    };
    if (!subjects.containsKey(subject)) {
      subject = selectDefault ? subjects.keys.firstOrNull : null;
    }
    final data = await widget.auth.call(
      'attendance/register?section_id=$section&date=$date',
    );
    roster = (data['students'] as List)
        .map((v) => (v as Map).cast<String, dynamic>())
        .toList();
  }

  Future<void> save(bool publish, {bool local = false}) async {
    if (!form.currentState!.validate() ||
        busy ||
        section == null ||
        subject == null) {
      return;
    }
    setState(() => busy = true);
    try {
      queueId = await queue.put(
        kind: 'diary',
        path:
            widget.draft?['path'] as String? ??
            (widget.existing == null
                ? 'diary'
                : 'diary/${widget.existing!['id']}'),
        method:
            widget.draft?['method'] as String? ??
            (widget.existing == null ? 'POST' : 'PATCH'),
        pending: !local,
        id: queueId,
        payload: {
          'section_id': section,
          'subject_id': subject,
          'date': date,
          'topic': topic.text.trim(),
          'homework': homework.text.trim(),
          'whole_class': all,
          'students': all ? [] : selected,
          'attachments': attachments,
          'status': publish ? 'published' : 'draft',
          'version': baseVersion,
          'client_id': clientId,
        },
      );
      if (!local) await queue.sync(widget.auth, only: queueId);
      final item = queue.items.firstWhere((v) => v['id'] == queueId);
      state = item['state'] as String;
      error = item['error'] as String?;
      if (state == 'saved' && mounted) {
        Navigator.pop(context, true);
        return;
      }
    } catch (_) {
      error = 'storage';
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> reviewConflict() async {
    final path =
        widget.draft?['path'] as String? ??
        (widget.existing == null ? 'diary' : 'diary/${widget.existing!['id']}');
    if (path == 'diary') return;
    try {
      final latest = await widget.auth.call(path);
      if (!mounted) return;
      final s = Strings.of(context);
      final ok = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: Text(s.text('reviewConflict')),
          content: SingleChildScrollView(
            child: Text(
              '${s.text('keepDraftWarning')}\n\n${latest['topic']}\n${latest['homework']}',
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
          baseVersion = (latest['version'] as num).toInt();
          state = 'draft';
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('teacherDiary'))),
      body: loading
          ? const DataState()
          : SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpace.lg),
              child: SurfaceCard(
                child: Form(
                  key: form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (widget.existing == null)
                        AcademicSelection(
                          auth: widget.auth,
                          subject: true,
                          initial: {
                            'section_id': ?section,
                            'subject_id': ?subject,
                          },
                          onChanged: (v) async {
                            section = v['section_id'];
                            subject = v['subject_id'];
                            selected.clear();
                            try {
                              await loadRoster(selectDefault: true);
                            } on ApiFailure catch (e) {
                              error = e.code;
                            }
                            if (mounted) setState(() {});
                          },
                        ),
                      if (widget.existing != null)
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
                          onChanged: busy || widget.existing != null
                              ? null
                              : (v) async {
                                  section = v;
                                  selected.clear();
                                  try {
                                    await loadRoster(selectDefault: true);
                                  } on ApiFailure catch (e) {
                                    error = e.code;
                                  }
                                  if (mounted) setState(() {});
                                },
                        ),
                      const SizedBox(height: AppSpace.lg),
                      if (widget.existing != null)
                        DropdownButtonFormField<String>(
                          initialValue: subject,
                          isExpanded: true,
                          decoration: InputDecoration(
                            labelText: s.text('subject'),
                          ),
                          items: subjects.entries
                              .map(
                                (e) => DropdownMenuItem(
                                  value: e.key,
                                  child: Text(e.value),
                                ),
                              )
                              .toList(),
                          onChanged: busy || widget.existing != null
                              ? null
                              : (v) => setState(() => subject = v),
                        ),
                      TextButton.icon(
                        onPressed: busy
                            ? null
                            : () async {
                                final value = await showDatePicker(
                                  context: context,
                                  initialDate: DateTime.parse(date),
                                  firstDate: DateTime(2020),
                                  lastDate: DateTime(2100),
                                );
                                if (value != null) {
                                  date = value.toIso8601String().substring(
                                    0,
                                    10,
                                  );
                                  try {
                                    await loadRoster(selectDefault: true);
                                  } on ApiFailure catch (e) {
                                    error = e.code;
                                  }
                                  if (mounted) setState(() {});
                                }
                              },
                        icon: const Icon(Icons.calendar_today),
                        label: Text(date),
                      ),
                      TextFormField(
                        controller: topic,
                        enabled: !busy,
                        maxLength: 300,
                        decoration: InputDecoration(labelText: s.text('topic')),
                        validator: (v) => v == null || v.trim().isEmpty
                            ? s.text('fieldRequired')
                            : null,
                      ),
                      const SizedBox(height: AppSpace.lg),
                      TextFormField(
                        controller: homework,
                        enabled: !busy,
                        minLines: 4,
                        maxLines: 10,
                        maxLength: 10000,
                        decoration: InputDecoration(
                          labelText: s.text('homework'),
                        ),
                        validator: (v) => v == null || v.trim().isEmpty
                            ? s.text('fieldRequired')
                            : null,
                      ),
                      SwitchListTile(
                        title: Text(s.text('wholeClass')),
                        value: all,
                        onChanged: busy ? null : (v) => setState(() => all = v),
                      ),
                      if (!all)
                        for (final student in roster)
                          CheckboxListTile(
                            title: Text(student['full_name'] as String),
                            value: selected.contains(student['id']),
                            onChanged: busy
                                ? null
                                : (v) => setState(() {
                                    v == true
                                        ? selected.add(student['id'] as String)
                                        : selected.remove(student['id']);
                                  }),
                          ),
                      OutlinedButton.icon(
                        onPressed: busy || section == null
                            ? null
                            : () async {
                                setState(() => busy = true);
                                try {
                                  final files = await const PrivateFileService()
                                      .pickAndUpload(
                                        widget.auth,
                                        purpose: 'diary',
                                        sectionId: section,
                                      );
                                  attachments.addAll(
                                    files.map((f) => f['id'] as String),
                                  );
                                } on ApiFailure catch (e) {
                                  error = e.code;
                                } catch (_) {
                                  error = 'invalidFile';
                                } finally {
                                  if (mounted) setState(() => busy = false);
                                }
                              },
                        icon: const Icon(Icons.attach_file),
                        label: Text(
                          '${s.text('attachments')} (${attachments.length})',
                        ),
                      ),
                      const SizedBox(height: AppSpace.lg),
                      AppBadge(label: s.text(state), status: state),
                      if (state == 'syncConflict')
                        OutlinedButton(
                          onPressed: reviewConflict,
                          child: Text(s.text('reviewConflict')),
                        ),
                      if (error != null)
                        StatusBanner(
                          message: s.text(error!),
                          kind: StatusKind.error,
                        ),
                      const SizedBox(height: AppSpace.xl),
                      Wrap(
                        spacing: AppSpace.sm,
                        runSpacing: AppSpace.sm,
                        children: [
                          OutlinedButton(
                            onPressed: busy
                                ? null
                                : () => save(false, local: true),
                            child: Text(s.text('saveLocalDraft')),
                          ),
                          OutlinedButton(
                            onPressed: busy ? null : () => save(false),
                            child: Text(s.text('saveDraft')),
                          ),
                          FilledButton(
                            onPressed: busy ? null : () => save(true),
                            child: Text(s.text('publish')),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
    );
  }
}
