<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Prepares image assets for PDF rendering.
 *
 * dompdf embeds a raster at its native resolution regardless of the size it is
 * displayed at. The Boulevard logo is 2104x1534 (~518 KB) but the letterhead
 * draws it 80px wide, which pushed a one-page letter to 522 KB and 124 MB of
 * peak memory — under PHP's 128 MB default. Downscaling first brings both back
 * to a few tens of KB.
 */
class PrintAssetService
{
    /** Widest the logo is ever drawn on the letter, times a print-quality factor. */
    private const LOGO_WIDTH = 320;

    /**
     * A dompdf-safe source for a public image path.
     *
     * Returns an absolute filesystem path for vectors (dompdf renders those
     * cheaply) and a downscaled base64 PNG data URI for rasters. Null when the
     * file is missing or unreadable, so callers can omit the image rather than
     * failing the whole document.
     */
    public function logoForPdf(string $publicUrl): ?string
    {
        $path = realpath(public_path(ltrim($publicUrl, '/')));
        $root = realpath(public_path());

        // Never resolve outside the web root. The caller passes a hardcoded name
        // today, but handing dompdf an arbitrary path is not a mistake worth
        // leaving available — '/../.env' resolves to a real file.
        if ($path === false || $root === false || ! str_starts_with($path, $root)) {
            return null;
        }

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        // SVG has no raster cost and php-svg-lib handles it directly.
        if (str_ends_with(strtolower($path), '.svg')) {
            return $path;
        }

        $dimensions = @getimagesize($path);

        // Not a decodable image — omit it rather than passing dompdf a file it
        // will choke on.
        if ($dimensions === false) {
            return null;
        }

        // Already small enough, or no GD to scale with: let dompdf read the file.
        if ($dimensions[0] <= self::LOGO_WIDTH || ! extension_loaded('gd')) {
            return $path;
        }

        // Keyed on mtime and size so dropping in a new logo invalidates it.
        $key = 'print-asset:logo:'.md5($path.filemtime($path).filesize($path));

        return Cache::rememberForever($key, fn () => $this->downscaledPngDataUri($path) ?? $path);
    }

    /** Scale an image down to LOGO_WIDTH and return it as a PNG data URI. */
    private function downscaledPngDataUri(string $path): ?string
    {
        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            return null;
        }

        $scaled = imagescale($source, self::LOGO_WIDTH);
        imagedestroy($source);

        if ($scaled === false) {
            return null;
        }

        // Preserve transparency: the mark sits on the letterhead's white.
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        ob_start();
        imagepng($scaled);
        $png = (string) ob_get_clean();
        imagedestroy($scaled);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
