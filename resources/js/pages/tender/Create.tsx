import { Head, useForm } from '@inertiajs/react';
import { useState, ReactNode } from 'react';
import { Check, ChevronDown, ChevronRight, Plus, Trash2, Upload } from 'lucide-react';
import Heading from '@/components/heading';
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

const STEPS = [
    'Basic Info',
    'BOQ Builder',
    'Documents',
    'Categories',
    'Evaluation Criteria',
    'Review & Save',
];

const TENDER_TYPES = [
    { value: 'open', label: 'Open' },
    { value: 'restricted', label: 'Restricted' },
    { value: 'direct_invitation', label: 'Direct Invitation' },
    { value: 'framework', label: 'Framework' },
];

const CURRENCIES = [
    { value: 'USD', label: 'USD' },
    { value: 'IQD', label: 'IQD' },
    { value: 'EUR', label: 'EUR' },
];

const DOC_TYPES = [
    { value: 'tender_document', label: 'Tender Document' },
    { value: 'technical_specs', label: 'Technical Specifications' },
    { value: 'drawings', label: 'Drawings' },
    { value: 'contract_template', label: 'Contract Template' },
    { value: 'other', label: 'Other' },
];

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
        status: 'draft',
    });

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
        setBoqSections([...boqSections, { title: '', title_ar: '', items: [] }]);
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
        const updated = [...boqSections];
        updated[sectionIndex].items.push({
            item_code: '',
            description_en: '',
            unit: '',
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
        setDocuments([...documents, { file: null, title: '', doc_type: 'tender_document' }]);
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
        setCriteria([
            ...criteria,
            { name_en: '', envelope: 'technical', weight_percentage: '', max_score: '' },
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

    function handleSubmit(status: 'draft' | 'published') {
        form.transform((data) => ({
            ...data,
            category_ids: categoryIds,
            status,
        }));
        form.post('/tenders');
    }

    return (
        <>
            <Head title="Create Tender" />

            <div className="space-y-6">
                <Heading title="Create Tender" />

                <StepIndicator current={currentStep} steps={STEPS} />

                <Card>
                    <CardContent className="pt-6">
                        {/* Step 1: Basic Info */}
                        {currentStep === 0 && (
                            <div className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="project_id">Project</Label>
                                        <Select
                                            value={form.data.project_id}
                                            onValueChange={(v) => form.setData('project_id', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select project" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {projects.map((p) => (
                                                    <SelectItem key={p.id} value={p.id}>
                                                        {p.code} — {p.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.project_id && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.project_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="tender_type">Tender Type</Label>
                                        <Select
                                            value={form.data.tender_type}
                                            onValueChange={(v) => form.setData('tender_type', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {TENDER_TYPES.map((t) => (
                                                    <SelectItem key={t.value} value={t.value}>
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="title_en">Title (English)</Label>
                                        <Input
                                            id="title_en"
                                            value={form.data.title_en}
                                            onChange={(e) =>
                                                form.setData('title_en', e.target.value)
                                            }
                                        />
                                        {form.errors.title_en && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.title_en}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="title_ar">Title (Arabic)</Label>
                                        <Input
                                            id="title_ar"
                                            value={form.data.title_ar}
                                            onChange={(e) =>
                                                form.setData('title_ar', e.target.value)
                                            }
                                            dir="rtl"
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="description_en">Description (English)</Label>
                                        <textarea
                                            id="description_en"
                                            className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            value={form.data.description_en}
                                            onChange={(e) =>
                                                form.setData('description_en', e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="description_ar">Description (Arabic)</Label>
                                        <textarea
                                            id="description_ar"
                                            className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            value={form.data.description_ar}
                                            onChange={(e) =>
                                                form.setData('description_ar', e.target.value)
                                            }
                                            dir="rtl"
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="estimated_value">Estimated Value</Label>
                                        <Input
                                            id="estimated_value"
                                            type="number"
                                            value={form.data.estimated_value}
                                            onChange={(e) =>
                                                form.setData('estimated_value', e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="currency">Currency</Label>
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
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="submission_deadline">
                                            Submission Deadline
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
                                        {form.errors.submission_deadline && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.submission_deadline}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="opening_date">Opening Date</Label>
                                        <Input
                                            id="opening_date"
                                            type="datetime-local"
                                            value={form.data.opening_date}
                                            onChange={(e) =>
                                                form.setData('opening_date', e.target.value)
                                            }
                                        />
                                        {form.errors.opening_date && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.opening_date}
                                            </p>
                                        )}
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
                                            Two-envelope system (Technical + Financial)
                                        </Label>
                                    </div>

                                    {form.data.is_two_envelope && (
                                        <div className="ml-6 space-y-2">
                                            <Label htmlFor="technical_pass_score">
                                                Technical Pass Score (%)
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
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Step 2: BOQ Builder */}
                        {currentStep === 1 && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium">Bill of Quantities</h3>
                                    <Button type="button" variant="outline" onClick={addBoqSection}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Section
                                    </Button>
                                </div>

                                {boqSections.length === 0 && (
                                    <p className="text-center text-muted-foreground py-8">
                                        No BOQ sections yet. Click "Add Section" to begin.
                                    </p>
                                )}

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
                                                        <Label>Section Title (English)</Label>
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
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Section Title (Arabic)</Label>
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
                                                    </div>
                                                </div>

                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <Label>Items</Label>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => addBoqItem(si)}
                                                        >
                                                            <Plus className="mr-1 h-3 w-3" />
                                                            Add Item
                                                        </Button>
                                                    </div>

                                                    {section.items.length > 0 && (
                                                        <div className="rounded-md border">
                                                            <table className="w-full text-sm">
                                                                <thead>
                                                                    <tr className="border-b bg-muted/50">
                                                                        <th className="px-3 py-2 text-left font-medium">
                                                                            Code
                                                                        </th>
                                                                        <th className="px-3 py-2 text-left font-medium">
                                                                            Description
                                                                        </th>
                                                                        <th className="px-3 py-2 text-left font-medium">
                                                                            Unit
                                                                        </th>
                                                                        <th className="px-3 py-2 text-left font-medium">
                                                                            Qty
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
                                                                                <td className="px-3 py-2">
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
                                                                                </td>
                                                                                <td className="px-3 py-2">
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
                                                                                </td>
                                                                                <td className="px-3 py-2">
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
                                                                                        placeholder="m2"
                                                                                    />
                                                                                </td>
                                                                                <td className="px-3 py-2">
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
                                    <h3 className="text-lg font-medium">Tender Documents</h3>
                                    <Button type="button" variant="outline" onClick={addDocument}>
                                        <Upload className="mr-2 h-4 w-4" />
                                        Add Document
                                    </Button>
                                </div>

                                {documents.length === 0 && (
                                    <p className="text-center text-muted-foreground py-8">
                                        No documents added yet.
                                    </p>
                                )}

                                {documents.map((doc, i) => (
                                    <Card key={i}>
                                        <CardContent className="pt-4">
                                            <div className="flex gap-4 items-end">
                                                <div className="flex-1 space-y-2">
                                                    <Label>Title</Label>
                                                    <Input
                                                        value={doc.title}
                                                        onChange={(e) =>
                                                            updateDocument(
                                                                i,
                                                                'title',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Document title"
                                                    />
                                                </div>
                                                <div className="w-48 space-y-2">
                                                    <Label>Type</Label>
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
                                                </div>
                                                <div className="flex-1 space-y-2">
                                                    <Label>File</Label>
                                                    <Input
                                                        type="file"
                                                        onChange={(e) =>
                                                            updateDocument(
                                                                i,
                                                                'file',
                                                                e.target.files?.[0] ?? null,
                                                            )
                                                        }
                                                    />
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
                                <h3 className="text-lg font-medium">Select Categories</h3>
                                {categories.length === 0 ? (
                                    <p className="text-center text-muted-foreground py-8">
                                        No categories available.
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
                                        {categoryIds.length} category(ies) selected
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Step 5: Evaluation Criteria */}
                        {currentStep === 4 && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium">Evaluation Criteria</h3>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addCriterion}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Criterion
                                    </Button>
                                </div>

                                <div className="flex gap-4 text-sm">
                                    <span className="text-muted-foreground">
                                        Technical weight total:{' '}
                                        <strong>{getTotalWeight('technical')}%</strong>
                                    </span>
                                    <span className="text-muted-foreground">
                                        Financial weight total:{' '}
                                        <strong>{getTotalWeight('financial')}%</strong>
                                    </span>
                                </div>

                                {criteria.length === 0 && (
                                    <p className="text-center text-muted-foreground py-8">
                                        No evaluation criteria defined yet.
                                    </p>
                                )}

                                {criteria.map((c, i) => (
                                    <Card key={i}>
                                        <CardContent className="pt-4">
                                            <div className="flex gap-4 items-end">
                                                <div className="flex-1 space-y-2">
                                                    <Label>Name</Label>
                                                    <Input
                                                        value={c.name_en}
                                                        onChange={(e) =>
                                                            updateCriterion(
                                                                i,
                                                                'name_en',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Criterion name"
                                                    />
                                                </div>
                                                <div className="w-36 space-y-2">
                                                    <Label>Envelope</Label>
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
                                                                Technical
                                                            </SelectItem>
                                                            <SelectItem value="financial">
                                                                Financial
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="w-28 space-y-2">
                                                    <Label>Weight %</Label>
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
                                                </div>
                                                <div className="w-28 space-y-2">
                                                    <Label>Max Score</Label>
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
                                <h3 className="text-lg font-medium">Review & Save</h3>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Basic Information</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <dl className="grid gap-2 sm:grid-cols-2 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">Title (EN)</dt>
                                                <dd className="font-medium">
                                                    {form.data.title_en || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">Title (AR)</dt>
                                                <dd className="font-medium" dir="rtl">
                                                    {form.data.title_ar || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">Type</dt>
                                                <dd className="font-medium capitalize">
                                                    {form.data.tender_type.replace('_', ' ')}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Estimated Value
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.estimated_value
                                                        ? `${form.data.estimated_value} ${form.data.currency}`
                                                        : '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Submission Deadline
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.submission_deadline || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Opening Date
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.opening_date || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Two-Envelope
                                                </dt>
                                                <dd className="font-medium">
                                                    {form.data.is_two_envelope ? 'Yes' : 'No'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">BOQ Sections</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {boqSections.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No BOQ sections defined.
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
                                        <CardTitle className="text-base">Documents</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {documents.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No documents attached.
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
                                        <CardTitle className="text-base">Categories</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm text-muted-foreground">
                                            {categoryIds.length} category(ies) selected
                                        </p>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Evaluation Criteria
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {criteria.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No evaluation criteria defined.
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
                        Previous
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
                                    Save as Draft
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => handleSubmit('published')}
                                    disabled={form.processing}
                                >
                                    Save & Publish
                                </Button>
                            </>
                        ) : (
                            <Button type="button" onClick={next}>
                                Next
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
