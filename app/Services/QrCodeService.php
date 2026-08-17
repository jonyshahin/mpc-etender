<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;
use RuntimeException;

/**
 * Renders QR codes as inline SVG.
 *
 * Built on BaconQrCode's pure-PHP SVG backend, which is already installed as a
 * Fortify dependency (it renders the 2FA enrolment codes). That means no new
 * package and, unlike the PNG/Imagick backends, no GD or imagick extension —
 * which matters because the container image ships neither.
 */
class QrCodeService
{
    /**
     * Render $data as an SVG document.
     *
     * @param  string  $data  The payload to encode, typically a URL
     * @param  int  $size  Rendered edge length in pixels
     */
    public function svg(string $data, int $size = 220): string
    {
        if (trim($data) === '') {
            throw new InvalidArgumentException('Cannot render a QR code for an empty payload.');
        }

        $svg = (new Writer(
            new ImageRenderer(
                // Quiet zone of 4 modules per ISO/IEC 18004 — scanners need the
                // margin to locate the symbol reliably on a printed page.
                new RendererStyle($size, 4, null, null, Fill::uniformColor(
                    new Rgb(255, 255, 255),
                    new Rgb(17, 24, 39),
                )),
                new SvgImageBackEnd,
            )
            // 'UTF-8' is load-bearing, not decoration. writeString() defaults to
            // ISO-8859-1, which throws outright on Arabic/Kurdish URLs and — worse
            // — silently encodes Latin-1-representable characters with no ECI
            // header, so scanners decoding UTF-8 resolve a mojibake address.
        ))->writeString($data, 'UTF-8');

        // Drop the leading XML prolog line. It is invalid inside an inlined
        // document and breaks the data URI in some renderers. (Written without
        // the literal closing tag: it would end PHP mode even in a comment.)
        //
        // Guarded rather than trusting strpos: a false return would make this
        // substr($svg, 1) and silently lop the opening '<' off the document.
        $prologEnd = strpos($svg, "\n");

        return trim($prologEnd === false ? $svg : substr($svg, $prologEnd + 1));
    }

    /**
     * Render $data as a base64 SVG data URI, suitable for an <img src>.
     *
     * Preferred over passing raw markup to the frontend: the page can render it
     * with a plain <img> instead of dangerouslySetInnerHTML.
     */
    public function svgDataUri(string $data, int $size = 220): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($data, $size));
    }

    /**
     * Render $data as a base64 PNG data URI.
     *
     * For dompdf specifically. The SVG renderer emits one path per module, and
     * dompdf's SVG layer expands those into thousands of vector operations — a
     * one-page letter ballooned to 1.1 MB and exhausted a 128 MB process. A flat
     * raster costs a few KB and dompdf draws it in one operation.
     *
     * Drawn straight from the QR matrix with GD, because BaconQrCode's only
     * bundled raster backend needs the imagick extension, which is not installed.
     *
     * @param  int  $moduleSize  Pixels per QR module
     * @param  int  $margin  Quiet zone in modules — 4 per ISO/IEC 18004
     */
    public function pngDataUri(string $data, int $moduleSize = 6, int $margin = 4): string
    {
        if (trim($data) === '') {
            throw new InvalidArgumentException('Cannot render a QR code for an empty payload.');
        }

        // ext-gd is not declared in composer.json (adding it would invalidate the
        // lock file's content hash), so fail with something actionable rather than
        // a bare "call to undefined function imagecreatetruecolor".
        if (! extension_loaded('gd')) {
            throw new RuntimeException(
                'The GD extension is required to render the PDF QR code. Enable ext-gd, '.
                'or use the browser Print button instead of the PDF download.'
            );
        }

        // Level M (~15% recovery) rather than the library default of L (~7%):
        // this ends up on paper that gets folded, stamped and handled.
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();

        $modules = $matrix->getWidth();
        $edge = ($modules + 2 * $margin) * $moduleSize;

        $image = imagecreatetruecolor($edge, $edge);
        $light = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 17, 24, 39);
        imagefilledrectangle($image, 0, 0, $edge, $edge, $light);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $left = ($x + $margin) * $moduleSize;
                    $top = ($y + $margin) * $moduleSize;
                    imagefilledrectangle(
                        $image,
                        $left,
                        $top,
                        $left + $moduleSize - 1,
                        $top + $moduleSize - 1,
                        $dark,
                    );
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
