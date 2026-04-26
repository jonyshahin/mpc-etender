import { router, useForm } from '@inertiajs/react';
import { Download, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

export type ExistingDoc = {
    id: string;
    title: string;
    original_filename: string | null;
    file_size: number;
    doc_type: string;
    envelope_type: string;
    uploaded_at: string | null;
    download_url: string;
};

export type FileUploadProps = {
    bidId: string;
    envelope: 'single' | 'technical' | 'financial';
    existingFiles: ExistingDoc[];
    allowedDocTypes: Array<{ value: string; label: string }>;
    /** Pre-fill the doc-type select. Vendor can still change it. */
    defaultDocType?: string;
    /** Bytes. Default 5 MB to match BidDocumentRequest server-side cap. */
    maxFileSize?: number;
    /** Default 'application/pdf'. Server-side mimes:pdf is the source of truth. */
    accept?: string;
    /** False locks the UI to read-only (file list + download only, no upload/delete). */
    canEdit: boolean;
    /** Shown when existingFiles is empty. */
    emptyMessage?: string;
};

const FIVE_MB = 5 * 1024 * 1024;

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function FileUpload({
    bidId,
    envelope,
    existingFiles,
    allowedDocTypes,
    defaultDocType = '',
    maxFileSize = FIVE_MB,
    accept = 'application/pdf',
    canEdit,
    emptyMessage,
}: FileUploadProps) {
    const { t } = useTranslation();
    const [deleteId, setDeleteId] = useState<string | null>(null);

    const form = useForm({
        file: null as File | null,
        title: '',
        envelope_type: envelope,
        doc_type: defaultDocType,
    });

    function handleFilePick(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        if (!file) {
            form.setData('file', null);
            return;
        }
        // UX guards. BidDocumentRequest still validates server-side.
        if (file.size > maxFileSize) {
            toast.error(t('bid.documents.file_too_large'));
            e.target.value = '';
            return;
        }
        if (file.type && file.type !== accept) {
            toast.error(t('bid.documents.pdf_only'));
            e.target.value = '';
            return;
        }
        form.setData('file', file);
        // Default the title to the filename (without extension) if vendor hasn't set one.
        if (!form.data.title) {
            const base = file.name.replace(/\.[^/.]+$/, '');
            form.setData('title', base);
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post(`/vendor/bids/${bidId}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('file', 'title'),
        });
    }

    function handleDelete() {
        if (!deleteId) return;
        router.delete(`/vendor/bids/${bidId}/documents/${deleteId}`, {
            preserveScroll: true,
            onFinish: () => setDeleteId(null),
        });
    }

    return (
        <div className="space-y-4">
            {/* Existing files list */}
            {existingFiles.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    {emptyMessage ?? '—'}
                </p>
            ) : (
                <ul className="divide-y rounded-md border">
                    {existingFiles.map((doc) => (
                        <li key={doc.id} className="flex items-center justify-between gap-3 px-3 py-2">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">{doc.title}</p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {doc.original_filename ?? doc.title}
                                    {' · '}
                                    {formatFileSize(doc.file_size)}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <Button asChild variant="ghost" size="sm">
                                    <a href={doc.download_url} download>
                                        <Download className="h-4 w-4" />
                                    </a>
                                </Button>
                                {canEdit && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setDeleteId(doc.id)}
                                        type="button"
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {/* Upload form (only when editable) */}
            {canEdit && (
                <form onSubmit={handleSubmit} className="space-y-3 rounded-md border border-dashed p-3">
                    <p className="text-xs text-muted-foreground">
                        {t('bid.documents.pdf_only')}
                    </p>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="space-y-1.5 sm:col-span-1">
                            <Label htmlFor={`upload-title-${envelope}`}>
                                {t('bid.documents.title_label')}
                            </Label>
                            <Input
                                id={`upload-title-${envelope}`}
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                placeholder={t('bid.documents.title_label')}
                            />
                            {form.errors.title && (
                                <p className="text-xs text-destructive">{form.errors.title}</p>
                            )}
                        </div>
                        <div className="space-y-1.5 sm:col-span-1">
                            <Label htmlFor={`upload-doctype-${envelope}`}>
                                {t('bid.documents.type_label')}
                            </Label>
                            <Select
                                value={form.data.doc_type}
                                onValueChange={(v) => form.setData('doc_type', v)}
                            >
                                <SelectTrigger id={`upload-doctype-${envelope}`}>
                                    <SelectValue placeholder={t('bid.documents.type_label')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {allowedDocTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.doc_type && (
                                <p className="text-xs text-destructive">{form.errors.doc_type}</p>
                            )}
                        </div>
                        <div className="space-y-1.5 sm:col-span-1">
                            <Label htmlFor={`upload-file-${envelope}`}>
                                {t('bid.documents.choose_file')}
                            </Label>
                            <Input
                                id={`upload-file-${envelope}`}
                                type="file"
                                accept={accept}
                                onChange={handleFilePick}
                            />
                            {form.errors.file && (
                                <p className="text-xs text-destructive">{form.errors.file}</p>
                            )}
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing || !form.data.file || !form.data.title || !form.data.doc_type}
                        >
                            <Upload className="me-1 h-4 w-4" />
                            {form.processing ? t('btn.uploading') : t('bid.documents.upload_button')}
                        </Button>
                    </div>
                </form>
            )}

            <ConfirmDialog
                open={deleteId !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteId(null);
                }}
                title={t('bid.documents.delete_confirm')}
                description={t('bid.documents.delete_confirm')}
                onConfirm={handleDelete}
            />
        </div>
    );
}
