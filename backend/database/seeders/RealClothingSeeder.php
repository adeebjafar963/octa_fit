<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Imports real clothing products from uploaded garment images.
 * All products belong to Clothing (sportswear slug / الملابس).
 * Names and descriptions never include "OCTA FIT PRO".
 */
class RealClothingSeeder extends Seeder
{
    public function run(): void
    {
        $clothing = ProductCategory::query()->updateOrCreate(
            ['slug' => 'sportswear'],
            [
                'name_en' => 'Clothing',
                'name_ar' => 'الملابس',
                'description_en' => 'Training and sports clothing.',
                'description_ar' => 'ملابس التدريب والرياضة.',
                'image_url' => null,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        foreach ($this->catalog() as $index => $item) {
            $imageUrl = $this->publishImage($item['image']);

            Product::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $clothing->id,
                    'name_en' => $item['name_en'],
                    'name_ar' => $item['name_ar'],
                    'description_en' => $item['description_en'],
                    'description_ar' => $item['description_ar'],
                    'price' => $item['price'],
                    'compare_at_price' => $item['compare'] ?? null,
                    'stock_quantity' => $item['stock'],
                    'image_url' => $imageUrl,
                    'sku' => $item['sku'],
                    'is_active' => true,
                    'is_demo' => false,
                    'sort_order' => 200 + $index,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $stock = 20;

        return [
            [
                'slug' => 'athletic-racerback-tank-black-red',
                'sku' => 'CLH-TANK-01',
                'image' => 'athletic-racerback-tank.png',
                'name_en' => 'Athletic Racerback Tank Top',
                'name_ar' => 'قميص رياضي بدون أكمام',
                'description_en' => 'Sleeveless athletic tank top in black with red side panels and trim. Practical sportswear design suitable for training.',
                'description_ar' => 'قميص رياضي بدون أكمام باللون الأسود مع تفاصيل حمراء على الجوانب والحواف. تصميم عملي مناسب للتمارين.',
                'price' => 29.99,
                'compare' => 34.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'long-sleeve-compression-shirt',
                'sku' => 'CLH-LS-01',
                'image' => 'long-sleeve-compression-shirt.png',
                'name_en' => 'Long-Sleeve Compression Shirt',
                'name_ar' => 'قميص رياضي ضاغط بأكمام طويلة',
                'description_en' => 'Long-sleeve athletic compression shirt in black with geometric grey patterning. Functional sportswear for intense workouts and combat training.',
                'description_ar' => 'قميص رياضي ضاغط بأكمام طويلة باللون الأسود مع نقش هندسي رمادي. ملابس رياضية عملية للتمارين المكثفة وفنون القتال.',
                'price' => 44.99,
                'compare' => 49.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'hybrid-performance-training-shorts',
                'sku' => 'CLH-SHT-01',
                'image' => 'hybrid-performance-shorts.png',
                'name_en' => 'Hybrid Performance Training Shorts',
                'name_ar' => 'شورت تدريب هجين',
                'description_en' => 'Black performance training shorts with maroon accents, drawstring waistband, and side slits for mobility.',
                'description_ar' => 'شورت تدريب أسود بلمسات حمراء داكنة، خصر برباط، وفتحات جانبية لتسهيل الحركة.',
                'price' => 34.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'power-series-training-shorts',
                'sku' => 'CLH-SHT-02',
                'image' => 'power-series-training-shorts.png',
                'name_en' => 'Performance Training Shorts',
                'name_ar' => 'شورت رياضي للتمارين',
                'description_en' => 'Black athletic training shorts with elastic waistband and side slits. Clean performance cut for gym use.',
                'description_ar' => 'شورت رياضي أسود بخصر مطاطي وفتحات جانبية. قصة عملية مناسبة للتمارين في النادي.',
                'price' => 32.99,
                'compare' => 37.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'athletic-tshirt-red-stitch',
                'sku' => 'CLH-TEE-01',
                'image' => 'athletic-tshirt-red-stitch.png',
                'name_en' => 'Athletic Training T-Shirt',
                'name_ar' => 'تيشيرت رياضي للتمارين',
                'description_en' => 'Short-sleeve athletic t-shirt in charcoal black with red contrast stitching. Practical sportswear for training.',
                'description_ar' => 'تيشيرت رياضي بأكمام قصيرة باللون الأسود مع خياطة حمراء متباينة. ملابس رياضية عملية للتمارين.',
                'price' => 27.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'classic-drawstring-athletic-shorts',
                'sku' => 'CLH-SHT-03',
                'image' => 'classic-drawstring-shorts.png',
                'name_en' => 'Classic Athletic Shorts',
                'name_ar' => 'شورت رياضي كلاسيكي',
                'description_en' => 'Solid black athletic shorts with elastic drawstring waistband and side pockets. Suitable for workouts or casual wear.',
                'description_ar' => 'شورت رياضي أسود بخصر مطاطي برباط وجيوب جانبية. مناسب للتمارين أو الاستخدام اليومي.',
                'price' => 24.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'full-zip-compression-hoodie',
                'sku' => 'CLH-HDY-01',
                'image' => 'full-zip-compression-hoodie.png',
                'name_en' => 'Full-Zip Compression Hoodie',
                'name_ar' => 'سترة رياضية مضغوطة بسحاب كامل',
                'description_en' => 'Black full-zip athletic hoodie with hood, red contrast zipper and stitching, and thumbhole cuffs. Technical sportswear for training.',
                'description_ar' => 'سترة رياضية سوداء بسحاب كامل وغطاء رأس، مع سحاب وخياطة حمراء وفتحات إبهام عند الأكمام. ملابس تقنية مناسبة للتمارين.',
                'price' => 59.99,
                'compare' => 69.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'championship-zip-pocket-shorts',
                'sku' => 'CLH-SHT-04',
                'image' => 'championship-zip-pocket-shorts.png',
                'name_en' => 'Zip-Pocket Training Shorts',
                'name_ar' => 'شورت تدريب بجيوب سحاب',
                'description_en' => 'Black athletic training shorts with zippered side pockets, perforated ventilation panels, drawstring waist, and side slits.',
                'description_ar' => 'شورت تدريب أسود بجيوب جانبية بسحاب، مناطق تهوية مثقبة، خصر برباط، وفتحات جانبية.',
                'price' => 39.99,
                'compare' => 44.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'championship-series-athletic-tshirt',
                'sku' => 'CLH-TEE-02',
                'image' => 'championship-series-tshirt.png',
                'name_en' => 'Championship Series Athletic T-Shirt',
                'name_ar' => 'تيشيرت رياضي سلسلة البطولة',
                'description_en' => 'Short-sleeve athletic t-shirt in black with red geometric accents. Performance sportswear design for training and combat sports.',
                'description_ar' => 'تيشيرت رياضي بأكمام قصيرة باللون الأسود مع لمسات هندسية حمراء. تصميم رياضي مناسب للتمارين وفنون القتال.',
                'price' => 34.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'quarter-zip-athletic-hoodie',
                'sku' => 'CLH-HDY-02',
                'image' => 'quarter-zip-athletic-hoodie.png',
                'name_en' => 'Quarter-Zip Athletic Hoodie',
                'name_ar' => 'سترة رياضية بقلنسوة وسحاب نصفي',
                'description_en' => 'Black technical pullover hoodie with quarter-zip neckline, hood, kangaroo zip pockets, and a sleeve utility pocket.',
                'description_ar' => 'سترة رياضية سوداء بقلنسوة وسحاب نصفي، مع جيب أمامي بسحاب وجيب صغير على الكم.',
                'price' => 54.99,
                'compare' => 64.99,
                'stock' => $stock,
            ],
        ];
    }

    private function publishImage(string $filename): string
    {
        $source = database_path('seeders/assets/real_clothing/'.$filename);
        if (! File::isFile($source)) {
            throw new \RuntimeException("Missing real clothing image asset: {$filename}");
        }

        $relative = 'products/real/clothing/'.$filename;
        Storage::disk('public')->put($relative, File::get($source));

        return Storage::disk('public')->url($relative);
    }
}
