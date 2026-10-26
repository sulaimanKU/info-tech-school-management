import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../features/auth/auth_controller.dart';
import '../../l10n/strings.dart';

class AuthorizedPhoto extends StatefulWidget {
  const AuthorizedPhoto({
    super.key,
    required this.auth,
    this.fileId,
    this.size = 64,
    this.name,
  });
  final AuthController auth;
  final String? fileId, name;
  final double size;
  @override
  State<AuthorizedPhoto> createState() => _AuthorizedPhotoState();
}

class _AuthorizedPhotoState extends State<AuthorizedPhoto> {
  Uint8List? bytes;
  bool loading = false, failed = false;
  int generation = 0;
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void didUpdateWidget(covariant AuthorizedPhoto old) {
    super.didUpdateWidget(old);
    if (old.fileId != widget.fileId ||
        old.auth != widget.auth ||
        old.auth.user?['id'] != widget.auth.user?['id']) {
      bytes = null;
      load();
    }
  }

  Future<void> load() async {
    final current = ++generation;
    if (widget.fileId == null) {
      loading = false;
      failed = false;
      return;
    }
    setState(() {
      loading = true;
      failed = false;
    });
    try {
      final data = await widget.auth.download('files/${widget.fileId}');
      if (mounted && current == generation) setState(() => bytes = data);
    } catch (_) {
      if (mounted && current == generation) setState(() => failed = true);
    } finally {
      if (mounted && current == generation) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final scheme = Theme.of(context).colorScheme;
    final initials = widget.name
        ?.trim()
        .split(RegExp(r'\s+'))
        .take(2)
        .map((v) => v.isEmpty ? '' : v.characters.first)
        .join();
    final fallback = Center(
      child: initials?.isNotEmpty == true
          ? Text(
              initials!,
              style: Theme.of(context).textTheme.titleLarge
                  ?.copyWith(color: scheme.onPrimaryContainer),
            )
          : Icon(
              Icons.person_outline,
              size: widget.size / 2,
              color: scheme.onPrimaryContainer,
            ),
    );
    return Semantics(
      label: s.text('profilePhoto'),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: Container(
          width: widget.size,
          height: widget.size,
          color: scheme.primaryContainer,
          child: loading
              ? const Center(
                  child: SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                )
              : failed
              ? IconButton(
                  tooltip: s.text('retryPhoto'),
                  onPressed: load,
                  icon: const Icon(Icons.refresh_rounded),
                )
              : bytes == null
              ? fallback
              : Image.memory(
                  bytes!,
                  fit: BoxFit.cover,
                  cacheWidth:
                      (widget.size * MediaQuery.devicePixelRatioOf(context))
                          .ceil(),
                  errorBuilder: (_, _, _) => Tooltip(
                    message: s.text('retryPhoto'),
                    child: InkWell(onTap: load, child: fallback),
                  ),
                ),
        ),
      ),
    );
  }
}
