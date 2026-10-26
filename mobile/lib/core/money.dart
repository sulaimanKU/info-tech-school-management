abstract final class Money {
  static int? tryParse(String value) {
    final v = value.trim();
    if (!RegExp(r'^-?\d+(\.\d{1,2})?$').hasMatch(v)) return null;
    final negative = v.startsWith('-');
    final pieces = v.replaceFirst('-', '').split('.');
    final amount = int.tryParse(pieces[0]);
    if (amount == null || amount > 1000000000) return null;
    final minor =
        amount * 100 +
        int.parse((pieces.length == 1 ? '' : pieces[1]).padRight(2, '0'));
    return negative ? -minor : minor;
  }

  static String numeric(num minor) {
    final value = minor.toInt();
    return '${value < 0 ? '-' : ''}${value.abs() ~/ 100}.${(value.abs() % 100).toString().padLeft(2, '0')}';
  }

  static String format(dynamic minor) =>
      'PKR ${numeric(num.tryParse(minor?.toString() ?? '0') ?? 0)}';
}
