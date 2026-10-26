import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import 'draft_queue.dart';
import '../academics/attendance_screen.dart';
import '../academics/diary_editor.dart';

class DraftQueueScreen extends StatefulWidget {
  const DraftQueueScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<DraftQueueScreen> createState() => _DraftQueueScreenState();
}

class _DraftQueueScreenState extends State<DraftQueueScreen> {
  late final DraftQueue queue;
  bool loading = true;
  @override
  void initState() {
    super.initState();
    queue = DraftQueue(
      widget.auth.user!['id'] as String,
      widget.auth.localStore,
    );
    queue.load().then((_) {
      if (mounted) setState(() => loading = false);
    });
  }

  @override
  void dispose() {
    queue.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return AnimatedBuilder(
      animation: queue,
      builder: (context, _) => Scaffold(
        appBar: AppBar(
          title: Text(s.text('offlineDrafts')),
          actions: [
            IconButton(
              tooltip: s.text('syncNow'),
              onPressed: queue.syncing ? null : () => queue.sync(widget.auth),
              icon: const Icon(Icons.sync),
            ),
          ],
        ),
        body: loading
            ? const DataState()
            : ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  StatusBanner(message: s.text('draftSafety')),
                  const SizedBox(height: AppSpace.lg),
                  if (queue.items.isEmpty) const DataState(empty: true),
                  for (final item in queue.items)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpace.md),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${s.text(item['kind'] as String)} · ${(item['payload'] as Map)['date'] ?? ''}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: AppSpace.sm),
                            AppBadge(
                              label: s.text(item['state'] as String),
                              status: item['state'] as String,
                            ),
                            if (item['error'] != null)
                              Text(s.text(item['error'] as String)),
                            if (item['state'] == 'syncConflict')
                              Text(s.text('conflictHelp')),
                            Wrap(
                              children: [
                                if (item['state'] != 'saved')
                                  TextButton(
                                    onPressed: queue.syncing
                                        ? null
                                        : () async {
                                            final payload =
                                                (item['payload'] as Map)
                                                    .cast<String, dynamic>();
                                            await Navigator.push(
                                              context,
                                              MaterialPageRoute<void>(
                                                builder: (_) =>
                                                    item['kind'] == 'diary'
                                                    ? DiaryEditor(
                                                        auth: widget.auth,
                                                        draft: item,
                                                      )
                                                    : AttendanceScreen(
                                                        auth: widget.auth,
                                                        initialSection:
                                                            payload['section_id']
                                                                as String,
                                                        initialDate:
                                                            payload['date']
                                                                as String,
                                                      ),
                                              ),
                                            );
                                            await queue.load();
                                          },
                                    child: Text(s.text('resumeDraft')),
                                  ),
                                if ([
                                  'retry',
                                  'pendingSync',
                                ].contains(item['state']))
                                  TextButton(
                                    onPressed: queue.syncing
                                        ? null
                                        : () => queue.sync(
                                            widget.auth,
                                            only: item['id'] as String,
                                          ),
                                    child: Text(s.text('retry')),
                                  ),
                                TextButton(
                                  onPressed: queue.syncing
                                      ? null
                                      : () async {
                                          final ok = await showDialog<bool>(
                                            context: context,
                                            builder: (c) => AlertDialog(
                                              title: Text(
                                                s.text('discardDraft'),
                                              ),
                                              content: Text(
                                                s.text('discardDraftConfirm'),
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
                                            queue.discard(item['id'] as String);
                                          }
                                        },
                                  child: Text(s.text('discard')),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
      ),
    );
  }
}
