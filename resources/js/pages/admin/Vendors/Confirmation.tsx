import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
};

type Vendor = {
    id: string;
    company_name: string;
    company_name_ar: string | null;
    trade_license_no: string;
    address: string | null;
    city: string | null;
    country: string | null;
    website: string | null;
    contact_person: string;
    email: string;
    phone: string | null;
    whatsapp_number: string | null;
    prequalification_status: string;
    created_at: string;
    categories?: Category[];
};

type Props = {
    vendor: Vendor;
    companyName: string;
    projectName: string;
    logoUrl: string;
    websiteUrl: string;
    qrCode: string;
    generatedAt: string;
    /** Only set immediately after onboarding — see the controller. */
    temporaryPassword: string | null;
};

/** One label/value row. Values fall back to an em dash so the sheet never shows a blank. */
function Field({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="break-inside-avoid">
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium">{value?.trim() ? value : '—'}</dd>
        </div>
    );
}

/**
 * Intl locale for a given app locale.
 *
 * 'ku' in this app means Sorani Kurdish (lang/ku.json, RTL), but Intl reads a
 * bare 'ku' as Kurmanji and renders Latin script — "17ê tebaxa 2026an" on an
 * otherwise Arabic-script sheet. 'ckb' is the Sorani subtag and gives
 * "١٧ی ئابی ٢٠٢٦".
 */
const DATE_LOCALES: Record<string, string> = {
    en: 'en-GB',
    ku: 'ckb',
};

function formatDate(value: string, locale: string): string {
    return new Date(value).toLocaleDateString(DATE_LOCALES[locale] ?? locale, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Printable application-confirmation sheet handed to a vendor after onboarding.
 *
 * Rendered without the admin shell (see the layout resolver in app.tsx) so it
 * prints as a clean document; the on-screen controls carry `print:hidden`.
 */
export default function Confirmation({
    vendor,
    companyName,
    projectName,
    logoUrl,
    websiteUrl,
    qrCode,
    generatedAt,
    temporaryPassword,
}: Props) {
    const { t, locale } = useTranslation();

    // Short, human-quotable handle. The UUID is unwieldy on a printed page.
    const reference = vendor.id.split('-')[0].toUpperCase();

    return (
        <>
            <Head title={t('pages.admin.vendor_confirmation')} />

            <div className="min-h-screen bg-muted/40 py-8 print:bg-white print:py-0">
                <div className="mx-auto flex max-w-3xl items-center justify-between px-6 pb-6 print:hidden">
                    <Link
                        href="/admin/vendors"
                        className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4 rtl:rotate-180" />
                        {t('btn.back_to_vendors')}
                    </Link>
                    <div className="flex items-center gap-2">
                        {/* Plain anchor, not an Inertia Link: this is a file
                            download, not a page visit. */}
                        <Button asChild variant="outline">
                            <a href={`/admin/vendors/${vendor.id}/confirmation.pdf`}>
                                <Download className="me-2 size-4" />
                                {t('btn.download_pdf')}
                            </a>
                        </Button>
                        <Button onClick={() => window.print()}>
                            <Printer className="me-2 size-4" />
                            {t('btn.print')}
                        </Button>
                    </div>
                </div>

                {/* print-document forces a light palette when printing — see the
                    @media print block in resources/css/app.css. Without it an
                    admin in dark mode prints white text onto white paper. */}
                <article className="print-document mx-auto max-w-3xl bg-background p-10 shadow-sm print:max-w-none print:p-0 print:shadow-none">
                    <header className="flex items-start justify-between gap-6 border-b pb-6">
                        <div className="flex items-center gap-4">
                            <img
                                src={logoUrl}
                                alt={projectName || companyName}
                                className="size-16 object-contain"
                            />
                            <div>
                                {projectName && (
                                    <p className="text-xl font-bold leading-tight">{projectName}</p>
                                )}
                                <p className="text-sm text-muted-foreground">{companyName}</p>
                                <h1 className="mt-1 text-xs uppercase tracking-wide text-muted-foreground">
                                    {t('pages.admin.vendor_confirmation')}
                                </h1>
                            </div>
                        </div>
                        <dl className="text-end">
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                                {t('confirmation.reference')}
                            </dt>
                            <dd className="font-mono text-sm font-semibold">{reference}</dd>
                            <dt className="mt-2 text-xs uppercase tracking-wide text-muted-foreground">
                                {t('confirmation.issued_on')}
                            </dt>
                            <dd className="text-sm">{formatDate(generatedAt, locale)}</dd>
                        </dl>
                    </header>

                    <section className="mt-8">
                        <h2 className="mb-3 text-sm font-semibold">{t('vendor.company_information')}</h2>
                        <dl className="grid grid-cols-2 gap-x-8 gap-y-4">
                            <Field label={t('form.company_name')} value={vendor.company_name} />
                            <Field label={t('form.company_name_ar')} value={vendor.company_name_ar} />
                            <Field label={t('form.trade_license_no')} value={vendor.trade_license_no} />
                            <Field label={t('form.website')} value={vendor.website} />
                            <Field label={t('form.address')} value={vendor.address} />
                            <Field
                                label={t('form.city')}
                                value={[vendor.city, vendor.country].filter(Boolean).join(', ')}
                            />
                        </dl>
                    </section>

                    <section className="mt-8">
                        <h2 className="mb-3 text-sm font-semibold">{t('vendor.contact_person')}</h2>
                        <dl className="grid grid-cols-2 gap-x-8 gap-y-4">
                            <Field label={t('form.contact_person')} value={vendor.contact_person} />
                            <Field label={t('form.email')} value={vendor.email} />
                            <Field label={t('form.phone')} value={vendor.phone} />
                            <Field label={t('form.whatsapp_number')} value={vendor.whatsapp_number} />
                        </dl>
                    </section>

                    <section className="mt-8">
                        <h2 className="mb-3 text-sm font-semibold">{t('pages.vendor.business_categories')}</h2>
                        {vendor.categories && vendor.categories.length > 0 ? (
                            <ul className="flex flex-wrap gap-2">
                                {vendor.categories.map((category) => (
                                    <li
                                        key={category.id}
                                        className="rounded-md border px-2.5 py-1 text-sm"
                                    >
                                        {locale === 'ar' && category.name_ar
                                            ? category.name_ar
                                            : category.name_en}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">—</p>
                        )}
                    </section>

                    {/* Shown only right after onboarding. The password is stored
                        bcrypt-hashed, so it cannot be recovered for a later
                        reprint — the admin reissues one from the vendor detail
                        page instead. */}
                    {temporaryPassword && (
                        <section className="mt-8 rounded-md border border-primary/40 bg-primary/5 p-4">
                            <h2 className="text-xs uppercase tracking-wide text-muted-foreground">
                                {t('confirmation.portal_sign_in')}
                            </h2>
                            <dl className="mt-2 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                                        {t('form.email')}
                                    </dt>
                                    <dd className="font-mono text-sm font-medium">{vendor.email}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                                        {t('confirmation.temporary_password')}
                                    </dt>
                                    <dd className="font-mono text-lg font-bold tracking-wider">
                                        {temporaryPassword}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-3 text-xs leading-relaxed text-muted-foreground">
                                {t('confirmation.password_note')}
                            </p>
                        </section>
                    )}

                    <section className="mt-8 flex items-end justify-between gap-8 border-t pt-6">
                        <div>
                            <h2 className="mb-3 text-sm font-semibold">
                                {t('confirmation.application_status')}
                            </h2>
                            <StatusBadge status={vendor.prequalification_status} />
                            <p className="mt-3 max-w-sm text-xs leading-relaxed text-muted-foreground">
                                {t('confirmation.footer_note')}
                            </p>
                        </div>

                        <figure className="shrink-0 text-center">
                            <img
                                src={qrCode}
                                alt={t('confirmation.scan_hint')}
                                className="size-32"
                                width={128}
                                height={128}
                            />
                            <figcaption className="mt-2 max-w-32 text-[11px] leading-tight text-muted-foreground">
                                {t('confirmation.scan_hint')}
                                <span className="mt-1 block break-all font-mono">{websiteUrl}</span>
                            </figcaption>
                        </figure>
                    </section>
                </article>
            </div>
        </>
    );
}
