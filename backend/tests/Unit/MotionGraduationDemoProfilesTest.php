<?php

namespace Tests\Unit;

use App\Services\Motion\Demo\MotionDemoScenarioService;
use App\Services\MotionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotionGraduationDemoProfilesTest extends TestCase
{
    private MotionDemoScenarioService $demo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->demo = new MotionDemoScenarioService;
    }

    #[Test]
    public function supported_exercises_are_squat_and_deadlift_only(): void
    {
        $this->assertSame(['squat', 'deadlift'], MotionService::supportedExercises());
    }

    #[Test]
    public function demo_mode_defaults_to_false_in_phpunit(): void
    {
        $this->assertFalse((bool) config('services.ai.motion_demo_mode'));
        $this->assertNull($this->demo->tryBuild('squat', 'en'));
    }

    #[Test]
    public function squat_profile_is_video_specific_when_enabled(): void
    {
        config(['services.ai.motion_demo_mode' => true]);

        $squat = $this->demo->tryBuild('squat', 'en');
        $this->assertNotNull($squat);
        $this->assertSame('squat', $squat['exercise']);
        $this->assertTrue($squat['structured']);
        $this->assertNotEmpty($squat['positive_points']);
        $this->assertNotEmpty($squat['movement_points']);
        $this->assertNotEmpty($squat['coaching_cues']);
        $this->assertNotEmpty($squat['improvement_drills']);
        $this->assertNotEmpty($squat['next_attempt']);
        $this->assertNotEmpty($squat['disclaimer']);

        $blob = mb_strtolower(json_encode($squat, JSON_UNESCAPED_UNICODE) ?: '');
        $this->assertStringContainsString('forward trunk lean', $blob);
        $this->assertStringContainsString('depth', $blob);
        $this->assertStringContainsString('goblet squat', $blob);
        $this->assertStringContainsString('box squat', $blob);
        $this->assertStringContainsString('tempo squat', $blob);
        $this->assertStringNotContainsString('knee valgus', $blob);
        $this->assertStringNotContainsString('knees collapsing', $blob);
        $this->assertStringNotContainsString('hip hinge drill', $blob);
        $this->assertStringNotContainsString('back rounding at the bottom', $blob);
        $this->assertStringNotContainsString('knee_angle', $blob);
        $this->assertStringNotContainsString('27.4', $blob);

        foreach ($squat['movement_points'] as $point) {
            $this->assertArrayHasKey('correction', $point);
            $this->assertNotSame('', (string) $point['correction']);
        }
    }

    #[Test]
    public function deadlift_profile_is_video_specific_when_enabled(): void
    {
        config(['services.ai.motion_demo_mode' => true]);

        $deadlift = $this->demo->tryBuild('deadlift', 'en');
        $this->assertNotNull($deadlift);
        $this->assertSame('deadlift', $deadlift['exercise']);
        $this->assertTrue($deadlift['structured']);
        $this->assertNotEmpty($deadlift['positive_points']);
        $this->assertNotEmpty($deadlift['movement_points']);
        $this->assertNotEmpty($deadlift['improvement_drills']);

        $blob = mb_strtolower(json_encode($deadlift, JSON_UNESCAPED_UNICODE) ?: '');
        $this->assertStringContainsString('back rounding', $blob);
        $this->assertStringContainsString('hip-hinge', $blob);
        $this->assertStringContainsString('hip hinge drill', $blob);
        $this->assertStringContainsString('load', $blob);
        $this->assertStringNotContainsString('forward trunk lean', $blob);
        $this->assertStringNotContainsString('goblet squat', $blob);
        $this->assertStringNotContainsString('box squat', $blob);
        $this->assertStringNotContainsString('knee valgus', $blob);
        $this->assertStringNotContainsString('hip_angle', $blob);
        $this->assertStringNotContainsString('bar velocity', $blob);

        foreach ($deadlift['movement_points'] as $point) {
            $this->assertArrayHasKey('correction', $point);
            $this->assertNotSame('', (string) $point['correction']);
        }
    }

    #[Test]
    public function squat_and_deadlift_profiles_never_mix(): void
    {
        config(['services.ai.motion_demo_mode' => true]);

        $squat = $this->demo->tryBuild('squat', 'en');
        $deadlift = $this->demo->tryBuild('deadlift', 'en');
        $this->assertNotNull($squat);
        $this->assertNotNull($deadlift);
        $this->assertNotSame($squat['overall_summary'], $deadlift['overall_summary']);

        $squatBlob = mb_strtolower(json_encode($squat, JSON_UNESCAPED_UNICODE) ?: '');
        $deadliftBlob = mb_strtolower(json_encode($deadlift, JSON_UNESCAPED_UNICODE) ?: '');

        $this->assertStringNotContainsString('back rounding at the bottom', $squatBlob);
        $this->assertStringNotContainsString('forward trunk lean', $deadliftBlob);
        $this->assertStringNotContainsString('goblet squat', $deadliftBlob);
        $this->assertStringNotContainsString('hip hinge drill', $squatBlob);
    }

    #[Test]
    public function arabic_profiles_exist_and_use_required_disclaimer(): void
    {
        config(['services.ai.motion_demo_mode' => true]);
        $en = $this->demo->tryBuild('squat', 'en');
        $ar = $this->demo->tryBuild('squat', 'ar');
        $this->assertNotNull($en);
        $this->assertNotNull($ar);
        $this->assertNotSame($en['overall_summary'], $ar['overall_summary']);
        $this->assertStringContainsString('القرفصاء', (string) $ar['title']);
        $this->assertStringContainsString('ميل', (string) ($ar['overall_summary'] ?? ''));
        $this->assertStringContainsString('تعليمي', (string) $ar['disclaimer']);
        $this->assertStringContainsString('تشخيصاً طبياً', (string) $ar['disclaimer']);
        $this->assertNotEmpty($ar['positive_points']);

        $deadliftAr = $this->demo->tryBuild('deadlift', 'ar');
        $this->assertNotNull($deadliftAr);
        $this->assertStringContainsString('تقوس', (string) ($deadliftAr['overall_summary'] ?? ''));
        $this->assertStringNotContainsString('ميل واضح للجذع للأمام', (string) ($deadliftAr['overall_summary'] ?? ''));
    }

    #[Test]
    public function unsupported_exercises_have_no_demo_profile(): void
    {
        config(['services.ai.motion_demo_mode' => true]);
        $this->assertNull($this->demo->tryBuild('bench_press', 'en'));
        $this->assertNull($this->demo->tryBuild('push_up', 'ar'));
        $this->assertNull($this->demo->tryBuild('lunge', 'en'));
    }
}
