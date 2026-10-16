<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Imports real supplement products from uploaded packaging images.
 *
 * - All products belong to the top-level "supplements" category (المكملات).
 * - Product names/descriptions never include "OCTA FIT PRO".
 * - Prices are approximate international retail USD estimates (not official quotes).
 * - Idempotent via stable product slugs.
 */
class RealSupplementSeeder extends Seeder
{
    public function run(): void
    {
        $supplements = $this->ensureSupplementsCategory();
        $this->deactivateObsoleteTopLevelCategories($supplements->id);

        foreach ($this->catalog() as $index => $item) {
            $imageUrl = $this->publishImage($item['image']);

            Product::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $supplements->id,
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
                    'sort_order' => 100 + $index,
                ],
            );
        }
    }

    private function ensureSupplementsCategory(): ProductCategory
    {
        return ProductCategory::query()->updateOrCreate(
            ['slug' => 'supplements'],
            [
                'name_en' => 'Supplements',
                'name_ar' => 'المكملات',
                'description_en' => 'Sports nutrition and dietary supplements.',
                'description_ar' => 'مكملات التغذية الرياضية والغذائية.',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    /**
     * Reassign any products still on obsolete subtype categories, then deactivate those categories.
     * Historical order rows are unaffected (they snapshot product data, not live category FKs).
     */
    private function deactivateObsoleteTopLevelCategories(int $supplementsId): void
    {
        $obsolete = [
            'protein',
            'creatine',
            'pre-workout',
            'amino-acids',
            'vitamins-minerals',
            'recovery',
        ];

        Product::query()
            ->whereHas('category', fn ($q) => $q->whereIn('slug', $obsolete))
            ->update(['category_id' => $supplementsId]);

        ProductCategory::query()
            ->whereIn('slug', $obsolete)
            ->update(['is_active' => false]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $stock = 25;

        return [
            [
                'slug' => 'octa-fit-pro-pre-workout-450g',
                'sku' => 'SUP-PWO-450',
                'image' => 'pre-workout.png',
                'name_en' => 'Pre-Workout',
                'name_ar' => 'ما قبل التمرين',
                'description_en' => 'Energy Series pre-workout powder. Packaging shows 450g net weight, 30 servings, with caffeine, L-citrulline, and beta-alanine callouts on the label.',
                'description_ar' => 'مسحوق ما قبل التمرين من سلسلة الطاقة. العبوة تظهر وزن 450غ و30 حصة مع إبراز الكافيين والسيترولين والبيتا ألانين على الملصق.',
                'price' => 39.99,
                'compare' => 44.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-whey-protein-concentrate-2kg',
                'sku' => 'SUP-WPC-2KG',
                'image' => 'whey-protein-concentrate.png',
                'name_en' => 'Whey Protein Concentrate',
                'name_ar' => 'بروتين مصل اللبن المركز',
                'description_en' => 'Whey protein concentrate powder. Label shows Vanilla Bean flavor, 2 kg (4.4 lbs), with 30g protein and 6.5g BCAAs per serving callouts.',
                'description_ar' => 'مسحوق بروتين مصل اللبن المركز. الملصق يظهر نكهة فانيلا بين ووزن 2 كغ مع إبراز 30غ بروتين و6.5غ BCAA لكل حصة.',
                'price' => 54.99,
                'compare' => 59.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-whey-protein-isolate-5lbs',
                'sku' => 'SUP-WPI-5LB',
                'image' => 'whey-protein-isolate.png',
                'name_en' => 'Whey Protein Isolate',
                'name_ar' => 'بروتين مصل اللبن المعزول',
                'description_en' => 'Elite Series whey protein isolate. Label shows Vanilla Bean flavor, 2.27 kg (5 lbs), 80 servings, with 27g protein, 6.5g BCAAs, and 110 calories callouts.',
                'description_ar' => 'بروتين مصل اللبن المعزول من سلسلة Elite. الملصق يظهر نكهة فانيلا بين ووزن 2.27 كغ و80 حصة مع إبراز 27غ بروتين و6.5غ BCAA و110 سعرة.',
                'price' => 69.99,
                'compare' => 79.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-plant-protein-blend',
                'sku' => 'SUP-PPB-01',
                'image' => 'plant-protein-blend.png',
                'name_en' => 'Plant Protein Blend',
                'name_ar' => 'مزيج بروتين نباتي',
                'description_en' => 'Nutrition Line plant-based protein blend. Packaging highlights an advanced plant protein formula with protein and fiber callouts on the label.',
                'description_ar' => 'مزيج بروتين نباتي من خط التغذية. العبوة تبرز تركيبة بروتين نباتي مع إبرازات البروتين والألياف على الملصق.',
                'price' => 49.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-casein-protein-elite',
                'sku' => 'SUP-CAS-01',
                'image' => 'casein-protein.png',
                'name_en' => 'Casein Protein',
                'name_ar' => 'كازين بروتين',
                'description_en' => 'Elite Series casein protein. Label shows a slow-digesting nighttime recovery formula in Vanilla Bean flavor, with 25g protein, 7g BCAA, and 110 calorie callouts.',
                'description_ar' => 'كازين بروتين من سلسلة Elite. الملصق يظهر تركيبة بطيئة الهضم للتعافي الليلي بنكهة فانيلا بين مع إبراز 25غ بروتين و7غ BCAA و110 سعرة.',
                'price' => 59.99,
                'compare' => 64.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-mass-gainer-elite',
                'sku' => 'SUP-MG-ELITE',
                'image' => 'mass-gainer-elite.png',
                'name_en' => 'Mass Gainer Elite',
                'name_ar' => 'ماس جينر Elite',
                'description_en' => 'Elite Series mass gainer. Label shows Bulk & Build Power Formula in Vanilla Bean flavor with protein, BCAA, and calorie callouts on the packaging.',
                'description_ar' => 'ماس جينر من سلسلة Elite. الملصق يظهر تركيبة Bulk & Build بنكهة فانيلا بين مع إبرازات البروتين وBCAA والسعرات على العبوة.',
                'price' => 64.99,
                'compare' => 74.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-mass-gainer-extreme-5kg',
                'sku' => 'SUP-MG-EXT-5KG',
                'image' => 'mass-gainer-extreme.png',
                'name_en' => 'Mass Gainer Extreme',
                'name_ar' => 'ماس جينر Extreme',
                'description_en' => 'Extreme Mass Series mass gainer. Label shows a high-calorie mass formula, 5 kg (11 lbs), 20 servings, with creatine, HMB, and beta-alanine callouts.',
                'description_ar' => 'ماس جينر من سلسلة Extreme Mass. الملصق يظهر تركيبة عالية السعرات بوزن 5 كغ و20 حصة مع إبراز الكرياتين وHMB والبيتا ألانين.',
                'price' => 79.99,
                'compare' => 89.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-creatine-hcl-500g',
                'sku' => 'SUP-HCL-500',
                'image' => 'creatine-hcl.png',
                'name_en' => 'Creatine HCL',
                'name_ar' => 'كرياتين HCL',
                'description_en' => 'Strength Line creatine HCL. Label shows micronized HCL formula, 500g, 100 servings, unflavored, with a 3.5g creatine HCL callout.',
                'description_ar' => 'كرياتين HCL من خط القوة. الملصق يظهر تركيبة ميكرونيزد HCL بوزن 500غ و100 حصة بدون نكهة مع إبراز 3.5غ كرياتين HCL.',
                'price' => 34.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'creatine-monohydrate',
                'sku' => 'SUP-CRE-1KG',
                'image' => 'creatine-monohydrate.png',
                'name_en' => 'Creatine Monohydrate',
                'name_ar' => 'كرياتين مونوهيدرات',
                'description_en' => 'Elite Strength Series creatine monohydrate. Label shows 1 kg (2.2 lbs), unflavored, micro-filtration, with creatine monohydrate callouts on the packaging.',
                'description_ar' => 'كرياتين مونوهيدرات من سلسلة Elite Strength. الملصق يظهر وزن 1 كغ بدون نكهة مع ترشيح دقيق وإبرازات الكرياتين على العبوة.',
                'price' => 29.99,
                'compare' => 34.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-nitric-oxide-booster',
                'sku' => 'SUP-NO-01',
                'image' => 'nitric-oxide-booster.png',
                'name_en' => 'Nitric Oxide Booster',
                'name_ar' => 'معزز أكسيد النيتريك',
                'description_en' => 'Pump Series nitric oxide booster. Label shows Premium Pump Synergy with a citrulline malate callout on the packaging.',
                'description_ar' => 'معزز أكسيد النيتريك من سلسلة Pump. الملصق يظهر Premium Pump Synergy مع إبراز سيترولين مالات على العبوة.',
                'price' => 36.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-caffeine-capsules',
                'sku' => 'SUP-CAF-120',
                'image' => 'caffeine-capsules.png',
                'name_en' => 'Caffeine Capsules',
                'name_ar' => 'كبسولات كافيين',
                'description_en' => 'Energy Series caffeine capsules. Packaging shows 120 capsules and 200mg caffeine callouts on the label.',
                'description_ar' => 'كبسولات كافيين من سلسلة الطاقة. العبوة تظهر 120 كبسولة مع إبراز 200ملغ كافيين على الملصق.',
                'price' => 19.99,
                'compare' => 24.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-citrulline-malate',
                'sku' => 'SUP-CIT-01',
                'image' => 'citrulline-malate.png',
                'name_en' => 'Citrulline Malate',
                'name_ar' => 'سيترولين مالات',
                'description_en' => 'Elite Series citrulline malate powder. Label shows an elite performance pump / volumizer formula with a 6g citrulline malate callout.',
                'description_ar' => 'مسحوق سيترولين مالات من سلسلة Elite. الملصق يظهر تركيبة أداء الضخ مع إبراز 6غ سيترولين مالات.',
                'price' => 32.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-beta-alanine',
                'sku' => 'SUP-BA-01',
                'image' => 'beta-alanine.png',
                'name_en' => 'Beta-Alanine',
                'name_ar' => 'بيتا ألانين',
                'description_en' => 'Performance Series beta-alanine powder. Label shows an ultra-pure carnosine boost formula, unflavored, with a 5g beta-alanine callout.',
                'description_ar' => 'مسحوق بيتا ألانين من سلسلة الأداء. الملصق يظهر تركيبة كارنوزين نقية بدون نكهة مع إبراز 5غ بيتا ألانين.',
                'price' => 27.99,
                'compare' => null,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-vitamin-c',
                'sku' => 'SUP-VITC-60',
                'image' => 'vitamin-c.png',
                'name_en' => 'Vitamin C',
                'name_ar' => 'فيتامين C',
                'description_en' => 'Wellness Series vitamin C. Label shows 1000mg vitamin C, 20mg zinc, orange flavor, and 60 servings on the packaging.',
                'description_ar' => 'فيتامين C من سلسلة العافية. الملصق يظهر 1000ملغ فيتامين C و20ملغ زنك بنكهة البرتقال و60 حصة.',
                'price' => 16.99,
                'compare' => 19.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-marine-collagen-600g',
                'sku' => 'SUP-COL-600',
                'image' => 'marine-collagen.png',
                'name_en' => 'Marine Collagen',
                'name_ar' => 'كولاجين بحري',
                'description_en' => 'Wellness Series marine collagen. Label shows hydrolyzed peptides, peach taste, 600g, 60 servings, with collagen callouts on the packaging.',
                'description_ar' => 'كولاجين بحري من سلسلة العافية. الملصق يظهر ببتيدات مهدرجة بنكهة الخوخ ووزن 600غ و60 حصة مع إبرازات الكولاجين.',
                'price' => 44.99,
                'compare' => 49.99,
                'stock' => $stock,
            ],
            [
                'slug' => 'octa-fit-pro-recovery-510g',
                'sku' => 'SUP-REC-510',
                'image' => 'recovery.png',
                'name_en' => 'Recovery',
                'name_ar' => 'تعافي',
                'description_en' => 'Post-workout recovery formula. Label shows Restore & Rebuild product, 510g, 30 servings, with dosage callouts on the packaging.',
                'description_ar' => 'تركيبة تعافي بعد التمرين. الملصق يظهر منتج Restore & Rebuild بوزن 510غ و30 حصة مع إبرازات الجرعات على العبوة.',
                'price' => 38.99,
                'compare' => null,
                'stock' => $stock,
            ],
        ];
    }

    private function publishImage(string $filename): string
    {
        $source = database_path('seeders/assets/real_supplements/'.$filename);
        if (! File::isFile($source)) {
            throw new \RuntimeException("Missing real supplement image asset: {$filename}");
        }

        $relative = 'products/real/'.$filename;
        Storage::disk('public')->put($relative, File::get($source));

        return Storage::disk('public')->url($relative);
    }
}
