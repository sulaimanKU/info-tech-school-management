import 'dart:io';

import 'package:file_selector/file_selector.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api_client.dart';
import '../../core/operation_id.dart';
import '../../design/app_theme.dart';
import '../../features/auth/auth_controller.dart';
import '../../features/files/private_file_service.dart';
import '../../l10n/strings.dart';
import '../widgets/authorized_photo.dart';
import '../widgets/surface_card.dart';

class ProfilePhotoDraft {
  ProfilePhotoDraft({this.existingId, this.bytes, this.removed = false});
  final String? existingId;
  final Uint8List? bytes;
  final bool removed;
}

/// Shares the existing picker/compressor and authenticated private-file endpoint.
class ProfilePhotoPicker extends StatefulWidget {
  const ProfilePhotoPicker({
    super.key,
    required this.auth,
    required this.value,
    required this.onChanged,
    this.enabled = true,
    this.pickImage,
  });
  final AuthController auth;
  final ProfilePhotoDraft value;
  final ValueChanged<ProfilePhotoDraft> onChanged;
  final bool enabled;
  final Future<Uint8List?> Function()? pickImage;
  @override
  State<ProfilePhotoPicker> createState() => _ProfilePhotoPickerState();
}

class _ProfilePhotoPickerState extends State<ProfilePhotoPicker> {
  bool picking = false;
  String? error;
  Future<void> pick() async {
    setState(() {
      picking = true;
      error = null;
    });
    try {
      Uint8List? bytes;
      if (widget.pickImage != null) {
        bytes = await widget.pickImage!();
      } else {
        final file = await openFile(
          acceptedTypeGroups: [
            XTypeGroup(
              label: Strings.of(context).text('profilePhoto'),
              extensions: const ['jpg', 'jpeg', 'png', 'webp'],
              mimeTypes: const ['image/jpeg', 'image/png', 'image/webp'],
            ),
          ],
        );
        if (file != null) {
          if (await file.length() > 5 * 1024 * 1024) {
            throw const ApiFailure('invalidFile');
          }
          bytes = await compute(compressSchoolImage, await file.readAsBytes());
        }
      }
      if (bytes != null && mounted) {
        widget.onChanged(
          ProfilePhotoDraft(existingId: widget.value.existingId, bytes: bytes),
        );
      }
    } catch (_) {
      if (mounted) setState(() => error = 'invalidFile');
    } finally {
      if (mounted) setState(() => picking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final hasPhoto =
        widget.value.bytes != null ||
        (!widget.value.removed && widget.value.existingId != null);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Center(
          child: widget.value.bytes != null
              ? ClipRRect(
                  borderRadius: BorderRadius.circular(AppRadius.card),
                  child: Image.memory(
                    widget.value.bytes!,
                    width: 104,
                    height: 104,
                    fit: BoxFit.cover,
                    semanticLabel: s.text('photoPreview'),
                  ),
                )
              : AuthorizedPhoto(
                  auth: widget.auth,
                  fileId: widget.value.removed ? null : widget.value.existingId,
                  size: 104,
                ),
        ),
        const SizedBox(height: AppSpace.sm),
        Text(
          s.text('optionalPhoto'),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        Wrap(
          alignment: WrapAlignment.center,
          spacing: AppSpace.sm,
          children: [
            TextButton.icon(
              key: const Key('choose-profile-photo'),
              onPressed: widget.enabled && !picking ? pick : null,
              icon: Icon(
                hasPhoto ? Icons.edit_outlined : Icons.add_a_photo_outlined,
              ),
              label: Text(
                s.text(
                  picking
                      ? 'loading'
                      : hasPhoto
                      ? 'changePhoto'
                      : 'uploadPhoto',
                ),
              ),
            ),
            if (hasPhoto)
              TextButton.icon(
                key: const Key('remove-profile-photo'),
                onPressed: widget.enabled && !picking
                    ? () => widget.onChanged(
                        ProfilePhotoDraft(
                          existingId: widget.value.existingId,
                          removed: true,
                        ),
                      )
                    : null,
                icon: const Icon(Icons.delete_outline),
                label: Text(s.text('removePhoto')),
              ),
          ],
        ),
        if (error != null)
          StatusBanner(message: s.text(error!), kind: StatusKind.error),
      ],
    );
  }
}

Future<void> saveProfilePhoto(
  AuthController auth,
  ProfilePhotoDraft? draft,
  String id, {
  bool guardian = false,
  bool staff = false,
}) async {
  if (draft == null) return;
  if (draft.bytes != null) {
    final temp = File(
      '${(await getTemporaryDirectory()).path}/${operationId()}.jpg',
    );
    try {
      await temp.writeAsBytes(draft.bytes!, flush: true);
      await auth.upload(
        staff ? 'account/photo' : 'files',
        temp.path,
        staff
            ? {}
            : {
                'purpose': 'photo',
                guardian ? 'parent_guardian_id' : 'student_id': id,
              },
      );
    } finally {
      if (await temp.exists()) await temp.delete();
    }
  } else if (draft.removed && draft.existingId != null) {
    await auth.call(
      staff
          ? 'account/photo'
          : '${guardian ? 'guardians' : 'students'}/$id/photo',
      method: 'DELETE',
    );
  }
}
