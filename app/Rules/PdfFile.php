<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * POLICY-01: every user-uploaded document in the e-Tender system must be
 * a PDF, capped at MAX_KB. This rule encapsulates that policy so the
 * FormRequests using it stay in sync — change the constant here once,
 * not in seven places.
 *
 * The size is also the source of truth for the client: app.blade.php
 * injects MAX_BYTES as window.__maxUploadBytes__ so the browser-side
 * checks and the "up to :size" hints cannot drift from what the server
 * will actually accept.
 *
 * A cap this high needs matching PHP settings — upload_max_filesize and
 * post_max_size must both exceed it, and post_max_size must exceed the
 * SUM of files in one request (the tender wizard posts several). When
 * post_max_size is too small PHP discards the body before Laravel sees
 * it, and the user gets a 413 from ValidatePostSize rather than a
 * per-field message.
 *
 * BOQ template imports (xlsx/csv) deliberately bypass this rule — those
 * are data uploads, not document storage, and are capped separately in
 * BoqController. If a FormRequest needs different mimes/size, write a
 * different rule rather than parameterising this one (keeps the policy
 * intent unambiguous at every callsite).
 */
final class PdfFile implements ValidationRule
{
    public const MAX_KB = 102400;

    public const MAX_BYTES = self::MAX_KB * 1024;

    /** Human-readable cap for messages, e.g. "100 MB". */
    public static function maxLabel(): string
    {
        $mb = self::MAX_KB / 1024;

        return ($mb === floor($mb) ? (string) (int) $mb : number_format($mb, 1)).' MB';
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(__('validation.uploaded', ['attribute' => $attribute]));

            return;
        }

        // Size before mime: an oversized .docx fails both rules but the
        // user-actionable problem is the size — surface that first.
        if ($value->getSize() > self::MAX_BYTES) {
            $fail(__('bid.documents.file_too_large', ['size' => self::maxLabel()]));

            return;
        }

        // Delegate the mime check to Laravel's mimes:pdf — does extension
        // + finfo content sniff, so a renamed `evil.exe` → `evil.pdf`
        // won't pass. (FileUploadService still does extension-only,
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
            $fail(__('bid.documents.pdf_only', ['size' => self::maxLabel()]));
        }
    }
}
