<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TrainingCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        $this->userRole = Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    private function normalUser(): User
    {
        return User::factory()->create([
            'role_id' => $this->userRole->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_admin_endpoint_returns_401(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_normal_user_forbidden(): void
    {
        Sanctum::actingAs($this->normalUser());
        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_admin_dashboard_stats(): void
    {
        Sanctum::actingAs($this->admin());
        $this->normalUser();

        $response = $this->getJson('/api/admin/dashboard');
        $response->assertOk()
            ->assertJsonPath('data.stats.total_users', 2)
            ->assertJsonPath('data.stats.total_admins', 1);
    }

    public function test_admin_can_list_and_view_users(): void
    {
        Sanctum::actingAs($this->admin());
        $user = $this->normalUser();

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->getJson('/api/admin/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_role_update_and_last_admin_protection(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $user = $this->normalUser();
        $this->putJson('/api/admin/users/'.$user->id.'/role', ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role.name', 'admin');

        // Two admins now; demote original admin should work
        $this->putJson('/api/admin/users/'.$admin->id.'/role', ['role' => 'user'])
            ->assertOk();

        // Only one admin left — cannot demote
        $this->putJson('/api/admin/users/'.$user->id.'/role', ['role' => 'user'])
            ->assertStatus(422);
    }

    public function test_training_category_and_exercise_crud(): void
    {
        Sanctum::actingAs($this->admin());

        $create = $this->postJson('/api/admin/training/categories', [
            'name_en' => 'Mobility',
            'name_ar' => 'مرونة',
            'type' => 'bodybuilding',
        ]);
        $create->assertCreated();
        $categoryId = $create->json('data.id');

        $this->putJson('/api/admin/training/categories/'.$categoryId, [
            'name_en' => 'Mobility Updated',
        ])->assertOk();

        $exercise = $this->postJson('/api/admin/training/exercises', [
            'training_category_id' => $categoryId,
            'name_en' => 'Hip Opener',
            'name_ar' => 'فتح الورك',
            'difficulty' => 'beginner',
        ]);
        $exercise->assertCreated();
        $exerciseId = $exercise->json('data.id');

        $this->postJson('/api/admin/training/exercises/'.$exerciseId.'/videos', [
            'title_en' => 'Demo',
            'title_ar' => 'تجريبي',
            'video_url' => 'https://example.com/video.mp4',
            'is_placeholder' => true,
        ])->assertCreated();

        $this->deleteJson('/api/admin/training/exercises/'.$exerciseId)->assertOk();
        $this->deleteJson('/api/admin/training/categories/'.$categoryId)->assertOk();
    }

    public function test_product_crud_and_validation(): void
    {
        Sanctum::actingAs($this->admin());

        $cat = $this->postJson('/api/admin/store/categories', [
            'name_en' => 'Gear',
            'name_ar' => 'معدات',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/admin/store/products', [
            'category_id' => $cat,
            'name_en' => 'Belt',
            'name_ar' => 'حزام',
            'price' => -5,
            'stock_quantity' => 3,
        ])->assertStatus(422);

        $product = $this->postJson('/api/admin/store/products', [
            'category_id' => $cat,
            'name_en' => 'Belt',
            'name_ar' => 'حزام',
            'price' => 19.99,
            'stock_quantity' => 5,
        ])->assertCreated()->json('data.id');

        $this->putJson('/api/admin/store/products/'.$product, [
            'stock_quantity' => 12,
            'price' => 21.5,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.stock_quantity', 12);

        $this->deleteJson('/api/admin/store/products/'.$product)->assertOk();
    }

    public function test_admin_orders_list_and_status(): void
    {
        Sanctum::actingAs($this->admin());
        $customer = $this->normalUser();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-TEST-1',
            'status' => Order::STATUS_PENDING,
            'payment_method' => Order::PAYMENT_DEMO,
            'subtotal' => 10,
            'total' => 10,
        ]);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.order_number', 'ORD-TEST-1');

        $this->putJson('/api/admin/orders/'.$order->id.'/status', [
            'status' => Order::STATUS_COMPLETED,
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        Sanctum::actingAs($this->normalUser());
        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_admin_can_moderate_post(): void
    {
        $author = $this->normalUser();
        $post = Post::query()->create([
            'user_id' => $author->id,
            'content' => 'Spam content',
            'is_published' => true,
        ]);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/community/posts')->assertOk();
        $this->deleteJson('/api/admin/community/posts/'.$post->id)->assertOk();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);

        $another = Post::query()->create([
            'user_id' => $author->id,
            'content' => 'Another post',
            'is_published' => true,
        ]);

        Sanctum::actingAs($this->normalUser());
        $this->getJson('/api/admin/community/posts')->assertForbidden();
        $this->deleteJson('/api/admin/community/posts/'.$another->id)->assertForbidden();
    }

    public function test_safe_settings_update_rejects_secrets(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/settings')->assertOk();

        $this->putJson('/api/admin/settings', [
            'settings' => [
                'app_name' => 'OCTA FIT Admin',
                'feature_coach_enabled' => '1',
            ],
        ])->assertOk()->assertJsonPath('data.items.0.key', fn ($v) => true);

        $this->assertDatabaseHas('settings', ['key' => 'app_name', 'value' => 'OCTA FIT Admin']);

        $this->putJson('/api/admin/settings', [
            'settings' => [
                'openai_api_key' => 'sk-secret',
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('settings', ['key' => 'openai_api_key']);
    }
}
