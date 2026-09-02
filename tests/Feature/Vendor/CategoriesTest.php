<?php

use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A vendor with one approved child category under an active root.
 *
 * Names are pinned: CategoryFactory picks name_en at random from a short list
 * that includes 'Steelwork', so a fixture row could collide with a search term
 * a test below looks for and make it pass or fail by luck of the seed.
 */
function categoriesFixture(): array
{
    $root = Category::factory()->create([
        'parent_id' => null,
        'is_active' => true,
        'sort_order' => 0,
        'name_en' => 'Fixture Root',
        'name_ar' => 'جذر',
    ]);
    $child = Category::factory()->create([
        'parent_id' => $root->id,
        'is_active' => true,
        'name_en' => 'Fixture Child',
        'name_ar' => 'فرع',
    ]);

    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach($child->id);

    return [$vendor, $root, $child];
}

// ── Retired categories ──

test('a retired child category is not offered to the vendor', function () {
    [$vendor, $root] = categoriesFixture();

    Category::factory()->create([
        'parent_id' => $root->id,
        'name_en' => 'Retired Trade',
        'is_active' => false,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.categories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // The root query filtered active(); the children eager load did not,
        // so categories MPC had retired were still listed.
        ->has('categories.0.children', 1)
    );
});

test('a retired root category is not offered either', function () {
    [$vendor] = categoriesFixture();
    Category::factory()->create(['parent_id' => null, 'is_active' => false]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.categories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('categories', 1));
});

test('a category the vendor already holds stays listed even once retired', function () {
    [$vendor, $root, $child] = categoriesFixture();
    $child->update(['is_active' => false]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.categories.index'));

    $response->assertOk();
    // Hiding it would make the page disagree with the vendor's own record:
    // they hold it, and they should be able to see that they do.
    $response->assertInertia(fn ($page) => $page
        ->has('categories.0.children', 1)
        ->where('categories.0.children.0.id', $child->id)
        ->where('categories.0.children.0.is_approved', true)
    );
});

// ── What the page ships ──

test('the raw is_active flag is not shipped', function () {
    [$vendor] = categoriesFixture();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.categories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // Selected on the eager load, never declared by the page's own type,
        // never rendered.
        ->missing('categories.0.children.0.is_active')
        ->missing('categories.0.is_active')
    );
});

test('each category says whether the vendor holds it', function () {
    [$vendor, , $child] = categoriesFixture();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.categories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // The page rebuilt this client-side from a flat id list. Sending it per
        // row lets the server count approvals for the tiles off the same data.
        ->where('categories.0.children.0.is_approved', true)
        ->where('categories.0.is_approved', false)
    );
});

// ── Headline figures and search ──

test('the page leads with how many categories the vendor holds', function () {
    [$vendor, $root] = categoriesFixture();
    foreach (range(1, 3) as $n) {
        Category::factory()->create([
            'parent_id' => $root->id,
            'is_active' => true,
            'name_en' => "Fixture Sibling {$n}",
        ]);
    }

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.categories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.approved', 1)
        ->where('summary.available', 5)
        ->has('summary.open_request')
    );
});

test('the tree can be searched', function () {
    [$vendor, $root] = categoriesFixture();
    Category::factory()->create([
        'parent_id' => $root->id,
        'name_en' => 'Structural Steelwork',
        'is_active' => true,
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.categories.index', ['search' => 'Steelwork']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('categories.0.children', 1)
        ->where('categories.0.children.0.name_en', 'Structural Steelwork')
    );
});

test('searching can be narrowed to the categories the vendor holds', function () {
    [$vendor, $root, $child] = categoriesFixture();
    Category::factory()->create([
        'parent_id' => $root->id,
        'is_active' => true,
        'name_en' => 'Fixture Unapproved',
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.categories.index', ['filter' => 'approved']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('categories.0.children', 1)
        ->where('categories.0.children.0.id', $child->id)
    );
});

test('a root with no matching children drops out of a search', function () {
    [$vendor] = categoriesFixture();
    Category::factory()->create([
        'parent_id' => null,
        'name_en' => 'Unrelated Root',
        'is_active' => true,
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.categories.index', ['search' => 'zzzz-no-such-category']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('categories', 0));
});
