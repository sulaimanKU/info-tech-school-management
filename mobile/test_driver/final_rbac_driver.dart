import 'dart:io';

import 'package:integration_test/integration_test_driver_extended.dart';

Future<void> main() async {
  final directory = Directory('../docs/screenshots/final-rbac');
  await directory.create(recursive: true);
  await integrationDriver(
    onScreenshot:
        (String name, List<int> bytes, [Map<String, Object?>? args]) async {
          if (!RegExp(r'^[a-z0-9-]+$').hasMatch(name)) return false;
          await File('${directory.path}/$name.png').writeAsBytes(bytes);
          return true;
        },
  );
}
