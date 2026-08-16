<?php

namespace App\Services;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

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
}
