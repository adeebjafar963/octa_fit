<?php

namespace App\Services;

use App\Models\User;
use App\Models\WaterIntake;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HydrationService
{
    public function __construct(
        private readonly HydrationGoalService $goalService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function today(User $user, ?string $timezone = null): array
    {
        [$tz, $date, $startUtc, $endUtc] = $this->dayBounds($timezone);

        $intakes = WaterIntake::query()
            ->where('user_id', $user->id)
            ->whereBetween('consumed_at', [$startUtc, $endUtc])
            ->orderBy('consumed_at')
            ->get();

        $consumed = (int) $intakes->sum('amount_ml');
        $goal = $this->goalService->effectiveGoal($user);
        $remaining = max(0, $goal - $consumed);
        $progress = $goal > 0 ? min(1.0, round($consumed / $goal, 4)) : 0.0;

        return [
            'date' => $date,
            'timezone' => $tz,
            'goal_ml' => $goal,
            'recommended_goal_ml' => $this->goalService->recommend($user),
            'goal_is_custom' => (bool) ($user->fresh(['profile'])->profile?->water_goal_is_custom),
            'consumed_ml' => $consumed,
            'remaining_ml' => $remaining,
            'progress' => $progress,
            'intake_count' => $intakes->count(),
            'goal_met' => $consumed >= $goal,
            'intakes' => $intakes->map(fn (WaterIntake $i) => $this->intakePayload($i, $tz))->all(),
            'streak_days' => $this->streakDays($user, $tz),
        ];
    }

    public function addIntake(User $user, int $amountMl, ?Carbon $consumedAt = null, ?string $timezone = null): WaterIntake
    {
        if ($amountMl < 1 || $amountMl > 5000) {
            throw new HttpException(422, 'Invalid water amount.');
        }

        $intake = WaterIntake::query()->create([
            'user_id' => $user->id,
            'amount_ml' => $amountMl,
            'consumed_at' => $consumedAt ?? now(),
        ]);

        // Ensure goal exists for first-time users.
        $this->goalService->effectiveGoal($user);

        return $intake;
    }

    public function deleteIntake(User $user, int $intakeId): void
    {
        $intake = WaterIntake::query()
            ->where('user_id', $user->id)
            ->where('id', $intakeId)
            ->first();

        if ($intake === null) {
            throw new HttpException(404, 'Water intake not found.');
        }

        $intake->delete();
    }

    /**
     * @return array{days: list<array<string, mixed>>, streak_days: int}
     */
    public function history(User $user, int $days = 14, ?string $timezone = null): array
    {
        $days = max(1, min(60, $days));
        [$tz] = $this->dayBounds($timezone);
        $goal = $this->goalService->effectiveGoal($user);

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now($tz)->subDays($i)->toDateString();
            $start = Carbon::parse($date, $tz)->startOfDay()->utc();
            $end = Carbon::parse($date, $tz)->endOfDay()->utc();
            $consumed = (int) WaterIntake::query()
                ->where('user_id', $user->id)
                ->whereBetween('consumed_at', [$start, $end])
                ->sum('amount_ml');

            $result[] = [
                'date' => $date,
                'goal_ml' => $goal,
                'consumed_ml' => $consumed,
                'remaining_ml' => max(0, $goal - $consumed),
                'progress' => $goal > 0 ? min(1.0, round($consumed / $goal, 4)) : 0.0,
                'goal_met' => $consumed >= $goal,
            ];
        }

        return [
            'days' => $result,
            'streak_days' => $this->streakDays($user, $tz),
        ];
    }

    /**
     * Compact snapshot for AI context (today only).
     *
     * @return array<string, mixed>
     */
    public function aiSnapshot(User $user, ?string $timezone = null): array
    {
        $today = $this->today($user, $timezone);

        return [
            'date' => $today['date'],
            'goal_ml' => $today['goal_ml'],
            'consumed_ml' => $today['consumed_ml'],
            'remaining_ml' => $today['remaining_ml'],
            'progress' => $today['progress'],
            'goal_met' => $today['goal_met'],
            'intake_count' => $today['intake_count'],
            'streak_days' => $today['streak_days'],
        ];
    }

    /**
     * Consecutive days (including today) where consumed >= goal.
     * Definition: a day counts when consumed_ml >= goal_ml.
     */
    public function streakDays(User $user, string $timezone): int
    {
        $goal = $this->goalService->effectiveGoal($user);
        $streak = 0;

        for ($i = 0; $i < 90; $i++) {
            $date = Carbon::now($timezone)->subDays($i)->toDateString();
            $start = Carbon::parse($date, $timezone)->startOfDay()->utc();
            $end = Carbon::parse($date, $timezone)->endOfDay()->utc();
            $consumed = (int) WaterIntake::query()
                ->where('user_id', $user->id)
                ->whereBetween('consumed_at', [$start, $end])
                ->sum('amount_ml');

            if ($consumed >= $goal) {
                $streak++;
            } else {
                // Today with 0 intake does not break "previous" streak for display —
                // but product definition: streak requires consecutive met days from today.
                break;
            }
        }

        return $streak;
    }

    /**
     * @return array{0: string, 1: string, 2: Carbon, 3: Carbon}
     */
    private function dayBounds(?string $timezone): array
    {
        $tz = $this->safeTimezone($timezone);
        $date = Carbon::now($tz)->toDateString();
        $startUtc = Carbon::parse($date, $tz)->startOfDay()->utc();
        $endUtc = Carbon::parse($date, $tz)->endOfDay()->utc();

        return [$tz, $date, $startUtc, $endUtc];
    }

    private function safeTimezone(?string $timezone): string
    {
        $tz = $timezone !== null && trim($timezone) !== '' ? trim($timezone) : (string) config('app.timezone', 'UTC');
        try {
            Carbon::now($tz);

            return $tz;
        } catch (\Throwable) {
            return (string) config('app.timezone', 'UTC');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function intakePayload(WaterIntake $intake, string $timezone): array
    {
        return [
            'id' => $intake->id,
            'amount_ml' => $intake->amount_ml,
            'consumed_at' => $intake->consumed_at?->utc()->toIso8601String(),
            'consumed_at_local' => $intake->consumed_at?->timezone($timezone)->toIso8601String(),
        ];
    }
}
