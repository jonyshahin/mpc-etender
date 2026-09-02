import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, KeyRound, Save, UserRound } from 'lucide-react';
import { StatusBadge } from '@/components/StatusBadge';
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
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/datetime';

type Props = {
    vendor: {
        id: string;
        company_name: string;
        company_name_ar: string | null;
        trade_license_no: string;
        contact_person: string;
        email: string;
        phone: string;
        whatsapp_number: string | null;
        address: string;
        city: string;
        country: string;
        website: string | null;
        language_pref: string;
    };
    /**
     * Read-only. Kept separate from `vendor` so the shape the form posts back
     * stays exactly the shape the form owns — the page used to receive the
     * whole model, including the reviewer's internal rejection note.
     */
    standing: {
        prequalification_status: string;
        qualified_at: string | null;
    };
};

/**
 * Offered languages, mirroring VendorProfileUpdateRequest's `in:en,ar,ku`.
 * Kurdish is here because admins can onboard a vendor as Kurdish, and dropping
 * it from the picker would silently reset their preference on the next save.
 */
const LANGUAGES = [
    { value: 'en', labelKey: 'form.english' },
    { value: 'ar', labelKey: 'form.arabic' },
    { value: 'ku', labelKey: 'form.kurdish' },
] as const;

export default function Profile({ vendor, standing }: Props) {
    const { t, locale } = useTranslation();

    const form = useForm({
        company_name: vendor.company_name,
        company_name_ar: vendor.company_name_ar ?? '',
        trade_license_no: vendor.trade_license_no,
        contact_person: vendor.contact_person,
        email: vendor.email,
        phone: vendor.phone,
        whatsapp_number: vendor.whatsapp_number ?? '',
        address: vendor.address,
        city: vendor.city,
        country: vendor.country,
        website: vendor.website ?? '',
        language_pref: vendor.language_pref,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/vendor/profile', { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('pages.vendor.company_profile')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.vendor.company_profile')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('vendor.update_company_info')}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/vendor/password/change">
                            <KeyRound className="me-2 size-4" />
                            {t('btn.change_password')}
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-center justify-between gap-3 p-5">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                                {t('vendor.prequalification_status')}
                            </span>
                            <StatusBadge status={standing.prequalification_status} />
                        </div>
                        {standing.qualified_at && (
                            <p className="text-sm text-muted-foreground">
                                {t('vendor.qualified_on')} {formatDate(standing.qualified_at, locale)}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Building2 className="size-4" aria-hidden="true" />
                                    {t('vendor.company_information')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Field
                                    id="company_name"
                                    label={t('form.company_name')}
                                    value={form.data.company_name}
                                    error={form.errors.company_name}
                                    onChange={(v) => form.setData('company_name', v)}
                                    required
                                />
                                <Field
                                    id="company_name_ar"
                                    label={t('form.company_name_ar')}
                                    value={form.data.company_name_ar}
                                    error={form.errors.company_name_ar}
                                    onChange={(v) => form.setData('company_name_ar', v)}
                                    dir="rtl"
                                />
                                <Field
                                    id="trade_license_no"
                                    label={t('form.trade_license_no')}
                                    value={form.data.trade_license_no}
                                    error={form.errors.trade_license_no}
                                    onChange={(v) => form.setData('trade_license_no', v)}
                                    required
                                />
                                <Field
                                    id="website"
                                    type="url"
                                    label={t('form.website')}
                                    value={form.data.website}
                                    error={form.errors.website}
                                    onChange={(v) => form.setData('website', v)}
                                    placeholder="https://"
                                />
                                <Field
                                    id="address"
                                    label={t('form.address')}
                                    value={form.data.address}
                                    error={form.errors.address}
                                    onChange={(v) => form.setData('address', v)}
                                    required
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        id="city"
                                        label={t('form.city')}
                                        value={form.data.city}
                                        error={form.errors.city}
                                        onChange={(v) => form.setData('city', v)}
                                        required
                                    />
                                    <Field
                                        id="country"
                                        label={t('form.country')}
                                        value={form.data.country}
                                        error={form.errors.country}
                                        onChange={(v) => form.setData('country', v)}
                                        required
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <UserRound className="size-4" aria-hidden="true" />
                                    {t('vendor.contact_information')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Field
                                    id="contact_person"
                                    label={t('form.contact_person')}
                                    value={form.data.contact_person}
                                    error={form.errors.contact_person}
                                    onChange={(v) => form.setData('contact_person', v)}
                                    required
                                />
                                <Field
                                    id="email"
                                    type="email"
                                    label={t('form.email')}
                                    value={form.data.email}
                                    error={form.errors.email}
                                    onChange={(v) => form.setData('email', v)}
                                    hint={t('vendor.email_is_your_login')}
                                    required
                                />
                                <Field
                                    id="phone"
                                    type="tel"
                                    label={t('form.phone')}
                                    value={form.data.phone}
                                    error={form.errors.phone}
                                    onChange={(v) => form.setData('phone', v)}
                                    dir="ltr"
                                    required
                                />
                                <Field
                                    id="whatsapp_number"
                                    type="tel"
                                    label={t('form.whatsapp_number')}
                                    value={form.data.whatsapp_number}
                                    error={form.errors.whatsapp_number}
                                    onChange={(v) => form.setData('whatsapp_number', v)}
                                    dir="ltr"
                                />

                                <div className="space-y-2">
                                    <Label htmlFor="language_pref">
                                        {t('form.language_preference')}
                                    </Label>
                                    <Select
                                        value={form.data.language_pref}
                                        onValueChange={(value) =>
                                            form.setData('language_pref', value)
                                        }
                                    >
                                        <SelectTrigger id="language_pref">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {LANGUAGES.map((language) => (
                                                <SelectItem
                                                    key={language.value}
                                                    value={language.value}
                                                >
                                                    {t(language.labelKey)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        {t('vendor.language_pref_hint')}
                                    </p>
                                    {form.errors.language_pref && (
                                        <p role="alert" className="text-sm text-destructive">
                                            {form.errors.language_pref}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        {form.isDirty && !form.processing && (
                            <p className="text-sm text-muted-foreground">
                                {t('form.unsaved_changes')}
                            </p>
                        )}
                        <Button type="submit" disabled={form.processing || !form.isDirty}>
                            <Save className="me-2 size-4" aria-hidden="true" />
                            {form.processing ? t('btn.saving') : t('btn.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function Field({
    id,
    label,
    value,
    error,
    onChange,
    type = 'text',
    dir,
    hint,
    placeholder,
    required = false,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: string;
    dir?: 'rtl' | 'ltr';
    hint?: string;
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type={type}
                dir={dir}
                value={value}
                required={required}
                placeholder={placeholder}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-error` : hint ? `${id}-hint` : undefined}
                onChange={(e) => onChange(e.target.value)}
            />
            {hint && !error && (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${id}-error`} role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
