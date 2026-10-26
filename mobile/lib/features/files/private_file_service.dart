import 'dart:io';

import 'package:file_selector/file_selector.dart';
import 'package:flutter/foundation.dart';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';

import '../../core/api_client.dart';
import '../../core/operation_id.dart';
import '../auth/auth_controller.dart';

Uint8List compressSchoolImage(Uint8List bytes) {
  final decoder = img.findDecoderForData(bytes);
  final info = decoder?.startDecode(bytes);
  if (info == null || info.width * info.height > 24000000) {
    throw const ApiFailure('invalidFile');
  }
  var image = decoder!.decodeFrame(0);
  if (image == null) throw const ApiFailure('invalidFile');
  image = img.bakeOrientation(image);
  if (image.width > 1600 || image.height > 1600) {
    image = img.copyResize(
      image,
      width: image.width >= image.height ? 1600 : null,
      height: image.height > image.width ? 1600 : null,
    );
  }
  image.exif = img.ExifData();
  return Uint8List.fromList(img.encodeJpg(image, quality: 82));
}

class PrivateFileService {
  const PrivateFileService();
  Future<List<Map<String, dynamic>>> pickAndUpload(
    AuthController auth, {
    required String purpose,
    String? studentId,
    String? sectionId,
    bool multiple = true,
  }) async {
    const types = [
      XTypeGroup(
        label: 'School files',
        extensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
      ),
    ];
    final files = multiple
        ? await openFiles(acceptedTypeGroups: types)
        : [
            if (await openFile(acceptedTypeGroups: types) case final XFile file)
              file,
          ];
    if (files.length > 10) throw const ApiFailure('invalidFile');
    final results = <Map<String, dynamic>>[];
    for (final file in files) {
      if (await file.length() > 5 * 1024 * 1024) {
        throw const ApiFailure('invalidFile');
      }
      String uploadPath = file.path;
      File? temporary;
      if (!file.name.toLowerCase().endsWith('.pdf')) {
        final bytes = await compute(
          compressSchoolImage,
          await file.readAsBytes(),
        );
        temporary = File(
          '${(await getTemporaryDirectory()).path}/${operationId()}.jpg',
        );
        await temporary.writeAsBytes(bytes, flush: true);
        uploadPath = temporary.path;
      }
      try {
        results.add(
          await auth.upload('files', uploadPath, {
            'purpose': purpose,
            'student_id': ?studentId,
            'section_id': ?sectionId,
          }),
        );
      } finally {
        if (temporary != null && await temporary.exists()) {
          await temporary.delete();
        }
      }
    }
    return results;
  }
}
