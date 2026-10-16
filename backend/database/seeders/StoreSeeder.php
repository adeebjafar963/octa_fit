<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Ensures the four top-level Store categories only.
 * Does NOT create any products — the catalog comes from RealSupplementSeeder
 * (and Admin-created real products). Demo/default products are purged on every run.
 */
class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->purgeDemoProducts();
        $this->ensureFourCategories();
        $this->deactivateObsoleteCategories();
    }

    private function purgeDemoProducts(): void
    {
        Product::query()
            ->where(function ($q) {
                $q->where('is_demo', true)
                    ->orWhere('image_url', 'like', '%demo.octafit.test%')
                    ->orWhere('description_en', 'like', '%Demonstration / sample%')
                    ->orWhere('description_en', 'like', '%for the graduation store%');
            })
            ->delete();
    }

    private function ensureFourCategories(): void
    {
        $categories = [
            [
                'slug' => 'supplements',
                'name_en' => 'Supplements',
                'name_ar' => 'المكملات',
                'description_en' => 'Sports nutrition and dietary supplements.',
                'description_ar' => 'مكملات التغذية الرياضية والغذائية.',
                'sort_order' => 1,
            ],
            [
                'slug' => 'sportswear',
                'name_en' => 'Clothing',
                'name_ar' => 'الملابس',
                'description_en' => 'Training and sports clothing.',
                'description_ar' => 'ملابس التدريب والرياضة.',
                'sort_order' => 2,
            ],
            [
                'slug' => 'equipment',
                'name_en' => 'Equipment',
                'name_ar' => 'المعدات',
                'description_en' => 'Training equipment and gear.',
                'description_ar' => 'معدات وأدوات التدريب.',
                'sort_order' => 3,
            ],
            [
                'slug' => 'accessories',
                'name_en' => 'Accessories',
                'name_ar' => 'الإكسسوارات',
                'description_en' => 'Gym and training accessories.',
                'description_ar' => 'إكسسوارات النادي والتدريب.',
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $row) {
            ProductCategory::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name_en' => $row['name_en'],
                    'name_ar' => $row['name_ar'],
                    'description_en' => $row['description_en'],
                    'description_ar' => $row['description_ar'],
                    'image_url' => null,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }

    private function deactivateObsoleteCategories(): void
    {
        $keep = ['supplements', 'sportswear', 'equipment', 'accessories'];

        ProductCategory::query()
            ->whereNotIn('slug', $keep)
            ->update(['is_active' => false]);
    }
}
