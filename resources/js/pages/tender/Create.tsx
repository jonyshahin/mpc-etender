import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState, ReactNode } from 'react';
import { Check, ChevronDown, ChevronRight, Plus, Trash2, Upload } from 'lucide-react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string | null;
    children?: Category[];
};

type Props = {
    projects: Array<{ id: string; name: string; code: string }>;
    categories: Category[];
};

type BoqItem = {
    item_code: string;
    description_en: string;
    unit: string;
    quantity: string;
};

type BoqSection = {
    title: string;
    title_ar: string;
    items: BoqItem[];
};

type DocEntry = {
    file: File | null;
    title: string;
    doc_type: string;
};

type Criterion = {
    name_en: string;
    envelope: string;
    weight_percentage: string;
    max_score: string;
};

const CURRENCIES = [
    { value: 'USD', label: 'USD' },
    { value: 'IQD', label: 'IQD' },
    { value: 'EUR', label: 'EUR' },
];

function FieldError({ errors, path }: { errors: Record<string, string>; path: string }) {
    const msg = errors[path];
    if (!msg) return null;
    return <p className="text-sm text-destructive">{msg}</p>;
}

function StepIndicator({ current, steps }: { current: number; steps: string[] }) {
    return (
        <div className="flex items-center justify-between">
            {steps.map((step, index) => (
                <div key={step} className="flex items-center">
                    <div className="flex flex-col items-center">
                        <div
                            className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium ${
                                index < current
                                    ? 'bg-primary text-primary-foreground'
                                    : index === current
                                      ? 'bg-primary text-primary-foreground ring-2 ring-primary ring-offset-2'
                                      : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            {index < current ? <Check className="h-4 w-4" /> : index + 1}
                        </div>
                        <span className="mt-1 text-xs font-medium text-muted-foreground hidden sm:block">
                            {step}
                        </span>
                    </div>
                    {index < steps.length - 1 && (
                        <div
                            className={`mx-2 h-0.5 w-8 sm:w-16 ${
                                index < current ? 'bg-primary' : 'bg-muted'
                            }`}
                        />
                    )}
                </div>
            ))}
        </div>
    );
}

function CategoryTree({
    categories,
    selectedIds,
    onToggle,
}: {
    categories: Category[];
    selectedIds: string[];
    onToggle: (id: string) => void;
}) {
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    function toggleExpand(id: string) {
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));
    }

    function renderCategory(cat: Category): ReactNode {
        const hasChildren = cat.children && cat.children.length > 0;
        const isExpanded = expanded[cat.id];

        return (
            <div key={cat.id} className="space-y-1">
                <div className="flex items-center gap-2">
                    {hasChildren ? (
                        <button
                            type="button"
                            onClick={() => toggleExpand(cat.id)}
                            className="p-0.5"
                        >
                            {isExpanded ? (
                                <ChevronDown className="h-4 w-4" />
                            ) : (
                                <ChevronRight className="h-4 w-4" />
                            )}
                        </button>
                    ) : (
                        <span className="w-5" />
                    )}
                    <Checkbox
                        id={`cat-${cat.id}`}
                        checked={selectedIds.includes(cat.id)}
                        onCheckedChange={() => onToggle(cat.id)}
                    />
                    <Label htmlFor={`cat-${cat.id}`} className="cursor-pointer text-sm">
                        {cat.name_en}
                        {cat.name_ar && (
                            <span className="text-muted-foreground ml-1 rtl:mr-1">
                                ({cat.name_ar})
                            </span>
                        )}
                    </Label>
                </div>
                {hasChildren && isExpanded && (
                    <div className="ml-6 space-y-1">
                        {cat.children!.map((child) => renderCategory(child))}
                    </div>
                )}
            </div>
        );
    }

    return <div className="space-y-2">{categories.map((cat) => renderCategory(cat))}</div>;
}

export default function Create({ projects, categories }: Props) {
    const { t } = useTranslation();

    const STEPS = [
        t('tender.step_basic_info'),
        t('tender.step_boq_builder'),
        t('tender.step_documents'),
        t('tender.step_categories'),
        t('tender.step_evaluation_criteria'),
        t('tender.step_review_save'),
    ];

    const TENDER_TYPES = [
        { value: 'open', label: t('tender.type_open') },
        { value: 'restricted', label: t('tender.type_restricted') },
        { value: 'direct_invitation', label: t('tender.type_direct_invitation') },
        { value: 'framework', label: t('tender.type_framework') },
    ];

    const DOC_TYPES = [
        { value: 'specification', label: t('tender.doc_technical_specs') },
        { value: 'drawing', label: t('tender.doc_drawings') },
        { value: 'contract_terms', label: t('tender.doc_contract_template') },
        { value: 'boq_template', label: t('tender.doc_boq_template') },
        { value: 'site_photo', label: t('tender.doc_site_photo') },
        { value: 'other', label: t('tender.doc_other') },
    ];

    const [currentStep, setCurrentStep] = useState(0);
    const [boqSections, setBoqSections] = useState<BoqSection[]>([]);
    const [documents, setDocuments] = useState<DocEntry[]>([]);
    const [categoryIds, setCategoryIds] = useState<string[]>([]);
    const [criteria, setCriteria] = useState<Criterion[]>([]);
    const [expandedSections, setExpandedSections] = useState<Record<number, boolean>>({});

    const form = useForm({
        project_id: '',
        title_en: '',
        title_ar: '',
        description_en: '',
        description_ar: '',
        tender_type: 'open',
        estimated_value: '',
        currency: 'USD',
        submission_deadline: '',
        opening_date: '',
        is_two_envelope: false,
        technical_pass_score: '',
        category_ids: [] as string[],
    });

    // Wizard data spans useState (boqSections, criteria, documents, categoryIds)
    // + useForm (scalars), and submission goes through router.post — that path
    // never populates useForm.errors. Read errors directly from the page props
    // bag so the post-submit Inertia partial reload surfaces them. Long-term
    // cleanup is unifying onto useForm + form.post() (BUG-12).
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;

    function next() {
        if (currentStep < STEPS.length - 1) setCurrentStep(currentStep + 1);
    }

    function prev() {
        if (currentStep > 0) setCurrentStep(currentStep - 1);
    }

    function toggleCategory(id: string) {
        setCategoryIds((prev) =>
            prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id],
        );
    }

    function addBoqSection() {
        // Default title to "Section N" so a user who skips the field still
        // satisfies boq_sections.*.title_en's required_with rule (BUG-11 tier 2).
        setBoqSections([
            ...boqSections,
            { title: `Section ${boqSections.length + 1}`, title_ar: '', items: [] },
        ]);
    }

    function updateSection(index: number, field: keyof BoqSection, value: any) {
        const updated = [...boqSections];
        (updated[index] as any)[field] = value;
        setBoqSections(updated);
    }

    function removeSection(index: number) {
        setBoqSections(boqSections.filter((_, i) => i !== index));
    }

    function addBoqItem(sectionIndex: number) {
        // 'EA' (each) is the most common construction-BOQ unit and a safe
        // default that satisfies the required_with rule on items.*.unit.
        const updated = [...boqSections];
        updated[sectionIndex].items.push({
            item_code: '',
            description_en: '',
            unit: 'EA',
            quantity: '',
        });
        setBoqSections(updated);
    }

    function updateBoqItem(
        sectionIndex: number,
        itemIndex: number,
        field: keyof BoqItem,
        value: string,
    ) {
        const updated = [...boqSections];
        updated[sectionIndex].items[itemIndex][field] = value;
        setBoqSections(updated);
    }

    function removeBoqItem(sectionIndex: number, itemIndex: number) {
        const updated = [...boqSections];
        updated[sectionIndex].items = updated[sectionIndex].items.filter(
            (_, i) => i !== itemIndex,
        );
        setBoqSections(updated);
    }

    function toggleSectionExpand(index: number) {
        setExpandedSections((prev) => ({ ...prev, [index]: !prev[index] }));
    }

    function addDocument() {
        setDocuments([...documents, { file: null, title: '', doc_type: 'specification' }]);
    }

    function updateDocument(index: number, field: keyof DocEntry, value: any) {
        const updated = [...documents];
        (updated[index] as any)[field] = value;
        setDocuments(updated);
    }

    function removeDocument(index: number) {
        setDocuments(documents.filter((_, i) => i !== index));
    }

    function addCriterion() {
        // weight_percentage and max_score are required_with on the server.
        // Pre-filling matches the placeholder hints (100/100) so the most
        // common single-criterion case (one financial criterion at 100%)
        // saves without any extra input (BUG-11 tier 2).
        setCriteria([
            ...criteria,
            { name_en: '', envelope: 'technical', weight_percentage: '100', max_score: '100' },
        ]);
    }

    function updateCriterion(index: number, field: keyof Criterion, value: string) {
        const updated = [...criteria];
        updated[index][field] = value;
        setCriteria(updated);
    }

    function removeCriterion(index: number) {
        setCriteria(criteria.filter((_, i) => i !== index));
    }

    function getTotalWeight(envelope: string) {
        return criteria
            .filter((c) => c.envelope === envelope)
            .reduce((sum, c) => sum + (parseFloat(c.weight_percentage) || 0), 0);
    }

    function handleSubmit(action: 'draft' | 'published') {
        const payload: Record<string, any> = {
            ...form.data,
            category_ids: categoryIds,
            boq_sections: boqSections
                .filter((s) => s.title.trim() !== '')
                .map((s, i) => ({
                    title_en: s.title,
                    title_ar: s.title_ar || null,
                    sort_order: i,
                    items: s.items
                        .filter((it) => it.item_code.trim() !== '')
                        .map((it, j) => ({
                            item_code: it.item_code,
                            description_en: it.description_en,
                            unit: it.unit,
                            quantity: it.quantity,
                            sort_order: j,
                        })),
                })),
            evaluation_criteria: criteria
                .filter((c) => c.name_en.trim() !== '')
                .map((c, i) => ({
                    name_en: c.name_en,
                    envelope: c.envelope,
                    weight_percentage: c.weight_percentage,
                    max_score: c.max_score,
                    sort_order: i,
                })),
            publish: action === 'published' ? 1 : 0,
        };

        const validDocs = documents.filter((d) => d.file && d.title.trim() !== '');
        validDocs.forEach((doc, i) => {
            payload[`documents[${i}][file]`] = doc.file;
            payload[`documents[${i}][title]`] = doc.title;
            payload[`documents[${i}][doc_type]`] = doc.doc_type;
        });

        router.post('/tenders', payload, {
            forceFormData: true,
            preserveScroll: true,
            // preserveState keeps the wizard's useState arrays (boqSections,
            // criteria, documents, categoryIds) and currentStep intact after
            // the redirect-back-with-errors. Without it, Inertia's default
            // remount drops the user back at Step 1 of an empty wizard with
            // their data gone — the visible symptom that "nothing happened"
            // when validation actually failed (BUG-11 tier 3).
            preserveState: true,
            onError: (errs) => {
                // Per-call diagnostic log. The global useErrorToast hook
                // (resources/js/hooks/use-error-toast.ts) handles the
                // user-visible bottom-center toast as a backstop in case
                // this callback isn't invoked for any reason.
                console.log('[tender create] validation errors:', errs);
            },
        });
    }

    return (
        <>
            <Head title="Create Tender" />

            <div className="space-y-6">
                <Heading title={t('pages.tender_create.title')} />

                <StepIndicator current={currentStep} steps={STEPS} />

                <Card>
                    <CardContent className="pt-6">
                        {/* Step 1: Basic Info */}
                        {currentStep === 0 && (
                            <div className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="project_id">{t('form.project')}</Label>
                                        <Select
                                            value={form.data.project_id}
                                            onValueChange={(v) => form.setData('project_id', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('form.select_project')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {projects.map((p) => (
                                                    <SelectItem key={p.id} value={p.id}>
                                                        {p.code} — {p.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FieldError errors={errors} path="project_id" />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="tender_type">{t('form.tender_type')}</Label>
                                        <Select
                                            value={form.data.tender_type}
                                            onValueChange={(v) => form.setData('tender_type', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {TENDER_TYPES.map((tt) => (
                                                    <SelectItem key={tt.value} value={tt.value}>
                                                        {tt.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FieldError errors={errors} path="tender_type" />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="title_en">{t('form.title_en')}</Label>
                                        <Input
                                            id="title_en"
                                            value={form.data.title_en}
                                            onChange={(e) =>
                                                form.setData('title_en', e.target.value)
                                            }
                                        />
                                        <FieldError errors={errors} path="title_en" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="title_ar">{t('form.title_ar')}</Label>
                                        <Input
                                            id="title_ar"
                                            value={form.data.title_ar}
                                            onChange={(e) =>
                                                form.setData('title_ar', e.target.value)
                                            }
                                            dir="rtl"
                                        />
                                        <FieldError errors={errors} path="title_ar" />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="description_en">{t('form.description_en')}</Label>
                                        <textarea
                                            id="description_en"
                                            className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            value={form.data.description_en}
                                            onChange={(e) =>
                                                form.setData('description_en', e.target.value)
                                            }
                                        />
                                        <FieldError errors={errors} path="description_en" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="description_ar">{t('form.description_ar')}</Label>
                                        <textarea
                                            id="description_ar"
                                            className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            value={form.data.description_ar}
                                            onChange={(e) =>
                                                form.setData('description_ar', e.target.value)
                                            }
                                            dir="rtl"
                                        />
                                        <FieldError errors={errors} path="description_ar" />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="estimated_value">{t('form.estimated_value')}</Label>
                                        <Input
                                            id="estimated_value"
                                            type="number"
                                            value={form.data.estimated_value}
                                            onChange={(e) =>
                                                form.setData('estimated_value', e.target.value)
                                            }
                                        />
                                        <FieldError errors={errors} path="estimated_value" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="currency">{t('form.currency')}</Label>
                                        <Select
                                            value={form.data.currency}
                                            onValueChange={(v) => form.setData('currency', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {CURRENCIES.map((c) => (
                                                    <SelectItem key={c.value} value={c.value}>
                                                        {c.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FieldError errors={errors} path="currency" />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="submission_deadline">
                                            {t('form.submission_deadline')}
                                        </Label>
                                        <Input
                                            id="submission_deadline"
                                            type="datetime-local"
                                            value={form.data.submission_deadline}
                                            onChange={(e) =>
                                                form.setData(
                                                    'submission_deadline',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <FieldError errors={errors} path="submission_deadline" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="opening_date">{t('form.opening_date')}</Label>
                                        <Input
                                            id="opening_date"
                                            type="datetime-local"
                                            value={form.data.opening_date}
                                            onChange={(e) =>
                                                form.setData('opening_date', e.target.value)
                                            }
                                        />
                                        <FieldError errors={errors} path="opening_date" />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="is_two_envelope"
                                            checked={form.data.is_two_envelope}
                                            onCheckedChange={(checked) =>
                                                form.setData('is_two_envelope', checked === true)
                                            }
                                        />
                                        <Label htmlFor="is_two_envelope">
                                            {t('form.two_envelope_system')}
                                        </Label>
                                    </div>

                                    {form.data.is_two_envelope && (
                                        <div className="ml-6 space-y-2">
                                            <Label htmlFor="technical_pass_score">
                                                {t('form.technical_pass_score')}
                                            </Label>
                                            <Input
                                                id="technical_pass_score"
                                                type="number"
                                                min="0"
                                                max="100"
                                                className="w-32"
                                                value={form.data.technical_pass_score}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'technical_pass_score',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <FieldError errors={errors} path="technical_pass_score" />
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Step 2: BOQ Builder */}
                        {currentStep === 1 && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium">{t('tender.bill_of_quantities')}</h3>
                                    <Button type="button" variant="outline" onClick={addBoqSection}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('tender.add_section')}
                                    </Button>
                                </div>

                                {boqSections.length === 0 && (
                                    <p className="text-center text-muted-foreground py-8">
                                        {t('empty.no_boq_sections_hint')}
                                    </p>
                                )}

                                <FieldError errors={errors} path="boq_sections" />

                                {boqSections.map((section, si) => (
                                    <Card key={si}>
                                        <CardHeader className="pb-3">
                                            <div className="flex items-center justify-between">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleSectionExpand(si)}
                                                    className="flex items-center gap-2"
                                                >
                                                    {expandedSections[si] !== false ? (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronRight className="h-4 w-4" />
                                                    )}
                                                    <CardTitle className="text-base">
                                                        {section.title || `Section ${si + 1}`}
                                                    </CardTitle>
                                                </button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeSection(si)}
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </CardHeader>
                                        {expandedSections[si] !== false && (
                                            <CardContent className="space-y-4">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label>{t('form.section_title_en')}</Label>
                                                        <Input
                                                            value={section.title}
                                                            onChange={(e) =>
                                                                updateSection(
                                                                    si,
                                                                    'title',
                                                                    e.target.value,
                                                                )
                                                            }
                                                        />
                                                        <FieldError errors={errors} path={`boq_sections.${si}.title_en`} />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>{t('form.section_title_ar')}</Label>
                                                        <Input
                                                            value={section.title_ar}
                                                            onChange={(e) =>
                                                                updateSection(
                                                                    si,
                                                                    'title_ar',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            dir="rtl"
                                                        />
                                                        <FieldError errors={errors} path={`boq_sections.${si}.title_ar`} />
                                                    </div>
                                                </div>

                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <Label>{t('form.items')}</Label>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => addBoqItem(si)}
                                                        >
                                                            <Plus className="mr-1 h-3 w-3" />
                                                            {t('btn.add_item')}
                                                        </Button>
                                                    </div>

                                                    {section.items.length > 0 && (
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
                                                                        <th className="px-3 py-2 text-left font-medium">
                                                                            {t('table.qty')}
                                                                        </th>
                                                                        <th className="px-3 py-2 w-10"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {section.items.map(
                                                                        (item, ii) => (
                                                                            <tr
                                                                                key={ii}
                                                                                className="border-b last:border-0"
                                                                            >
                                                                                <td className="px-3 py-2 align-top">
                                                                                    <Input
                                                                                        value={
                                                                                            item.item_code
                                                                                        }
                                                                                        onChange={(
                                                                                            e,
                                                                                        ) =>
                                                                                            updateBoqItem(
                                                                                                si,
                                                                                                ii,
                                                                                                'item_code',
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            )
                                                                                        }
                                                                                        className="h-8"
                                                                                        placeholder="A.1"
                                                                                    />
                                                                                    <FieldError errors={errors} path={`boq_sections.${si}.items.${ii}.item_code`} />
                                                                                </td>
                                                                                <td className="px-3 py-2 align-top">
                                                                                    <Input
                                                                                        value={
                                                                                            item.description_en
                                                                                        }
                                                                                        onChange={(
                                                                                            e,
                                                                                        ) =>
                                                                                            updateBoqItem(
                                                                                                si,
                                                                                                ii,
                                                                                                'description_en',
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            )
                                                                                        }
                                                                                        className="h-8"
                                                                                        placeholder="Description"
                                                                                    />
                                                                                    <FieldError errors={errors} path={`boq_sections.${si}.items.${ii}.description_en`} />
                                                                                </td>
                                                                                <td className="px-3 py-2 align-top">
                                                                                    <Input
                                                                                        value={
                                                                                            item.unit
                                                                                        }
                                                                                        onChange={(
                                                                                            e,
                                                                                        ) =>
                                                                                            updateBoqItem(
                                                                                                si,
                                                                                                ii,
                                                                                                'unit',
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            )
                                                                                        }
                                                                                        className="h-8 w-20"
                                                                                        placeholder="EA"
                                                                                    />
                                                                                    <FieldError errors={errors} path={`boq_sections.${si}.items.${ii}.unit`} />
                                                                                </td>
                                                                                <td className="px-3 py-2 align-top">
                                                                                    <Input
                                                                                        type="number"
                                                                                        value={
                                                                                            item.quantity
                                                                                        }
                                                                                        onChange={(
                                                                                            e,
                                                                                        ) =>
                                                                                            updateBoqItem(
                                                                                                si,
                                                                                                ii,
                                                                                                'quantity',
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            )
                                                                                        }
                                                                                        className="h-8 w-24"
                                                                                        placeholder="0"
                                                                                    />
                                                                                    <FieldError errors={errors} path={`boq_sections.${si}.items.${ii}.quantity`} />
                                                                                </td>
                                                                                <td className="px-3 py-2">
                                                                                    <Button
                                                                                        type="button"
                                                                                        variant="ghost"
                                                                                        size="sm"
                                                                                        onClick={() =>
                                                                                            removeBoqItem(
                                                                                                si,
                                                                                                ii,
                                                                                            )
                                                                                        }
                                                                                    >
                                                                                        <Trash2 className="h-3 w-3 text-destructive" />
                                                                                    </Button>
                                                                                </td>
                                                                            </tr>
                                                                        ),
                                                                    )}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                        )}
                                    </Card>
                                ))}
                            </div>
                        )}

                        {/* Step 3: Documents */}
                        {currentStep === 2 && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium">{t('tender.tender_documents')}</h3>
                                    <Button type="button" variant="outline" onClick={addDocument}>
                                        <Upload className="mr-2 h-4 w-4" />
                                        {t('btn.add_document')}
                                    </Button>
                                </div>

                                {documents.length === 0 && (
                                    <p className="text-center text-muted-foreground py-8">
                                        {t('empty.no_documents_added')}
                                    </p>
                                )}

                                <FieldError errors={errors} path="documents" />

                                {documents.map((doc, i) => (
                                    <Card key={i}>
                                        <CardContent className="pt-4">
                                            <div className="flex gap-4 items-end">
                                                <div className="flex-1 space-y-2">
                                                    <Label>{t('form.title')}</Label>
                                                    <Input
                                                        value={doc.title}
                                                        onChange={(e) =>
                                                            updateDocument(
                                                                i,
                                                                'title',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder={t('form.document_title_placeholder')}
                                                    />
                                                    <FieldError errors={errors} path={`documents.${i}.title`} />
                                                </div>
                                                <div className="w-48 space-y-2">
                                                    <Label>{t('form.type')}</Label>
                                                    <Select
                                                        value={doc.doc_type}
                                                        onValueChange={(v) =>
                                                            updateDocument(i, 'doc_type', v)
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {DOC_TYPES.map((dt) => (
                                                                <SelectItem
                                                                    key={dt.value}
                                                                    value={dt.value}
                                                                >
                                                                    {dt.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FieldError errors={errors} path={`documents.${i}.doc_type`} />
                                                </div>
                                                <div className="flex-1 space-y-2">
                                                    <Label>{t('form.file')}</Label>
                                                    <Input
                                                        type="file"
                                                        accept="application/pdf"
                                                        onChange={(e) =>
                                                            updateDocument(
                                                                i,
                                                                'file',
                                                                e.target.files?.[0] ?? null,
                                                            )
                                                        }
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        {t('bid.documents.pdf_only')}
                                                    </p>
                                                    <FieldError errors={errors} path={`documents.${i}.file`} />
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeDocument(i)}
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}

                        {/* Step 4: Categories */}
                        {currentStep === 3 && (
                            <div className="space-y-4">
                                <h3 className="text-lg font-medium">{t('tender.select_categories')}</h3>
                                {categories.length === 0 ? (
                                    <p className="text-center text-muted-foreground py-8">
                                        {t('empty.no_categories')}
                                    </p>
                                ) : (
                                    <CategoryTree
                                        categories={categories}
                                        selectedIds={categoryIds}
                                        onToggle={toggleCategory}
                                    />
                                )}
                                {categoryIds.length > 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        {categoryIds.length} {t('tender.categories_selected')}
                                    </p>
                                )}
                                <FieldError errors={errors} path="category_ids" />
                            </div>
                        )}

                        {/* Step 5: Evaluation Criteria */}
                        {currentStep === 4 && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium">{t('tender.evaluation_criteria')}</h3>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addCriterion}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('tender.add_criterion')}
                                    </Button>
                                </div>

                                <div className="flex gap-4 text-sm">
                                    <span className="text-muted-foreground">
                                        {t('tender.technical_weight_total')}:{' '}
                                        <strong>{getTotalWeight('technical')}%</strong>
                                    </span>
                                    <span className="text-muted-foreground">
                                        {t('tender.financial_weight_total')}:{' '}
                                        <strong>{getTotalWeight('financial')}%</strong>
                                    </span>
                                </div>

                                {criteria.length === 0 && (
                                    <p className="text-center text-muted-foreground py-8">
                                        {t('empty.no_evaluation_criteria')}
                                    </p>
                                )}

                                <FieldError errors={errors} path="evaluation_criteria" />

                                {criteria.map((c, i) => (
                                    <Card key={i}>
                                        <CardContent className="pt-4">
                                            <div className="flex gap-4 items-end">
                                                <div className="flex-1 space-y-2">
                                                    <Label>{t('form.name')}</Label>
                                                    <Input
                                                        value={c.name_en}
                                                        onChange={(e) =>
                                                            updateCriterion(
                                                                i,
                                                                'name_en',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder={t('tender.criterion_name_placeholder')}
                                                    />
                                                    <FieldError errors={errors} path={`evaluation_criteria.${i}.name_en`} />
                                                </div>
                                                <div className="w-36 space-y-2">
                                                    <Label>{t('form.envelope')}</Label>
                                                    <Select
                                                        value={c.envelope}
                                                        onValueChange={(v) =>
                                                            updateCriterion(i, 'envelope', v)
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="technical">
                                                                {t('tender.technical')}
                                                            </SelectItem>
                                                            <SelectItem value="financial">
                                                                {t('tender.financial')}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <FieldError errors={errors} path={`evaluation_criteria.${i}.envelope`} />
                                                </div>
                                                <div className="w-28 space-y-2">
                                                    <Label>{t('form.weight_pct')}</Label>
                                                    <Input
                                                        type="number"
                                                        value={c.weight_percentage}
                                                        onChange={(e) =>
                                                            updateCriterion(
                                                                i,
                                                                'weight_percentage',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="0"
                                                    />
                                                    <FieldError errors={errors} path={`evaluation_criteria.${i}.weight_percentage`} />
                                                </div>
                                                <div className="w-28 space-y-2">
                                                    <Label>{t('form.max_score')}</Label>
                                                    <Input
                                                        type="number"
                                                        value={c.max_score}
                                                        onChange={(e) =>
                                                            updateCriterion(
                                                                i,
                                                                'max_score',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="100"
                                                    />
                                                    <FieldError errors={errors} path={`evaluation_criteria.${i}.max_score`} />
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeCriterion(i)}
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}

                        {/* Step 6: Review & Save */}
                        {currentStep === 5 && (
                            <div className="space-y-6">
                                <h3 className="text-lg font-medium">{t('tender.step_review_save')}</h3>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">{t('tender.basic_information')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <dl className="grid gap-2 sm:grid-cols-2 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">{t('form.title_en')}</dt>
                                                <dd className="font-medium">
                                                    {form.data.title_en || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">{t('form.title_ar')}</dt>
                                                <dd className="font-medium" dir="rtl">
                                                    {form.data.title_ar || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">{t('form.type')}</dt>
                                                <dd className="font-medium capitalize">
                                                    {form.data.tender_type.replace('_', ' ')}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('tender.estimated_value')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.estimated_value
                                                        ? `${form.data.estimated_value} ${form.data.currency}`
                                                        : '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('form.submission_deadline')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.submission_deadline || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('form.opening_date')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.opening_date || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('tender.two_envelope')}
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.is_two_envelope ? t('common.yes') : t('common.no')}
                                                </dd>
                                            </div>
                                        </dl>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">{t('tender.boq_sections')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {boqSections.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                {t('empty.no_boq_sections_defined')}
                                            </p>
                                        ) : (
                                            <ul className="space-y-1 text-sm">
                                                {boqSections.map((s, i) => (
                                                    <li key={i}>
                                                        <span className="font-medium">
                                                            {s.title || `Section ${i + 1}`}
                                                        </span>{' '}
                                                        — {s.items.length} item(s)
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">{t('tender.tab_documents')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {documents.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                {t('empty.no_documents_attached')}
                                            </p>
                                        ) : (
                                            <ul className="space-y-1 text-sm">
                                                {documents.map((d, i) => (
                                                    <li key={i}>
                                                        {d.title || 'Untitled'} ({d.doc_type})
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">{t('tender.categories')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm text-muted-foreground">
                                            {categoryIds.length} {t('tender.categories_selected')}
                                        </p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            {t('tender.evaluation_criteria')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {criteria.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                {t('empty.no_evaluation_criteria_defined')}
                                            </p>
                                        ) : (
                                            <ul className="space-y-1 text-sm">
                                                {criteria.map((c, i) => (
                                                    <li key={i}>
                                                        {c.name_en} — {c.envelope} —{' '}
                                                        {c.weight_percentage}% (max {c.max_score})
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Navigation */}
                <div className="flex items-center justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={prev}
                        disabled={currentStep === 0}
                    >
                        {t('btn.previous')}
                    </Button>

                    <div className="flex gap-2">
                        {currentStep === STEPS.length - 1 ? (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => handleSubmit('draft')}
                                    disabled={form.processing}
                                >
                                    {t('btn.save_as_draft')}
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => handleSubmit('published')}
                                    disabled={form.processing}
                                >
                                    {t('btn.save_and_publish')}
                                </Button>
                            </>
                        ) : (
                            <Button type="button" onClick={next}>
                                {t('btn.next')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
