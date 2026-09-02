import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Clock,
    Download,
    FileText,
    Layers,
    MapPin,
    MessageCircle,
    Plus,
    Send,
} from 'lucide-react';
import type {FormEvent} from 'react';
import { FileSize } from '@/components/FileSize';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate, formatDeadline } from '@/lib/datetime';
import { DEADLINE_TONE_CLASS, deadlineStatus } from '@/lib/deadline';
import { localized } from '@/lib/locales';
import { formatQuantity } from '@/lib/money';
import { cn } from '@/lib/utils';

type BoqItem = {
    id: string;
    item_code: string;
    description_en: string;
    description_ar: string | null;
    unit: string;
    quantity: string;
};

type Props = {
    /**
     * Hand-projected by the controller. It deliberately carries no
     * `estimated_value` or `notes_internal`: those are the employer's own
     * budget figure and the commentary behind it, and this page used to
     * render the estimate under an "Estimated Value" heading — to the very
     * bidders pricing against it.
     */
    tender: {
        id: string;
        reference_number: string;
        title_en: string;
        title_ar: string | null;
        description_en: string | null;
        description_ar: string | null;
        tender_type_label_key: string;
        status: string;
        currency: string;
        publish_date: string | null;
        submission_deadline: string;
        opening_date: string;
        is_two_envelope: boolean;
        requires_site_visit: boolean;
        site_visit_date: string | null;
        project: { id: string; name: string; name_ar: string | null } | null;
        categories: Array<{ id: string; name_en: string; name_ar: string | null }>;
        boq_sections: Array<{
            id: string;
            title: string;
            title_ar: string | null;
            items: BoqItem[];
        }>;
        documents: Array<{
            id: string;
            title: string;
            doc_type_label_key: string;
            file_size: number;
            version: number;
            download_url: string;
        }>;
        addenda: Array<{
            id: string;
            addendum_number: number;
            subject: string;
            content_en: string;
            content_ar: string | null;
            extends_deadline: boolean;
            new_deadline: string | null;
            published_at: string | null;
        }>;
        clarifications: Array<{
            id: string;
            question: string;
            answer: string | null;
            asked_at: string | null;
            answered_at: string | null;
        }>;
    };
    canBid: boolean;
    canAskClarification: boolean;
    existingBid: { id: string; status: string; is_editable: boolean } | null;
};

/**
 * A tender as the bidder sees it: scope, documents, amendments and the Q&A,
 * with the route into their own bid.
 */
export default function Show({ tender, canBid, canAskClarification, existingBid }: Props) {
    const { t, locale } = useTranslation();
    const clarificationForm = useForm({ question: '' });

    const title = localized(locale, tender.title_en, tender.title_ar);
    const description = localized(locale, tender.description_en, tender.description_ar);
    const deadline = deadlineStatus(tender.submission_deadline);

    function submitClarification(e: FormEvent) {
        e.preventDefault();
        clarificationForm.post(`/vendor/tenders/${tender.id}/clarifications`, {
            preserveScroll: true,
            onSuccess: () => clarificationForm.reset('question'),
        });
    }

    return (
        <>
            <Head title={title} />

            <div className="space-y-6 p-4 md:p-6">
                <Button asChild variant="ghost" size="sm" className="-ms-2">
                    <Link href="/vendor/tenders">
                        {/* Logical margin plus a flip: the arrow points back
                            along the reading direction, which reverses in RTL. */}
                        <ArrowLeft className="me-1 size-4 rtl:rotate-180" />
                        {t('btn.back_to_tenders')}
                    </Link>
                </Button>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="font-mono text-sm text-muted-foreground">
                            {tender.reference_number}
                        </p>
                        <Heading title={title} />
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={tender.status} />
                            <Badge variant="secondary">
                                {t(
                                    tender.is_two_envelope
                                        ? 'tender.two_envelope'
                                        : 'tender.single_envelope',
                                )}
                            </Badge>
                        </div>
                    </div>

                    <div className="flex shrink-0 gap-2">
                        {canBid && !existingBid && (
                            <Button asChild>
                                <Link href={`/vendor/tenders/${tender.id}/bid`}>
                                    <Plus className="me-1 size-4" />
                                    {t('btn.start_bid')}
                                </Link>
                            </Button>
                        )}
                        {existingBid && (
                            <Button asChild variant="outline">
                                <Link href={`/vendor/bids/${existingBid.id}`}>
                                    {/* "Continue Bid" is only meaningful for an
                                        unsubmitted draft. Every other status is
                                        terminal from the vendor's side, so the
                                        button reads "View Bid". */}
                                    {t(
                                        existingBid.is_editable
                                            ? 'vendor.tender.continue_bid'
                                            : 'vendor.tender.view_bid',
                                    )}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('tender.overview')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {tender.project && (
                                <Field label={t('tender.project')}>
                                    {localized(locale, tender.project.name, tender.project.name_ar)}
                                </Field>
                            )}
                            <Field label={t('tender.tender_type')}>
                                {t(tender.tender_type_label_key)}
                            </Field>
                            <Field label={t('tender.currency')}>{tender.currency}</Field>
                            <Field label={t('tender.submission_deadline')}>
                                <span className="flex items-center gap-1.5">
                                    <CalendarClock
                                        className="size-3.5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {formatDeadline(tender.submission_deadline, locale)}
                                </span>
                                {deadline && (
                                    <span
                                        className={cn(
                                            'mt-0.5 block text-xs',
                                            DEADLINE_TONE_CLASS[deadline.tone],
                                        )}
                                    >
                                        {t(deadline.labelKey, { count: deadline.days })}
                                    </span>
                                )}
                            </Field>
                            <Field label={t('tender.opening_date')}>
                                <span className="flex items-center gap-1.5">
                                    <Clock
                                        className="size-3.5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {formatDeadline(tender.opening_date, locale)}
                                </span>
                            </Field>
                            <Field label={t('tender.site_visit')}>
                                <span className="flex items-center gap-1.5">
                                    <MapPin
                                        className="size-3.5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {tender.requires_site_visit
                                        ? tender.site_visit_date
                                            ? formatDeadline(tender.site_visit_date, locale)
                                            : t('tender.site_visit_required')
                                        : t('tender.site_visit_not_required')}
                                </span>
                            </Field>
                        </dl>

                        {tender.categories.length > 0 && (
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('tender.categories')}
                                </p>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {tender.categories.map((category) => (
                                        <Badge key={category.id} variant="secondary">
                                            {localized(locale, category.name_en, category.name_ar)}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {description && (
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('tender.description')}
                                </p>
                                <p className="mt-1 whitespace-pre-line text-sm">{description}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* BOQ — read-only here; pricing happens on the bid page. */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Layers className="size-5" aria-hidden="true" />
                            {t('tender.bill_of_quantities')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {tender.boq_sections.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t('tender.no_boq_published')}
                            </p>
                        ) : (
                            tender.boq_sections.map((section) => (
                                <div key={section.id}>
                                    <h3 className="mb-2 font-medium">
                                        {localized(locale, section.title, section.title_ar)}
                                    </h3>

                                    {/* Four columns of BOQ do not fit a phone. */}
                                    <ul className="space-y-2 md:hidden">
                                        {section.items.map((item) => (
                                            <li key={item.id} className="rounded-lg border p-3">
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {item.item_code}
                                                </p>
                                                <p className="mt-1 text-sm">
                                                    {localized(
                                                        locale,
                                                        item.description_en,
                                                        item.description_ar,
                                                    )}
                                                </p>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {/* bdi: a bare "12.5 / m³" gets its
                                                        two sides swapped under dir="rtl",
                                                        so the quantity reads as the unit
                                                        and back to front. */}
                                                    <bdi className="tabular-nums">
                                                        {formatQuantity(item.quantity, locale)}
                                                    </bdi>{' '}
                                                    <bdi>{item.unit}</bdi>
                                                </p>
                                            </li>
                                        ))}
                                    </ul>

                                    <div className="hidden overflow-x-auto md:block">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-start">
                                                    <th className="px-3 py-2 text-start font-medium text-muted-foreground">
                                                        {t('table.code')}
                                                    </th>
                                                    <th className="px-3 py-2 text-start font-medium text-muted-foreground">
                                                        {t('table.description')}
                                                    </th>
                                                    <th className="px-3 py-2 text-start font-medium text-muted-foreground">
                                                        {t('table.unit')}
                                                    </th>
                                                    <th className="px-3 py-2 text-end font-medium text-muted-foreground">
                                                        {t('table.quantity')}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {section.items.map((item) => (
                                                    <tr key={item.id} className="border-b">
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {item.item_code}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {localized(
                                                                locale,
                                                                item.description_en,
                                                                item.description_ar,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2">{item.unit}</td>
                                                        <td className="px-3 py-2 text-end tabular-nums">
                                                            {formatQuantity(item.quantity, locale)}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {tender.documents.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="size-5" aria-hidden="true" />
                                {t('tender.documents')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {tender.documents.map((doc) => (
                                    <li
                                        key={doc.id}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {doc.title}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {t(doc.doc_type_label_key)} &middot;{' '}
                                                <FileSize bytes={doc.file_size} />{' '}
                                                &middot;{' '}
                                                {t('tender.document_version', { number: doc.version })}
                                            </p>
                                        </div>
                                        {/* The route this points at did not exist
                                            until now: every Download button on this
                                            page was a 404, so a vendor could not
                                            obtain the documents they bid against. */}
                                        <Button asChild variant="outline" size="sm">
                                            <a href={doc.download_url}>
                                                <Download className="me-1 size-4" />
                                                {t('btn.download')}
                                            </a>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {tender.addenda.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tender.addenda')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {tender.addenda.map((addendum) => (
                                <div key={addendum.id} className="rounded-lg border p-4">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <h3 className="font-medium">
                                            {t('tender.addendum_number', {
                                                number: addendum.addendum_number,
                                            })}
                                            {': '}
                                            {addendum.subject}
                                        </h3>
                                        {addendum.published_at && (
                                            <span className="text-xs text-muted-foreground">
                                                {formatDate(addendum.published_at, locale)}
                                            </span>
                                        )}
                                    </div>
                                    {addendum.extends_deadline && (
                                        <p className="mt-1 text-xs font-medium text-amber-600 dark:text-amber-500">
                                            {t('tender.addendum_extends_deadline')}
                                            {addendum.new_deadline && (
                                                <> {formatDeadline(addendum.new_deadline, locale)}</>
                                            )}
                                        </p>
                                    )}
                                    <p className="mt-2 whitespace-pre-line text-sm">
                                        {localized(
                                            locale,
                                            addendum.content_en,
                                            addendum.content_ar,
                                        )}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MessageCircle className="size-5" aria-hidden="true" />
                            {t('tender.clarifications')}
                        </CardTitle>
                        <CardDescription>{t('tender.ask_questions_about_tender')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {tender.clarifications.length > 0 ? (
                            <ul className="space-y-3">
                                {tender.clarifications.map((clarification) => (
                                    <li key={clarification.id} className="rounded-lg border p-4">
                                        <p className="text-sm font-medium">
                                            <span className="text-muted-foreground">
                                                {t('tender.question')}:{' '}
                                            </span>
                                            {clarification.question}
                                        </p>
                                        {clarification.asked_at && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {t('tender.asked_on', {
                                                    date: formatDate(clarification.asked_at, locale),
                                                })}
                                            </p>
                                        )}
                                        {clarification.answer ? (
                                            <p className="mt-2 text-sm">
                                                <span className="font-medium text-muted-foreground">
                                                    {t('tender.answer')}:{' '}
                                                </span>
                                                {clarification.answer}
                                            </p>
                                        ) : (
                                            <p className="mt-2 text-sm italic text-muted-foreground">
                                                {t('tender.awaiting_response')}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('empty.no_clarifications')}
                            </p>
                        )}

                        {canAskClarification ? (
                            <form onSubmit={submitClarification} className="space-y-3 border-t pt-4">
                                <Label htmlFor="question">{t('tender.ask_a_question')}</Label>
                                <textarea
                                    id="question"
                                    className="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    placeholder={t('tender.question_placeholder')}
                                    value={clarificationForm.data.question}
                                    onChange={(e) =>
                                        clarificationForm.setData('question', e.target.value)
                                    }
                                />
                                {clarificationForm.errors.question && (
                                    <p className="text-sm text-destructive">
                                        {clarificationForm.errors.question}
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    disabled={
                                        clarificationForm.processing ||
                                        !clarificationForm.data.question.trim()
                                    }
                                >
                                    <Send className="me-1 size-4" />
                                    {t('btn.submit_question')}
                                </Button>
                            </form>
                        ) : (
                            // The form used to render past the deadline too, and
                            // posting it just failed.
                            <p className="border-t pt-4 text-sm text-muted-foreground">
                                {t('tender.clarifications_closed')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
            <dd className="mt-1 text-sm">{children}</dd>
        </div>
    );
}
