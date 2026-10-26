import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';

/// Read-only state belongs to one authorized child/path, never a global family cache.
class ParentResource extends StatefulWidget {
  const ParentResource({
    super.key,
    required this.auth,
    required this.path,
    required this.builder,
  });
  final AuthController auth;
  final String path;
  final Widget Function(BuildContext, Map<String, dynamic>) builder;
  @override
  State<ParentResource> createState() => _ParentResourceState();
}

class _ParentResourceState extends State<ParentResource> {
  Map<String, dynamic>? data;
  String? error;
  int generation = 0;
  bool loading = false;
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void didUpdateWidget(covariant ParentResource old) {
    super.didUpdateWidget(old);
    if (old.path != widget.path || old.auth != widget.auth) {
      data = null;
      error = null;
      load();
    }
  }

  Future<void> load() async {
    final version = ++generation;
    setState(() => loading = true);
    try {
      final value = await widget.auth.call(widget.path);
      if (mounted && version == generation) {
        setState(() {
          data = value;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted && version == generation) {
        setState(() {
          error = e.code;
          if (e.code != 'network') data = null;
        });
      }
    } finally {
      if (mounted && version == generation) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (data == null) return DataState(error: error, onRetry: load);
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(AppSpace.lg),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          if (loading) const LinearProgressIndicator(),
          if (error != null) ...[
            StatusBanner(
              message: Strings.of(context).text(error!),
              kind: StatusKind.error,
            ),
            TextButton.icon(
              onPressed: load,
              icon: const Icon(Icons.refresh),
              label: Text(Strings.of(context).text('retry')),
            ),
          ],
          widget.builder(context, data!),
        ],
      ),
    );
  }
}
