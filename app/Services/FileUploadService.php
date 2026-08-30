<?php

namespace App\Services;

use App\Models\DocumentAccessLog;
use App\Rules\PdfFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Centralized file upload to S3 with validation and access logging.
 */
class FileUploadService
{
    /**
     * Upload a file to S3-compatible storage.
     *
     * @param  string|null  $allowedTypes  Comma-separated MIME types, e.g. 'pdf,doc,docx,jpg,png,xlsx'
     * @return string The stored file path
     */
    public function upload(UploadedFile $file, string $directory, ?string $allowedTypes = null): string
    {
        if ($allowedTypes) {
            $allowed = array_map('trim', explode(',', $allowedTypes));
            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, $allowed)) {
                throw new \InvalidArgumentException(
                    "File type '{$extension}' is not allowed. Allowed types: {$allowedTypes}"
                );
            }
        }

        // Reads POLICY-01 off the rule rather than restating it: this ceiling
        // and the FormRequest layer drifted apart the moment either moved.
        // TODO(TECH-DEBT-02): add mime-sniffing (currently extension-based,
        // weaker than FormRequest's mimes:pdf).
        if ($file->getSize() > PdfFile::MAX_BYTES) {
            throw new \InvalidArgumentException(
                'File size exceeds the maximum allowed size of '.PdfFile::maxLabel().'.'
            );
        }

        return $file->store($directory, 's3');
    }

    /**
     * Delete a file from S3 storage.
     */
    public function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    /**
     * Generate a temporary presigned URL for secure download.
     */
    public function getTemporaryUrl(string $path, int $expirationMinutes = 30): string
    {
        return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes($expirationMinutes));
    }

    /**
     * Log a document access event (view, download, print).
     */
    public function logAccess(
        string $documentType,
        string $documentId,
        string $action,
        ?string $userId = null,
        ?string $vendorId = null,
    ): void {
        DocumentAccessLog::create([
            'user_id' => $userId,
            'vendor_id' => $vendorId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'accessed_at' => now(),
        ]);
    }
}
