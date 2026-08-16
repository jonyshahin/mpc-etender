import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { MultiSelect } from '@/components/MultiSelect';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

export type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string | null;
    children?: Category[];
};

type Props = {
    categories: Category[];
    open: boolean;
    onClose: () => void;
};

/** Arabic name when reading Arabic, else the English one. There is no ku column. */
function categoryName(category: Category, locale: string): string {
    return locale === 'ar' ? (category.name_ar ?? category.name_en) : category.name_en;
}

/**
 * Flatten the category tree into MultiSelect options, qualifying children with
 * their parent name so sibling categories with the same leaf name stay
 * distinguishable. Parents remain selectable, matching the old vendor
 * self-registration wizard.
 */
function toOptions(categories: Category[], locale: string): Array<{ value: string; label: string }> {
    return categories.flatMap((parent) => [
        { value: parent.id, label: categoryName(parent, locale) },
        ...(parent.children ?? []).map((child) => ({
            value: child.id,
            label: `${categoryName(parent, locale)} › ${categoryName(child, locale)}`,
        })),
    ]);
}

/**
 * Admin-side vendor onboarding form.
 *
 * Deliberately has no password field: the server generates a temporary
 * password and surfaces it once on the vendor detail page after redirect.
 */
function VendorForm({ categories, onClose }: Omit<Props, 'open'>) {
    const { t, locale } = useTranslation();

    const form = useForm({
        company_name: '',
        company_name_ar: '',
        trade_license_no: '',
        address: '',
        city: '',
        country: '',
        website: '',
        contact_person: '',
        email: '',
        phone: '',
        whatsapp_number: '',
        language_pref: 'en',
        category_ids: [] as string[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post('/admin/vendors', {
            onSuccess: () => onClose(),
        });
    };

    const error = (field: keyof typeof form.errors) =>
        form.errors[field] ? (
            <p className="text-sm text-destructive">{form.errors[field]}</p>
        ) : null;

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="company_name">{t('form.company_name')} *</Label>
                    <Input
                        id="company_name"
                        value={form.data.company_name}
                        onChange={(e) => form.setData('company_name', e.target.value)}
                    />
                    {error('company_name')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="company_name_ar">{t('form.company_name_ar')}</Label>
                    <Input
                        id="company_name_ar"
                        dir="rtl"
                        value={form.data.company_name_ar}
                        onChange={(e) => form.setData('company_name_ar', e.target.value)}
                    />
                    {error('company_name_ar')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="trade_license_no">{t('form.trade_license_no')} *</Label>
                    <Input
                        id="trade_license_no"
                        value={form.data.trade_license_no}
                        onChange={(e) => form.setData('trade_license_no', e.target.value)}
                    />
                    {error('trade_license_no')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="website">{t('form.website')}</Label>
                    <Input
                        id="website"
                        type="url"
                        value={form.data.website}
                        onChange={(e) => form.setData('website', e.target.value)}
                    />
                    {error('website')}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="address">{t('form.address')} *</Label>
                <Input
                    id="address"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                />
                {error('address')}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="city">{t('form.city')} *</Label>
                    <Input
                        id="city"
                        value={form.data.city}
                        onChange={(e) => form.setData('city', e.target.value)}
                    />
                    {error('city')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="country">{t('form.country')} *</Label>
                    <Input
                        id="country"
                        value={form.data.country}
                        onChange={(e) => form.setData('country', e.target.value)}
                    />
                    {error('country')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="contact_person">{t('form.contact_person')} *</Label>
                    <Input
                        id="contact_person"
                        value={form.data.contact_person}
                        onChange={(e) => form.setData('contact_person', e.target.value)}
                    />
                    {error('contact_person')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="email">{t('form.email')} *</Label>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                    {error('email')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="phone">{t('form.phone')} *</Label>
                    <Input
                        id="phone"
                        type="tel"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                    />
                    {error('phone')}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="whatsapp_number">{t('form.whatsapp_number')}</Label>
                    <Input
                        id="whatsapp_number"
                        type="tel"
                        value={form.data.whatsapp_number}
                        onChange={(e) => form.setData('whatsapp_number', e.target.value)}
                    />
                    {error('whatsapp_number')}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="language_pref">{t('form.language')}</Label>
                <Select
                    value={form.data.language_pref}
                    onValueChange={(value) => form.setData('language_pref', value)}
                >
                    <SelectTrigger id="language_pref">
                        <SelectValue placeholder={t('form.select_language')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="en">{t('form.english')}</SelectItem>
                        <SelectItem value="ar">{t('form.arabic')}</SelectItem>
                        <SelectItem value="ku">{t('form.kurdish')}</SelectItem>
                    </SelectContent>
                </Select>
                {error('language_pref')}
            </div>

            <div className="space-y-2">
                <Label>{t('pages.vendor.business_categories')} *</Label>
                <MultiSelect
                    options={toOptions(categories, locale)}
                    value={form.data.category_ids}
                    onChange={(value: string[]) => form.setData('category_ids', value)}
                    placeholder={t('form.select_categories')}
                />
                {error('category_ids')}
            </div>

            <div className="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onClick={onClose}>
                    {t('btn.cancel')}
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? t('btn.submitting') : t('btn.create_vendor')}
                </Button>
            </div>
        </form>
    );
}

export function VendorFormDialog({ categories, open, onClose }: Props) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('pages.admin.create_vendor')}</DialogTitle>
                    <DialogDescription>
                        {t('pages.admin.create_vendor_description')}
                    </DialogDescription>
                </DialogHeader>
                <VendorForm categories={categories} onClose={onClose} />
            </DialogContent>
        </Dialog>
    );
}

export default VendorFormDialog;
