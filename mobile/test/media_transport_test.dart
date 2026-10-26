import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:school/core/api_client.dart';

void main() {
  test(
    'private download retries a truncated stream with the same bearer',
    () async {
      var calls = 0;
      final api = ApiClient(
        baseUrl: 'http://localhost/api/v1',
        client: MockClient((r) async {
          calls++;
          expect(r.headers['Authorization'], 'Bearer fictional-token');
          if (calls == 1) {
            throw http.ClientException(
              'Connection closed while receiving data',
            );
          }
          return http.Response.bytes([1, 2, 3], 200);
        }),
      );
      expect(await api.download('files/fictional', token: 'fictional-token'), [
        1,
        2,
        3,
      ]);
      expect(calls, 2);
    },
  );
  test(
    'private read recovery is bounded and still reports network failure',
    () async {
      var calls = 0;
      final api = ApiClient(
        baseUrl: 'http://localhost/api/v1',
        client: MockClient((_) async {
          calls++;
          throw http.ClientException('Connection closed while receiving data');
        }),
      );
      await expectLater(
        api.download('files/fictional', token: 'fictional-token'),
        throwsA(isA<ApiFailure>().having((e) => e.code, 'code', 'network')),
      );
      expect(calls, 3);
    },
  );
  test(
    'HTTP authorization failures server errors and writes are never replayed',
    () async {
      for (final status in [401, 403, 500]) {
        var calls = 0;
        final api = ApiClient(
          baseUrl: 'http://localhost/api/v1',
          client: MockClient((_) async {
            calls++;
            return http.Response('{}', status);
          }),
        );
        await expectLater(
          api.download('files/fictional', token: 'fictional-token'),
          throwsA(isA<ApiFailure>()),
        );
        expect(calls, 1);
      }
      var writes = 0;
      final api = ApiClient(
        baseUrl: 'http://localhost/api/v1',
        client: MockClient((_) async {
          writes++;
          throw http.ClientException('Connection closed');
        }),
      );
      await expectLater(
        api.request(
          'parent-auth/verify',
          body: {'challenge_id': 'fictional', 'otp': '000000'},
        ),
        throwsA(isA<ApiFailure>()),
      );
      expect(writes, 1);
    },
  );
}
