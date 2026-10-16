<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Imports real sports equipment from uploaded product images.
 * All products belong to Equipment (المعدات).
 * Names/descriptions never include "OCTA FIT PRO".
 * Real manufacturer brands (e.g. UFC, Venum) are kept when clearly visible.
 */
class RealEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipment = ProductCategory::query()->updateOrCreate(
            ['slug' => 'equipment'],
            [
                'name_en' => 'Equipment',
                'name_ar' => 'المعدات',
                'description_en' => 'Training equipment and gear.',
                'description_ar' => 'معدات وأدوات التدريب.',
                'image_url' => null,
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        foreach ($this->catalog() as $index => $item) {
            $imageUrl = $this->publishImage($item['image']);

            Product::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $equipment->id,
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
                    'sort_order' => 300 + $index,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $stock = 15;

        return [
            // Image 13 — two products share image
            [
                'slug' => 'boxing-speed-bag-black',
                'sku' => 'EQP-SB-01',
                'image' => 'speed-bag-and-focus-mitts-black.png',
                'name_en' => 'Boxing Speed Bag',
                'name_ar' => 'كيس سرعة للملاكمة',
                'description_en' => 'Teardrop boxing speed bag in glossy black. Suitable for rhythm and timing training. Mounting loop with metal D-ring visible.',
                'description_ar' => 'كيس سرعة للملاكمة بشكل كمثرى باللون الأسود اللامع. مناسب لتدريب الإيقاع والتوقيت. يتضمن حلقة تعليق مع حلقة معدنية.',
                'price' => 34.99,
                'compare' => 39.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'boxing-focus-mitts-black',
                'sku' => 'EQP-FM-01',
                'image' => 'speed-bag-and-focus-mitts-black.png',
                'name_en' => 'Boxing Focus Mitts',
                'name_ar' => 'قفازات تدريب ملاكمة',
                'description_en' => 'Pair of black boxing focus mitts with adjustable wrist strap. Suitable for strike accuracy and pad work.',
                'description_ar' => 'زوج من قفازات تدريب الملاكمة السوداء مع حزام معصم قابل للتعديل. مناسبة للتدريب على دقة الضربات.',
                'price' => 39.99,
                'compare' => null,
                'stock' => $stock,
            ],
            // Image 7
            [
                'slug' => 'competitor-series-speed-bag',
                'sku' => 'EQP-SB-02',
                'image' => 'competitor-series-speed-bag.png',
                'name_en' => 'Competitor Series Speed Bag',
                'name_ar' => 'كيس سرعة سلسلة المنافس',
                'description_en' => 'Multi-panel speed bag in maroon, black, and grey with swivel mount. Suitable for boxing and combat training.',
                'description_ar' => 'كيس سرعة متعدد الألواح باللون العنابي والأسود والرمادي مع حامل دوّار. مناسب لتدريب الملاكمة والقتال.',
                'price' => 39.99,
                'compare' => 44.99,
                'stock' => $stock,
            ],
            // Image 47
            [
                'slug' => 'horizontal-heavy-bag',
                'sku' => 'EQP-HB-01',
                'image' => 'horizontal-heavy-bag.png',
                'name_en' => 'Horizontal Heavy Bag',
                'name_ar' => 'كيس ملاكمة أفقي',
                'description_en' => 'Horizontal cylindrical heavy bag in black and grey with red stitching and dual strap suspension. For striking practice.',
                'description_ar' => 'كيس ملاكمة أسطواني أفقي بالأسود والرمادي مع خياطة حمراء وتعليق بحزامين. مناسب لتدريب الضربات.',
                'price' => 149.99,
                'compare' => 169.99,
                'stock' => $stock,
            ],
            // Image 3 — three products share image
            [
                'slug' => 'boxing-speed-bag-red-trim',
                'sku' => 'EQP-SB-03',
                'image' => 'boxing-training-trio.png',
                'name_en' => 'Speed Bag with Red Trim',
                'name_ar' => 'كيس سرعة بحواف حمراء',
                'description_en' => 'Teardrop speed bag in black with red piping and hanging loop. Suitable for rhythm training.',
                'description_ar' => 'كيس سرعة بشكل كمثرى بالأسود مع حواف حمراء وحلقة تعليق. مناسب لتدريب الإيقاع.',
                'price' => 36.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'boxing-focus-mitts-red-trim',
                'sku' => 'EQP-FM-02',
                'image' => 'boxing-training-trio.png',
                'name_en' => 'Focus Mitts with Red Trim',
                'name_ar' => 'قفازات تدريب بحواف حمراء',
                'description_en' => 'Pair of black focus mitts with red accents and adjustable straps. Suitable for pad work and strike training.',
                'description_ar' => 'زوج من قفازات التدريب السوداء بلمسات حمراء وأحزمة قابلة للتعديل. مناسبة لتدريب الضربات.',
                'price' => 42.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'boxing-reflex-ball-set',
                'sku' => 'EQP-RB-01',
                'image' => 'boxing-training-trio.png',
                'name_en' => 'Boxing Reflex Ball Set',
                'name_ar' => 'مجموعة كرة انعكاسية للملاكمة',
                'description_en' => 'Reflex training set with adjustable headband, elastic cord, and ball. Suitable for reaction and coordination practice.',
                'description_ar' => 'مجموعة تدريب انعكاسية مع عصابة رأس قابلة للتعديل وخيط مرن وكرة. مناسبة لتدريب رد الفعل والتنسيق.',
                'price' => 19.99,
                'compare' => 24.99,
                'stock' => $stock,
            ],
            // Image 96
            [
                'slug' => 'percussive-massage-gun',
                'sku' => 'EQP-MG-01',
                'image' => 'percussive-massage-gun.png',
                'name_en' => 'Percussive Massage Gun',
                'name_ar' => 'جهاز مساج إيقاعي',
                'description_en' => 'Handheld percussive massage gun with digital speed display and four interchangeable heads. Suitable for muscle recovery use.',
                'description_ar' => 'جهاز مساج إيقاعي محمول مع شاشة سرعة رقمية وأربعة رؤوس قابلة للتبديل. مناسب للاستخدام في تعافي العضلات.',
                'price' => 89.99,
                'compare' => 109.99,
                'stock' => $stock,
            ],
            // Image 81
            [
                'slug' => 'agility-ladder',
                'sku' => 'EQP-AL-01',
                'image' => 'agility-ladder.png',
                'name_en' => 'Agility Ladder',
                'name_ar' => 'سلم رشاقة',
                'description_en' => 'Agility ladder with black straps, orange stitching, flat rungs, and metal hardware. Suitable for speed and footwork drills.',
                'description_ar' => 'سلم رشاقة بأحزمة سوداء وخياطة برتقالية ودرجات مسطحة وقطع معدنية. مناسب لتدريبات السرعة وحركة القدمين.',
                'price' => 29.99,
                'compare' => null,
                'stock' => $stock,
            ],
            // Image 82 — three weight variants, weights visible
            [
                'slug' => 'kettlebell-12kg',
                'sku' => 'EQP-KB-12',
                'image' => 'kettlebell-set-12-16-20.png',
                'name_en' => 'Kettlebell 12kg',
                'name_ar' => 'كيتل بيل 12 كغ',
                'description_en' => 'Matte black kettlebell with silver handle. Weight marking 12kg visible on the product.',
                'description_ar' => 'كيتل بيل أسود مطفي بمقبض فضي. وزن 12 كغ ظاهر على المنتج.',
                'price' => 39.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'kettlebell-16kg',
                'sku' => 'EQP-KB-16',
                'image' => 'kettlebell-set-12-16-20.png',
                'name_en' => 'Kettlebell 16kg',
                'name_ar' => 'كيتل بيل 16 كغ',
                'description_en' => 'Matte black kettlebell with silver handle. Weight marking 16kg visible on the product.',
                'description_ar' => 'كيتل بيل أسود مطفي بمقبض فضي. وزن 16 كغ ظاهر على المنتج.',
                'price' => 49.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'kettlebell-20kg',
                'sku' => 'EQP-KB-20',
                'image' => 'kettlebell-set-12-16-20.png',
                'name_en' => 'Kettlebell 20kg',
                'name_ar' => 'كيتل بيل 20 كغ',
                'description_en' => 'Matte black kettlebell with silver handle. Weight marking 20kg visible on the product.',
                'description_ar' => 'كيتل بيل أسود مطفي بمقبض فضي. وزن 20 كغ ظاهر على المنتج.',
                'price' => 59.99,
                'compare' => 64.99,
                'stock' => $stock,
            ],
            // Image 17
            [
                'slug' => 'mma-fight-gloves-ofg-50',
                'sku' => 'EQP-GLV-OFG50',
                'image' => 'mma-fight-gloves-ofg-50.png',
                'name_en' => 'MMA Fight Gloves OFG-50',
                'name_ar' => 'قفازات قتال مختلط OFG-50',
                'description_en' => 'Fingerless MMA fight gloves with knuckle padding, mesh palm, and wide wrist strap. Model OFG-50 visible on the product.',
                'description_ar' => 'قفازات قتال مختلط بدون أصابع مع حشوة للكوع وراحة شبكية وحزام معصم عريض. الموديل OFG-50 ظاهر على المنتج.',
                'price' => 54.99,
                'compare' => 64.99,
                'stock' => $stock,
            ],
            // Image 57
            [
                'slug' => 'smith-machine',
                'sku' => 'EQP-SM-01',
                'image' => 'smith-machine.png',
                'name_en' => 'Smith Machine',
                'name_ar' => 'جهاز سميث',
                'description_en' => 'Guided barbell Smith machine with black frame, chrome bar path, safety notches, and plate storage pegs. Shown with 20 kg plates.',
                'description_ar' => 'جهاز سميث بقضيب موجه وإطار أسود ومسار كروم ونقاط أمان وحاملات أقراص. يظهر مع أقراص 20 كغ.',
                'price' => 899.99,
                'compare' => 999.99,
                'stock' => 5,
            ],
            // Image 55
            [
                'slug' => 'fid-weight-bench',
                'sku' => 'EQP-BN-01',
                'image' => 'fid-weight-bench.png',
                'name_en' => 'Adjustable FID Weight Bench',
                'name_ar' => 'مقعد أوزان قابل للتعديل FID',
                'description_en' => 'Adjustable flat/incline/decline weight bench with black padded upholstery, steel frame, and foam leg rollers.',
                'description_ar' => 'مقعد أوزان قابل للتعديل (مسطح/مائل/منخفض) مع تنجيد أسود وإطار فولاذي وبكرات أرجل إسفنجية.',
                'price' => 199.99,
                'compare' => 229.99,
                'stock' => $stock,
            ],
            // Image 10
            [
                'slug' => 'kettlebell-50lb',
                'sku' => 'EQP-KB-50LB',
                'image' => 'kettlebell-50lb.png',
                'name_en' => '50 lb Kettlebell',
                'name_ar' => 'كيتل بيل 50 رطلاً',
                'description_en' => 'Matte black kettlebell with ergonomic handle. Weight marking 50 lbs visible on the product.',
                'description_ar' => 'كيتل بيل أسود مطفي بمقبض مريح. وزن 50 رطلاً ظاهر على المنتج.',
                'price' => 69.99,
                'compare' => null,
                'stock' => $stock,
            ],
            // Image 53 — three product types share image
            [
                'slug' => 'straight-barbell-with-plates',
                'sku' => 'EQP-BB-01',
                'image' => 'barbell-curl-collar-set.png',
                'name_en' => 'Straight Barbell with Plates',
                'name_ar' => 'بار مستقيم مع أقراص',
                'description_en' => 'Straight Olympic-style barbell shown loaded with black 25 kg / 55 lb plates. Suitable for strength training.',
                'description_ar' => 'بار مستقيم بطراز أولمبي يظهر محملاً بأقراص سوداء 25 كغ / 55 رطل. مناسب لتدريب القوة.',
                'price' => 249.99,
                'compare' => 279.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'ez-curl-bar-with-plates',
                'sku' => 'EQP-EZ-01',
                'image' => 'barbell-curl-collar-set.png',
                'name_en' => 'EZ Curl Bar with Plates',
                'name_ar' => 'بار كيرل EZ مع أقراص',
                'description_en' => 'EZ curl bar with knurled grip, shown with color-coded 55 lb plates. Suitable for arm training.',
                'description_ar' => 'بار كيرل EZ بقبضة محززة، يظهر مع أقراص ملونة بوزن 55 رطل. مناسب لتدريب الذراعين.',
                'price' => 179.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'barbell-spring-collars',
                'sku' => 'EQP-CL-01',
                'image' => 'barbell-curl-collar-set.png',
                'name_en' => 'Barbell Spring Collars',
                'name_ar' => 'مشابك زنبركية للبار',
                'description_en' => 'Pair of metal spring collars for securing weight plates on a barbell sleeve.',
                'description_ar' => 'زوج من المشابك الزنبركية المعدنية لتثبيت أقراص الأوزان على كم البار.',
                'price' => 14.99,
                'compare' => null,
                'stock' => 40,
            ],
            // Image 6 — keep UFC / Venum brands
            [
                'slug' => 'ufc-venum-shin-guards',
                'sku' => 'EQP-SG-UFC',
                'image' => 'ufc-venum-shin-guards.png',
                'name_en' => 'UFC Venum Shin Guards',
                'name_ar' => 'واقيات ساق UFC Venum',
                'description_en' => 'UFC Venum shin and instep protectors in black and gold. Size L and 7 oz markings visible. Dual calf straps and numbered toe loops.',
                'description_ar' => 'واقيات ساق وقدم UFC Venum بالأسود والذهبي. مقاس L ووزن 7 أونصة ظاهران. حزامان للساق وحلقات أصابع مرقمة.',
                'price' => 79.99,
                'compare' => 89.99,
                'stock' => $stock,
            ],
            // Image 30 — one product, weight variants in collage
            [
                'slug' => 'lace-up-boxing-gloves',
                'sku' => 'EQP-BG-01',
                'image' => 'lace-up-boxing-gloves.png',
                'name_en' => 'Lace-Up Boxing Gloves',
                'name_ar' => 'قفازات ملاكمة برباط',
                'description_en' => 'Lace-up boxing gloves in red and charcoal grey. Competition-style pair shown in 12 oz, 14 oz, and 16 oz variants.',
                'description_ar' => 'قفازات ملاكمة برباط بالأحمر والرمادي الفحمي. تظهر بمقاسات 12 و14 و16 أونصة.',
                'price' => 89.99,
                'compare' => 99.99,
                'stock' => $stock,
            ],
            // Image 2 — keep UFC / Venum
            [
                'slug' => 'ufc-venum-mma-training-gloves',
                'sku' => 'EQP-GLV-UFC',
                'image' => 'ufc-venum-mma-training-gloves.png',
                'name_en' => 'UFC Venum MMA Training Gloves',
                'name_ar' => 'قفازات تدريب MMA من UFC Venum',
                'description_en' => 'UFC Venum open-palm MMA training gloves in black and gold. Markings show 4 oz and size L. Wide wrap-around wrist strap.',
                'description_ar' => 'قفازات تدريب MMA مفتوحة الراحة من UFC Venum بالأسود والذهبي. يظهر وزن 4 أونصة ومقاس L. حزام معصم عريض.',
                'price' => 69.99,
                'compare' => 79.99,
                'stock' => $stock,
            ],
            // Image 29
            [
                'slug' => 'olive-boxing-focus-mitts',
                'sku' => 'EQP-FM-03',
                'image' => 'olive-focus-mitts.png',
                'name_en' => 'Olive Focus Mitts',
                'name_ar' => 'قفازات تدريب زيتونية',
                'description_en' => 'Curved focus mitts in dark olive with red stitching and adjustable wrist straps. Suitable for boxing and MMA pad work.',
                'description_ar' => 'قفازات تدريب منحنية باللون الزيتوني الداكن مع خياطة حمراء وأحزمة معصم قابلة للتعديل. مناسبة لتدريب الملاكمة والقتال المختلط.',
                'price' => 44.99,
                'compare' => null,
                'stock' => $stock,
            ],
            // Image 68
            [
                'slug' => 'lever-weightlifting-belt-size-l',
                'sku' => 'EQP-BLT-L',
                'image' => 'lever-weightlifting-belt.png',
                'name_en' => 'Lever Weightlifting Belt',
                'name_ar' => 'حزام رفع أثقال بمشبك رافعة',
                'description_en' => 'Lever-action weightlifting belt with black exterior, red interior lining, and metal buckle. Size L visible on the inner label.',
                'description_ar' => 'حزام رفع أثقال بمشبك رافعة، خارج أسود وبطانة حمراء ومشبك معدني. مقاس L ظاهر على الملصق الداخلي.',
                'price' => 79.99,
                'compare' => 89.99,
                'stock' => $stock,
            ],
            // Image 52 — two products share image
            [
                'slug' => 'fixed-dumbbell-25kg',
                'sku' => 'EQP-DB-25',
                'image' => 'dumbbells-fixed-and-adjustable.png',
                'name_en' => 'Fixed Dumbbell 25kg',
                'name_ar' => 'دمبل ثابت 25 كغ',
                'description_en' => 'Fixed-weight dumbbell with rubber-coated heads and knurled metal handle. Weight marking 25 kg visible on the end cap.',
                'description_ar' => 'دمبل ثابت الوزن برؤوس مطاطية ومقبض معدني محزز. وزن 25 كغ ظاهر على الغطاء الطرفي.',
                'price' => 89.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'adjustable-dumbbell-with-cradle',
                'sku' => 'EQP-DB-ADJ',
                'image' => 'dumbbells-fixed-and-adjustable.png',
                'name_en' => 'Adjustable Dumbbell with Cradle',
                'name_ar' => 'دمبل قابل للتعديل مع حامل',
                'description_en' => 'Selectorized adjustable dumbbell with storage cradle and dial. Visible increments range from 2 to 24 on the selector.',
                'description_ar' => 'دمبل قابل للتعديل مع حامل تخزين وقرص اختيار. تظهر زيادات من 2 إلى 24 على القرص.',
                'price' => 299.99,
                'compare' => 349.99,
                'stock' => 10,
            ],
            // Image 25
            [
                'slug' => 'heavy-punching-bag',
                'sku' => 'EQP-HB-02',
                'image' => 'heavy-punching-bag.png',
                'name_en' => 'Heavy Punching Bag',
                'name_ar' => 'كيس ملاكمة ثقيل',
                'description_en' => 'Vertical heavy punching bag in charcoal grey with red stitching and four hanging straps with D-rings. Suitable for striking training.',
                'description_ar' => 'كيس ملاكمة ثقيل عمودي بالرمادي الفحمي مع خياطة حمراء وأربعة أحزمة تعليق بحلقات معدنية. مناسب لتدريب الضربات.',
                'price' => 129.99,
                'compare' => 149.99,
                'stock' => $stock,
            ],
        ];
    }

    private function publishImage(string $filename): string
    {
        $source = database_path('seeders/assets/real_equipment/'.$filename);
        if (! File::isFile($source)) {
            throw new \RuntimeException("Missing real equipment image asset: {$filename}");
        }

        $relative = 'products/real/equipment/'.$filename;
        Storage::disk('public')->put($relative, File::get($source));

        return Storage::disk('public')->url($relative);
    }
}
