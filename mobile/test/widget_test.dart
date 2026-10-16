import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/config/app_config.dart';

void main() {
  test('AppConfig exposes OCTA FIT product name', () {
    expect(AppConfig.appName, 'OCTA FIT');
  });
}
