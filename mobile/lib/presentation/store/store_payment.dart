/// Syrian Syriatel Cash numbers are exactly 10 digits and start with 09.
final RegExp syriatelCashPhonePattern = RegExp(r'^09\d{8}$');

String? syriatelCashPhoneErrorKey(String phone) {
  final value = phone.trim();
  if (value.isEmpty) return 'store_payment_phone_required';
  if (!syriatelCashPhonePattern.hasMatch(value)) return 'store_payment_phone_invalid';
  return null;
}

String maskSyriatelPhone(String phone) {
  if (phone.length < 4) return phone;
  final hidden = phone.length - 4;
  return '${phone.substring(0, 2)}${'*' * hidden}${phone.substring(phone.length - 2)}';
}
