class ApiException implements Exception {
  ApiException(
    this.message, {
    this.statusCode,
    this.errors,
    this.messageKey,
  });

  final String message;
  final int? statusCode;
  final Map<String, List<String>>? errors;

  /// Optional localization key for UI (e.g. auth_error_unauthorized).
  final String? messageKey;

  bool get isUnauthorized => statusCode == 401;
  bool get isValidation => statusCode == 422;
  bool get isNetwork => statusCode == null || statusCode == 0;

  @override
  String toString() => message;
}
