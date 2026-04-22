<?php

/*
|--------------------------------------------------------------------------
| Tender Test Datasets (adapted to real MPC e-Tender API contract)
|--------------------------------------------------------------------------
| Field-name conventions used by StoreTenderRequest:
|   description_en / description_ar (not "description")
|   boq_sections.*.title_en        (not "name")
|   boq_sections.*.items.*.description_en + item_code + unit + quantity
|   evaluation_criteria.*.name_en  (not "name")
|   tender_type ∈ open|restricted|direct_invitation|framework
|--------------------------------------------------------------------------
*/

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Baseline valid payload. Tests pass overrides for only the fields they care about.
 */
function tenderPayload(array $overrides = []): array
{
    $project = Project::first() ?? Project::factory()->create();
    $category = Category::first() ?? Category::factory()->create();

    // Use array_merge (not recursive) so overrides fully replace nested
    // arrays like boq_sections / evaluation_criteria — recursive merge
    // would keep stale items at indices the override didn't touch.
    return array_merge([
        'project_id' => $project->id,
        'title_en' => 'Standard Test Tender',
        'title_ar' => 'مناقصة اختبار قياسية',
        'description_en' => 'Baseline description for test purposes.',
        'description_ar' => null,
        'tender_type' => 'open',
        'currency' => 'USD',
        'estimated_value' => 100000,
        'is_two_envelope' => false,
        'submission_deadline' => Carbon::now()->addDays(30)->format('Y-m-d H:i:s'),
        'opening_date' => Carbon::now()->addDays(31)->format('Y-m-d H:i:s'),
        'category_ids' => [$category->id],
        'boq_sections' => [
            [
                'title_en' => 'Section 1',
                'title_ar' => 'القسم الأول',
                'sort_order' => 0,
                'items' => [
                    ['item_code' => 'A.1', 'description_en' => 'Item A', 'unit' => 'Ton', 'quantity' => 10, 'sort_order' => 0],
                    ['item_code' => 'A.2', 'description_en' => 'Item B', 'unit' => 'Ton', 'quantity' => 20, 'sort_order' => 1],
                ],
            ],
        ],
        'evaluation_criteria' => [
            ['name_en' => 'Price', 'weight_percentage' => 100, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 0],
        ],
        'publish' => false,
    ], $overrides);
}

function fakeDoc(string $filename = 'spec.pdf', int $kilobytes = 500, string $mime = 'application/pdf'): UploadedFile
{
    return UploadedFile::fake()->create($filename, $kilobytes, $mime);
}

dataset('invalidTenderPayloads', [
    'unknown tender_type' => [['tender_type' => 'unknown'], 'tender_type'],
    'past submission deadline' => [['submission_deadline' => Carbon::now()->subDay()->format('Y-m-d H:i:s'), 'publish' => true], 'submission_deadline'],
    'opening before submission' => [['opening_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'), 'submission_deadline' => Carbon::now()->addDays(10)->format('Y-m-d H:i:s')], 'opening_date'],
    'non-existent category' => [['category_ids' => ['00000000-0000-0000-0000-000000000000']], 'category_ids.0'],
    // Note: "weights not summing to 100" is NOT a validation-layer failure —
    // it's a publish prerequisite. See T-C-34 in CreateTenderMatrixTest.
    'negative criterion weight' => [['evaluation_criteria' => [
        ['name_en' => 'A', 'weight_percentage' => -10, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 0],
    ]], 'evaluation_criteria.0.weight_percentage'],
    'negative BOQ quantity' => [['boq_sections' => [[
        'title_en' => 'S', 'sort_order' => 0,
        'items' => [['item_code' => 'X.1', 'description_en' => 'X', 'unit' => 'Ton', 'quantity' => -5, 'sort_order' => 0]],
    ]]], 'boq_sections.0.items.0.quantity'],
    'BOQ section missing title_en' => [['boq_sections' => [[
        'title_en' => '', 'sort_order' => 0, 'items' => [],
    ]]], 'boq_sections.0.title_en'],
    'missing title_en' => [['title_en' => ''], 'title_en'],
    'missing project_id' => [['project_id' => null], 'project_id'],
]);

dataset('tenderTypeVariants', [
    'open' => ['open'],
    'restricted' => ['restricted'],
    'direct_invitation' => ['direct_invitation'],
    'framework' => ['framework'],
]);

dataset('validBoqShapes', [
    'single section single item' => [[[
        'title_en' => 'S1', 'sort_order' => 0,
        'items' => [['item_code' => 'A.1', 'description_en' => 'A', 'unit' => 'Ton', 'quantity' => 1, 'sort_order' => 0]],
    ]], 1],
    'single section multiple items' => [[[
        'title_en' => 'S1', 'sort_order' => 0,
        'items' => [
            ['item_code' => 'A.1', 'description_en' => 'A', 'unit' => 'Ton', 'quantity' => 1, 'sort_order' => 0],
            ['item_code' => 'A.2', 'description_en' => 'B', 'unit' => 'm3', 'quantity' => 2.5, 'sort_order' => 1],
            ['item_code' => 'A.3', 'description_en' => 'C', 'unit' => 'Each', 'quantity' => 100, 'sort_order' => 2],
        ],
    ]], 3],
    'multi section mixed' => [[
        ['title_en' => 'Civil', 'sort_order' => 0, 'items' => array_map(fn ($i) => ['item_code' => "C.$i", 'description_en' => "C$i", 'unit' => 'Ton', 'quantity' => $i + 1, 'sort_order' => $i], range(0, 4))],
        ['title_en' => 'Mechanical', 'sort_order' => 1, 'items' => array_map(fn ($i) => ['item_code' => "M.$i", 'description_en' => "M$i", 'unit' => 'Each', 'quantity' => ($i + 1) * 10, 'sort_order' => $i], range(0, 4))],
        ['title_en' => 'Electrical', 'sort_order' => 2, 'items' => array_map(fn ($i) => ['item_code' => "E.$i", 'description_en' => "E$i", 'unit' => 'm', 'quantity' => ($i + 1) * 100, 'sort_order' => $i], range(0, 4))],
    ], 15],
    'arabic descriptions and units' => [[[
        'title_en' => 'قسم الحديد', 'sort_order' => 0,
        'items' => [['item_code' => 'AR.1', 'description_en' => 'حديد تسليح 12مم', 'unit' => 'طن', 'quantity' => 50, 'sort_order' => 0]],
    ]], 1],
]);

dataset('actorsWhoCannotCreate', [
    'evaluator' => [fn () => User::factory()->evaluator()->create()],
    'unassigned user' => [fn () => User::factory()->create()],
]);

dataset('terminalTenderStatuses', [
    'cancelled' => ['cancelled'],
    'awarded' => ['awarded'],
]);
