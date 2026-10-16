<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RealClothingSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealClothingImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
        Storage::fake('public');
    }

    public function test_real_clothing_import_is_idempotent_and_under_clothing_category(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(RealClothingSeeder::class);
        $this->seed(RealClothingSeeder::class);

        $this->assertSame(10, Product::query()->count());
        $this->assertSame(0, Product::query()->where('is_demo', true)->count());

        $clothingId = ProductCategory::query()->where('slug', 'sportswear')->value('id');
        $this->assertSame(10, Product::query()->where('category_id', $clothingId)->count());

        $tank = Product::query()->where('slug', 'athletic-racerback-tank-black-red')->firstOrFail();
        $this->assertSame('Athletic Racerback Tank Top', $tank->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', $tank->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', (string) $tank->description_en);
        $this->assertStringContainsString('/storage/products/real/clothing/athletic-racerback-tank.png', $tank->image_url);
        $this->assertGreaterThan(0, (float) $tank->price);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/store/products?category=sportswear')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 10);

        $this->getJson('/api/store/products/athletic-racerback-tank-black-red')
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Athletic Racerback Tank Top')
            ->assertJsonPath('data.is_demo', false);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $tank->id,
            'quantity' => 1,
        ])->assertOk();
    }
}
