import { Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { KeyRound, Save } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

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
};

export default function Profile({ vendor }: Props) {
    const { t } = useTranslation();
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
        form.put('/vendor/profile');
    };

    return (
        <>
            <Head title="Company Profile" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={t('pages.vendor.company_profile')} description={t('vendor.update_company_info')} />
                    <Link
                        href="/vendor/password/change"
                        className="inline-flex items-center gap-2 text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        <KeyRound className="h-4 w-4" />
                        {t('btn.change_password')}
                    </Link>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Company Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('vendor.company_information')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="company_name">{t('form.company_name')}</Label>
                                    <Input
                                        id="company_name"
                                        value={form.data.company_name}
                                        onChange={(e) => form.setData('company_name', e.target.value)}
                                    />
                                    {form.errors.company_name && (
                                        <p className="text-sm text-destructive">{form.errors.company_name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="company_name_ar">{t('form.company_name_ar')}</Label>
                                    <Input
                                        id="company_name_ar"
                                        dir="rtl"
                                        value={form.data.company_name_ar}
                                        onChange={(e) => form.setData('company_name_ar', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="trade_license_no">{t('form.trade_license_no')}</Label>
                                    <Input
                                        id="trade_license_no"
                                        value={form.data.trade_license_no}
                                        onChange={(e) => form.setData('trade_license_no', e.target.value)}
                                    />
                                    {form.errors.trade_license_no && (
                                        <p className="text-sm text-destructive">{form.errors.trade_license_no}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="website">{t('form.website')}</Label>
                                    <Input
                                        id="website"
                                        type="url"
                                        value={form.data.website}
                                        onChange={(e) => form.setData('website', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="address">{t('form.address')}</Label>
                                    <Input
                                        id="address"
                                        value={form.data.address}
                                        onChange={(e) => form.setData('address', e.target.value)}
                                    />
                                    {form.errors.address && (
                                        <p className="text-sm text-destructive">{form.errors.address}</p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="city">{t('form.city')}</Label>
                                        <Input
                                            id="city"
                                            value={form.data.city}
                                            onChange={(e) => form.setData('city', e.target.value)}
                                        />
                                        {form.errors.city && (
                                            <p className="text-sm text-destructive">{form.errors.city}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="country">{t('form.country')}</Label>
                                        <Input
                                            id="country"
                                            value={form.data.country}
                                            onChange={(e) => form.setData('country', e.target.value)}
                                        />
                                        {form.errors.country && (
                                            <p className="text-sm text-destructive">{form.errors.country}</p>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Contact Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('vendor.contact_information')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="contact_person">{t('form.contact_person')}</Label>
                                    <Input
                                        id="contact_person"
                                        value={form.data.contact_person}
                                        onChange={(e) => form.setData('contact_person', e.target.value)}
                                    />
                                    {form.errors.contact_person && (
                                        <p className="text-sm text-destructive">{form.errors.contact_person}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email">{t('form.email')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                    />
                                    {form.errors.email && (
                                        <p className="text-sm text-destructive">{form.errors.email}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="phone">{t('form.phone')}</Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={form.data.phone}
                                        onChange={(e) => form.setData('phone', e.target.value)}
                                    />
                                    {form.errors.phone && (
                                        <p className="text-sm text-destructive">{form.errors.phone}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="whatsapp_number">{t('form.whatsapp_number')}</Label>
                                    <Input
                                        id="whatsapp_number"
                                        type="tel"
                                        value={form.data.whatsapp_number}
                                        onChange={(e) => form.setData('whatsapp_number', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="language_pref">{t('form.language_preference')}</Label>
                                    <Select
                                        value={form.data.language_pref}
                                        onValueChange={(value) => form.setData('language_pref', value)}
                                    >
                                        <SelectTrigger id="language_pref">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="en">{t('form.english')}</SelectItem>
                                            <SelectItem value="ar">{t('form.arabic')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-6 flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {form.processing ? t('btn.saving') : t('btn.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
