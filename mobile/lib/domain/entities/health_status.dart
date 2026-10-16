import 'package:equatable/equatable.dart';

class HealthStatus extends Equatable {
  const HealthStatus({
    required this.success,
    required this.message,
  });

  final bool success;
  final String message;

  @override
  List<Object?> get props => [success, message];
}
