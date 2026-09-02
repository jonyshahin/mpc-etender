import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Download,
    FileText,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { StatTile } from '@/components/dashboard/StatTile';
import { FileSize } from '@/components/FileSize';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useNow } from '@/hooks/use-now';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/datetime';
import { maxUploadLabel } from '@/lib/uploads';
import { cn } from '@/lib/utils';

type VendorDocument = {
    id: string;
    document_type: string;
    title: string;
    file_size: number;
    mime_type: string;
    issue_date: string | null;
    expiry_date: string | null;
    status: string;
    review_notes: string | null;
    reviewed_at: string | null;
    created_at: string;
    /**
     * Signed, short-lived, and logged to document_access_logs on use. Replaces
     * the raw S3 object key the page used to receive and could not open.
     */
    download_url: string;
};

type Props = {
    documents: VendorDocument[];
    /**
     * Served from App\Enums\DocumentType so the picker cannot drift from what
     * validation accepts. It had: four of the eight options hardcoded here were
     * rejected on submit.
     */
    documentTypes: Array<{ value: string; labelKey: string }>;
    summary: {
        total: number;
        approved: number;
        pending: number;
        rejected: number;
        expiring: number;
        expired: number;
    };
};

/**
 * Expiry state, derived here so the badge and the row tint agree.
 *
 * A rejected document reports no expiry state at all. Its date still shows, but
 * flagging it as lapsing invites a renew-and-reupload that fixes nothing — the
 * document was turned down, not timed out. The dashboard's expiry alarms skip
 * rejected documents for the same reason.
 */
function expiryState(
    expiry: string | null,
    status: string,
    now: number,
): 'none' | 'soon' | 'past' {
    if (!expiry || status === 'rejected') {
        return 'none';
    }

    const at = new Date(expiry).getTime();

    if (Number.isNaN(at)) {
        return 'none';
    }

    if (at < now) {
        return 'past';
    }

    return at <= now + 30 * 24 * 60 * 60 * 1000 ? 'soon' : 'none';
}

export default function Index({ documents, documentTypes, summary }: Props) {
    const { t, locale } = useTranslation();
    const [deleteId, setDeleteId] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const fileInputRef = useRef<HTMLInputElement>(null);

    // One instant for the whole render, so two rows cannot land on opposite
    // sides of the same expiry boundary. Slow tick: these are dates, not clocks.
    const now = useNow(60_000);

    const typeLabel = useMemo(() => {
        const map = new Map(documentTypes.map((type) => [type.value, t(type.labelKey)]));

        return (value: string) => map.get(value) ?? value;
    }, [documentTypes, t]);

    // Debounced rather than filtering on every keystroke: the list is local, but
    // matching the rhythm of the admin lists keeps the whole app feeling the same.
    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search.trim().toLowerCase()), 250);

        return () => clearTimeout(timer);
    }, [search]);

    const visible = useMemo(() => {
        if (!debouncedSearch) {
            return documents;
        }

        return documents.filter(
            (doc) =>
                doc.title.toLowerCase().includes(debouncedSearch) ||
                typeLabel(doc.document_type).toLowerCase().includes(debouncedSearch),
        );
    }, [documents, debouncedSearch, typeLabel]);

    const uploadForm = useForm({
        file: null as File | null,
        document_type: '',
        title: '',
        issue_date: '',
        expiry_date: '',
    });

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post('/vendor/documents', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();

                // reset() clears the form state but not the file control's own
                // value, which would otherwise keep showing the filename of a
                // document already uploaded.
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    };

    const handleDelete = () => {
        if (!deleteId) {
return;
}

        router.delete(`/vendor/documents/${deleteId}`, {
            preserveScroll: true,
            onFinish: () => setDeleteId(null),
        });
    };

    const pendingDocument = documents.find((doc) => doc.id === deleteId);

    return (
        <>
            <Head title={t('pages.vendor.documents')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('pages.vendor.documents')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('vendor.manage_documents_description')}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('vendor.documents_on_file')}
                        value={String(summary.total)}
                        hint={t('vendor.all_uploads')}
                        icon={FileText}
                    />
                    <StatTile
                        label={t('status.approved')}
                        value={String(summary.approved)}
                        hint={t('vendor.accepted_by_mpc')}
                        icon={CheckCircle2}
                    />
                    <StatTile
                        label={t('status.pending')}
                        value={String(summary.pending)}
                        hint={t('vendor.awaiting_review')}
                        icon={Clock}
                    />
                    <StatTile
                        label={t('vendor.expiring_or_expired')}
                        value={String(summary.expiring + summary.expired)}
                        hint={t('vendor.within_thirty_days')}
                        icon={AlertTriangle}
                    />
                </div>

                {/* ── Upload ── */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Upload className="size-4" aria-hidden="true" />
                            {t('vendor.upload_document')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleUpload} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="title">{t('form.title')}</Label>
                                    <Input
                                        id="title"
                                        value={uploadForm.data.title}
                                        onChange={(e) =>
                                            uploadForm.setData('title', e.target.value)
                                        }
                                        placeholder={t('form.document_title_placeholder')}
                                        aria-invalid={uploadForm.errors.title ? true : undefined}
                                    />
                                    {uploadForm.errors.title && (
                                        <p role="alert" className="text-sm text-destructive">
                                            {uploadForm.errors.title}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="document_type">{t('form.document_type')}</Label>
                                    <Select
                                        value={uploadForm.data.document_type}
                                        onValueChange={(value) =>
                                            uploadForm.setData('document_type', value)
                                        }
                                    >
                                        <SelectTrigger id="document_type">
                                            <SelectValue placeholder={t('form.select_type')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {documentTypes.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
                                                    {t(type.labelKey)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {uploadForm.errors.document_type && (
                                        <p role="alert" className="text-sm text-destructive">
                                            {uploadForm.errors.document_type}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="file">{t('form.file')}</Label>
                                    <Input
                                        id="file"
                                        ref={fileInputRef}
                                        type="file"
                                        accept="application/pdf"
                                        onChange={(e) =>
                                            uploadForm.setData('file', e.target.files?.[0] ?? null)
                                        }
                                        aria-invalid={uploadForm.errors.file ? true : undefined}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('bid.documents.pdf_only', { size: maxUploadLabel() })}
                                    </p>
                                    {uploadForm.errors.file && (
                                        <p role="alert" className="text-sm text-destructive">
                                            {uploadForm.errors.file}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="issue_date">{t('form.issue_date')}</Label>
                                    <Input
                                        id="issue_date"
                                        type="date"
                                        value={uploadForm.data.issue_date}
                                        onChange={(e) =>
                                            uploadForm.setData('issue_date', e.target.value)
                                        }
                                    />
                                    {uploadForm.errors.issue_date && (
                                        <p role="alert" className="text-sm text-destructive">
                                            {uploadForm.errors.issue_date}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="expiry_date">{t('form.expiry_date')}</Label>
                                    <Input
                                        id="expiry_date"
                                        type="date"
                                        value={uploadForm.data.expiry_date}
                                        onChange={(e) =>
                                            uploadForm.setData('expiry_date', e.target.value)
                                        }
                                    />
                                    {uploadForm.errors.expiry_date && (
                                        <p role="alert" className="text-sm text-destructive">
                                            {uploadForm.errors.expiry_date}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={uploadForm.processing}>
                                    <Upload className="me-2 size-4" aria-hidden="true" />
                                    {uploadForm.processing
                                        ? t('btn.uploading')
                                        : t('btn.upload_document')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* ── The file ── */}
                {documents.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <FileText
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">{t('empty.no_documents')}</p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                            {t('empty.no_documents_hint')}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <div className="relative max-w-sm">
                            <Search
                                className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('form.search_documents')}
                                aria-label={t('form.search_documents')}
                                className="ps-9"
                            />
                        </div>

                        {visible.length === 0 ? (
                            <div className="rounded-xl border border-dashed py-12 text-center">
                                <p className="font-medium">{t('empty.no_matches')}</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('empty.try_clearing')}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-4"
                                    onClick={() => setSearch('')}
                                >
                                    <X className="me-2 size-4" aria-hidden="true" />
                                    {t('tender.clear_filters')}
                                </Button>
                            </div>
                        ) : (
                            <>
                                {/* Seven columns do not survive a phone; the
                                    same rows render as cards below md. */}
                                <ul className="space-y-2 md:hidden">
                                    {visible.map((doc) => (
                                        <li
                                            key={doc.id}
                                            className="rounded-xl border bg-card p-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">
                                                        {doc.title}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {typeLabel(doc.document_type)}
                                                    </p>
                                                </div>
                                                <StatusBadge status={doc.status} />
                                            </div>
                                            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                <FileSize bytes={doc.file_size} />
                                                <ExpiryLabel
                                                    expiry={doc.expiry_date}
                                                    status={doc.status}
                                                    now={now}
                                                    locale={locale}
                                                />
                                            </div>
                                            {doc.review_notes && (
                                                <p className="mt-2 rounded-md bg-muted/60 p-2 text-xs">
                                                    <span className="font-medium">
                                                        {t('vendor.reviewer_note')}:
                                                    </span>{' '}
                                                    {doc.review_notes}
                                                </p>
                                            )}
                                            <div className="mt-3 flex gap-2">
                                                <Button asChild variant="outline" size="sm">
                                                    <a href={doc.download_url}>
                                                        <Download
                                                            className="me-2 size-4"
                                                            aria-hidden="true"
                                                        />
                                                        {t('btn.download')}
                                                    </a>
                                                </Button>
                                                {doc.status === 'pending' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setDeleteId(doc.id)}
                                                    >
                                                        <Trash2
                                                            className="me-2 size-4 text-destructive"
                                                            aria-hidden="true"
                                                        />
                                                        {t('btn.delete')}
                                                    </Button>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>

                                <div className="hidden overflow-x-auto rounded-xl border md:block">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                {[
                                                    'table.title',
                                                    'table.type',
                                                    'table.status',
                                                    'table.size',
                                                    'table.issue_date',
                                                    'table.expiry_date',
                                                ].map((key) => (
                                                    <th
                                                        key={key}
                                                        className="px-4 py-3 text-start font-medium text-muted-foreground"
                                                    >
                                                        {t(key)}
                                                    </th>
                                                ))}
                                                <th className="px-4 py-3 text-end font-medium text-muted-foreground">
                                                    {t('table.actions')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {visible.map((doc) => (
                                                <tr
                                                    key={doc.id}
                                                    className="border-b transition-colors last:border-0 hover:bg-muted/50"
                                                >
                                                    <td className="px-4 py-3">
                                                        <span className="font-medium">
                                                            {doc.title}
                                                        </span>
                                                        {doc.review_notes && (
                                                            <span className="mt-1 block max-w-md text-xs text-muted-foreground">
                                                                <span className="font-medium">
                                                                    {t('vendor.reviewer_note')}:
                                                                </span>{' '}
                                                                {doc.review_notes}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant="outline">
                                                            {typeLabel(doc.document_type)}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge status={doc.status} />
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        <FileSize bytes={doc.file_size} />
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-muted-foreground">
                                                        {doc.issue_date
                                                            ? formatDate(doc.issue_date, locale)
                                                            : '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3">
                                                        <ExpiryLabel
                                                            expiry={doc.expiry_date}
                                                            status={doc.status}
                                                            now={now}
                                                            locale={locale}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <a
                                                                    href={doc.download_url}
                                                                    aria-label={`${t('btn.download')}: ${doc.title}`}
                                                                >
                                                                    <Download
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                </a>
                                                            </Button>
                                                            {doc.status === 'pending' && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    aria-label={`${t('btn.delete')}: ${doc.title}`}
                                                                    onClick={() =>
                                                                        setDeleteId(doc.id)
                                                                    }
                                                                >
                                                                    <Trash2
                                                                        className="size-4 text-destructive"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={deleteId !== null}
                onOpenChange={(open) => {
                    if (!open) {
setDeleteId(null);
}
                }}
                title={t('vendor.delete_document_title')}
                description={
                    pendingDocument
                        ? t('vendor.delete_document_confirm_named', {
                              title: pendingDocument.title,
                          })
                        : t('vendor.delete_document_confirm')
                }
                onConfirm={handleDelete}
            />
        </>
    );
}

function ExpiryLabel({
    expiry,
    status,
    now,
    locale,
}: {
    expiry: string | null;
    status: string;
    now: number;
    locale: string;
}) {
    const { t } = useTranslation();

    if (!expiry) {
        return <span className="text-muted-foreground">{t('vendor.no_expiry')}</span>;
    }

    const state = expiryState(expiry, status, now);

    return (
        <span
            className={cn(
                state === 'past' && 'font-medium text-destructive',
                state === 'soon' && 'font-medium text-amber-600 dark:text-amber-400',
                state === 'none' && 'text-muted-foreground',
            )}
        >
            {formatDate(expiry, locale)}
            {state !== 'none' && (
                <span className="ms-1 text-xs">
                    ({state === 'past' ? t('vendor.expired') : t('vendor.expiring_soon')})
                </span>
            )}
        </span>
    );
}
