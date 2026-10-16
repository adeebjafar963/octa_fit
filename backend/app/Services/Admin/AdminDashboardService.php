<?php

namespace App\Services\Admin;

use App\Models\CoachConversation;
use App\Models\Exercise;
use App\Models\Order;
use App\Models\PhysioAssessment;
use App\Models\Post;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterIntake;

class AdminDashboardService
{
    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');

        return [
            'total_users' => User::query()->count(),
            'total_admins' => $adminRoleId
                ? User::query()->where('role_id', $adminRoleId)->count()
                : 0,
            'total_exercises' => Exercise::query()->count(),
            'total_products' => Product::query()->count(),
            'active_products' => Product::query()->where('is_active', true)->count(),
            'total_orders' => Order::query()->count(),
            'total_posts' => Post::query()->count(),
            'total_coach_conversations' => CoachConversation::query()->count(),
            'total_physio_assessments' => PhysioAssessment::query()->count(),
            'hydration_active_users' => (int) WaterIntake::query()
                ->where('consumed_at', '>=', now()->subDays(7))
                ->selectRaw('COUNT(DISTINCT user_id) as c')
                ->value('c'),
            'hydration_intakes_7d' => WaterIntake::query()
                ->where('consumed_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }
}
