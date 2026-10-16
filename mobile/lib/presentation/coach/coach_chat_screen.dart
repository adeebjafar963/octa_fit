import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/entities/coach_structured.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/presentation/coach/cubit/coach_chat_cubit.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_evidence_section.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_nutrition_plan_card.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_training_plan_card.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class CoachChatScreen extends StatelessWidget {
  const CoachChatScreen({required this.conversationId, super.key});

  final int conversationId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) =>
          CoachChatCubit(sl<CoachRepository>())..load(conversationId),
      child: _CoachChatView(conversationId: conversationId),
    );
  }
}

class _CoachChatView extends StatefulWidget {
  const _CoachChatView({required this.conversationId});

  final int conversationId;

  @override
  State<_CoachChatView> createState() => _CoachChatViewState();
}

class _CoachChatViewState extends State<_CoachChatView> {
  final _input = TextEditingController();
  final _scroll = ScrollController();

  static const _suggestions = [
    'coach_suggestion_muscle',
    'coach_suggestion_fat',
    'coach_suggestion_beginner',
    'coach_suggestion_nutrition',
    'coach_suggestion_meal_plan',
    'coach_suggestion_protein',
    'coach_suggestion_mma',
    'coach_suggestion_overload',
  ];

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) return;
      _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  String get _lang =>
      context.locale.languageCode.startsWith('ar') ? 'ar' : 'en';

  Future<void> _send([String? preset]) async {
    final text = preset ?? _input.text;
    if (text.trim().isEmpty) return;
    _input.clear();
    await context.read<CoachChatCubit>().send(text, language: _lang);
  }

  void _fillAndFocus(String text) {
    _input.text = text;
    _input.selection = TextSelection.collapsed(offset: _input.text.length);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'coach_title'.tr(), accent: AppColors.ai),
      body: BlocConsumer<CoachChatCubit, CoachChatState>(
        listener: (context, state) {
          if (state is CoachChatLoaded) {
            _scrollToBottom();
            if (state.errorKey != null) {
              final messenger = ScaffoldMessenger.of(context);
              messenger.hideCurrentSnackBar();
              messenger.showSnackBar(
                SnackBar(
                  content: Text(state.errorKey!.tr()),
                  // Always auto-dismiss so error banners do not block the UI.
                  // Retry remains available via SnackBarAction while visible.
                  duration: const Duration(seconds: 4),
                  action: state.lastFailedText != null
                      ? SnackBarAction(
                          label: 'coach_retry'.tr(),
                          onPressed: () => context
                              .read<CoachChatCubit>()
                              .retryLastFailed(language: _lang),
                        )
                      : null,
                ),
              );
              context.read<CoachChatCubit>().clearPresentedError();
            }
          }
        },
        builder: (context, state) {
          return switch (state) {
            CoachChatLoading() || CoachChatInitial() => const AppLoadingView(),
            CoachChatError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                accent: AppColors.ai,
                icon: Icons.smart_toy_outlined,
                onRetry: () =>
                    context.read<CoachChatCubit>().load(widget.conversationId),
              ),
            CoachChatLoaded(
              :final messages,
              :final sending,
              :final extrasByMessageId,
            ) =>
              Column(
                children: [
                  const _CoachIdentityHeader(),
                  Expanded(
                    child: messages.isEmpty
                        ? ListView(
                            padding: const EdgeInsets.all(AppSpacing.lg),
                            children: [
                              OctaSurface(
                                elevated: true,
                                borderColor:
                                    AppColors.ai.withValues(alpha: 0.12),
                                padding: const EdgeInsets.all(AppSpacing.lg),
                                child: Text(
                                  'coach_welcome'.tr(),
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyLarge
                                      ?.copyWith(
                                        color: AppColors.textSecondary,
                                        height: 1.5,
                                      ),
                                ),
                              ),
                              const SizedBox(height: AppSpacing.lg),
                              Text(
                                'coach_try_asking'.tr(),
                                style: Theme.of(context)
                                    .textTheme
                                    .labelLarge
                                    ?.copyWith(fontWeight: FontWeight.w700),
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              Wrap(
                                spacing: AppSpacing.sm,
                                runSpacing: AppSpacing.sm,
                                children: _suggestions
                                    .map(
                                      (key) => _SuggestionChip(
                                        label: key.tr(),
                                        onTap: sending
                                            ? null
                                            : () => _send(key.tr()),
                                      ),
                                    )
                                    .toList(),
                              ),
                            ],
                          )
                        : ListView.builder(
                            controller: _scroll,
                            padding: const EdgeInsets.fromLTRB(
                              AppSpacing.lg,
                              AppSpacing.md,
                              AppSpacing.lg,
                              AppSpacing.lg,
                            ),
                            itemCount: messages.length + (sending ? 1 : 0),
                            itemBuilder: (context, index) {
                              if (sending && index == messages.length) {
                                return const Align(
                                  alignment: AlignmentDirectional.centerStart,
                                  child: Padding(
                                    padding: EdgeInsets.only(top: AppSpacing.sm),
                                    child: _TypingBubble(),
                                  ),
                                );
                              }
                              final message = messages[index];
                              return _MessageBubble(
                                message: message,
                                extras: extrasByMessageId[message.id],
                                onRegenerateTraining: () => _send(
                                  'coach_prompt_regenerate_training'.tr(),
                                ),
                                onRegenerateNutrition: () => _send(
                                  'coach_prompt_regenerate_nutrition'.tr(),
                                ),
                                onReplaceFood: () => _fillAndFocus(
                                  'coach_prompt_replace_food'.tr(),
                                ),
                              );
                            },
                          ),
                  ),
                  SafeArea(
                    top: false,
                    child: Container(
                      padding: const EdgeInsets.fromLTRB(
                        AppSpacing.md,
                        AppSpacing.sm,
                        AppSpacing.md,
                        AppSpacing.md,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        border: const Border(
                          top: BorderSide(color: AppColors.border),
                        ),
                        boxShadow: AppShadows.lift,
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _input,
                              enabled: !sending,
                              minLines: 1,
                              maxLines: 4,
                              decoration: InputDecoration(
                                hintText: 'coach_input_hint'.tr(),
                                filled: true,
                                fillColor: AppColors.elevated,
                              ),
                              textInputAction: TextInputAction.send,
                              onSubmitted: sending ? null : (_) => _send(),
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Material(
                            color: sending ? AppColors.primaryPressed : AppColors.primary,
                            borderRadius: BorderRadius.circular(AppRadii.md),
                            child: InkWell(
                              onTap: sending ? null : () => _send(),
                              borderRadius: BorderRadius.circular(AppRadii.md),
                              splashColor:
                                  AppColors.primary.withValues(alpha: 0.28),
                              child: SizedBox(
                                width: 48,
                                height: 48,
                                child: Icon(
                                  Icons.send_rounded,
                                  color: sending
                                      ? AppColors.textPrimary
                                      : AppColors.onPrimary,
                                  size: 20,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
          };
        },
      ),
    );
  }
}

class _CoachIdentityHeader extends StatelessWidget {
  const _CoachIdentityHeader();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.sm,
        AppSpacing.lg,
        AppSpacing.md,
      ),
      decoration: BoxDecoration(
        gradient: AppGradients.aiCard,
        boxShadow: AppShadows.ai,
        border: const Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.ai.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(AppRadii.sm),
              border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
            ),
            child: const Icon(
              Icons.smart_toy_outlined,
              color: AppColors.ai,
              size: 22,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'coach_title'.tr(),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 2),
                Text(
                  'coach_header_sub'.tr(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          OctaBadge(label: 'OCTA FIT', color: AppColors.ai),
        ],
      ),
    );
  }
}

class _SuggestionChip extends StatelessWidget {
  const _SuggestionChip({required this.label, required this.onTap});

  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.elevated,
      borderRadius: BorderRadius.circular(AppRadii.full),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.full),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.full),
            border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
          ),
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w600,
                ),
          ),
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({
    required this.message,
    this.extras,
    this.onRegenerateTraining,
    this.onRegenerateNutrition,
    this.onReplaceFood,
  });

  final CoachMessage message;
  final CoachMessageExtras? extras;
  final VoidCallback? onRegenerateTraining;
  final VoidCallback? onRegenerateNutrition;
  final VoidCallback? onReplaceFood;

  @override
  Widget build(BuildContext context) {
    final isUser = message.isUser;
    final structured = extras?.hasStructured == true;
    final maxW = MediaQuery.sizeOf(context).width;
    final bubbleMax = structured
        ? (maxW > 900 ? 720.0 : maxW - 32)
        : maxW * 0.82;

    return Align(
      alignment: isUser
          ? AlignmentDirectional.centerEnd
          : AlignmentDirectional.centerStart,
      child: Container(
        margin: const EdgeInsets.only(bottom: AppSpacing.md),
        constraints: BoxConstraints(maxWidth: bubbleMax),
        child: Column(
          crossAxisAlignment:
              isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: isUser ? AppColors.primary : AppColors.elevated,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(AppRadii.lg),
                  topRight: const Radius.circular(AppRadii.lg),
                  bottomLeft:
                      Radius.circular(isUser ? AppRadii.lg : AppRadii.xs),
                  bottomRight:
                      Radius.circular(isUser ? AppRadii.xs : AppRadii.lg),
                ),
                border: Border.all(
                  color: isUser
                      ? AppColors.aiHover.withValues(alpha: 0.4)
                      : AppColors.ai.withValues(alpha: 0.12),
                ),
                boxShadow: isUser ? null : AppShadows.lift,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!isUser) ...[
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.auto_awesome,
                          size: 12,
                          color: AppColors.ai,
                        ),
                        const SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            'coach_title'.tr(),
                            style: Theme.of(context)
                                .textTheme
                                .labelSmall
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w700,
                                ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                  ],
                  _CoachPlainText(
                    text: message.content,
                    isUser: isUser,
                    compress: structured,
                  ),
                ],
              ),
            ),
            if (!isUser && structured) ...[
              const SizedBox(height: 8),
              if (extras!.hasTrainingPlan)
                CoachTrainingPlanCard(
                  plan: extras!.trainingPlan!,
                  groundedExercises: extras!.groundedExercises,
                  onRegenerate: onRegenerateTraining,
                ),
              if (extras!.hasNutritionPlan) ...[
                if (extras!.hasTrainingPlan) const SizedBox(height: 8),
                CoachNutritionPlanCard(
                  plan: extras!.nutritionPlan!,
                  onRegenerate: onRegenerateNutrition,
                  onReplacePrompt: onReplaceFood,
                ),
              ],
              if (extras!.hasNutritionLookup && !extras!.hasNutritionPlan) ...[
                const SizedBox(height: 8),
                CoachNutritionLookupCard(nutrition: extras!.nutrition!),
              ],
              if (extras!.hasGroundedExercises && !extras!.hasTrainingPlan) ...[
                const SizedBox(height: 8),
                CoachGroundedExercisesStrip(
                  exercises: extras!.groundedExercises,
                ),
              ],
              if (extras!.hasKnowledge) ...[
                const SizedBox(height: 8),
                CoachEvidenceSection(
                  knowledgeContext: extras!.knowledgeContext!,
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

/// Lightweight paragraph/bullet rendering without HTML.
class _CoachPlainText extends StatelessWidget {
  const _CoachPlainText({
    required this.text,
    required this.isUser,
    this.compress = false,
  });

  final String text;
  final bool isUser;
  final bool compress;

  @override
  Widget build(BuildContext context) {
    var body = text.trim();
    if (compress && body.length > 420) {
      body = '${body.substring(0, 420).trim()}…';
    }
    final style = TextStyle(
      color: isUser ? AppColors.onPrimary : AppColors.textPrimary,
      height: 1.45,
      fontSize: 15,
    );
    final lines = body.split('\n');
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final line in lines)
          if (line.trim().isEmpty)
            const SizedBox(height: 6)
          else if (line.trimLeft().startsWith('•') ||
              line.trimLeft().startsWith('- '))
            Padding(
              padding: const EdgeInsets.only(bottom: 2),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('•  ', style: style),
                  Expanded(
                    child: Text(
                      line.trimLeft().replaceFirst(RegExp(r'^[-•]\s*'), ''),
                      style: style,
                    ),
                  ),
                ],
              ),
            )
          else
            Padding(
              padding: const EdgeInsets.only(bottom: 2),
              child: Text(line, style: style),
            ),
      ],
    );
  }
}

class _TypingBubble extends StatefulWidget {
  const _TypingBubble();

  @override
  State<_TypingBubble> createState() => _TypingBubbleState();
}

class _TypingBubbleState extends State<_TypingBubble>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          AnimatedBuilder(
            animation: _c,
            builder: (_, _) {
              return Row(
                children: List.generate(3, (i) {
                  final t = ((_c.value + i * 0.2) % 1.0);
                  final opacity =
                      0.35 + (0.65 * (1 - (t - 0.5).abs() * 2).clamp(0.0, 1.0));
                  return Padding(
                    padding: const EdgeInsets.only(right: 4),
                    child: Opacity(
                      opacity: opacity,
                      child: Container(
                        width: 7,
                        height: 7,
                        decoration: const BoxDecoration(
                          color: AppColors.ai,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ),
                  );
                }),
              );
            },
          ),
          const SizedBox(width: 10),
          Text(
            'coach_thinking'.tr(),
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(color: AppColors.textPrimary),
          ),
        ],
      ),
    );
  }
}
