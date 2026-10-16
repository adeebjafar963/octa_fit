<?php

namespace Database\Seeders;

use App\Services\Coach\Knowledge\KnowledgeIngestionService;
use Illuminate\Database\Seeder;

/**
 * Phase 11F — small REAL knowledge corpus (no fabricated papers/DOIs).
 *
 * Bodies are educational paraphrases for coach retrieval — not full-text copies
 * of copyrighted articles. Source names, organizations, years, and URLs point to
 * real identifiable publications/guidance pages.
 */
class KnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $ingest = app(KnowledgeIngestionService::class);

        $ingest->upsertSourceWithDocuments([
            'name' => 'ISSN Position Stand: Protein and Exercise',
            'organization' => 'International Society of Sports Nutrition (ISSN)',
            'source_type' => 'position_stand',
            'evidence_level' => 'position_stand',
            'url' => 'https://jissn.biomedcentral.com/articles/10.1186/s12970-017-0177-8',
            'version' => '2017',
            'publication_year' => 2017,
            'reliability_level' => 'high',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'society_position_stand',
                'population' => 'healthy exercising adults (general sports nutrition context)',
                'limitations' => 'Position stand synthesizes literature; individual needs vary. Not medical advice.',
                'applicability' => 'protein intake for training adults',
                'topics' => ['nutrition', 'protein'],
            ],
        ], [
            [
                'title' => 'Protein intake for exercising adults — ISSN summary points',
                'topic' => 'nutrition',
                'document_identifier' => 'issn-protein-exercise-2017-summary',
                'body' => <<<'TXT'
Educational summary of publicly discussed ISSN position-stand themes on protein and exercise (Jäger et al., Journal of the International Society of Sports Nutrition, 2017).

Protein supports repair and remodeling of muscle tissue in people who resistance train. Daily protein needs for exercising adults are often higher than sedentary reference intakes. Spreading protein across meals can help meet daily targets.

Anabolic responses to protein are influenced by total daily intake, per-meal dose, and training stimulus. Whole-food and supplemental proteins can both contribute to totals when overall diet quality is adequate.

Evidence-based coaching should personalize protein using body mass, training status, and energy intake — and must not invent brand-specific nutrition facts. Allergies and intolerances remain hard constraints that override general protein advice.
TXT,
                'metadata' => ['keywords' => ['protein', 'nutrition', 'muscle', 'resistance', 'intake']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'ISSN Position Stand: Nutrient Timing',
            'organization' => 'International Society of Sports Nutrition (ISSN)',
            'source_type' => 'position_stand',
            'evidence_level' => 'position_stand',
            'url' => 'https://jissn.biomedcentral.com/articles/10.1186/s12970-017-0189-4',
            'version' => '2017',
            'publication_year' => 2017,
            'reliability_level' => 'high',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'society_position_stand',
                'population' => 'athletes and exercising adults',
                'limitations' => 'Timing effects are often smaller than total daily intake effects.',
                'applicability' => 'meal timing around training',
                'topics' => ['nutrition', 'carbohydrates', 'protein'],
            ],
        ], [
            [
                'title' => 'Nutrient timing around training — ISSN themes',
                'topic' => 'nutrition',
                'document_identifier' => 'issn-nutrient-timing-2017-summary',
                'body' => <<<'TXT'
Educational summary of ISSN nutrient-timing position-stand themes (Kerksick et al., 2017).

Total daily energy and macronutrient intake generally matter more than narrow “anabolic window” claims. Still, consuming protein and carbohydrate in the hours around training can support performance and recovery, especially for high-volume sessions or multiple sessions per day.

Carbohydrates are a primary fuel for high-intensity training. Dietary fats are essential but should not crowd out carbohydrate needs for intense sports when performance is the priority.

Meal timing is a tool, not a substitute for energy balance. Personalized calorie and macro targets from OCTA FIT’s deterministic nutrition engine remain authoritative for numbers; this source provides explanatory context only.
TXT,
                'metadata' => ['keywords' => ['nutrient timing', 'carbohydrates', 'protein', 'meal', 'energy']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'ACSM Position Stand: Quantity and Quality of Exercise',
            'organization' => 'American College of Sports Medicine (ACSM)',
            'source_type' => 'position_stand',
            'evidence_level' => 'position_stand',
            'url' => 'https://journals.lww.com/acsm-msse/Fulltext/2011/07000/Quantity_and_Quality_of_Exercise_for_Developing.26.aspx',
            'version' => '2011',
            'publication_year' => 2011,
            'reliability_level' => 'high',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'society_position_stand',
                'population' => 'apparently healthy adults',
                'limitations' => 'General cardiorespiratory and resistance guidelines; not sport-specific programming.',
                'applicability' => 'exercise frequency, intensity, time, type (FITT)',
                'topics' => ['training', 'frequency', 'resistance'],
            ],
        ], [
            [
                'title' => 'Resistance and aerobic exercise quantity — ACSM themes',
                'topic' => 'training',
                'document_identifier' => 'acsm-quantity-quality-2011-summary',
                'body' => <<<'TXT'
Educational summary of ACSM quantity/quality of exercise position-stand themes (Garber et al., Medicine & Science in Sports & Exercise, 2011).

Adults benefit from regular aerobic activity and resistance exercise that stresses major muscle groups. Resistance training is commonly recommended on non-consecutive days for beginners, with progression in load, sets, or complexity over time.

Training frequency, intensity, time, and type (FITT) should match the individual’s fitness level and goals. Progressive overload — gradually increasing training demand — is a foundational principle for continued adaptation.

Programs should remain within safe technique limits. Injury symptoms and medical red flags require referral away from coach-only advice.
TXT,
                'metadata' => ['keywords' => ['resistance', 'frequency', 'progressive overload', 'aerobic', 'training']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'Strength and Hypertrophy Loading Recommendations (peer-reviewed synthesis)',
            'organization' => 'Strength and Conditioning Journal / sports-science literature synthesis',
            'source_type' => 'review',
            'evidence_level' => 'review',
            'url' => 'https://journals.lww.com/nsca-scj/Fulltext/2021/02000/Loading_Recommendations_for_Muscle_Strength,.4.aspx',
            'version' => '2021',
            'publication_year' => 2021,
            'reliability_level' => 'high',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'narrative_practical_review',
                'population' => 'resistance-trained and novice trainees (general)',
                'limitations' => 'Practical recommendations synthesize trials; individual response varies. Not equivalent to a systematic review with meta-analysis.',
                'applicability' => 'sets, reps, rest, hypertrophy vs strength loading',
                'topics' => ['training', 'hypertrophy', 'strength', 'rest'],
                'authors_note' => 'Schoenfeld, Grgic, et al. practical loading recommendations (NSCA SCJ).',
            ],
        ], [
            [
                'title' => 'Sets, reps, rest, and proximity to failure — practical themes',
                'topic' => 'training',
                'document_identifier' => 'scj-loading-recommendations-2021-summary',
                'body' => <<<'TXT'
Educational summary of widely cited practical loading themes for muscle strength and hypertrophy (Schoenfeld & Grgic et al., Strength and Conditioning Journal, 2021).

Hypertrophy can be stimulated across a spectrum of loads when sets are taken near enough to failure and weekly volume is adequate. Heavier loads are particularly useful for maximal strength. Rest intervals often range from roughly one to three+ minutes depending on load and goal; longer rests generally support performance on heavy strength sets.

Training to failure every set is not required for hypertrophy and can increase fatigue. Proximity to failure (leaving some reps in reserve on many sets) is a common evidence-aligned strategy, with selective failure use.

Progressive overload remains essential. Evidence is mixed on exact rest times and failure prescriptions — coaches should avoid claiming a single universal optimum.
TXT,
                'metadata' => ['keywords' => ['hypertrophy', 'strength', 'rest', 'failure', 'sets', 'reps', 'volume', 'proximity to failure']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'WHO Healthy Diet Fact Sheet',
            'organization' => 'World Health Organization (WHO)',
            'source_type' => 'official_guidance',
            'evidence_level' => 'official_guidance',
            'url' => 'https://www.who.int/news-room/fact-sheets/detail/healthy-diet',
            'version' => 'public fact sheet',
            'publication_year' => 2020,
            'reliability_level' => 'high',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'public_health_guidance',
                'population' => 'general population',
                'limitations' => 'Population guidance, not individualized clinical nutrition therapy.',
                'applicability' => 'energy balance, fats, sugars, salt, diet quality',
                'topics' => ['nutrition', 'energy balance', 'fats'],
            ],
        ], [
            [
                'title' => 'Healthy diet fundamentals — WHO public guidance themes',
                'topic' => 'nutrition',
                'document_identifier' => 'who-healthy-diet-summary',
                'body' => <<<'TXT'
Educational summary of WHO healthy-diet public guidance themes.

A healthy diet helps protect against malnutrition and noncommunicable disease risk factors. Energy intake should be balanced with energy expenditure. Limiting free sugars and excess salt, and shifting fat quality toward unsaturated fats where appropriate, are common public-health messages.

Fruits, vegetables, legumes, nuts, and whole grains feature prominently in healthy dietary patterns. Individual medical conditions require professional care beyond coach guidance.

OCTA FIT may compute personalized calorie targets with its deterministic engine; WHO guidance here supplies general diet-quality context, not personalized calorie numbers.
TXT,
                'metadata' => ['keywords' => ['healthy diet', 'energy balance', 'fats', 'sugars', 'nutrition']],
            ],
            [
                'title' => 'أسس النظام الغذائي الصحي — ملخص تعليمي بإسناد منظمة الصحة العالمية',
                'topic' => 'nutrition',
                'language' => 'ar',
                'document_identifier' => 'who-healthy-diet-summary-ar',
                'body' => <<<'TXT'
ملخص تعليمي لمفاهيم عامة من إرشادات منظمة الصحة العالمية حول النظام الغذائي الصحي (ليست ترجمة رسمية كاملة للنشرة).

يساعد النظام الغذائي الصحي على تقليل مخاطر سوء التغذية وعوامل الأمراض غير السارية. ينبغي موازنة الطاقة المستهلكة مع الطاقة المصروفة. تقليل السكريات الحرة والملح الزائد وتحسين جودة الدهون من الرسائل الشائعة للصحة العامة.

يبقى حساب السعرات والماكروز الشخصي في أوكتا فيت عبر المحرك الحتمي؛ هذا النص للتوضيح فقط وليس تشخيصاً طبياً.
TXT,
                'metadata' => ['keywords' => ['تغذية', 'سعرات', 'دهون', 'نظام غذائي']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'CDC Water & Healthier Drinks guidance',
            'organization' => 'Centers for Disease Control and Prevention (CDC)',
            'source_type' => 'official_guidance',
            'evidence_level' => 'official_guidance',
            'url' => 'https://www.cdc.gov/healthy-weight-growth/water-healthy-drinks/index.html',
            'version' => 'public guidance',
            'publication_year' => 2024,
            'reliability_level' => 'high',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'public_health_guidance',
                'population' => 'general public',
                'limitations' => 'General hydration messaging; athletic sweat losses vary widely.',
                'applicability' => 'everyday hydration habits',
                'topics' => ['hydration'],
            ],
        ], [
            [
                'title' => 'Hydration fundamentals — public guidance themes',
                'topic' => 'hydration',
                'document_identifier' => 'cdc-water-healthy-drinks-summary',
                'body' => <<<'TXT'
Educational summary of CDC healthier-drinks / water guidance themes.

Water is a primary everyday beverage choice for hydration. Sugary drinks can add excess calories without improving training outcomes. Fluid needs rise with heat, humidity, and prolonged sweating; thirst and urine color are practical individual cues for many healthy people.

Athletes in extreme environments may need structured fluid plans from qualified professionals. Coach chat must not diagnose dehydration emergencies.
TXT,
                'metadata' => ['keywords' => ['hydration', 'water', 'fluid', 'sweat']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'Sleep and Athletic Performance — consensus themes',
            'organization' => 'American Academy of Sleep Medicine / sports performance literature',
            'source_type' => 'review',
            'evidence_level' => 'review',
            'url' => 'https://aasm.org/clinical-resources/practicing-medicine/practice-guidelines/',
            'version' => null,
            'publication_year' => 2015,
            'reliability_level' => 'medium',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'clinical_and_performance_synthesis',
                'population' => 'athletes and adults',
                'limitations' => 'Sleep disorders require clinical care. URL points to AASM clinical resources hub, not a single paper.',
                'applicability' => 'recovery, sleep duration habits',
                'topics' => ['recovery', 'sleep'],
            ],
        ], [
            [
                'title' => 'Sleep, recovery, and training readiness',
                'topic' => 'recovery',
                'document_identifier' => 'sleep-athletic-recovery-summary',
                'body' => <<<'TXT'
Educational summary of widely reported sleep and athletic recovery themes.

Adequate sleep supports cognitive function, mood, and physical recovery. Chronic restriction can impair performance and increase perceived fatigue. Consistent sleep schedules and dark, quiet sleep environments are common practical recommendations.

Sleep is not a substitute for appropriate training load management. Persistent insomnia, apnea symptoms, or daytime collapse need medical evaluation — not coach diagnosis.
TXT,
                'metadata' => ['keywords' => ['sleep', 'recovery', 'fatigue', 'performance']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'NSCA Guide themes: Strength & Conditioning for Combat Sports',
            'organization' => 'National Strength and Conditioning Association (NSCA)',
            'source_type' => 'educational',
            'evidence_level' => 'educational',
            'url' => 'https://www.nsca.com/',
            'version' => null,
            'publication_year' => null,
            'reliability_level' => 'medium',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'professional_education',
                'population' => 'combat-sport athletes (general)',
                'limitations' => 'Educational synthesis aligned with NSCA professional resources; not a single peer-reviewed trial. Organization URL only — no fabricated chapter DOI.',
                'applicability' => 'MMA/combat conditioning principles',
                'topics' => ['combat', 'conditioning', 'power'],
            ],
        ], [
            [
                'title' => 'Combat-sport strength and conditioning principles',
                'topic' => 'combat',
                'document_identifier' => 'nsca-combat-sc-principles-summary',
                'body' => <<<'TXT'
Educational summary of common NSCA-aligned strength and conditioning themes for combat sports.

Combat athletes typically need a mix of maximal strength, power, anaerobic capacity, and sport-specific technical work. Plyometric and Olympic-lift variations are sometimes used for power when technique and readiness allow.

Conditioning should respect weight-making practices and injury status. High-intensity intervals can develop fight-relevant energy systems, but recovery between hard sessions matters. Program design must remain grounded in real exercises available in OCTA FIT when IDs are recommended.
TXT,
                'metadata' => ['keywords' => ['mma', 'combat', 'conditioning', 'power', 'plyometric', 'strength']],
            ],
        ]);

        $ingest->upsertSourceWithDocuments([
            'name' => 'Bodyweight / calisthenics resistance training principles',
            'organization' => 'ACSM / NSCA educational practice themes',
            'source_type' => 'educational',
            'evidence_level' => 'educational',
            'url' => null, // intentionally no URL — do not invent
            'version' => null,
            'publication_year' => null,
            'reliability_level' => 'educational',
            'language' => 'en',
            'metadata' => [
                'study_type' => 'educational_synthesis',
                'population' => 'home trainees',
                'limitations' => 'No single canonical URL; principles drawn from general resistance-training education. Missing URL is intentional.',
                'applicability' => 'home and calisthenics progression',
                'topics' => ['home', 'training'],
            ],
        ], [
            [
                'title' => 'Progressing bodyweight resistance training at home',
                'topic' => 'home',
                'document_identifier' => 'home-calisthenics-progression-summary',
                'body' => <<<'TXT'
Educational summary of bodyweight and home resistance-training progression principles.

Calisthenics can build strength and hypertrophy when progressive overload is applied via harder variations, slower tempos, added sets, reduced rest, or external load (backpack, bands). Full range of motion with controlled technique is preferred when joints tolerate it.

Home programs should match available equipment and recovery. Exercise IDs must come from the OCTA FIT catalog when the coach recommends specific movements.
TXT,
                'metadata' => ['keywords' => ['bodyweight', 'calisthenics', 'home', 'progression', 'range of motion']],
            ],
        ]);
    }
}
