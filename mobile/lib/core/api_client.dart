import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

class ApiFailure implements Exception {
  const ApiFailure(this.code, {this.fields = const {}});
  final String code;
  final Map<String, dynamic> fields;
}

class ApiClient {
  ApiClient({http.Client? client, String? baseUrl})
    : _client = client ?? http.Client(),
      baseUrl = baseUrl ?? const String.fromEnvironment('API_BASE_URL');
  final http.Client _client;
  final String baseUrl;

  Uri _uri(String path) {
    final base = Uri.tryParse(baseUrl);
    if (base == null ||
        !base.hasAuthority ||
        !['http', 'https'].contains(base.scheme) ||
        (const bool.fromEnvironment('dart.vm.product') &&
            base.scheme != 'https')) {
      throw const ApiFailure('configuration');
    }
    return Uri.parse('${baseUrl.replaceFirst(RegExp(r'/$'), '')}/$path');
  }

  Map<String, String> _headers(String? token) => {
    'Accept': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };

  Future<http.Response> _send(Future<http.Response> Function() action) async {
    try {
      final response = await action().timeout(const Duration(seconds: 30));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        Map<String, dynamic> fields = {};
        try {
          fields = (jsonDecode(response.body)['error']['fields'] as Map)
              .cast<String, dynamic>();
        } catch (_) {}
        throw ApiFailure(switch (response.statusCode) {
          401 => 'unauthorized',
          403 => 'forbidden',
          404 => 'notFound',
          409 => 'conflict',
          422 => 'validation',
          429 => 'rateLimit',
          503 => 'integrationUnavailable',
          _ => 'server',
        }, fields: fields);
      }
      return response;
    } on TimeoutException {
      throw const ApiFailure('network');
    } on SocketException {
      throw const ApiFailure('network');
    } on http.ClientException {
      throw const ApiFailure('network');
    }
  }

  Future<Map<String, dynamic>> request(
    String path, {
    String? token,
    Map<String, dynamic>? body,
    String? method,
  }) async {
    final uri = _uri(path);
    final headers = {..._headers(token), 'Content-Type': 'application/json'};
    final verb = method ?? (body == null ? 'GET' : 'POST');
    final response = await _send(
      () => switch (verb) {
        'GET' => _client.get(uri, headers: headers),
        'PATCH' => _client.patch(
          uri,
          headers: headers,
          body: jsonEncode(body ?? {}),
        ),
        'PUT' => _client.put(
          uri,
          headers: headers,
          body: jsonEncode(body ?? {}),
        ),
        'DELETE' => _client.delete(
          uri,
          headers: headers,
          body: jsonEncode(body ?? {}),
        ),
        _ => _client.post(uri, headers: headers, body: jsonEncode(body ?? {})),
      },
    );
    try {
      return (jsonDecode(response.body) as Map<String, dynamic>)['data']
          as Map<String, dynamic>;
    } on FormatException {
      throw const ApiFailure('server');
    } on TypeError {
      throw const ApiFailure('server');
    }
  }

  Future<Uint8List> download(String path, {required String token}) async {
    Future<http.Response> read() async {
      // A truncated response is safe to retry for an authenticated read only.
      // Keep the existing overall timeout; never replay writes or auth requests.
      for (var attempt = 0; ; attempt++) {
        try {
          return await _client.get(_uri(path), headers: _headers(token));
        } on http.ClientException {
          if (attempt >= 2) rethrow;
          await Future<void>.delayed(
            Duration(milliseconds: 150 * (attempt + 1)),
          );
        }
      }
    }

    return (await _send(read)).bodyBytes;
  }

  Future<Map<String, dynamic>> upload(
    String path, {
    required String token,
    required String filePath,
    required Map<String, String> fields,
  }) async {
    final request = http.MultipartRequest('POST', _uri(path))
      ..headers.addAll(_headers(token))
      ..fields.addAll(fields);
    request.files.add(await http.MultipartFile.fromPath('file', filePath));
    final response = await _send(
      () async => http.Response.fromStream(await _client.send(request)),
    );
    return (jsonDecode(response.body)['data'] as Map).cast<String, dynamic>();
  }
}
