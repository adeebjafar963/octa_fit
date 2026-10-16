/// Application-wide configuration.
class AppConfig {
  AppConfig._();

  static const String appName = 'OCTA FIT';

  /// Laravel API base URL (no trailing slash).
  /// Android emulator: http://10.0.2.2:8000
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );

  static const String apiPrefix = '/api';

  static String get healthUrl => '$apiBaseUrl$apiPrefix/health';
}
