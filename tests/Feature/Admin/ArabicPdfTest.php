<?php

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ArabicTextService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Codepoints of a string, for asserting on which Unicode block they fall in. */
function codepoints(string $text): array
{
    return array_map(fn ($c) => mb_ord($c, 'UTF-8'), mb_str_split($text));
}

function hasPresentationForms(string $text): bool
{
    return (bool) preg_match('/[\x{FE70}-\x{FEFF}]/u', $text);
}

test('it converts Arabic to the joined forms dompdf can draw', function () {
    $shaped = app(ArabicTextService::class)->forPdf('اربيل / موصل');

    // Logical-order Arabic reaches dompdf as isolated letters in reverse; the
    // presentation forms are the joined shapes, already in visual order.
    expect(hasPresentationForms($shaped))->toBeTrue()
        ->and($shaped)->not->toBe('اربيل / موصل');

    // Every glyph produced must exist in the font the templates use, or dompdf
    // draws an empty box.
    $font = json_decode(file_get_contents(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ufm.json')), true);

    foreach (codepoints($shaped) as $cp) {
        expect(isset($font['C'][$cp]))->toBeTrue('DejaVu Sans has no glyph for U+'.strtoupper(dechex($cp)));
    }
});

test('it leaves text with no Arabic exactly as it was', function (string $text) {
    expect(app(ArabicTextService::class)->forPdf($text))->toBe($text);
})->with([
    'Sama cool',
    'MPC Group',
    'ibrahim.samir@samacoolhvac.com',
    '+9647704228222',
    '2020466320',
    'https://etender.mpciraq.com/',
]);

test('it handles absent values without fuss', function () {
    $service = app(ArabicTextService::class);

    expect($service->forPdf(null))->toBe('')
        ->and($service->forPdf(''))->toBe('')
        ->and($service->forPdf('   '))->toBe('   ');
});

/**
 * ar-php rewrites Western digits as Arabic-Indic by default — '2026' becomes
 * '٢٠٢٦'. Everything else numeric on these letters (licence number, dates,
 * phone) is Western, so an address carrying a street number would otherwise
 * disagree with the field beside it.
 */
test('it keeps numbers Western inside an Arabic string', function () {
    $shaped = app(ArabicTextService::class)->forPdf('شارع 52 الموصل');

    expect($shaped)->toContain('52')
        ->and($shaped)->not->toContain('٥٢')
        ->and(hasPresentationForms($shaped))->toBeTrue();
});

test('it keeps a Latin run inside a mixed string', function () {
    $shaped = app(ArabicTextService::class)->forPdf('اربيل 2026 Erbil');

    expect($shaped)->toContain('Erbil')
        ->and($shaped)->toContain('2026')
        ->and(hasPresentationForms($shaped))->toBeTrue();
});

test('it detects Arabic across the blocks that matter', function (string $text, bool $expected) {
    expect(app(ArabicTextService::class)->containsArabic($text))->toBe($expected);
})->with([
    ['اربيل', true],
    ['سما البرد', true],
    ['Sama cool', false],
    ['2020466320', false],
    // Already-shaped text must still register, or a double pass would look safe.
    ['ﻞﺻﻮﻣ', true],
]);

/**
 * The end of the chain: an Arabic vendor's letter must reach dompdf carrying
 * joined forms. Asserting on the rendered view rather than the PDF bytes keeps
 * this readable — the PDF is dompdf's faithful rendering of exactly this HTML.
 */
test('the confirmation letter hands dompdf shaped Arabic, not logical order', function () {
    $role = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
    $permission = Permission::firstOrCreate(
        ['slug' => 'vendors.view'],
        ['name' => 'View Vendors', 'module' => 'vendors'],
    );
    $role->permissions()->attach($permission->id);
    $admin = User::factory()->create(['role_id' => $role->id]);

    $vendor = Vendor::factory()->create([
        'company_name' => 'Sama cool',
        'company_name_ar' => 'سما البرد',
        'address' => 'اربيل / موصل',
        'city' => 'اربيل',
        'country' => 'العراق',
        'contact_person' => 'ابراهيم سمير يونس',
    ]);
    $vendor->categories()->attach(Category::factory()->create(['name_en' => 'Mechanical'])->id);

    $response = $this->actingAs($admin)->get(route('admin.vendors.confirmation.pdf', $vendor));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    // The same view the PDF is built from, rendered directly.
    $service = app(ArabicTextService::class);
    $html = view('pdf.vendor-confirmation', [
        'vendor' => $vendor->load('categories'),
        'companyName' => 'MPC Group',
        'projectName' => 'Boulevard Mosul Project',
        'websiteUrl' => 'https://example.test',
        'qrCode' => 'data:image/png;base64,',
        'generatedAt' => now()->toIso8601String(),
        'temporaryPassword' => null,
        'logoSrc' => null,
        'companyLogoSrc' => null,
    ])->render();

    foreach (['address', 'contact_person', 'company_name_ar'] as $field) {
        expect($html)->toContain($service->forPdf($vendor->{$field}))
            ->and($html)->not->toContain('>'.$vendor->{$field}.'<');
    }
});
