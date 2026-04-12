import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { ChevronRight, ChevronDown, Save } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string | null;
    children?: Category[];
};

type Props = {
    categories: Category[];
    selectedCategoryIds: string[];
};

export default function Categories({ categories, selectedCategoryIds }: Props) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const form = useForm({
        category_ids: selectedCategoryIds,
    });

    const toggleExpand = (id: string) => {
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const toggleCategory = (id: string) => {
        const current = form.data.category_ids;
        if (current.includes(id)) {
            form.setData('category_ids', current.filter((cid) => cid !== id));
        } else {
            form.setData('category_ids', [...current, id]);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/vendor/categories');
    };

    return (
        <>
            <Head title="Business Categories" />

            <div className="space-y-6">
                <Heading title={t('pages.vendor.business_categories')} description={t('vendor.select_categories_description')} />

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-3">
                                {categories.map((category) => (
                                    <div key={category.id} className="rounded-lg border p-4">
                                        <div className="flex items-center gap-3">
                                            {category.children && category.children.length > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleExpand(category.id)}
                                                    className="rounded p-1 hover:bg-muted"
                                                >
                                                    {expanded[category.id] ? (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronRight className="h-4 w-4" />
                                                    )}
                                                </button>
                                            )}
                                            {(!category.children || category.children.length === 0) && (
                                                <div className="w-6" />
                                            )}
                                            <Checkbox
                                                id={`category-${category.id}`}
                                                checked={form.data.category_ids.includes(category.id)}
                                                onCheckedChange={() => toggleCategory(category.id)}
                                            />
                                            <Label htmlFor={`category-${category.id}`} className="cursor-pointer font-medium">
                                                {category.name_en}
                                                {category.name_ar && (
                                                    <span className="ms-2 text-muted-foreground">({category.name_ar})</span>
                                                )}
                                            </Label>
                                        </div>

                                        {expanded[category.id] && category.children && category.children.length > 0 && (
                                            <div className="ms-12 mt-3 space-y-2 border-s-2 border-muted ps-4">
                                                {category.children.map((child) => (
                                                    <div key={child.id} className="flex items-center gap-3">
                                                        <Checkbox
                                                            id={`category-${child.id}`}
                                                            checked={form.data.category_ids.includes(child.id)}
                                                            onCheckedChange={() => toggleCategory(child.id)}
                                                        />
                                                        <Label
                                                            htmlFor={`category-${child.id}`}
                                                            className="cursor-pointer"
                                                        >
                                                            {child.name_en}
                                                            {child.name_ar && (
                                                                <span className="ms-2 text-muted-foreground">
                                                                    ({child.name_ar})
                                                                </span>
                                                            )}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {form.errors.category_ids && (
                                <p className="mt-4 text-sm text-destructive">{form.errors.category_ids}</p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="mt-6 flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {form.processing ? t('btn.saving') : t('btn.save_categories')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
