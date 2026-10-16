import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/data/datasources/features_remote_datasource.dart';

class FeatureFlags extends Equatable {
  const FeatureFlags({
    required this.coachEnabled,
    required this.physioEnabled,
    required this.motionEnabled,
  });

  static const allEnabled = FeatureFlags(
    coachEnabled: true,
    physioEnabled: true,
    motionEnabled: true,
  );

  final bool coachEnabled;
  final bool physioEnabled;
  final bool motionEnabled;

  @override
  List<Object?> get props => [coachEnabled, physioEnabled, motionEnabled];
}

class FeatureFlagsCubit extends Cubit<FeatureFlags> {
  FeatureFlagsCubit(this._dataSource) : super(FeatureFlags.allEnabled);

  final FeaturesRemoteDataSource _dataSource;

  Future<void> load() async {
    try {
      emit(await _dataSource.fetchFlags());
    } on ApiException {
      // Keep last-known / defaults so Home still works offline.
    } catch (_) {
      // Ignore — UI defaults to enabled until next successful load.
    }
  }
}
