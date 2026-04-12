import { Head, useForm, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { MultiSelect } from '@/components/MultiSelect';
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
    children?: Array<{
        id: string;
        name_en: string;
        name_ar: string | null;
        parent_id: string;
    }>;
};

type Props = {
    tender: {
        id: string;
        title_en: string;
        title_ar: string | null;
        description_en: string | null;
        description_ar: string | null;
        tender_type: string;
        estimated_value: string | null;
        currency: string;
        submission_deadline: string;
        opening_date: string;
        is_two_envelope: boolean;
        technical_pass_score: string | null;
        requires_site_visit: boolean;
        site_visit_date: string | null;
        project_id: string;
    };
    projects: Array<{ id: string; name: string; code: string }>;
    categories: Category[];
    tenderCategoryIds: string[];
};

const CURRENCIES = [
    { value: 'USD', label: 'USD' },
    { value: 'IQD', label: 'IQD' },
    { value: 'EUR', label: 'EUR' },
];

function flattenCategories(categories: Category[]) {
    const result: Array<{ value: string; label: string }> = [];
    for (const cat of categories) {
        result.push({ value: cat.id, label: cat.name_en });
        if (cat.children) {
            for (const child of cat.children) {
                result.push({ value: child.id, label: `  ${child.name_en}` });
            }
        }
    }
    return result;
}

export default function Edit({ tender, projects, categories, tenderCategoryIds }: Props) {
    const { t } = useTranslation();

    const TENDER_TYPES = [
        { value: 'open', label: t('tender.type_open') },
        { value: 'restricted', label: t('tender.type_restricted') },
        { value: 'direct_invitation', label: t('tender.type_direct_invitation') },
        { value: 'framework', label: t('tender.type_framework') },
    ];

    const form = useForm({
        project_id: tender.project_id,
        title_en: tender.title_en,
        title_ar: tender.title_ar ?? '',
        description_en: tender.description_en ?? '',
        description_ar: tender.description_ar ?? '',
        tender_type: tender.tender_type,
        estimated_value: tender.estimated_value ?? '',
        currency: tender.currency,
        submission_deadline: tender.submission_deadline,
        opening_date: tender.opening_date,
        is_two_envelope: tender.is_two_envelope,
        technical_pass_score: tender.technical_pass_score ?? '',
        requires_site_visit: tender.requires_site_visit,
        site_visit_date: tender.site_visit_date ?? '',
        category_ids: tenderCategoryIds,
    });

    const categoryOptions = flattenCategories(categories);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/tenders/${tender.id}`);
    }

    return (
        <>
            <Head title={`Edit Tender: ${tender.title_en}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading title={t('pages.tender_edit.title')} />
                    <Link href={`/tenders/${tender.id}`}>
                        <Button variant="outline">{t('btn.cancel')}</Button>
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tender.basic_information')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
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
                                    {form.errors.project_id && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.project_id}
                                        </p>
                                    )}
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
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="title_en">{t('form.title_en')}</Label>
                                    <Input
                                        id="title_en"
                                        value={form.data.title_en}
                                        onChange={(e) => form.setData('title_en', e.target.value)}
                                    />
                                    {form.errors.title_en && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.title_en}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="title_ar">{t('form.title_ar')}</Label>
                                    <Input
                                        id="title_ar"
                                        value={form.data.title_ar}
                                        onChange={(e) => form.setData('title_ar', e.target.value)}
                                        dir="rtl"
                                    />
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
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Financial */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tender.financial_details')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
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
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Dates */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tender.dates')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="submission_deadline">{t('form.submission_deadline')}</Label>
                                    <Input
                                        id="submission_deadline"
                                        type="datetime-local"
                                        value={form.data.submission_deadline}
                                        onChange={(e) =>
                                            form.setData('submission_deadline', e.target.value)
                                        }
                                    />
                                    {form.errors.submission_deadline && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.submission_deadline}
                                        </p>
                                    )}
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
                                        id="requires_site_visit"
                                        checked={form.data.requires_site_visit}
                                        onCheckedChange={(checked) =>
                                            form.setData('requires_site_visit', checked === true)
                                        }
                                    />
                                    <Label htmlFor="requires_site_visit">
                                        {t('form.requires_site_visit')}
                                    </Label>
                                </div>

                                {form.data.requires_site_visit && (
                                    <div className="ml-6 space-y-2">
                                        <Label htmlFor="site_visit_date">{t('form.site_visit_date')}</Label>
                                        <Input
                                            id="site_visit_date"
                                            type="datetime-local"
                                            value={form.data.site_visit_date}
                                            onChange={(e) =>
                                                form.setData('site_visit_date', e.target.value)
                                            }
                                            className="w-64"
                                        />
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Evaluation Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tender.evaluation_settings')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
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
                                            form.setData('technical_pass_score', e.target.value)
                                        }
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Categories */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tender.categories')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MultiSelect
                                options={categoryOptions}
                                value={form.data.category_ids}
                                onChange={(ids) => form.setData('category_ids', ids)}
                                placeholder={t('form.select_categories')}
                            />
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <div className="flex items-center justify-end gap-4">
                        <Link href={`/tenders/${tender.id}`}>
                            <Button type="button" variant="outline">
                                {t('btn.cancel')}
                            </Button>
                        </Link>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? t('btn.saving') : t('btn.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
