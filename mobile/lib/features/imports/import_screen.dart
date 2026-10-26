import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/import_review.dart';
import '../auth/auth_controller.dart';

class ImportScreen extends StatelessWidget {
  const ImportScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> upload(BuildContext context) async {
    final s = Strings.of(context);
    final info = await auth.call('imports');
    if (!context.mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'importData',
          fields: [
            FormFieldSpec(
              'kind',
              'importKind',
              kind: FieldKind.choice,
              options: {
                for (final k in (info['kinds'] as Map).keys)
                  k.toString(): s.text('import_$k'),
              },
            ),
          ],
          onSubmit: (v) async {
            final file = await openFile(
              acceptedTypeGroups: [
                const XTypeGroup(
                  label: 'CSV / Excel',
                  extensions: ['csv', 'xlsx', 'xls'],
                ),
              ],
            );
            if (file == null) return;
            if (await file.length() > 5 * 1024 * 1024) {
              throw const ApiFailure('fileSize');
            }
            final result = await auth.upload('imports', file.path, {
              'kind': v['kind'] as String,
            });
            if (context.mounted) {
              await Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => ImportDetailScreen(
                    auth: auth,
                    id: result['id'] as String,
                  ),
                ),
              );
            }
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'imports',
    title: 'importData',
    recordTitle: (v) => Strings.of(context).text('import_${v['kind']}'),
    recordSubtitle: (v) =>
        '${Strings.of(context).text(v['status'] as String)} · ${v['created_at']}',
    onCreate: upload,
    onOpen: (c, v) => Navigator.push(
      c,
      MaterialPageRoute<void>(
        builder: (_) => ImportDetailScreen(auth: auth, id: v['id'] as String),
      ),
    ),
  );
}

class ImportDetailScreen extends StatefulWidget {
  const ImportDetailScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<ImportDetailScreen> createState() => _ImportDetailScreenState();
}

class _ImportDetailScreenState extends State<ImportDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final v = await widget.auth.call('imports/${widget.id}');
      if (mounted) {
        setState(() {
          data = v;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> mapColumns() async {
    final s = Strings.of(context);
    final headers = (data!['headers'] as List).cast<String>();
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => DomainForm(
          title: 'mapColumns',
          fields: [
            for (final field in data!['fields'] as List)
              FormFieldSpec(
                field as String,
                field,
                kind: FieldKind.choice,
                required: false,
                initial:
                    (data!['mapping'] as Map)[field] ??
                    (headers.contains(field) ? field : null),
                options: {for (final h in headers) h: h},
              ),
          ],
          onSubmit: (v) async {
            await widget.auth.call(
              'imports/${widget.id}/preview',
              body: {'mapping': v},
            );
          },
        ),
      ),
    );
    if (mounted) {
      await load();
      if (mounted && data?['report']?['valid'] == false) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(s.text('importInvalid'))));
      }
    }
  }

  Future<void> confirm() async {
    final s = Strings.of(context);
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(s.text('confirmImport')),
        content: Text(s.text('confirmImportBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: Text(s.text('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: Text(s.text('confirm')),
          ),
        ],
      ),
    );
    if (yes != true || !mounted) return;
    setState(() => busy = true);
    try {
      await widget.auth.call(
        'imports/${widget.id}/confirm',
        body: {'confirm': true},
      );
      await load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('importData'))),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : ListView(
              padding: const EdgeInsets.all(AppSpace.lg),
              children: [
                ImportProgress(status: data!['status'] as String),
                const SizedBox(height: AppSpace.lg),
                StatusBanner(message: s.text('importPrivacy')),
                const SizedBox(height: AppSpace.lg),
                Text(
                  s.text('import_${data!['kind']}'),
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                Text(s.text(data!['status'] as String)),
                if (error != null) DataState(error: error, onRetry: load),
                if (data!['status'] != 'confirmed') ...[
                  FilledButton.icon(
                    onPressed: busy ? null : mapColumns,
                    icon: const Icon(Icons.table_chart_outlined),
                    label: Text(s.text('mapColumns')),
                  ),
                  Text(s.text('previewMasked')),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: DataTable(
                      columns: [
                        for (final h in data!['headers'] as List)
                          DataColumn(label: Text(h.toString())),
                      ],
                      rows: [
                        for (final row in data!['preview'] as List)
                          DataRow(
                            cells: [
                              for (
                                var i = 0;
                                i < (data!['headers'] as List).length;
                                i++
                              )
                                DataCell(
                                  Text(
                                    i < (row as List).length
                                        ? row[i].toString()
                                        : '',
                                  ),
                                ),
                            ],
                          ),
                      ],
                    ),
                  ),
                ],
                if (data!['report'] != null)
                  ImportReview(
                    report: (data!['report'] as Map).cast<String, dynamic>(),
                    confirmed: data!['status'] == 'confirmed',
                  ),
                if (data!['status'] == 'validated')
                  FilledButton.icon(
                    onPressed: busy ? null : confirm,
                    icon: const Icon(Icons.check_circle_outline),
                    label: Text(s.text('confirmImport')),
                  ),
                if (busy) const LinearProgressIndicator(),
              ],
            ),
    );
  }
}
