<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RealAccessoriesSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealAccessoriesImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
        Storage::fake('public');
    }

    public function test_real_accessories_import_under_accessories_category(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(RealAccessoriesSeeder::class);
        $this->seed(RealAccessoriesSeeder::class);

        $expected = 17;
        $this->assertSame($expected, Product::query()->count());
        $this->assertSame(0, Product::query()->where('is_demo', true)->count());

        $accessoriesId = ProductCategory::query()->where('slug', 'accessories')->value('id');
        $this->assertSame($expected, Product::query()->where('category_id', $accessoriesId)->count());

        $shaker = Product::query()->where('slug', 'shaker-bottle-700ml')->firstOrFail();
        $this->assertSame('Shaker Bottle 700ml', $shaker->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', $shaker->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', (string) $shaker->description_en);
        $this->assertStringContainsString('/storage/products/real/accessories/shaker-bottle-700ml.png', $shaker->image_url);

        $proHits = Product::query()
            ->where(function ($q) {
                $q->where('name_en', 'like', '%OCTA FIT PRO%')
                    ->orWhere('description_en', 'like', '%OCTA FIT PRO%');
            })
            ->count();
        $this->assertSame(0, $proHits);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/store/products?category=accessories')
            ->assertOk()
            ->assertJsonPath('data.meta.total', $expected);

        $this->getJson('/api/store/products/shaker-bottle-700ml')
            ->assertOk()
            ->assertJsonPath('data.is_demo', false);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $shaker->id,
            'quantity' => 1,
        ])->assertOk();
    }
}
