import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';

Color trainingAccentFor(String? typeOrAccent) {
  final v = (typeOrAccent ?? '').toLowerCase();
  if (v.contains('mma') || v == 'brick') return AppColors.mma;
  if (v.contains('ai') || v == 'steel' || v.contains('steel')) return AppColors.ai;
  return AppColors.primary;
}

String formatDuration(int totalSeconds) {
  final s = totalSeconds.clamp(0, 86400);
  final h = s ~/ 3600;
  final m = (s % 3600) ~/ 60;
  final sec = s % 60;
  if (h > 0) {
    return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}:${sec.toString().padLeft(2, '0')}';
  }
  return '${m.toString().padLeft(2, '0')}:${sec.toString().padLeft(2, '0')}';
}
