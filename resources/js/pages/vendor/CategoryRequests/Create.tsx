import { useMemo, useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ChevronRight, FileText, Send, Upload, X } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string | null;
};

type Props = {
    availableCategories: Category[];
    currentlyApprovedIds: string[];
};

const MAX_FILE_BYTES = 10 * 1024 * 1024; // 10 MB
const MAX_FILES = 10;
const ACCEPTED = '.pdf,.jpg,.jpeg,.png,.docx,.xlsx';

export default function Create({ availableCategories, currentlyApprovedIds }: Props) {
    const { t } = useTranslation();
    const approvedSet = useMemo(() => new Set(currentlyApprovedIds), [currentlyApprovedIds]);

    const form = useForm({
        add_categories: [] as string[],
        remove_categories: [] as string[],
        justification: '',
        evidence: [] as File[],
    });

    const { parents, childrenOf } = useMemo(() => {
        const parents = availableCategories.filter((c) => c.parent_id === null);
        const childrenOf = (parentId: string) =>
            availableCategories.filter((c) => c.parent_id === parentId);
        return { parents, childrenOf };
    }, [availableCategories]);

    const [expanded, setExpanded] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(parents.map((p) => [p.id, true])),
    );
    const toggleExpand = (id: string) =>
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));

    const toggleAdd = (id: string) => {
        const current = form.data.add_categories;
        form.setData(
            'add_categories',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const toggleRemove = (id: string) => {
        const current = form.data.remove_categories;
        form.setData(
            'remove_categories',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const approvedList = useMemo(
        () => availableCategories.filter((c) => approvedSet.has(c.id)),
        [availableCategories, approvedSet],
    );

    const canSubmit =
        (form.data.add_categories.length > 0 || form.data.remove_categories.length > 0) &&
        form.data.justification.trim().length >= 20 &&
        form.data.evidence.length > 0 &&
        !form.processing;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (form.data.add_categories.length === 0 && form.data.remove_categories.length === 0) {
            toast.error(t('vendor.category_requests.select_at_least_one'));
            return;
        }
        if (form.data.justification.trim().length < 20) {
            toast.error(t('vendor.category_requests.justification_too_short'));
            return;
        }
        if (form.data.evidence.length === 0) {
            toast.error(t('vendor.category_requests.evidence_required'));
            return;
        }

        form.post('/vendor/category-requests', {
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title={t('vendor.category_requests.page_title')} />

            <div className="space-y-6">
                <Link
                    href="/vendor/category-requests"
                    className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="me-1 h-4 w-4" />
                    {t('btn.back_to_requests')}
                </Link>

                <Heading
                    title={t('vendor.category_requests.page_title')}
                    description={t('vendor.category_requests.page_subtitle')}
                />

                <form onSubmit={submit} className="space-y-6">
                    {/* Categories to Add */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('vendor.category_requests.add_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {parents.map((parent) => {
                                const children = childrenOf(parent.id);
                                const hasChildren = children.length > 0;
                                const isExpanded = expanded[parent.id] ?? true;
                                const parentApproved = approvedSet.has(parent.id);
                                const parentChecked = form.data.add_categories.includes(parent.id);

                                return (
                                    <div key={parent.id} className="rounded-lg border p-3">
                                        <div className="flex items-center gap-3">
                                            {hasChildren ? (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleExpand(parent.id)}
                                                    className="rounded p-1 hover:bg-muted"
                                                    aria-label={t('btn.toggle')}
                                                >
                                                    {isExpanded ? (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronRight className="h-4 w-4" />
                                                    )}
                                                </button>
                                            ) : (
                                                <div className="w-6" />
                                            )}
                                            <Checkbox
                                                id={`add-${parent.id}`}
                                                checked={parentChecked}
                                                disabled={parentApproved}
                                                onCheckedChange={() => toggleAdd(parent.id)}
                                            />
                                            <Label
                                                htmlFor={`add-${parent.id}`}
                                                className={
                                                    parentApproved
                                                        ? 'cursor-not-allowed font-medium text-muted-foreground'
                                                        : 'cursor-pointer font-medium'
                                                }
                                            >
                                                {parent.name_en}
                                                {parent.name_ar && (
                                                    <span className="ms-2 text-muted-foreground">
                                                        ({parent.name_ar})
                                                    </span>
                                                )}
                                                {parentApproved && (
                                                    <span className="ms-2 text-xs text-muted-foreground">
                                                        ({t('vendor.category_requests.already_approved')})
                                                    </span>
                                                )}
                                            </Label>
                                        </div>

                                        {hasChildren && isExpanded && (
                                            <div className="ms-10 mt-3 space-y-2 border-s-2 border-muted ps-4">
                                                {children.map((child) => {
                                                    const childApproved = approvedSet.has(child.id);
                                                    const childChecked = form.data.add_categories.includes(child.id);
                                                    return (
                                                        <div key={child.id} className="flex items-center gap-3">
                                                            <Checkbox
                                                                id={`add-${child.id}`}
                                                                checked={childChecked}
                                                                disabled={childApproved}
                                                                onCheckedChange={() => toggleAdd(child.id)}
                                                            />
                                                            <Label
                                                                htmlFor={`add-${child.id}`}
                                                                className={
                                                                    childApproved
                                                                        ? 'cursor-not-allowed text-muted-foreground'
                                                                        : 'cursor-pointer'
                                                                }
                                                            >
                                                                {child.name_en}
                                                                {child.name_ar && (
                                                                    <span className="ms-2 text-muted-foreground">
                                                                        ({child.name_ar})
                                                                    </span>
                                                                )}
                                                                {childApproved && (
                                                                    <span className="ms-2 text-xs text-muted-foreground">
                                                                        ({t('vendor.category_requests.already_approved')})
                                                                    </span>
                                                                )}
                                                            </Label>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                            <p className="text-sm text-muted-foreground">
                                {t('vendor.category_requests.selected_count', {
                                    count: form.data.add_categories.length,
                                })}
                            </p>
                            {form.errors.add_categories && (
                                <p className="text-sm text-destructive">
                                    {form.errors.add_categories}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Categories to Remove */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('vendor.category_requests.remove_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                {t('vendor.category_requests.remove_hint')}
                            </p>

                            {approvedList.length === 0 ? (
                                <p className="text-sm italic text-muted-foreground">
                                    {t('vendor.category_requests.remove_empty')}
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {approvedList.map((cat) => (
                                        <div key={cat.id} className="flex items-center gap-3">
                                            <Checkbox
                                                id={`remove-${cat.id}`}
                                                checked={form.data.remove_categories.includes(cat.id)}
                                                onCheckedChange={() => toggleRemove(cat.id)}
                                            />
                                            <Label
                                                htmlFor={`remove-${cat.id}`}
                                                className="cursor-pointer"
                                            >
                                                {cat.name_en}
                                                {cat.name_ar && (
                                                    <span className="ms-2 text-muted-foreground">
                                                        ({cat.name_ar})
                                                    </span>
                                                )}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <p className="text-sm text-muted-foreground">
                                {t('vendor.category_requests.selected_count', {
                                    count: form.data.remove_categories.length,
                                })}
                            </p>
                            {form.errors.remove_categories && (
                                <p className="text-sm text-destructive">
                                    {form.errors.remove_categories}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Justification */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('vendor.category_requests.justification_label')}
                                <span className="ms-1 text-destructive">*</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <textarea
                                id="justification"
                                className="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                placeholder={t('vendor.category_requests.justification_placeholder')}
                                value={form.data.justification}
                                onChange={(e) => form.setData('justification', e.target.value)}
                                minLength={20}
                                maxLength={2000}
                                required
                            />
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>{form.data.justification.trim().length} / 2000</span>
                                {form.data.justification.trim().length < 20 && (
                                    <span className="text-destructive">
                                        {t('vendor.category_requests.justification_too_short')}
                                    </span>
                                )}
                            </div>
                            {form.errors.justification && (
                                <p className="text-sm text-destructive">
                                    {form.errors.justification}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Supporting Evidence */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('vendor.category_requests.evidence_label')}
                                <span className="ms-1 text-destructive">*</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <EvidenceDropzone
                                files={form.data.evidence}
                                onAdd={(newFiles) =>
                                    form.setData('evidence', [...form.data.evidence, ...newFiles])
                                }
                                onRemove={(index) =>
                                    form.setData(
                                        'evidence',
                                        form.data.evidence.filter((_, i) => i !== index),
                                    )
                                }
                                error={
                                    form.errors.evidence ??
                                    (form.errors['evidence.0'] as string | undefined)
                                }
                            />
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/vendor/category-requests">{t('btn.cancel')}</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={!canSubmit}
                            title={
                                !canSubmit
                                    ? t('vendor.category_requests.submit_disabled_hint')
                                    : undefined
                            }
                        >
                            <Send className="me-2 h-4 w-4" />
                            {form.processing
                                ? t('btn.submitting')
                                : t('btn.submit_request')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function EvidenceDropzone({
    files,
    onAdd,
    onRemove,
    error,
}: {
    files: File[];
    onAdd: (files: File[]) => void;
    onRemove: (index: number) => void;
    error?: string;
}) {
    const { t } = useTranslation();
    const [dragOver, setDragOver] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleFiles = (fileList: FileList | null) => {
        if (!fileList || fileList.length === 0) return;
        const newFiles = Array.from(fileList);

        const oversized = newFiles.filter((f) => f.size > MAX_FILE_BYTES);
        if (oversized.length > 0) {
            toast.error(
                t('vendor.category_requests.file_too_large', {
                    name: oversized.map((f) => f.name).join(', '),
                }),
            );
            return;
        }

        if (files.length + newFiles.length > MAX_FILES) {
            toast.error(t('vendor.category_requests.too_many_files'));
            return;
        }

        onAdd(newFiles);
    };

    return (
        <div>
            <div
                onClick={() => inputRef.current?.click()}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragOver(true);
                }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                    handleFiles(e.dataTransfer.files);
                }}
                className={`cursor-pointer rounded-lg border-2 border-dashed p-6 text-center transition-colors ${
                    dragOver
                        ? 'border-primary bg-primary/5'
                        : 'border-muted-foreground/30 hover:border-muted-foreground/60'
                }`}
            >
                <Upload className="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p className="text-sm font-medium">
                    {t('vendor.category_requests.dropzone_primary')}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    {t('vendor.category_requests.dropzone_hint')}
                </p>
            </div>

            <input
                ref={inputRef}
                type="file"
                multiple
                accept={ACCEPTED}
                className="hidden"
                onChange={(e) => {
                    handleFiles(e.target.files);
                    // Reset value so picking the same file twice still fires change.
                    if (inputRef.current) inputRef.current.value = '';
                }}
            />

            {files.length > 0 && (
                <ul className="mt-3 space-y-2">
                    {files.map((f, i) => (
                        <li
                            key={`${f.name}-${i}`}
                            className="flex items-center justify-between rounded border bg-muted/30 px-3 py-2"
                        >
                            <div className="flex items-center gap-2 overflow-hidden">
                                <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span className="truncate text-sm">{f.name}</span>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {(f.size / (1024 * 1024)).toFixed(1)} MB
                                </span>
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                onClick={() => onRemove(i)}
                                aria-label={t('btn.remove')}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
        </div>
    );
}
