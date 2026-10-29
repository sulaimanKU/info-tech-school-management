import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/operation_id.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/academic_filters.dart';
import '../auth/auth_controller.dart';
import 'diary_editor.dart';
import '../files/private_file_screen.dart';

class DiaryScreen extends StatefulWidget {
  const DiaryScreen({
    super.key,
    required this.auth,
    this.studentId,
    this.initialSection,
  });
  final AuthController auth;
  final String? studentId, initialSection;
  @override
  State<DiaryScreen> createState() => _DiaryScreenState();
}

class _DiaryScreenState extends State<DiaryScreen> {
  List<Map<String, dynamic>>? items;
  String? error;
  DateTime? selectedDate;
  String? selectedSubject;
  int page = 1, lastPage = 1;
  Map<String, String> filters = {};
  Future<void> advancedFilters() async {
    final v = await showFilterSheet(
      context,
      widget.auth,
      FilterSpec.forPath('diary', widget.auth.isParent)!,
      filters,
    );
    if (v != null && mounted) {
      setState(() => filters = v);
      load();
    }
  }

  Future<void> subjectFilter() async {
    try {
      final data = await widget.auth.call(
        widget.auth.isParent && widget.studentId != null
            ? 'parent/children/${widget.studentId}/overview'
            : 'catalog/subjects',
      );
      if (!mounted) return;
      selectedSubject = await showDialog<String>(
        context: context,
        builder: (c) => SimpleDialog(
          title: Text(Strings.of(c).text('subject')),
          children: [
            for (final v in (data['subjects'] ?? data['items']) as List)
              SimpleDialogOption(
                onPressed: () => Navigator.pop(c, v['id'] as String),
                child: Text(v['name'] as String),
              ),
          ],
        ),
      );
      await load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  void initState() {
    super.initState();
    if (widget.initialSection != null) {
      filters['section_id'] = widget.initialSection!;
    }
    load();
  }

  Future<void> load({bool more = false}) async {
    if (!more) page = 1;
    try {
      final data = await widget.auth.call(
        Uri(
          path: 'diary',
          queryParameters: {
            ...filters,
            'page': '$page',
            if (widget.studentId != null) 'student_id': widget.studentId!,
            if (selectedDate != null)
              'date': selectedDate!.toIso8601String().substring(0, 10),
            'subject_id': ?selectedSubject,
          },
        ).toString(),
      );
      if (mounted) {
        setState(() {
          lastPage = data['last_page'] as int? ?? 1;
          final incoming = (data['data'] as List)
              .map((v) => (v as Map).cast<String, dynamic>())
              .toList();
          items = more ? [...?items, ...incoming] : incoming;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> details(Map<String, dynamic> item) async {
    try {
      final data = await widget.auth.call(
        Uri(
          path: 'diary/${item['id']}',
          queryParameters: {
            if (widget.studentId != null) 'student_id': widget.studentId!,
          },
        ).toString(),
      );
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        builder: (c) {
          final s = Strings.of(c);
          return SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpace.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  data['topic'] as String,
                  style: Theme.of(c).textTheme.titleLarge,
                ),
                const SizedBox(height: AppSpace.lg),
                SelectableText(data['homework'] as String),
                const SizedBox(height: AppSpace.lg),
                for (final f in data['attachments'] as List)
                  ListTile(
                    leading: Icon(
                      (f['mime'] as String).startsWith('image/')
                          ? Icons.image_outlined
                          : Icons.picture_as_pdf_outlined,
                    ),
                    title: Text(s.text('viewAttachment')),
                    onTap: () => Navigator.push(
                      c,
                      MaterialPageRoute<void>(
                        builder: (_) => PrivateFileScreen(
                          auth: widget.auth,
                          id: f['id'] as String,
                          mime: f['mime'] as String,
                        ),
                      ),
                    ),
                  ),
                if (!widget.auth.isParent &&
                    (data['teacher_user_id'] == widget.auth.user!['id'] ||
                        widget.auth.can('academics.review')))
                  OutlinedButton(
                    onPressed: () {
                      Navigator.pop(c);
                      Navigator.push(
                        context,
                        MaterialPageRoute<bool>(
                          builder: (_) =>
                              DiaryEditor(auth: widget.auth, existing: data),
                        ),
                      ).then((_) => load());
                    },
                    child: Text(s.text('editDiary')),
                  ),
                if (widget.auth.can('academics.review'))
                  FilledButton(
                    onPressed: () async {
                      try {
                        await widget.auth.call(
                          'diary/${item['id']}/review',
                          body: {},
                        );
                        if (c.mounted) Navigator.pop(c);
                      } on ApiFailure catch (e) {
                        if (c.mounted) {
                          ScaffoldMessenger.of(c).showSnackBar(
                            SnackBar(content: Text(s.text(e.code))),
                          );
                        }
                      }
                    },
                    child: Text(s.text('reviewed')),
                  ),
              ],
            ),
          );
        },
      );
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.text('diary')),
        actions: [
          IconButton(
            key: const Key('open-filters'),
            tooltip: s.text('filters'),
            onPressed: advancedFilters,
            icon: Badge(
              isLabelVisible: filters.isNotEmpty,
              child: const Icon(Icons.filter_alt_outlined),
            ),
          ),
          IconButton(
            tooltip: s.text('subject'),
            onPressed: subjectFilter,
            icon: const Icon(Icons.filter_alt_outlined),
          ),
          IconButton(
            tooltip: s.text('filterDate'),
            onPressed: () async {
              selectedDate = await showDatePicker(
                context: context,
                initialDate: DateTime.now(),
                firstDate: DateTime(2020),
                lastDate: DateTime(2100),
              );
              load();
            },
            icon: const Icon(Icons.date_range),
          ),
          if (selectedDate != null || selectedSubject != null)
            IconButton(
              tooltip: s.text('clearFilter'),
              onPressed: () {
                selectedDate = null;
                selectedSubject = null;
                load();
              },
              icon: const Icon(Icons.filter_alt_off_outlined),
            ),
        ],
      ),
      floatingActionButton: widget.auth.can('academics.write')
          ? FloatingActionButton(
              onPressed: () async {
                await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        DiaryEditor(auth: widget.auth, clientId: operationId()),
                  ),
                );
                load();
              },
              child: const Icon(Icons.add),
            )
          : null,
      body: error != null
          ? DataState(error: error, onRetry: load)
          : items == null
          ? const DataState()
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  if (items!.isEmpty) const DataState(empty: true),
                  for (final item in items!)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpace.lg),
                      child: SurfaceCard(
                        padding: EdgeInsets.zero,
                        child: ListTile(
                          contentPadding: const EdgeInsets.all(AppSpace.lg),
                          title: Text(item['topic'] as String),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${item['subject_name']} · ${item['date']}'),
                              Text(item['teacher_name'] as String),
                              const SizedBox(height: AppSpace.sm),
                              AppBadge(
                                label: s.text(item['status'] as String),
                                status: item['status'] as String,
                              ),
                            ],
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => details(item),
                        ),
                      ),
                    ),
                  if (page < lastPage)
                    TextButton(
                      onPressed: () {
                        page++;
                        load(more: true);
                      },
                      child: Text(s.text('loadMore')),
                    ),
                ],
              ),
            ),
    );
  }
}
