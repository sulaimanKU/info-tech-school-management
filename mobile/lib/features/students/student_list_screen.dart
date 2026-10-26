import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/academic_filters.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import 'student_detail_screen.dart';
import 'student_form.dart';
import 'guardian_directory_screen.dart';

class StudentListScreen extends StatefulWidget {
  const StudentListScreen({
    super.key,
    required this.auth,
    this.selection = false,
  });
  final AuthController auth;
  final bool selection;
  @override
  State<StudentListScreen> createState() => _StudentListScreenState();
}

class _StudentListScreenState extends State<StudentListScreen> {
  final search = TextEditingController();
  Timer? timer;
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  int page = 1, last = 1, generation = 0;
  Map<String, String> filters = {};
  Future<void> filter() async {
    final value = await showFilterSheet(
      context,
      widget.auth,
      FilterSpec.forPath('students', false)!,
      filters,
    );
    if (value != null && mounted) {
      setState(() => filters = value);
      load();
    }
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    timer?.cancel();
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool more = false}) async {
    final version = ++generation;
    if (!more) setState(() => loading = true);
    setState(() => error = null);
    try {
      final data = await widget.auth.call(
        Uri(
          path: 'students',
          queryParameters: {
            ...filters,
            'search': search.text.trim(),
            'page': '${more ? page + 1 : 1}',
          },
        ).toString(),
      );
      if (!mounted || version != generation) return;
      setState(() {
        items = [
          if (more) ...items,
          ...(data['data'] as List? ?? []).map(
            (v) => (v as Map).cast<String, dynamic>(),
          ),
        ];
        page = (data['current_page'] as num? ?? 1).toInt();
        last = (data['last_page'] as num? ?? 1).toInt();
      });
    } on ApiFailure catch (e) {
      if (mounted && version == generation) setState(() => error = e.code);
    } finally {
      if (mounted && version == generation) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.text(widget.auth.isParent ? 'myChildren' : 'students')),
        actions: [
          if (widget.auth.can('guardians.manage'))
            IconButton(
              tooltip: s.text('guardians'),
              icon: const Icon(Icons.family_restroom_outlined),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => GuardianDirectoryScreen(auth: widget.auth),
                ),
              ),
            ),
          if (!widget.auth.isParent)
            IconButton(
              key: const Key('open-filters'),
              tooltip: s.text('filters'),
              onPressed: filter,
              icon: Badge(
                isLabelVisible: filters.isNotEmpty,
                label: Text('${filters.length}'),
                child: const Icon(Icons.filter_alt_outlined),
              ),
            ),
        ],
      ),
      floatingActionButton: widget.auth.can('students.manage')
          ? FloatingActionButton.extended(
              onPressed: () async {
                if (await openStudentForm(context, widget.auth) == true) load();
              },
              icon: const Icon(Icons.person_add_alt),
              label: Text(s.text('admitStudent')),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpace.lg),
            child: TextField(
              controller: search,
              decoration: InputDecoration(
                labelText: s.text('search'),
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (_) {
                timer?.cancel();
                timer = Timer(const Duration(milliseconds: 350), () => load());
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
                      padding: const EdgeInsets.fromLTRB(
                        AppSpace.lg,
                        0,
                        AppSpace.lg,
                        100,
                      ),
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        if (items.isEmpty) const DataState(empty: true),
                        for (final item in items)
                          Padding(
                            padding: const EdgeInsets.only(bottom: AppSpace.md),
                            child: SurfaceCard(
                              padding: EdgeInsets.zero,
                              child: ListTile(
                                contentPadding: const EdgeInsets.all(
                                  AppSpace.lg,
                                ),
                                leading: AuthorizedPhoto(
                                  auth: widget.auth,
                                  fileId: item['photo_file_id'] as String?,
                                  name: item['full_name'] as String?,
                                  size: 48,
                                ),
                                title: Text(item['full_name'] as String),
                                subtitle: Text(
                                  '${item['registration_number']} · ${s.text(item['status'] as String)}',
                                ),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () async {
                                  if (widget.selection) {
                                    Navigator.pop(context, item);
                                    return;
                                  }
                                  await Navigator.push(
                                    context,
                                    MaterialPageRoute<void>(
                                      builder: (_) => StudentDetailScreen(
                                        auth: widget.auth,
                                        id: item['id'] as String,
                                      ),
                                    ),
                                  );
                                  load();
                                },
                              ),
                            ),
                          ),
                        if (page < last)
                          OutlinedButton(
                            onPressed: () => load(more: true),
                            child: Text(s.text('loadMore')),
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
