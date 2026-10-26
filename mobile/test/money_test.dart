import 'package:flutter_test/flutter_test.dart';
import 'package:school/core/money.dart';

void main() {
  test('PKR conversion is exact and rejects excessive decimal precision', () {
    expect(Money.tryParse('1000'), 100000);
    expect(Money.tryParse('500.01'), 50001);
    expect(Money.tryParse('-10.50'), -1050);
    expect(Money.tryParse('0.001'), isNull);
    expect(Money.tryParse('NaN'), isNull);
    expect(Money.numeric(50001), '500.01');
    expect(Money.format(140000), 'PKR 1400.00');
  });
}
