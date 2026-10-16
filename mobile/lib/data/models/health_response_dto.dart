class HealthResponseDto {
  HealthResponseDto({
    required this.success,
    required this.message,
  });

  factory HealthResponseDto.fromJson(Map<String, dynamic> json) {
    return HealthResponseDto(
      success: json['success'] as bool? ?? false,
      message: json['message'] as String? ?? '',
    );
  }

  final bool success;
  final String message;
}
