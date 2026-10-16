<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
        $this->seed(StoreSeeder::class);
    }

    private function makeProduct(array $overrides = []): Product
    {
        $categorySlug = $overrides['category'] ?? 'supplements';
        unset($overrides['category']);

        $category = ProductCategory::query()->where('slug', $categorySlug)->firstOrFail();

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name_en' => 'Test Product',
            'name_ar' => 'منتج اختبار',
            'slug' => 'test-product-'.uniqid(),
            'description_en' => 'Test-only product for API coverage.',
            'description_ar' => 'منتج اختبار لتغطية واجهة المتجر.',
            'price' => 10.00,
            'compare_at_price' => null,
            'stock_quantity' => 50,
            'image_url' => null,
            'sku' => 'TEST-'.strtoupper(substr(uniqid(), -6)),
            'is_active' => true,
            'is_demo' => false,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_categories_require_auth(): void
    {
        $this->getJson('/api/store/categories')->assertUnauthorized();
    }

    public function test_categories_list(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/store/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name_en', 'Supplements')
            ->assertJsonPath('data.1.name_en', 'Clothing');
    }

    public function test_products_list_empty_without_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/store/products')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_products_list_and_search_and_filter(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->makeProduct([
            'name_en' => 'Whey Protein',
            'name_ar' => 'بروتين',
            'slug' => 'whey-protein-test',
            'category' => 'supplements',
        ]);
        $this->makeProduct([
            'name_en' => 'Yoga Mat',
            'name_ar' => 'سجادة',
            'slug' => 'yoga-mat-test',
            'category' => 'equipment',
            'price' => 22.00,
        ]);

        $this->getJson('/api/store/products')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->getJson('/api/store/products?search=whey')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name_en', 'Whey Protein');

        $this->getJson('/api/store/products?category=supplements')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_product_details_and_inactive_hidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->makeProduct([
            'name_en' => 'Yoga Mat',
            'slug' => 'yoga-mat',
            'category' => 'equipment',
        ]);

        $this->getJson('/api/store/products/yoga-mat')
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Yoga Mat');

        $product->update(['is_active' => false]);
        $this->getJson('/api/store/products/yoga-mat')->assertNotFound();
    }

    public function test_cart_add_update_remove_clear_and_stock_limit(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $this->makeProduct([
            'name_en' => 'Jump Rope',
            'slug' => 'jump-rope',
            'category' => 'equipment',
            'stock_quantity' => 60,
            'price' => 9.50,
        ]);

        $this->getJson('/api/store/cart')
            ->assertOk()
            ->assertJsonPath('data.items_count', 0);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk()->assertJsonPath('data.items_count', 2);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->assertJsonPath('data.items_count', 3);

        $itemId = $this->getJson('/api/store/cart')->json('data.items.0.id');

        $this->putJson('/api/store/cart/items/'.$itemId, ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 5);

        $this->putJson('/api/store/cart/items/'.$itemId, ['quantity' => 999])
            ->assertStatus(422);

        $this->deleteJson('/api/store/cart/items/'.$itemId)
            ->assertOk()
            ->assertJsonPath('data.items_count', 0);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->deleteJson('/api/store/cart')
            ->assertOk()
            ->assertJsonPath('data.items_count', 0);
    }

    public function test_cannot_add_inactive_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->makeProduct(['slug' => 'gym-towel', 'category' => 'accessories']);
        $product->update(['is_active' => false]);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_checkout_empty_cart_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_COD,
        ])->assertStatus(422);
    }

    public function test_unknown_and_legacy_payment_methods_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->makeProduct(['slug' => 'shaker-bottle', 'category' => 'accessories']);
        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_DEMO,
        ])->assertStatus(422);

        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_SHAM_CASH,
        ])->assertStatus(422);

        $this->postJson('/api/store/orders', [
            'payment_method' => 'wire_transfer',
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_on_delivery_does_not_require_a_phone(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->makeProduct(['slug' => 'gym-bag', 'category' => 'accessories', 'stock_quantity' => 8]);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_COD,
        ])->assertCreated()
            ->assertJsonPath('data.payment_method', 'cash_on_delivery')
            ->assertJsonPath('data.payment_phone', null);

        $this->assertDatabaseHas('orders', [
            'payment_method' => 'cash_on_delivery',
            'payment_phone' => null,
        ]);
    }

    public function test_syriatel_cash_requires_a_valid_phone(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->makeProduct(['slug' => 'towel', 'category' => 'accessories', 'stock_quantity' => 6]);
        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_SYRIATEL_CASH,
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['payment_phone']]);

        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_SYRIATEL_CASH,
            'payment_phone' => '12345',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['payment_phone']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(6, $product->fresh()->stock_quantity);
    }

    public function test_successful_checkout_creates_order_decreases_stock_clears_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $this->makeProduct([
            'name_en' => 'Shaker Bottle',
            'name_ar' => 'زجاجة شيكر',
            'slug' => 'shaker-bottle',
            'category' => 'accessories',
            'price' => 9.99,
            'stock_quantity' => 70,
        ]);
        $stockBefore = $product->stock_quantity;

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $response = $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_SYRIATEL_CASH,
            'payment_phone' => '0933123456',
            'notes' => 'Checkout note',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_method', 'syriatel_cash')
            ->assertJsonPath('data.payment_phone', '0933123456')
            ->assertJsonStructure(['data' => ['order_number', 'items', 'subtotal', 'total']]);

        $this->assertDatabaseHas('orders', [
            'payment_method' => 'syriatel_cash',
            'payment_phone' => '0933123456',
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame($stockBefore - 2, $product->fresh()->stock_quantity);
        $this->getJson('/api/store/cart')->assertJsonPath('data.items_count', 0);

        $orderId = $response->json('data.id');
        $this->getJson('/api/store/orders')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/store/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name_en', 'Shaker Bottle');
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $product = $this->makeProduct(['slug' => 'hand-grip', 'category' => 'equipment']);
        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $orderId = $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_COD,
        ])->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson('/api/store/orders/'.$orderId)->assertNotFound();
    }

    public function test_insufficient_stock_prevents_checkout(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->makeProduct([
            'slug' => 'mma-gloves',
            'category' => 'equipment',
            'stock_quantity' => 18,
        ]);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $product->update(['stock_quantity' => 1]);

        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_COD,
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    public function test_server_calculates_totals(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = $this->makeProduct([
            'slug' => 'protein-bar',
            'category' => 'supplements',
            'price' => 3.49,
        ]);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $expected = number_format((float) $product->price * 3, 2, '.', '');

        $this->postJson('/api/store/orders', [
            'payment_method' => Order::PAYMENT_COD,
            'total' => '1.00',
            'subtotal' => '1.00',
        ])->assertCreated()
            ->assertJsonPath('data.total', $expected)
            ->assertJsonPath('data.subtotal', $expected);
    }

    public function test_store_seeder_does_not_create_demo_products(): void
    {
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Product::query()->where('is_demo', true)->count());
        $this->assertSame(4, ProductCategory::query()->active()->count());
    }
}
