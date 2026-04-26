<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * POLICY-01: every user-uploaded document in the e-Tender system must be
 * a PDF, capped at 5 MB. This rule encapsulates that policy so the five
 * FormRequests using it stay in sync — change the constants here once,
 * not in seven places.
 *
 * BOQ template imports (xlsx/csv) deliberately bypass this rule — those
 * are data uploads, not document storage. If a sixth FormRequest needs
 * the same policy, just `new PdfFile()` it; if it needs different
 * mimes/size, write a different rule rather than parameterising this one
 * (keeps the policy intent unambiguous at every callsite).
 */
final class PdfFile implements ValidationRule
{
    public const MAX_KB = 5120;

    public const MAX_BYTES = self::MAX_KB * 1024;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(__('validation.uploaded', ['attribute' => $attribute]));

            return;
        }

        // Size before mime: a 50 MB .docx fails both rules but the
        // user-actionable problem is the size — surface that first.
        if ($value->getSize() > self::MAX_BYTES) {
            $fail(__('bid.documents.file_too_large'));

            return;
        }

        // Delegate the mime check to Laravel's mimes:pdf — does extension
        // + finfo content sniff, so a renamed `evil.exe` → `evil.pdf`
        // won't pass. (FileUploadService:24 still does extension-only,
        // tracked as TECH-DEBT-02 in BUGS.md.)
        //
        // Use a flat 'file' key in the inner validator regardless of the
        // outer $attribute — when this rule is applied to a nested field
        // like `evidence.*` or `documents.*.file`, the delegated validator
        // would otherwise try to traverse the dot-path and find no value.
        $mimeCheck = Validator::make(
            ['file' => $value],
            ['file' => ['file', 'mimes:pdf']]
        );

        if ($mimeCheck->fails()) {
            $fail(__('bid.documents.pdf_only'));
        }
    }
}
