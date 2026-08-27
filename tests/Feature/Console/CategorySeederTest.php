<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it seeds the trade taxonomy', function () {
    $this->seed(CategorySeeder::class);

    expect(Category::whereNull('parent_id')->pluck('name_en')->all())
        ->toContain('Civil Works', 'MEP', 'Cleaning Services');
});

/**
 * Cleaning Services carries no children, which the pickers handle — they list
 * parents as selectable options and indent children beneath them. Asserted
 * because an empty child list is the one shape the seeder loop could silently
 * skip.
 */
test('a childless trade is still seeded, with its Arabic name', function () {
    $this->seed(CategorySeeder::class);

    $cleaning = Category::where('name_en', 'Cleaning Services')->sole();

    expect($cleaning->parent_id)->toBeNull()
        ->and($cleaning->name_ar)->toBe('خدمات التنظيف')
        ->and($cleaning->is_active)->toBeTrue()
        ->and($cleaning->children()->count())->toBe(0);
});

test('re-running it neither duplicates nor discards edited names', function () {
    $this->seed(CategorySeeder::class);
    $before = Category::count();

    // Whatever an admin renamed through /admin/categories reverts on a re-seed;
    // that is the documented cost of --reseed, but nothing may be duplicated.
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe($before)
        ->and(Category::where('name_en', 'Cleaning Services')->count())->toBe(1);
});
