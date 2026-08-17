<?php

use App\Services\PrintAssetService;

/**
 * dompdf embeds a raster at its native resolution no matter how small it is
 * drawn. The official 2104x1534 logo pushed a one-page letter to 522 KB and
 * 124 MB of peak memory — under PHP's 128 MB default, so production would have
 * OOM'd on a PDF download.
 */
test('a large raster logo is downscaled into a data URI', function () {
    $source = public_path('boulevard-logo.png');

    expect(is_file($source))->toBeTrue('boulevard-logo.png should be committed');

    $result = app(PrintAssetService::class)->logoForPdf('/boulevard-logo.png');

    expect($result)->toStartWith('data:image/png;base64,');

    $decoded = base64_decode(substr($result, strlen('data:image/png;base64,')));

    // Comfortably smaller than the source, and no wider than the print width.
    expect(strlen($decoded))->toBeLessThan((int) (filesize($source) / 2));

    [$width] = getimagesizefromstring($decoded);
    expect($width)->toBeLessThanOrEqual(320);
});

test('a vector logo is passed through as a path rather than rasterised', function () {
    $result = app(PrintAssetService::class)->logoForPdf('/boulevard-logo.svg');

    // php-svg-lib draws SVG cheaply; converting it would only lose fidelity.
    expect($result)->toBe(public_path('boulevard-logo.svg'));
});

test('a missing logo yields null so the letter still renders', function () {
    $result = app(PrintAssetService::class)->logoForPdf('/does-not-exist.png');

    expect($result)->toBeNull();
});

test('a path outside public resolves to null rather than escaping the web root', function () {
    expect(app(PrintAssetService::class)->logoForPdf('/../.env'))->toBeNull();
});
