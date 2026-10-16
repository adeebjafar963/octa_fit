<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RealSupplementSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealSupplementImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
        Storage::fake('public');
    }

    public function test_real_supplement_import_uses_four_main_categories_only(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(RealSupplementSeeder::class);
        $this->seed(RealSupplementSeeder::class);

        $activeSlugs = ProductCategory::query()->active()->orderBy('sort_order')->pluck('slug')->all();
        $this->assertSame(['supplements', 'sportswear', 'equipment', 'accessories'], $activeSlugs);

        $this->assertSame(15, Product::query()->where('slug', 'like', 'octa-fit-pro-%')->count());
        $this->assertSame(16, Product::query()->count());
        $this->assertSame(16, Product::query()->where('is_demo', false)->count());
        $this->assertSame(0, Product::query()->where('is_demo', true)->count());
        $this->assertFalse((bool) Product::query()->where('slug', 'creatine-monohydrate')->value('is_demo'));

        $supplementsId = ProductCategory::query()->where('slug', 'supplements')->value('id');
        $this->assertSame(16, Product::query()->where('category_id', $supplementsId)->count());

        foreach (['protein', 'creatine', 'pre-workout', 'amino-acids', 'vitamins-minerals', 'recovery'] as $slug) {
            $cat = ProductCategory::query()->where('slug', $slug)->first();
            if ($cat !== null) {
                $this->assertFalse($cat->is_active);
            }
            $this->assertSame(
                0,
                Product::query()->whereHas('category', fn ($q) => $q->where('slug', $slug))->count(),
            );
        }

        $pre = Product::query()->where('slug', 'octa-fit-pro-pre-workout-450g')->firstOrFail();
        $this->assertSame('Pre-Workout', $pre->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', $pre->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', (string) $pre->description_en);
        $this->assertGreaterThan(0, (float) $pre->price);
        $this->assertStringContainsString('/storage/products/real/pre-workout.png', $pre->image_url);

        $proHits = Product::query()
            ->where(function ($q) {
                $q->where('name_en', 'like', '%OCTA FIT PRO%')
                    ->orWhere('description_en', 'like', '%OCTA FIT PRO%')
                    ->orWhere('name_en', 'like', '%OCTAFIT PRO%')
                    ->orWhere('description_en', 'like', '%OCTAFIT PRO%');
            })
            ->count();
        $this->assertSame(0, $proHits);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/store/categories')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->getJson('/api/store/products/octa-fit-pro-pre-workout-450g')
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Pre-Workout')
            ->assertJsonPath('data.is_demo', false);

        $this->assertSame(16, $this->getJson('/api/store/products?category=supplements')->json('data.meta.total'));
        $this->assertSame(0, $this->getJson('/api/store/products?category=sportswear')->json('data.meta.total'));

        $this->postJson('/api/store/cart/items', [
            'product_id' => $pre->id,
            'quantity' => 1,
        ])->assertOk();
    }

    public function test_store_seeder_purges_demo_products_and_does_not_recreate_them(): void
    {
        $this->seed(StoreSeeder::class);
        $supplements = ProductCategory::query()->where('slug', 'supplements')->firstOrFail();

        Product::query()->create([
            'category_id' => $supplements->id,
            'name_en' => 'Fake Demo',
            'name_ar' => 'تجريبي',
            'slug' => 'fake-demo-product',
            'description_en' => 'Demonstration / sample product for OCTA FIT graduation store.',
            'description_ar' => 'تجريبي',
            'price' => 1,
            'stock_quantity' => 1,
            'image_url' => 'https://demo.octafit.test/placeholder/store/products/fake.png',
            'sku' => 'FAKE-01',
            'is_active' => true,
            'is_demo' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame(1, Product::query()->count());
        $this->seed(StoreSeeder::class);
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Product::query()->where('is_demo', true)->count());
    }
}
