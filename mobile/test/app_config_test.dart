import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/config/app_config.dart';

void main() {
  test('AppConfig exposes health URL under /api/health', () {
    expect(AppConfig.healthUrl.endsWith('/api/health'), isTrue);
  });
}
