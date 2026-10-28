import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../auth/auth_controller.dart';

class PrivateFileScreen extends StatefulWidget {
  const PrivateFileScreen({
    super.key,
    required this.auth,
    required this.id,
    required this.mime,
    this.path,
    this.errorBuilder,
  });
  final AuthController auth;
  final String id, mime;
  final String? path;
  final Widget Function(BuildContext, String, VoidCallback)? errorBuilder;
  @override
  State<PrivateFileScreen> createState() => _PrivateFileScreenState();
}

class _PrivateFileScreenState extends State<PrivateFileScreen> {
  Uint8List? bytes;
  String? error;
  File? file;
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    final temp = file;
    if (temp != null) {
      temp.exists().then((exists) {
        if (exists) temp.delete();
      });
    }
    super.dispose();
  }

  Future<void> load() async {
    try {
      final data = await widget.auth.download(
        widget.path ?? 'files/${widget.id}',
      );
      if (mounted) {
        setState(() {
          bytes = data;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> open() async {
    try {
      final temp = await getTemporaryDirectory();
      file = File('${temp.path}/${widget.id}.pdf');
      await file!.writeAsBytes(bytes!, flush: true);
      final result = await OpenFilex.open(file!.path, type: 'application/pdf');
      if (result.type != ResultType.done && mounted) {
        setState(() => error = 'viewerUnavailable');
      }
    } catch (_) {
      if (mounted) setState(() => error = 'viewerUnavailable');
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('privateDocument'))),
      body: error != null
          ? widget.errorBuilder?.call(context, error!, load) ??
                DataState(error: error, onRetry: load)
          : bytes == null
          ? const DataState()
          : widget.mime.startsWith('image/')
          ? InteractiveViewer(
              minScale: .5,
              maxScale: 6,
              child: Center(child: Image.memory(bytes!, fit: BoxFit.contain)),
            )
          : Center(
              child: Padding(
                padding: const EdgeInsets.all(AppSpace.xl),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.picture_as_pdf_outlined, size: 64),
                    const SizedBox(height: AppSpace.xl),
                    Text(
                      s.text('externalViewerNotice'),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: AppSpace.xl),
                    FilledButton.icon(
                      onPressed: open,
                      icon: const Icon(Icons.open_in_new),
                      label: Text(s.text('openPdf')),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
