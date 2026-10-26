import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:school/core/api_client.dart';
import 'package:school/features/auth/auth_controller.dart';
import 'package:school/features/auth/session_store.dart';

class MemoryStore implements SessionStore {
  String? token;
  int writes = 0;
  final writtenValues = <String>[];
  @override
  Future<String?> read() async => token;
  @override
  Future<void> write(String value) async {
    token = value;
    writes++;
    writtenValues.add(value);
  }

  @override
  Future<void> clear() async {
    token = null;
  }
}

const testUser = {
  'id': 'test-user',
  'name': 'Test Staff',
  'email': 'staff@example.test',
  'permissions': ['students.read', 'academics.read', 'academics.write'],
  'roles': [
    {'code': 'teacher', 'name': 'Teacher'},
  ],
};

AuthController createAuth(
  MemoryStore store, {
  Future<http.Response> Function(http.Request)? handler,
}) => AuthController(
  ApiClient(
    baseUrl: 'http://localhost/api/v1',
    client: MockClient(
      handler ??
          (r) async {
            final data = r.url.path.endsWith('login')
                ? {'token': 'test-token', 'user': testUser}
                : r.url.path.endsWith('logout')
                ? {'logged_out': true}
                : testUser;
            return http.Response(jsonEncode({'data': data}), 200);
          },
    ),
  ),
  store,
);
