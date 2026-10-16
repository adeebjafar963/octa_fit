import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';
import 'package:octa_fit/presentation/physio/cubit/physio_assessment_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_body_viewer.dart';

const _bodyAreas = [
  'neck',
  'shoulder',
  'upper_back',
  'lower_back',
  'elbow',
  'wrist',
  'hand',
  'hip',
  'knee',
  'ankle',
  'foot',
  'legs',
  'other',
];

String _areaFromRegion(OctaBodyRegion region) => switch (region) {
  OctaBodyRegion.head => 'neck',
  OctaBodyRegion.shoulders => 'shoulder',
  OctaBodyRegion.chest => 'upper_back',
  OctaBodyRegion.upperBack => 'upper_back',
  OctaBodyRegion.lowerBack => 'lower_back',
  OctaBodyRegion.arms => 'elbow',
  OctaBodyRegion.core => 'other',
  OctaBodyRegion.hips => 'hip',
  OctaBodyRegion.legs => 'legs',
  OctaBodyRegion.knees => 'knee',
  OctaBodyRegion.ankles => 'ankle',
};

OctaBodyRegion? _regionFromArea(String? area) => switch (area) {
  'neck' => OctaBodyRegion.head,
  'shoulder' => OctaBodyRegion.shoulders,
  'upper_back' => OctaBodyRegion.upperBack,
  'lower_back' => OctaBodyRegion.lowerBack,
  'elbow' => OctaBodyRegion.arms,
  'hip' => OctaBodyRegion.hips,
  'legs' => OctaBodyRegion.legs,
  'knee' => OctaBodyRegion.knees,
  'ankle' => OctaBodyRegion.ankles,
  'other' => OctaBodyRegion.core,
  _ => null,
};

const _symptomHints = [
  'pain',
  'stiffness',
  'swelling',
  'weakness',
  'numbness',
  'tingling',
  'reduced_movement',
  'muscle_soreness',
  'other',
];

class PhysioNewAssessmentScreen extends StatelessWidget {
  const PhysioNewAssessmentScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => PhysioAssessmentCubit(sl<PhysioRepository>()),
      child: const _PhysioNewAssessmentView(),
    );
  }
}

class _PhysioNewAssessmentView extends StatefulWidget {
  const _PhysioNewAssessmentView();

  @override
  State<_PhysioNewAssessmentView> createState() =>
      _PhysioNewAssessmentViewState();
}

class _PhysioNewAssessmentViewState extends State<_PhysioNewAssessmentView> {
  final _symptomsController = TextEditingController();
  final _durationController = TextEditingController();
  final _onsetController = TextEditingController();
  final _triggerController = TextEditingController();
  final _activityController = TextEditingController();
  final _additionalController = TextEditingController();

  String? _bodyArea;
  final Set<String> _selectedSymptoms = {};
  double _painLevel = 3;

  @override
  void dispose() {
    _symptomsController.dispose();
    _durationController.dispose();
    _onsetController.dispose();
    _triggerController.dispose();
    _activityController.dispose();
    _additionalController.dispose();
    super.dispose();
  }

  String _composeSymptoms() {
    final chips = _selectedSymptoms
        .map((k) => 'physio_symptom_$k'.tr())
        .toList();
    final free = _symptomsController.text.trim();
    final parts = <String>[...chips];
    if (free.isNotEmpty) parts.add(free);
    return parts.join('. ');
  }

  Future<void> _submit() async {
    final bodyArea = _bodyArea;
    final symptoms = _composeSymptoms();
    if (bodyArea == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('physio_body_area_required'.tr())));
      return;
    }
    if (symptoms.trim().isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('physio_symptoms_required'.tr())));
      return;
    }

    final language = context.locale.languageCode == 'ar' ? 'ar' : 'en';
    final assessment = await context.read<PhysioAssessmentCubit>().submit(
      PhysioCreateInput(
        bodyArea: bodyArea,
        symptoms: symptoms,
        painLevel: _painLevel.round(),
        duration: _durationController.text,
        onset: _onsetController.text,
        trigger: _triggerController.text,
        activity: _activityController.text,
        additionalInformation: _additionalController.text,
        language: language,
      ),
    );

    if (assessment != null && mounted) {
      context.pushReplacement('/physio/assessment/${assessment.id}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'physio_new_assessment'.tr(),
        accent: AppColors.ai,
      ),
      body: BlocConsumer<PhysioAssessmentCubit, PhysioAssessmentState>(
        listener: (context, state) {
          if (state is PhysioAssessmentFormState && state.errorKey != null) {
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(SnackBar(content: Text(state.errorKey!.tr())));
          }
        },
        builder: (context, state) {
          final submitting =
              state is PhysioAssessmentFormState && state.submitting;

          return AbsorbPointer(
            absorbing: submitting,
            child: OctaPage(
              padding: EdgeInsets.zero,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  _PhysioDisclaimerBanner(),
                  const SizedBox(height: AppSpacing.xl),
                  OctaBodyViewer(
                    selected: _regionFromArea(_bodyArea),
                    accent: AppColors.ai,
                    onRegionSelected: (region) {
                      setState(() => _bodyArea = _areaFromRegion(region));
                    },
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  _StepCard(
                    step: '1',
                    title: 'physio_step_body_area'.tr(),
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: _bodyAreas.map((area) {
                        final selected = _bodyArea == area;
                        return ChoiceChip(
                          label: Text('physio_area_$area'.tr()),
                          selected: selected,
                          selectedColor: AppColors.primary.withValues(alpha: 0.22),
                          side: BorderSide(
                            color: selected ? AppColors.primary : AppColors.border,
                          ),
                          labelStyle: TextStyle(
                            color: selected ? AppColors.textPrimary
                                : AppColors.textSecondary,
                            fontWeight: selected
                                ? FontWeight.w700
                                : FontWeight.w500,
                          ),
                          onSelected: (_) => setState(() => _bodyArea = area),
                        );
                      }).toList(),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _StepCard(
                    step: '2',
                    title: 'physio_step_symptoms'.tr(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: _symptomHints.map((key) {
                            final selected = _selectedSymptoms.contains(key);
                            return FilterChip(
                              label: Text('physio_symptom_$key'.tr()),
                              selected: selected,
                              selectedColor: AppColors.primary.withValues(
                                alpha: 0.22,
                              ),
                              checkmarkColor: AppColors.primary,
                              side: BorderSide(
                                color: selected ? AppColors.primary
                                    : AppColors.border,
                              ),
                              onSelected: (v) {
                                setState(() {
                                  if (v) {
                                    _selectedSymptoms.add(key);
                                  } else {
                                    _selectedSymptoms.remove(key);
                                  }
                                });
                              },
                            );
                          }).toList(),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        TextField(
                          controller: _symptomsController,
                          maxLines: 3,
                          maxLength: 2000,
                          decoration: InputDecoration(
                            hintText: 'physio_symptoms_hint'.tr(),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _StepCard(
                    step: '3',
                    title: 'physio_step_pain'.tr(
                      namedArgs: {'level': '${_painLevel.round()}'},
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SliderTheme(
                          data: SliderTheme.of(context).copyWith(
                            activeTrackColor: AppColors.primary,
                            thumbColor: AppColors.primary,
                            inactiveTrackColor: AppColors.border,
                            overlayColor: AppColors.ai.withValues(alpha: 0.12),
                          ),
                          child: Slider(
                            value: _painLevel,
                            min: 0,
                            max: 10,
                            divisions: 10,
                            label: '${_painLevel.round()}',
                            onChanged: (v) => setState(() => _painLevel = v),
                          ),
                        ),
                        Text(
                          'physio_pain_scale'.tr(),
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _StepCard(
                    step: '4',
                    title: 'physio_step_duration'.tr(),
                    child: Column(
                      children: [
                        TextField(
                          controller: _durationController,
                          decoration: InputDecoration(
                            hintText: 'physio_duration_hint'.tr(),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        TextField(
                          controller: _onsetController,
                          decoration: InputDecoration(
                            hintText: 'physio_onset_hint'.tr(),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _StepCard(
                    step: '5',
                    title: 'physio_step_trigger'.tr(),
                    child: Column(
                      children: [
                        TextField(
                          controller: _triggerController,
                          decoration: InputDecoration(
                            hintText: 'physio_trigger_hint'.tr(),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        TextField(
                          controller: _activityController,
                          decoration: InputDecoration(
                            hintText: 'physio_activity_hint'.tr(),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _StepCard(
                    step: '6',
                    title: 'physio_step_additional'.tr(),
                    child: TextField(
                      controller: _additionalController,
                      maxLines: 3,
                      maxLength: 2000,
                      decoration: InputDecoration(
                        hintText: 'physio_additional_hint'.tr(),
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  AppPrimaryButton(
                    labelKey: 'physio_assess',
                    accent: OctaAccent.ai,
                    icon: Icons.health_and_safety_outlined,
                    isLoading: submitting,
                    onPressed: submitting ? null : _submit,
                  ),
                  const SizedBox(height: AppSpacing.xl),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _PhysioDisclaimerBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      borderColor: AppColors.ai.withValues(alpha: 0.12),
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: AppColors.ai.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(AppRadii.sm),
            ),
            child: const Icon(
              Icons.info_outline_rounded,
              color: AppColors.ai,
              size: 18,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Text(
              'physio_disclaimer'.tr(),
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(height: 1.45),
            ),
          ),
        ],
      ),
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({
    required this.step,
    required this.title,
    required this.child,
  });

  final String step;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      elevated: true,
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.ai.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(AppRadii.xs),
                  border: Border.all(
                    color: AppColors.ai.withValues(alpha: 0.10),
                  ),
                ),
                child: Text(
                  step,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          child,
        ],
      ),
    );
  }
}
