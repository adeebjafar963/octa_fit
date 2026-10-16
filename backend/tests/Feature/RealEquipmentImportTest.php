<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RealEquipmentSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealEquipmentImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
        Storage::fake('public');
    }

    public function test_real_equipment_import_under_equipment_category(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(RealEquipmentSeeder::class);
        $this->seed(RealEquipmentSeeder::class);

        $expected = 27;
        $this->assertSame($expected, Product::query()->count());
        $this->assertSame(0, Product::query()->where('is_demo', true)->count());

        $equipmentId = ProductCategory::query()->where('slug', 'equipment')->value('id');
        $this->assertSame($expected, Product::query()->where('category_id', $equipmentId)->count());

        $smith = Product::query()->where('slug', 'smith-machine')->firstOrFail();
        $this->assertSame('Smith Machine', $smith->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', $smith->name_en);
        $this->assertStringNotContainsString('OCTA FIT PRO', (string) $smith->description_en);
        $this->assertStringContainsString('/storage/products/real/equipment/smith-machine.png', $smith->image_url);

        $ufc = Product::query()->where('slug', 'ufc-venum-shin-guards')->firstOrFail();
        $this->assertStringContainsString('UFC', $ufc->name_en);
        $this->assertStringContainsString('Venum', $ufc->name_en);

        $proHits = Product::query()
            ->where(function ($q) {
                $q->where('name_en', 'like', '%OCTA FIT PRO%')
                    ->orWhere('description_en', 'like', '%OCTA FIT PRO%')
                    ->orWhere('name_ar', 'like', '%OCTA FIT PRO%')
                    ->orWhere('description_ar', 'like', '%OCTA FIT PRO%');
            })
            ->count();
        $this->assertSame(0, $proHits);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/store/products?category=equipment')
            ->assertOk()
            ->assertJsonPath('data.meta.total', $expected);

        $this->getJson('/api/store/products/smith-machine')
            ->assertOk()
            ->assertJsonPath('data.is_demo', false);

        $this->postJson('/api/store/cart/items', [
            'product_id' => $smith->id,
            'quantity' => 1,
        ])->assertOk();
    }
}
