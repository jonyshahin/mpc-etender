<?php

namespace App\Services;

use ArPHP\I18N\Arabic;

/**
 * Prepares Arabic text for PDF rendering.
 *
 * dompdf draws glyphs in the order it receives them and does neither of the
 * two things Arabic needs: it does not reorder right-to-left runs (bidi), and
 * it does not pick the joined letter form that depends on a character's
 * neighbours (shaping). Handed logical-order Arabic it produces text that is
 * both reversed and written in disconnected isolated letters — "اربيل / موصل"
 * came out as "لصوم / ليبرا".
 *
 * This converts a logical-order string into the visually-ordered Arabic
 * Presentation Forms-B sequence dompdf can lay out correctly left to right.
 * DejaVu Sans, which the PDF templates use, carries 141 of the 144 glyphs in
 * that block.
 *
 * Only for PDF output. The React pages must receive the original logical
 * order — browsers do their own bidi and shaping, and pre-shaped text breaks
 * selection, search and screen readers.
 */
class ArabicTextService
{
    /**
     * High enough that ar-php never inserts its own line breaks.
     *
     * Its wrapping is the correct way to break a long RTL paragraph, but it
     * returns newlines that an HTML table cell collapses. The fields on these
     * letters are short, so letting the renderer wrap is the lesser problem —
     * a wrapped visual-order line reads out of order, but the words inside it
     * stay intact.
     */
    private const NO_WRAP = 10000;

    private ?Arabic $arabic = null;

    /**
     * Shaped and reordered for a PDF, or the input unchanged if it holds no
     * Arabic.
     *
     * The guard is not just an optimisation: it keeps Latin company names and
     * reference codes away from a transform that has no reason to touch them.
     */
    public function forPdf(?string $text): string
    {
        if ($text === null || trim($text) === '' || ! $this->containsArabic($text)) {
            return (string) $text;
        }

        // $hindo = false: ar-php otherwise rewrites Western digits as Arabic-Indic
        // (2026 becomes ٢٠٢٦). These letters are bilingual and every other number
        // on them — licence numbers, dates, phone numbers — is Western, so an
        // address with a street number would suddenly disagree with the rest.
        return $this->arabic()->utf8Glyphs($text, self::NO_WRAP, hindo: false);
    }

    /** True when the string holds at least one character in an Arabic block. */
    public function containsArabic(string $text): bool
    {
        // Arabic, Supplement, Extended-A, and both Presentation Forms ranges.
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /** Deferred: ar-php loads its glyph tables in the constructor. */
    private function arabic(): Arabic
    {
        return $this->arabic ??= new Arabic;
    }
}
