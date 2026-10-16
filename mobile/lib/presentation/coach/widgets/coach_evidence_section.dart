import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/coach_structured.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_food_widgets.dart';
import 'package:url_launcher/url_launcher.dart';

class CoachEvidenceSection extends StatefulWidget {
  const CoachEvidenceSection({required this.knowledgeContext, super.key});

  final Map<String, dynamic> knowledgeContext;

  @override
  State<CoachEvidenceSection> createState() => _CoachEvidenceSectionState();
}

class _CoachEvidenceSectionState extends State<CoachEvidenceSection> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final items = CoachPlanParsing.asMapList(widget.knowledgeContext['items']);
    if (items.isEmpty) return const SizedBox.shrink();

    final targets =
        CoachPlanParsing.asMap(widget.knowledgeContext['personalized_targets']);

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        children: [
          if (targets != null && targets['authoritative'] == true) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'coach_personalized_targets'.tr(),
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                  ),
                  const SizedBox(height: 8),
                  CoachMacroMetrics(
                    calories: CoachPlanParsing.asDouble(targets['calorie_target']),
                    protein: CoachPlanParsing.asDouble(
                      CoachPlanParsing.asMap(targets['macro_targets'])?['protein_g'],
                    ),
                    carbs: CoachPlanParsing.asDouble(
                      CoachPlanParsing.asMap(targets['macro_targets'])?['carbohydrates_g'],
                    ),
                    fat: CoachPlanParsing.asDouble(
                      CoachPlanParsing.asMap(targets['macro_targets'])?['fat_g'],
                    ),
                  ),
                ],
              ),
            ),
          ],
          Material(
            color: Colors.transparent,
            child: ListTile(
              dense: true,
              title: Text(
                'coach_sources_count'.tr(namedArgs: {'count': '${items.length}'}),
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              subtitle: Text('coach_evidence_subtitle'.tr()),
              trailing: Icon(
                _expanded ? Icons.expand_less : Icons.expand_more,
                color: AppColors.textPrimary,
              ),
              onTap: () => setState(() => _expanded = !_expanded),
            ),
          ),
          if (_expanded)
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: Column(
                children: items.map((item) => _SourceTile(item: item)).toList(),
              ),
            ),
        ],
      ),
    );
  }
}

class _SourceTile extends StatelessWidget {
  const _SourceTile({required this.item});

  final Map<String, dynamic> item;

  Future<void> _openUrl(BuildContext context, String raw) async {
    final uri = Uri.tryParse(raw);
    if (uri == null || !(uri.isScheme('http') || uri.isScheme('https'))) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('coach_invalid_source_url'.tr())),
        );
      }
      return;
    }
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('coach_open_url_failed'.tr())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final source = CoachPlanParsing.asString(item['source'] ?? item['title']) ??
        'coach_source_unknown'.tr();
    final org = CoachPlanParsing.asString(item['organization']);
    final year = CoachPlanParsing.asInt(item['publication_year']);
    final level = CoachPlanParsing.asString(item['evidence_level']);
    final url = CoachPlanParsing.asString(item['url']);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(AppRadii.sm),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            source,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
          if (org != null) ...[
            const SizedBox(height: 2),
            Text(
              org,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ],
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              if (year != null)
                Text(
                  '$year',
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              if (level != null)
                Text(
                  'coach_evidence_level'.tr(namedArgs: {'value': level}),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: AppColors.textPrimary,
                      ),
                ),
            ],
          ),
          if (url != null) ...[
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: () => _openUrl(context, url),
              icon: const Icon(Icons.link, size: 16),
              label: Text('coach_open_source'.tr()),
            ),
          ],
        ],
      ),
    );
  }
}
