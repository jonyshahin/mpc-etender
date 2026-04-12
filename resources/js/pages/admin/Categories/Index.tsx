import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '@/components/ui/select';
import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { ChevronRight, ChevronDown, Plus, Pencil, Trash2, FolderTree, Check, X } from 'lucide-react';

type ChildCategory = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string;
    is_active: boolean;
    sort_order: number;
};

type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string | null;
    is_active: boolean;
    sort_order: number;
    vendors_count: number;
    children: ChildCategory[];
};

type Props = {
    categories: Category[];
};

export default function Index({ categories }: Props) {
    const { t } = useTranslation();
    const [openIds, setOpenIds] = useState<Set<string>>(new Set());
    const [editingId, setEditingId] = useState<string | null>(null);
    const [deleteId, setDeleteId] = useState<string | null>(null);

    const addForm = useForm({
        name_en: '',
        name_ar: '',
        parent_id: '',
    });

    const editForm = useForm({
        name_en: '',
        name_ar: '',
    });

    function toggleOpen(id: string) {
        setOpenIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    function startEdit(cat: { id: string; name_en: string; name_ar: string | null }) {
        editForm.setData({
            name_en: cat.name_en,
            name_ar: cat.name_ar ?? '',
        });
        setEditingId(cat.id);
    }

    function cancelEdit() {
        setEditingId(null);
        editForm.reset();
    }

    function saveEdit(id: string) {
        editForm.put(`/admin/categories/${id}`, {
            onSuccess: () => setEditingId(null),
        });
    }

    function handleAdd(e: React.FormEvent) {
        e.preventDefault();
        addForm.post('/admin/categories', {
            onSuccess: () => addForm.reset(),
        });
    }

    function confirmDelete() {
        if (!deleteId) return;
        router.delete(`/admin/categories/${deleteId}`, {
            onSuccess: () => setDeleteId(null),
        });
    }

    function renderCategoryRow(
        cat: { id: string; name_en: string; name_ar: string | null; is_active: boolean },
        indent: number,
        vendorsCount?: number,
    ) {
        const isEditing = editingId === cat.id;

        return (
            <div
                key={cat.id}
                className="flex items-center gap-3 rounded-md border-b px-3 py-2 hover:bg-muted/50"
                style={{ paddingLeft: `${indent * 24 + 12}px` }}
            >
                {isEditing ? (
                    <>
                        <div className="flex flex-1 items-center gap-2">
                            <Input
                                value={editForm.data.name_en}
                                onChange={(e) => editForm.setData('name_en', e.target.value)}
                                placeholder={t('form.name_english')}
                                className="h-8 w-48"
                            />
                            <Input
                                value={editForm.data.name_ar}
                                onChange={(e) => editForm.setData('name_ar', e.target.value)}
                                placeholder={t('form.name_arabic')}
                                className="h-8 w-48"
                                dir="rtl"
                            />
                        </div>
                        <Button variant="ghost" size="sm" onClick={() => saveEdit(cat.id)} disabled={editForm.processing}>
                            <Check className="h-4 w-4 text-green-600" />
                        </Button>
                        <Button variant="ghost" size="sm" onClick={cancelEdit}>
                            <X className="h-4 w-4 text-red-600" />
                        </Button>
                    </>
                ) : (
                    <>
                        <span className="flex-1 text-sm font-medium">{cat.name_en}</span>
                        {cat.name_ar && (
                            <span className="text-sm text-muted-foreground" dir="rtl">
                                {cat.name_ar}
                            </span>
                        )}
                        {vendorsCount !== undefined && (
                            <Badge variant="secondary">{vendorsCount} {t('table.vendors')}</Badge>
                        )}
                        <Badge variant={cat.is_active ? 'default' : 'outline'}>
                            {cat.is_active ? t('status.active') : t('status.inactive')}
                        </Badge>
                        <Button variant="ghost" size="sm" onClick={() => startEdit(cat)}>
                            <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => setDeleteId(cat.id)}>
                            <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                    </>
                )}
            </div>
        );
    }

    return (
        <>
            <Head title="Categories" />

            <div className="space-y-6">
                <Heading
                    title={t('pages.admin.procurement_categories')}
                    description={t('pages.admin.procurement_categories_description')}
                />

                <div className="rounded-md border">
                    {categories.map((cat) => (
                        <Collapsible
                            key={cat.id}
                            open={openIds.has(cat.id)}
                            onOpenChange={() => toggleOpen(cat.id)}
                        >
                            <div className="flex items-center">
                                {cat.children.length > 0 && (
                                    <CollapsibleTrigger asChild>
                                        <Button variant="ghost" size="sm" className="ml-1 h-8 w-8 p-0">
                                            {openIds.has(cat.id) ? (
                                                <ChevronDown className="h-4 w-4" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </CollapsibleTrigger>
                                )}
                                {cat.children.length === 0 && (
                                    <div className="ml-1 h-8 w-8 flex items-center justify-center">
                                        <FolderTree className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                )}
                                <div className="flex-1">
                                    {renderCategoryRow(cat, 0, cat.vendors_count)}
                                </div>
                            </div>
                            {cat.children.length > 0 && (
                                <CollapsibleContent>
                                    {cat.children.map((child) => (
                                        <div key={child.id} className="flex items-center">
                                            <div className="ml-1 h-8 w-8" />
                                            <div className="flex-1">
                                                {renderCategoryRow(child, 1)}
                                            </div>
                                        </div>
                                    ))}
                                </CollapsibleContent>
                            )}
                        </Collapsible>
                    ))}

                    {categories.length === 0 && (
                        <div className="p-8 text-center text-muted-foreground">
                            {t('empty.no_categories_yet')}
                        </div>
                    )}
                </div>

                {/* Add Category Form */}
                <form onSubmit={handleAdd} className="flex items-end gap-3 rounded-md border p-4">
                    <div className="space-y-2">
                        <Label htmlFor="add-name-en">{t('form.name_english')}</Label>
                        <Input
                            id="add-name-en"
                            value={addForm.data.name_en}
                            onChange={(e) => addForm.setData('name_en', e.target.value)}
                            placeholder={t('form.category_name')}
                        />
                        {addForm.errors.name_en && (
                            <p className="text-sm text-destructive">{addForm.errors.name_en}</p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="add-name-ar">{t('form.name_arabic')}</Label>
                        <Input
                            id="add-name-ar"
                            value={addForm.data.name_ar}
                            onChange={(e) => addForm.setData('name_ar', e.target.value)}
                            placeholder="اسم الفئة"
                            dir="rtl"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('form.parent_category')}</Label>
                        <Select
                            value={addForm.data.parent_id}
                            onValueChange={(value) => addForm.setData('parent_id', value)}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder={t('form.none_root')} />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((cat) => (
                                    <SelectItem key={cat.id} value={cat.id}>
                                        {cat.name_en}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="submit" disabled={addForm.processing}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('btn.add_category')}
                    </Button>
                </form>
            </div>

            <ConfirmDialog
                open={!!deleteId}
                onOpenChange={(open: boolean) => !open && setDeleteId(null)}
                onConfirm={confirmDelete}
                title={t('pages.admin.delete_category')}
                description={t('pages.admin.delete_category_confirm')}
            />
        </>
    );
}
