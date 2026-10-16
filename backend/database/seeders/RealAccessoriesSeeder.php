<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Imports real sports accessories from uploaded product images.
 * All products belong to Accessories (الإكسسوارات).
 * Names/descriptions never include "OCTA FIT PRO".
 */
class RealAccessoriesSeeder extends Seeder
{
    public function run(): void
    {
        $accessories = ProductCategory::query()->updateOrCreate(
            ['slug' => 'accessories'],
            [
                'name_en' => 'Accessories',
                'name_ar' => 'الإكسسوارات',
                'description_en' => 'Gym and training accessories.',
                'description_ar' => 'إكسسوارات النادي والتدريب.',
                'image_url' => null,
                'is_active' => true,
                'sort_order' => 4,
            ],
        );

        foreach ($this->catalog() as $index => $item) {
            $imageUrl = $this->publishImage($item['image']);

            Product::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $accessories->id,
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
                    'sort_order' => 400 + $index,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $stock = 25;

        return [
            [
                'slug' => 'watch-50-sport-smartwatch',
                'sku' => 'ACC-W50',
                'image' => 'watch-50.png',
                'name_en' => 'WATCH 50 Sport Smartwatch',
                'name_ar' => 'ساعة رياضية WATCH 50',
                'description_en' => 'Square-faced matte black sport smartwatch with textured band. Screen labeling shows WATCH 50 with activity metrics display.',
                'description_ar' => 'ساعة رياضية مربعة الوجه بالأسود المطفي مع سوار مزخرف. يظهر على الشاشة اسم WATCH 50 ومؤشرات نشاط.',
                'price' => 79.99,
                'compare' => 89.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'smart-fitness-watch-circular',
                'sku' => 'ACC-SW-01',
                'image' => 'smart-fitness-watch.png',
                'name_en' => 'Smart Fitness Watch',
                'name_ar' => 'ساعة لياقة ذكية',
                'description_en' => 'Circular black fitness smartwatch with silicone strap and side crown controls. Dashboard widgets for pulse, steps, calories, and workout are visible on screen.',
                'description_ar' => 'ساعة لياقة دائرية سوداء بسوار سيليكون وأزرار جانبية. تظهر على الشاشة مؤشرات النبض والخطوات والسعرات والتمرين.',
                'price' => 69.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'shaker-bottle-700ml',
                'sku' => 'ACC-SHK-700',
                'image' => 'shaker-bottle-700ml.png',
                'name_en' => 'Shaker Bottle 700ml',
                'name_ar' => 'زجاجة شيكر 700 مل',
                'description_en' => 'Matte black shaker bottle labeled 700ml / 24oz, with flip-top lid, carrying loop, measurement window, and visible wire whisk ball.',
                'description_ar' => 'زجاجة شيكر سوداء مطفية بسعة 700 مل / 24 أونصة، بغطاء قلّاب وحلقة حمل ونافذة قياس وكرة خفق معدنية ظاهرة.',
                'price' => 14.99,
                'compare' => 17.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'ab-roller',
                'sku' => 'ACC-AB-01',
                'image' => 'ab-roller.png',
                'name_en' => 'Ab Roller',
                'name_ar' => 'عجلة تمارين البطن',
                'description_en' => 'Black ab roller with wide treaded wheel and dual ergonomic textured handles. Suitable for core training.',
                'description_ar' => 'عجلة تمارين بطن سوداء بعجلات عريضة بمطب ومقبضين مريحين مزخرفين. مناسبة لتدريب الجذع.',
                'price' => 24.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'strength-chalk',
                'sku' => 'ACC-CH-01',
                'image' => 'strength-chalk.png',
                'name_en' => 'Strength Chalk',
                'name_ar' => 'طباشير قوة',
                'description_en' => 'Matte black jar of gym strength chalk with a solid white chalk block inside. Product labeling shows Strength Chalk.',
                'description_ar' => 'علبة سوداء مطفية من طباشير القوة تحتوي كتلة طباشير بيضاء. يظهر على العبوة اسم Strength Chalk.',
                'price' => 12.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'black-ribbed-beanie',
                'sku' => 'ACC-BN-01',
                'image' => 'ribbed-beanie.png',
                'name_en' => 'Black Ribbed Beanie',
                'name_ar' => 'قبعة بياني مضلعة سوداء',
                'description_en' => 'Solid black rib-knit beanie with folded cuff and front fabric patch. Suitable as a sports lifestyle accessory.',
                'description_ar' => 'قبعة بياني مضلعة سوداء بحافة مطوية ورقعة قماش أمامية. مناسبة كإكسسوار رياضي يومي.',
                'price' => 18.99,
                'compare' => 22.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'hydration-jug-2300ml',
                'sku' => 'ACC-JG-2300',
                'image' => 'hydration-jug-2300ml.png',
                'name_en' => 'Hydration Jug 2300ml',
                'name_ar' => 'قارورة ترطيب 2300 مل',
                'description_en' => 'Large matte black hydration jug with integrated handle, flip-top lid, and measurement scale marked up to 2300 ml.',
                'description_ar' => 'قارورة ترطيب كبيرة سوداء مطفية بمقبض مدمج وغطاء قلّاب ومقياس حتى 2300 مل.',
                'price' => 22.99,
                'compare' => 26.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'high-density-foam-roller',
                'sku' => 'ACC-FR-01',
                'image' => 'foam-roller.png',
                'name_en' => 'High-Density Foam Roller',
                'name_ar' => 'فوم رولر عالي الكثافة',
                'description_en' => 'Black high-density foam roller with multi-zone textured surface including honeycomb and ribbed sections. Suitable for muscle recovery use.',
                'description_ar' => 'فوم رولر أسود عالي الكثافة بسطح متعدد المناطق بنقوش خلية نحل وأخاديد. مناسب للاستخدام في تعافي العضلات.',
                'price' => 29.99,
                'compare' => 34.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'resistance-band-training-set',
                'sku' => 'ACC-RB-SET',
                'image' => 'resistance-band-set.png',
                'name_en' => 'Resistance Band Training Set',
                'name_ar' => 'مجموعة أحزمة مقاومة للتدريب',
                'description_en' => 'Home resistance training set with foam handles, ankle straps, loop bands, resistance tubes, carabiners, and a door anchor. Sold as one commercial set.',
                'description_ar' => 'مجموعة تدريب مقاومة منزلية تتضمن مقابض إسفنجية وأحزمة كاحل وأحزمة حلقية وأنابيب مقاومة وكارابينات ومثبت باب. تُباع كمجموعة واحدة.',
                'price' => 49.99,
                'compare' => 59.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'elite-sports-towel',
                'sku' => 'ACC-TW-01',
                'image' => 'elite-sports-towel.png',
                'name_en' => 'Elite Sports Towel',
                'name_ar' => 'منشفة رياضية Elite',
                'description_en' => 'Folded black sports towel with debossed Elite accessories marking and a red hexagon corner patch.',
                'description_ar' => 'منشفة رياضية سوداء مطوية مع نقش Elite ورقعة سداسية حمراء في الزاوية.',
                'price' => 16.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'adjustable-hand-gripper',
                'sku' => 'ACC-HG-01',
                'image' => 'adjustable-hand-gripper.png',
                'name_en' => 'Adjustable Hand Gripper',
                'name_ar' => 'مقبض يد قابل للتعديل',
                'description_en' => 'Adjustable hand gripper with textured handles and steel spring. Visible resistance range marking: 100–300 LBS.',
                'description_ar' => 'مقبض يد قابل للتعديل بمقابض مزخرفة ونابض فولاذي. يظهر نطاق مقاومة 100–300 رطل.',
                'price' => 27.99,
                'compare' => 32.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'lifting-straps-pair',
                'sku' => 'ACC-LS-01',
                'image' => 'lifting-straps.png',
                'name_en' => 'Lifting Straps',
                'name_ar' => 'أحزمة رفع أثقال',
                'description_en' => 'Pair of padded lifting straps with woven webbing and metal D-ring buckles. Suitable for grip support during strength training.',
                'description_ar' => 'زوج من أحزمة الرفع المبطنة بحزام منسوج ومشابك حلقية معدنية. مناسبة لدعم القبضة أثناء تمارين القوة.',
                'price' => 19.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'sweatband-set',
                'sku' => 'ACC-SB-SET',
                'image' => 'sweatband-set.png',
                'name_en' => 'Sweatband Set',
                'name_ar' => 'مجموعة أربطة عرق',
                'description_en' => 'Black terry sweatband set with one headband and two matching wristbands. Sold as one commercial set.',
                'description_ar' => 'مجموعة أربطة عرق سوداء من نسيج تيري تضم عصابة رأس ومعصمين متطابقين. تُباع كمجموعة واحدة.',
                'price' => 14.99,
                'compare' => 17.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'weightlifting-wrist-wraps',
                'sku' => 'ACC-WW-01',
                'image' => 'wrist-wraps.png',
                'name_en' => 'Weightlifting Wrist Wraps',
                'name_ar' => 'لفّافات معصم لرفع الأثقال',
                'description_en' => 'Pair of heavy-duty wrist wraps with red edge stitching, metal loop, and Velcro closure. Suitable for wrist support during lifts.',
                'description_ar' => 'زوج من لفّافات المعصم المتينة بخياطة حمراء وحلقة معدنية وإغلاق فيلكرو. مناسبة لدعم المعصم أثناء الرفع.',
                'price' => 21.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'electronic-referee-whistle-set',
                'sku' => 'ACC-WH-01',
                'image' => 'electronic-referee-whistle-set.png',
                'name_en' => 'Electronic Sports Whistle Set',
                'name_ar' => 'مجموعة صفارة رياضية إلكترونية',
                'description_en' => 'Premium referee set with electronic sports whistle, hard carrying case, and braided lanyard. Controls for power, sound, and volume are visible.',
                'description_ar' => 'مجموعة حكم تتضمن صفارة رياضية إلكترونية وعلبة حمل صلبة وشريط رقبة مضفر. تظهر أزرار التشغيل والصوت ومستوى الصوت.',
                'price' => 34.99,
                'compare' => 39.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'performance-towel-pt50',
                'sku' => 'ACC-TW-PT50',
                'image' => 'performance-towel-pt50.png',
                'name_en' => 'Performance Towel PT-50',
                'name_ar' => 'منشفة أداء PT-50',
                'description_en' => 'Black microfiber performance towel with red graphics. Tag shows 100% Microfiber, size S/M, product ID PT-50, and wash cold.',
                'description_ar' => 'منشفة أداء سوداء من المايكروفايبر برسوم حمراء. الملصق يظهر 100% مايكروفايبر ومقاس S/M ومعرف PT-50 وغسل بارد.',
                'price' => 15.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'padded-weightlifting-gloves',
                'sku' => 'ACC-GLV-01',
                'image' => 'weightlifting-gloves.png',
                'name_en' => 'Padded Weightlifting Gloves',
                'name_ar' => 'قفازات رفع أثقال مبطنة',
                'description_en' => 'Fingerless black weightlifting gloves with red stitching, mesh back, padded palm, and wide Velcro wrist support. Sold as a pair.',
                'description_ar' => 'قفازات رفع أثقال سوداء بدون أصابع بخياطة حمراء وظهر شبكي وراحة مبطنة ودعم معصم عريض بفيلكرو. تُباع كزوج.',
                'price' => 24.99,
                'compare' => 29.99,
                'stock' => $stock,
            ],
        ];
    }

    private function publishImage(string $filename): string
    {
        $source = database_path('seeders/assets/real_accessories/'.$filename);
        if (! File::isFile($source)) {
            throw new \RuntimeException("Missing real accessories image asset: {$filename}");
        }

        $relative = 'products/real/accessories/'.$filename;
        Storage::disk('public')->put($relative, File::get($source));

        return Storage::disk('public')->url($relative);
    }
}
