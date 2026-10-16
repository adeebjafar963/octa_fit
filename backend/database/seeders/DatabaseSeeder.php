<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator'],
        );

        $userRole = Role::query()->firstOrCreate(
            ['name' => 'user'],
            ['display_name' => 'User'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'app_name'],
            ['value' => 'OCTA FIT', 'group' => 'general'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'default_locale'],
            ['value' => 'en', 'group' => 'general'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'support_text'],
            ['value' => 'Contact support via your university supervisor.', 'group' => 'general'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'feature_coach_enabled'],
            ['value' => '1', 'group' => 'features'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'feature_physio_enabled'],
            ['value' => '1', 'group' => 'features'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'feature_motion_enabled'],
            ['value' => '1', 'group' => 'features'],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@octafit.test'],
            [
                'name' => 'OCTA Admin',
                'password' => Hash::make('Admin123!'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ],
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $admin->id],
            ['locale' => 'en', 'training_goal' => null],
        );

        $demo = User::query()->updateOrCreate(
            ['email' => 'demo@octafit.test'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('Demo123!'),
                'role_id' => $userRole->id,
                'is_active' => true,
            ],
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $demo->id],
            [
                'locale' => 'en',
                'age' => 25,
                'gender' => 'male',
                'height_cm' => 175,
                'weight_kg' => 75,
                'training_goal' => 'Build muscle',
            ],
        );

        $this->call(TrainingSeeder::class);
        $this->call(NutritionFoodSeeder::class);
        $this->call(KnowledgeSeeder::class);
        $this->call(StoreSeeder::class);
        $this->call(RealSupplementSeeder::class);
        $this->call(RealClothingSeeder::class);
        $this->call(RealEquipmentSeeder::class);
        $this->call(RealAccessoriesSeeder::class);
        $this->call(CommunitySeeder::class);
    }
}
