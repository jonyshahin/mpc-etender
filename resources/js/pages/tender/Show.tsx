import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Calendar,
    Clock,
    FileText,
    Plus,
    Trash2,
    Send,
    Eye,
    AlertCircle,
    CheckCircle2,
} from 'lucide-react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { StatusBadge } from '@/components/StatusBadge';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type BoqItem = {
    id: string;
    item_code: string;
    description_en: string;
    unit: string;
    quantity: string;
    sort_order: number;
};

type BoqSection = {
    id: string;
    title: string;
    title_ar: string | null;
    sort_order: number;
    items: BoqItem[];
};

type TenderDocument = {
    id: string;
    title: string;
    doc_type: string;
    file_size: number;
    version: number;
    created_at: string;
};

type Addendum = {
    id: string;
    addendum_number: number;
    subject: string;
    content_en: string;
    published_at: string;
};

type Clarification = {
    id: string;
    question: string;
    answer: string | null;
    is_published: boolean;
    asked_at: string;
    answered_at: string | null;
    asked_by?: { id: string; company_name: string };
};

type EvalCriterion = {
    id: string;
    name_en: string;
    envelope: string;
    weight_percentage: string;
    max_score: string;
};

type Tender = {
    id: string;
    reference_number: string;
    title_en: string;
    title_ar: string | null;
    description_en: string | null;
    description_ar: string | null;
    tender_type: string;
    status: string;
    estimated_value: string | null;
    currency: string;
    submission_deadline: string;
    opening_date: string;
    publish_date: string | null;
    is_two_envelope: boolean;
    technical_pass_score: string | null;
    cancelled_reason: string | null;
    bids_count: number;
    created_at: string;
    project?: { id: string; name: string; code: string };
    creator?: { id: string; name: string };
    categories?: Array<{ id: string; name_en: string }>;
    boq_sections?: BoqSection[];
    documents?: TenderDocument[];
    addenda?: Addendum[];
    clarifications?: Clarification[];
    evaluation_criteria?: EvalCriterion[];
};

type Props = {
    tender: Tender;
    canEdit: boolean;
    // BUG-23: separate gate for the addendum form. canEdit covers
    // tender-content edits (Draft only). canIssueAddendum covers
    // amendments to a Published tender — distinct concern, distinct
    // permission (`tenders.issue_addenda`).
    canIssueAddendum: boolean;
    canPublish: boolean;
    canCancel: boolean;
};

const TABS = ['Overview', 'BOQ', 'Documents', 'Addenda', 'Clarifications', 'Evaluation'] as const;

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleString();
}

function formatFileSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Show({ tender, canEdit, canPublish, canCancel, canIssueAddendum }: Props) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('Overview');
    const [showPublishConfirm, setShowPublishConfirm] = useState(false);
    const [showCancelDialog, setShowCancelDialog] = useState(false);
    const [cancelReason, setCancelReason] = useState('');

    // BOQ forms
    const sectionForm = useForm({ title: '', title_ar: '' });
    const [addingItemToSection, setAddingItemToSection] = useState<string | null>(null);
    const itemForm = useForm({ item_code: '', description_en: '', unit: '', quantity: '' });

    // Document upload form
    const docForm = useForm<{ title: string; doc_type: string; file: File | null }>({
        title: '',
        doc_type: 'specification',
        file: null,
    });

    // Addendum form. BUG-26: when extends_deadline is true the form
    // collects BOTH the new submission deadline AND a new opening date
    // — without the second field, the cascade can't fire and we recreate
    // the un-openable-tender state the fix is meant to prevent.
    const addendumForm = useForm({
        subject: '',
        content_en: '',
        extends_deadline: false,
        new_deadline: '',
        new_opening_date: '',
    });

    // BUG-26: deadline-to-opening buffer surfaced from server-side
    // SystemSetting via HandleInertiaRequests shared props. Default
    // 24h matches the seeder. Used to pre-fill the new_opening_date
    // field reactively when the user picks a new_deadline.
    const tenderConfig = (usePage().props.tenderConfig ?? {}) as {
        min_hours_between_deadline_and_opening?: number;
    };
    const bufferHours = tenderConfig.min_hours_between_deadline_and_opening ?? 24;

    // Clarification answer form
    const [answeringId, setAnsweringId] = useState<string | null>(null);
    const answerForm = useForm({ answer: '' });

    // Criteria form
    const criteriaForm = useForm({
        name_en: '',
        envelope: 'technical',
        weight_percentage: '',
        max_score: '',
    });

    function handlePublish() {
        router.post(`/tenders/${tender.id}/publish`, {}, { preserveScroll: true });
        setShowPublishConfirm(false);
    }

    function handleCancel() {
        router.post(
            `/tenders/${tender.id}/cancel`,
            { reason: cancelReason },
            { preserveScroll: true },
        );
        setShowCancelDialog(false);
        setCancelReason('');
    }

    function handleAddSection(e: React.FormEvent) {
        e.preventDefault();
        sectionForm.post(`/tenders/${tender.id}/boq-sections`, {
            preserveScroll: true,
            onSuccess: () => sectionForm.reset(),
        });
    }

    function handleAddItem(sectionId: string, e: React.FormEvent) {
        e.preventDefault();
        itemForm.post(`/tenders/${tender.id}/boq-sections/${sectionId}/items`, {
            preserveScroll: true,
            onSuccess: () => {
                itemForm.reset();
                setAddingItemToSection(null);
            },
        });
    }

    function handleUploadDoc(e: React.FormEvent) {
        e.preventDefault();
        docForm.post(`/tenders/${tender.id}/documents`, {
            preserveScroll: true,
            onSuccess: () => docForm.reset(),
        });
    }

    function handleAddAddendum(e: React.FormEvent) {
        e.preventDefault();
        addendumForm.post(`/tenders/${tender.id}/addenda`, {
            preserveScroll: true,
            onSuccess: () => addendumForm.reset(),
        });
    }

    function handleSubmitAnswer(clarificationId: string, e: React.FormEvent) {
        e.preventDefault();
        answerForm.put(`/tenders/${tender.id}/clarifications/${clarificationId}`, {
            preserveScroll: true,
            onSuccess: () => {
                answerForm.reset();
                setAnsweringId(null);
            },
        });
    }

    function handlePublishClarification(clarificationId: string) {
        router.post(
            `/tenders/${tender.id}/clarifications/${clarificationId}/publish`,
            {},
            { preserveScroll: true },
        );
    }

    function handleAddCriteria(e: React.FormEvent) {
        e.preventDefault();
        criteriaForm.post(`/tenders/${tender.id}/evaluation-criteria`, {
            preserveScroll: true,
            onSuccess: () => criteriaForm.reset(),
        });
    }

    function handleDeleteCriteria(criterionId: string) {
        router.delete(`/tenders/${tender.id}/evaluation-criteria/${criterionId}`, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Tender: ${tender.reference_number}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Heading title={tender.title_en} />
                        <p className="text-muted-foreground mt-1">
                            {tender.reference_number}
                            {tender.project && (
                                <span>
                                    {' '}
                                    | {tender.project.code} — {tender.project.name}
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={tender.status} />
                        {canEdit && (
                            <Link href={`/tenders/${tender.id}/edit`}>
                                <Button variant="outline">{t('btn.edit')}</Button>
                            </Link>
                        )}
                        {canPublish && (
                            <Button onClick={() => setShowPublishConfirm(true)}>{t('btn.publish')}</Button>
                        )}
                        {canCancel && (
                            <Button
                                variant="destructive"
                                onClick={() => setShowCancelDialog(true)}
                            >
                                {t('btn.cancel_tender')}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Tab Navigation */}
                <div className="flex gap-1 border-b">
                    {TABS.map((tab) => (
                        <button
                            key={tab}
                            onClick={() => setActiveTab(tab)}
                            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                                activeTab === tab
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t(`tender.tab_${tab.toLowerCase()}`)}
                        </button>
                    ))}
                </div>

                {/* Overview Tab */}
                {activeTab === 'Overview' && (
                    <div className="space-y-6">
                        {/* Status Timeline */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('tender.timeline')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-4 text-sm">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-green-500" />
                                        <span>{t('tender.created')}: {formatDate(tender.created_at)}</span>
                                    </div>
                                    {tender.publish_date && (
                                        <div className="flex items-center gap-2">
                                            <Send className="h-4 w-4 text-blue-500" />
                                            <span>
                                                {t('tender.published_on')}: {formatDate(tender.publish_date)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex items-center gap-2">
                                        <Clock className="h-4 w-4 text-orange-500" />
                                        <span>
                                            {t('tender.deadline')}: {formatDate(tender.submission_deadline)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-purple-500" />
                                        <span>{t('tender.opening')}: {formatDate(tender.opening_date)}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Key Info */}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="text-sm text-muted-foreground">{t('tender.tender_type')}</div>
                                    <div className="mt-1 text-lg font-semibold capitalize">
                                        {tender.tender_type.replace('_', ' ')}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="text-sm text-muted-foreground">
                                        {t('tender.estimated_value')}
                                    </div>
                                    <div className="mt-1 text-lg font-semibold">
                                        {tender.estimated_value
                                            ? `${Number(tender.estimated_value).toLocaleString()} ${tender.currency}`
                                            : '—'}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="text-sm text-muted-foreground">{t('tender.bids_received')}</div>
                                    <div className="mt-1 text-lg font-semibold">
                                        {tender.bids_count}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Description */}
                        {tender.description_en && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('tender.description')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {tender.description_en}
                                    </p>
                                    {tender.description_ar && (
                                        <p className="text-sm whitespace-pre-wrap mt-4" dir="rtl">
                                            {tender.description_ar}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('tender.details')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3 sm:grid-cols-2 text-sm">
                                    <div>
                                        <dt className="text-muted-foreground">{t('tender.two_envelope')}</dt>
                                        <dd className="font-medium">
                                            {tender.is_two_envelope ? t('common.yes') : t('common.no')}
                                        </dd>
                                    </div>
                                    {tender.is_two_envelope && tender.technical_pass_score && (
                                        <div>
                                            <dt className="text-muted-foreground">
                                                {t('tender.technical_pass_score')}
                                            </dt>
                                            <dd className="font-medium">
                                                {tender.technical_pass_score}%
                                            </dd>
                                        </div>
                                    )}
                                    {tender.creator && (
                                        <div>
                                            <dt className="text-muted-foreground">{t('tender.created_by')}</dt>
                                            <dd className="font-medium">{tender.creator.name}</dd>
                                        </div>
                                    )}
                                </dl>
                            </CardContent>
                        </Card>

                        {/* Categories */}
                        {tender.categories && tender.categories.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('tender.categories')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {tender.categories.map((cat) => (
                                            <Badge key={cat.id} variant="secondary">
                                                {cat.name_en}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Cancelled reason */}
                        {tender.cancelled_reason && (
                            <Card className="border-destructive">
                                <CardContent className="pt-6">
                                    <div className="flex items-start gap-2">
                                        <AlertCircle className="h-5 w-5 text-destructive mt-0.5" />
                                        <div>
                                            <p className="font-medium text-destructive">
                                                {t('tender.cancellation_reason')}
                                            </p>
                                            <p className="text-sm mt-1">
                                                {tender.cancelled_reason}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* BOQ Tab */}
                {activeTab === 'BOQ' && (
                    <div className="space-y-4">
                        {tender.boq_sections?.map((section) => (
                            <Card key={section.id}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {section.title}
                                        {section.title_ar && (
                                            <span className="text-muted-foreground ml-2 font-normal">
                                                ({section.title_ar})
                                            </span>
                                        )}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {section.items.length > 0 ? (
                                        <div className="rounded-md border">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b bg-muted/50">
                                                        <th className="px-3 py-2 text-left font-medium">
                                                            {t('table.code')}
                                                        </th>
                                                        <th className="px-3 py-2 text-left font-medium">
                                                            {t('table.description')}
                                                        </th>
                                                        <th className="px-3 py-2 text-left font-medium">
                                                            {t('table.unit')}
                                                        </th>
                                                        <th className="px-3 py-2 text-right font-medium">
                                                            {t('table.quantity')}
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {section.items.map((item) => (
                                                        <tr
                                                            key={item.id}
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="px-3 py-2 font-mono">
                                                                {item.item_code}
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {item.description_en}
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {item.unit}
                                                            </td>
                                                            <td className="px-3 py-2 text-right">
                                                                {Number(
                                                                    item.quantity,
                                                                ).toLocaleString()}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {t('empty.no_items_in_section')}
                                        </p>
                                    )}

                                    {canEdit && (
                                        <div className="mt-3">
                                            {addingItemToSection === section.id ? (
                                                <form
                                                    onSubmit={(e) =>
                                                        handleAddItem(section.id, e)
                                                    }
                                                    className="flex gap-2 items-end"
                                                >
                                                    <div className="space-y-1">
                                                        <Label className="text-xs">{t('table.code')}</Label>
                                                        <Input
                                                            value={itemForm.data.item_code}
                                                            onChange={(e) =>
                                                                itemForm.setData(
                                                                    'item_code',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="h-8 w-20"
                                                        />
                                                    </div>
                                                    <div className="flex-1 space-y-1">
                                                        <Label className="text-xs">
                                                            {t('table.description')}
                                                        </Label>
                                                        <Input
                                                            value={itemForm.data.description_en}
                                                            onChange={(e) =>
                                                                itemForm.setData(
                                                                    'description_en',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="h-8"
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-xs">{t('table.unit')}</Label>
                                                        <Input
                                                            value={itemForm.data.unit}
                                                            onChange={(e) =>
                                                                itemForm.setData(
                                                                    'unit',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="h-8 w-20"
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label className="text-xs">{t('table.qty')}</Label>
                                                        <Input
                                                            type="number"
                                                            value={itemForm.data.quantity}
                                                            onChange={(e) =>
                                                                itemForm.setData(
                                                                    'quantity',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="h-8 w-24"
                                                        />
                                                    </div>
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={itemForm.processing}
                                                    >
                                                        {t('btn.add')}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setAddingItemToSection(null)
                                                        }
                                                    >
                                                        {t('btn.cancel')}
                                                    </Button>
                                                </form>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setAddingItemToSection(section.id)
                                                    }
                                                >
                                                    <Plus className="mr-1 h-3 w-3" />
                                                    {t('btn.add_item')}
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}

                        {(!tender.boq_sections || tender.boq_sections.length === 0) && (
                            <p className="text-center text-muted-foreground py-8">
                                {t('empty.no_boq_sections')}
                            </p>
                        )}

                        {canEdit && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('tender.add_section')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleAddSection} className="flex gap-4 items-end">
                                        <div className="flex-1 space-y-2">
                                            <Label>{t('form.title_en')}</Label>
                                            <Input
                                                value={sectionForm.data.title}
                                                onChange={(e) =>
                                                    sectionForm.setData('title', e.target.value)
                                                }
                                            />
                                        </div>
                                        <div className="flex-1 space-y-2">
                                            <Label>{t('form.title_ar')}</Label>
                                            <Input
                                                value={sectionForm.data.title_ar}
                                                onChange={(e) =>
                                                    sectionForm.setData('title_ar', e.target.value)
                                                }
                                                dir="rtl"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={sectionForm.processing}
                                        >
                                            {t('tender.add_section')}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Documents Tab */}
                {activeTab === 'Documents' && (
                    <div className="space-y-4">
                        {tender.documents && tender.documents.length > 0 ? (
                            <div className="rounded-md border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="px-4 py-3 text-left font-medium">
                                                {t('table.title')}
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                {t('table.type')}
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                {t('table.version')}
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                {t('table.size')}
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                {t('table.uploaded')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tender.documents.map((doc) => (
                                            <tr key={doc.id} className="border-b last:border-0">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <FileText className="h-4 w-4 text-muted-foreground" />
                                                        {doc.title}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 capitalize">
                                                    {doc.doc_type.replace('_', ' ')}
                                                </td>
                                                <td className="px-4 py-3">v{doc.version}</td>
                                                <td className="px-4 py-3">
                                                    {formatFileSize(doc.file_size)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatDate(doc.created_at)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">
                                {t('empty.no_documents')}
                            </p>
                        )}

                        {canEdit && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('tender.upload_document')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={handleUploadDoc}
                                        className="flex gap-4 items-end"
                                    >
                                        <div className="flex-1 space-y-2">
                                            <Label>{t('form.title')}</Label>
                                            <Input
                                                value={docForm.data.title}
                                                onChange={(e) =>
                                                    docForm.setData('title', e.target.value)
                                                }
                                            />
                                        </div>
                                        <div className="w-48 space-y-2">
                                            <Label>{t('form.type')}</Label>
                                            <Select
                                                value={docForm.data.doc_type}
                                                onValueChange={(value) =>
                                                    docForm.setData('doc_type', value)
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="specification">{t('tender.doc_specification')}</SelectItem>
                                                    <SelectItem value="drawing">{t('tender.doc_drawing')}</SelectItem>
                                                    <SelectItem value="contract_terms">{t('tender.doc_contract_terms')}</SelectItem>
                                                    <SelectItem value="boq_template">{t('tender.doc_boq_template')}</SelectItem>
                                                    <SelectItem value="site_photo">{t('tender.doc_site_photo')}</SelectItem>
                                                    <SelectItem value="other">{t('tender.doc_other')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="flex-1 space-y-2">
                                            <Label>{t('form.file')}</Label>
                                            <Input
                                                type="file"
                                                accept="application/pdf"
                                                onChange={(e) =>
                                                    docForm.setData(
                                                        'file',
                                                        e.target.files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {t('bid.documents.pdf_only')}
                                            </p>
                                        </div>
                                        <Button type="submit" disabled={docForm.processing}>
                                            {t('btn.upload')}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Addenda Tab */}
                {activeTab === 'Addenda' && (
                    <div className="space-y-4">
                        {tender.addenda && tender.addenda.length > 0 ? (
                            tender.addenda.map((addendum) => (
                                <Card key={addendum.id}>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="text-base">
                                                Addendum #{addendum.addendum_number}:{' '}
                                                {addendum.subject}
                                            </CardTitle>
                                            <span className="text-sm text-muted-foreground">
                                                {formatDate(addendum.published_at)}
                                            </span>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm whitespace-pre-wrap">
                                            {addendum.content_en}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))
                        ) : (
                            <p className="text-center text-muted-foreground py-8">
                                {t('empty.no_addenda')}
                            </p>
                        )}

                        {/* BUG-23: addendum form gated by canIssueAddendum
                            (separate from canEdit which covers tender-content
                            editing and is correctly false on Published). */}
                        {canIssueAddendum && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('tender.issue_addendum')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleAddAddendum} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>{t('form.subject')}</Label>
                                            <Input
                                                value={addendumForm.data.subject}
                                                onChange={(e) =>
                                                    addendumForm.setData(
                                                        'subject',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('form.content')}</Label>
                                            <textarea
                                                className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                value={addendumForm.data.content_en}
                                                onChange={(e) =>
                                                    addendumForm.setData(
                                                        'content_en',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="extends_deadline"
                                                    checked={addendumForm.data.extends_deadline}
                                                    onCheckedChange={(checked) =>
                                                        addendumForm.setData(
                                                            'extends_deadline',
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <Label htmlFor="extends_deadline">
                                                    {t('tender.extend_deadline')}
                                                </Label>
                                            </div>
                                            {addendumForm.data.extends_deadline && (
                                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <div className="space-y-1">
                                                        <Label>{t('form.new_deadline')}</Label>
                                                        <Input
                                                            type="datetime-local"
                                                            value={addendumForm.data.new_deadline}
                                                            onChange={(e) => {
                                                                const newDeadline = e.target.value;
                                                                addendumForm.setData(
                                                                    'new_deadline',
                                                                    newDeadline,
                                                                );
                                                                // BUG-26: reactively pre-fill the
                                                                // opening date as deadline + buffer
                                                                // hours so the user sees a sensible
                                                                // default they can override. Buffer
                                                                // sourced from Inertia shared props
                                                                // (HandleInertiaRequests middleware).
                                                                if (newDeadline) {
                                                                    const opening = new Date(newDeadline);
                                                                    opening.setHours(
                                                                        opening.getHours() + bufferHours,
                                                                    );
                                                                    const pad = (n: number) =>
                                                                        String(n).padStart(2, '0');
                                                                    const fmt = `${opening.getFullYear()}-${pad(opening.getMonth() + 1)}-${pad(opening.getDate())}T${pad(opening.getHours())}:${pad(opening.getMinutes())}`;
                                                                    addendumForm.setData(
                                                                        'new_opening_date',
                                                                        fmt,
                                                                    );
                                                                }
                                                            }}
                                                        />
                                                        {addendumForm.errors.new_deadline && (
                                                            <p className="text-sm text-destructive">
                                                                {addendumForm.errors.new_deadline}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label>
                                                            {t('form.new_opening_date')}
                                                        </Label>
                                                        <Input
                                                            type="datetime-local"
                                                            value={addendumForm.data.new_opening_date}
                                                            onChange={(e) =>
                                                                addendumForm.setData(
                                                                    'new_opening_date',
                                                                    e.target.value,
                                                                )
                                                            }
                                                        />
                                                        {addendumForm.errors.new_opening_date && (
                                                            <p className="text-sm text-destructive">
                                                                {addendumForm.errors.new_opening_date}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={addendumForm.processing}
                                        >
                                            {t('tender.issue_addendum')}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Clarifications Tab */}
                {activeTab === 'Clarifications' && (
                    <div className="space-y-4">
                        {tender.clarifications && tender.clarifications.length > 0 ? (
                            tender.clarifications.map((c) => (
                                <Card key={c.id}>
                                    <CardContent className="pt-6">
                                        <div className="space-y-3">
                                            <div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium">
                                                        {t('tender.question')}
                                                    </span>
                                                    <div className="flex items-center gap-2">
                                                        {c.asked_by && (
                                                            <span className="text-xs text-muted-foreground">
                                                                by {c.asked_by.company_name}
                                                            </span>
                                                        )}
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatDate(c.asked_at)}
                                                        </span>
                                                        {c.is_published ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="text-xs"
                                                            >
                                                                {t('status.published')}
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-xs"
                                                            >
                                                                {t('status.unpublished')}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                                <p className="text-sm mt-1">{c.question}</p>
                                            </div>

                                            {c.answer ? (
                                                <div className="border-l-2 border-primary pl-4">
                                                    <span className="text-sm font-medium">
                                                        {t('tender.answer')}
                                                    </span>
                                                    {c.answered_at && (
                                                        <span className="text-xs text-muted-foreground ml-2">
                                                            {formatDate(c.answered_at)}
                                                        </span>
                                                    )}
                                                    <p className="text-sm mt-1">{c.answer}</p>
                                                </div>
                                            ) : (
                                                <>
                                                    {answeringId === c.id ? (
                                                        <form
                                                            onSubmit={(e) =>
                                                                handleSubmitAnswer(c.id, e)
                                                            }
                                                            className="space-y-2"
                                                        >
                                                            <textarea
                                                                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                                value={answerForm.data.answer}
                                                                onChange={(e) =>
                                                                    answerForm.setData(
                                                                        'answer',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                placeholder={t('tender.type_answer_placeholder')}
                                                            />
                                                            <div className="flex gap-2">
                                                                <Button
                                                                    type="submit"
                                                                    size="sm"
                                                                    disabled={
                                                                        answerForm.processing
                                                                    }
                                                                >
                                                                    {t('btn.submit_answer')}
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setAnsweringId(null)
                                                                    }
                                                                >
                                                                    {t('btn.cancel')}
                                                                </Button>
                                                            </div>
                                                        </form>
                                                    ) : (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setAnsweringId(c.id)}
                                                        >
                                                            {t('tender.answer')}
                                                        </Button>
                                                    )}
                                                </>
                                            )}

                                            {c.answer && !c.is_published && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handlePublishClarification(c.id)
                                                    }
                                                >
                                                    <Eye className="mr-1 h-3 w-3" />
                                                    {t('btn.publish')}
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        ) : (
                            <p className="text-center text-muted-foreground py-8">
                                {t('empty.no_clarifications')}
                            </p>
                        )}
                    </div>
                )}

                {/* Evaluation Tab */}
                {activeTab === 'Evaluation' && (
                    <div className="space-y-4">
                        {(['technical', 'financial'] as const).map((envelope) => {
                            const envelopeCriteria =
                                tender.evaluation_criteria?.filter(
                                    (c) => c.envelope === envelope,
                                ) ?? [];
                            const totalWeight = envelopeCriteria.reduce(
                                (sum, c) => sum + parseFloat(c.weight_percentage),
                                0,
                            );

                            return (
                                <Card key={envelope}>
                                    <CardHeader>
                                        <CardTitle className="text-base capitalize">
                                            {t(`tender.${envelope}_criteria`)}
                                            <span className="text-sm font-normal text-muted-foreground ml-2">
                                                ({t('tender.total_weight')}: {totalWeight}%)
                                            </span>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {envelopeCriteria.length > 0 ? (
                                            <div className="rounded-md border">
                                                <table className="w-full text-sm">
                                                    <thead>
                                                        <tr className="border-b bg-muted/50">
                                                            <th className="px-4 py-2 text-left font-medium">
                                                                {t('table.name')}
                                                            </th>
                                                            <th className="px-4 py-2 text-right font-medium">
                                                                {t('table.weight_pct')}
                                                            </th>
                                                            <th className="px-4 py-2 text-right font-medium">
                                                                {t('table.max_score')}
                                                            </th>
                                                            {canEdit && (
                                                                <th className="px-4 py-2 w-10"></th>
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {envelopeCriteria.map((c) => (
                                                            <tr
                                                                key={c.id}
                                                                className="border-b last:border-0"
                                                            >
                                                                <td className="px-4 py-2">
                                                                    {c.name_en}
                                                                </td>
                                                                <td className="px-4 py-2 text-right">
                                                                    {c.weight_percentage}%
                                                                </td>
                                                                <td className="px-4 py-2 text-right">
                                                                    {c.max_score}
                                                                </td>
                                                                {canEdit && (
                                                                    <td className="px-4 py-2">
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={() =>
                                                                                handleDeleteCriteria(
                                                                                    c.id,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Trash2 className="h-3 w-3 text-destructive" />
                                                                        </Button>
                                                                    </td>
                                                                )}
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                {t('empty.no_criteria_defined', { envelope })}
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}

                        {canEdit && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('tender.add_criterion')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={handleAddCriteria}
                                        className="flex gap-4 items-end"
                                    >
                                        <div className="flex-1 space-y-2">
                                            <Label>{t('form.name')}</Label>
                                            <Input
                                                value={criteriaForm.data.name_en}
                                                onChange={(e) =>
                                                    criteriaForm.setData(
                                                        'name_en',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t('tender.criterion_name_placeholder')}
                                            />
                                        </div>
                                        <div className="w-36 space-y-2">
                                            <Label>{t('form.envelope')}</Label>
                                            <Select
                                                value={criteriaForm.data.envelope}
                                                onValueChange={(value) =>
                                                    criteriaForm.setData('envelope', value)
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="technical">{t('tender.technical')}</SelectItem>
                                                    <SelectItem value="financial">{t('tender.financial')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="w-28 space-y-2">
                                            <Label>{t('form.weight_pct')}</Label>
                                            <Input
                                                type="number"
                                                value={criteriaForm.data.weight_percentage}
                                                onChange={(e) =>
                                                    criteriaForm.setData(
                                                        'weight_percentage',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="w-28 space-y-2">
                                            <Label>{t('form.max_score')}</Label>
                                            <Input
                                                type="number"
                                                value={criteriaForm.data.max_score}
                                                onChange={(e) =>
                                                    criteriaForm.setData(
                                                        'max_score',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={criteriaForm.processing}
                                        >
                                            {t('btn.add')}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>

            {/* Publish Confirmation */}
            <ConfirmDialog
                open={showPublishConfirm}
                onOpenChange={setShowPublishConfirm}
                title={t('confirm.publish_tender_title')}
                description={t('confirm.publish_tender_description')}
                onConfirm={handlePublish}
                confirmLabel={t('btn.publish')}
            />

            {/* Cancel Dialog */}
            <Dialog open={showCancelDialog} onOpenChange={setShowCancelDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('confirm.cancel_tender_title')}</DialogTitle>
                        <DialogDescription>
                            {t('confirm.cancel_tender_description')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label>{t('form.reason')}</Label>
                        <textarea
                            className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            value={cancelReason}
                            onChange={(e) => setCancelReason(e.target.value)}
                            placeholder={t('tender.cancellation_reason_placeholder')}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowCancelDialog(false)}
                        >
                            {t('btn.keep_tender')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleCancel}
                            disabled={!cancelReason.trim()}
                        >
                            {t('btn.cancel_tender')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
