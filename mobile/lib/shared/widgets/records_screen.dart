import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../features/auth/auth_controller.dart';
import 'data_state.dart';
import 'surface_card.dart';
import 'polished_components.dart';
import 'academic_filters.dart';

class RecordsScreen extends StatefulWidget {
  const RecordsScreen({
    super.key,
    required this.auth,
    required this.path,
    required this.title,
    required this.recordTitle,
    this.recordSubtitle,
    this.onCreate,
    this.onOpen,
    this.summary,
    this.recordLeading,
    this.reloadToken,
    this.emptyBuilder,
  });
  final AuthController auth;
  final String path, title;
  final String Function(Map<String, dynamic>) recordTitle;
  final String Function(Map<String, dynamic>)? recordSubtitle;
  final Future<void> Function(BuildContext)? onCreate;
  final Future<void> Function(BuildContext, Map<String, dynamic>)? onOpen;
  final Widget Function(Map<String, dynamic>)? summary;
  final Widget Function(Map<String, dynamic>)? recordLeading;
  final Object? reloadToken;
  final Widget Function(BuildContext, bool searched)? emptyBuilder;
  @override
  State<RecordsScreen> createState() => _RecordsScreenState();
}

class _RecordsScreenState extends State<RecordsScreen> {
  Map<String, dynamic>? response;
  String? error;
  int page = 1;
  bool loadingMore = false;
  String query = '';
  Map<String, String> filters = {};
  Timer? debounce;
  int generation = 0;
  FilterSpec? get filterSpec =>
      FilterSpec.forPath(widget.path, widget.auth.isParent);
  Future<void> filter() async {
    final value = await showFilterSheet(
      context,
      widget.auth,
      filterSpec!,
      filters,
    );
    if (value != null && mounted) {
      setState(() => filters = value);
      load();
    }
  }

  final search = TextEditingController();
  @override
  void dispose() {
    debounce?.cancel();
    search.dispose();
    super.dispose();
  }

  final accumulated = <dynamic>[];
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void didUpdateWidget(covariant RecordsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.path != oldWidget.path ||
        widget.reloadToken != oldWidget.reloadToken) {
      load();
    }
  }

  Future<void> load({bool more = false}) async {
    if (loadingMore && more) return;
    final version = ++generation;
    if (!more) page = 1;
    if (more && mounted) setState(() => loadingMore = true);
    try {
      final uri = Uri.parse(widget.path);
      final path = uri
          .replace(
            queryParameters: {
              ...uri.queryParameters,
              ...filters,
              if (filterSpec != null && query.isNotEmpty) 'search': query,
              'page': '$page',
            },
          )
          .toString();
      final data = await widget.auth.call(path);
      if (mounted && version == generation) {
        setState(() {
          final incoming = (data['data'] ?? data['items'] ?? []) as List;
          if (!more) accumulated.clear();
          accumulated.addAll(incoming);
          response = {
            ...data,
            if (data.containsKey('data'))
              'data': accumulated
            else
              'items': accumulated,
          };
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (version == generation) {
        if (more) page--;
        if (mounted) setState(() => error = e.code);
      }
    } finally {
      if (mounted && version == generation) setState(() => loadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final allRows = (response?['data'] ?? response?['items'] ?? []) as List;
    final rows = allRows.where((raw) {
      final item = (raw as Map).cast<String, dynamic>();
      return '${widget.recordTitle(item)} ${widget.recordSubtitle?.call(item) ?? ''}'
          .toLowerCase()
          .contains(filterSpec != null ? '' : query.toLowerCase());
    }).toList();
    return Scaffold(
      appBar: AppBar(
        title: Text(s.text(widget.title)),
        actions: [
          if (filterSpec != null)
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
          IconButton(
            tooltip: s.text('refresh'),
            onPressed: load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: widget.onCreate == null
          ? null
          : FloatingActionButton(
              onPressed: () async {
                try {
                  await widget.onCreate!(context);
                  if (mounted) load();
                } on ApiFailure catch (e) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context)
                        .showSnackBar(SnackBar(content: Text(s.text(e.code))));
                  }
                }
              },
              tooltip: s.text(widget.title),
              child: const Icon(Icons.add),
            ),
      body: error != null
          ? DataState(error: error, onRetry: load)
          : response == null
          ? const DataState()
          : RefreshIndicator(
              onRefresh: load,
              child: PageBody(
                child: ListView.builder(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpace.lg,
                    AppSpace.lg,
                    AppSpace.lg,
                    100,
                  ),
                  physics: const AlwaysScrollableScrollPhysics(),
                  itemCount: rows.length + 2,
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if (widget.summary != null) ...[
                            widget.summary!(response!),
                            const SizedBox(height: AppSpace.lg),
                          ],
                          TextField(
                            controller: search,
                            onChanged: (v) {
                              setState(() => query = v);
                              if (filterSpec != null) {
                                debounce?.cancel();
                                debounce = Timer(
                                  const Duration(milliseconds: 350),
                                  () => load(),
                                );
                              }
                            },
                            decoration: InputDecoration(
                              labelText: s.text(
                                filterSpec == null ? 'searchLoaded' : 'search',
                              ),
                              prefixIcon: const Icon(Icons.search_rounded),
                              suffixIcon: query.isEmpty
                                  ? null
                                  : IconButton(
                                      tooltip: s.text('clearSearch'),
                                      icon: const Icon(Icons.close),
                                      onPressed: () {
                                        search.clear();
                                        setState(() => query = '');
                                        if (filterSpec != null) load();
                                      },
                                    ),
                            ),
                          ),
                          const SizedBox(height: AppSpace.lg),
                          if (rows.isEmpty && widget.emptyBuilder != null)
                            widget.emptyBuilder!(
                              context,
                              query.isNotEmpty || filters.isNotEmpty,
                            ),
                          if (rows.isEmpty && widget.emptyBuilder == null)
                            DataState(
                              empty: true,
                              emptyMessage: switch (Uri.parse(widget.path)
                                  .path) {
                                'diary' => 'noHomeworkPosted',
                                'teaching-assignments' => 'noAssignmentRecords',
                                final p
                                    when p.startsWith(
                                      'my-teaching/sections/',
                                    ) =>
                                  'noStudentsSection',
                                _ => null,
                              },
                            ),
                        ],
                      );
                    }
                    if (index <= rows.length) {
                      return record(
                        (rows[index - 1] as Map).cast<String, dynamic>(),
                        s,
                      );
                    }
                    if (response?['current_page'] != null &&
                        page < (response!['last_page'] as int)) {
                      return TextButton.icon(
                        onPressed: loadingMore
                            ? null
                            : () {
                                page++;
                                load(more: true);
                              },
                        icon: const Icon(Icons.expand_more),
                        label: Text(
                          s.text(loadingMore ? 'loadingWorkspace' : 'loadMore'),
                        ),
                      );
                    }
                    return const SizedBox.shrink();
                  },
                ),
              ),
            ),
    );
  }

  Widget record(Map<String, dynamic> item, Strings s) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpace.md),
    child: SurfaceCard(
      padding: EdgeInsets.zero,
      child: ListTile(
        leading: widget.recordLeading?.call(item),
        contentPadding: const EdgeInsets.all(AppSpace.lg),
        title: Text(widget.recordTitle(item)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (widget.recordSubtitle != null)
              Text(widget.recordSubtitle!(item)),
            if (item['status'] != null) ...[
              const SizedBox(height: AppSpace.sm),
              AppBadge(
                label: s.text(item['status'] as String),
                status: item['status'] as String,
              ),
            ],
          ],
        ),
        trailing: widget.onOpen == null
            ? null
            : const Icon(Icons.chevron_right),
        onTap: widget.onOpen == null
            ? null
            : () async {
                try {
                  await widget.onOpen!(context, item);
                  if (mounted) load();
                } on ApiFailure catch (e) {
                  if (mounted) {
                    ScaffoldMessenger.of(context)
                        .showSnackBar(SnackBar(content: Text(s.text(e.code))));
                  }
                }
              },
      ),
    ),
  );
}
