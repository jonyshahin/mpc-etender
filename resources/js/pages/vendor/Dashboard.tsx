import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, AlertCircle, Clock, FileText, Send } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    vendor: {
        id: string;
        company_name: string;
        prequalification_status: string;
        qualified_at: string | null;
    };
    documentWarnings: Array<{ id: string; title: string; expiry_date: string }>;
    expiredDocuments: Array<{ id: string; title: string; expiry_date: string }>;
    openTenders: Array<{ id: string; title_en: string; title_ar: string | null; reference_number: string; submission_deadline: string }>;
    submittedBids: Array<{
        id: string;
        tender_id: string;
        status: string;
        submitted_at: string | null;
        tender?: { id: string; title_en: string; title_ar: string | null; reference_number: string };
    }>;
};

function formatDeadline(deadline: string): string {
    const now = new Date();
    const target = new Date(deadline);
    const diffMs = target.getTime() - now.getTime();

    if (diffMs < 0) return 'Expired';

    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

    if (days > 0) return `${days}d ${hours}h remaining`;
    return `${hours}h remaining`;
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function Dashboard({ vendor, documentWarnings, expiredDocuments, openTenders, submittedBids }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title="Vendor Dashboard" />

            <div className="space-y-6">
                <Heading title={`${t('vendor.welcome')}, ${vendor.company_name}`} description={t('vendor.dashboard_description')} />

                {/* Prequalification Status */}
                <Card>
                    <CardContent className="flex items-center justify-between p-6">
                        <div>
                            <h2 className="text-lg font-semibold">{t('vendor.prequalification_status')}</h2>
                            {vendor.qualified_at && (
                                <p className="text-sm text-muted-foreground">
                                    {t('vendor.qualified_on')} {formatDate(vendor.qualified_at)}
                                </p>
                            )}
                        </div>
                        <StatusBadge status={vendor.prequalification_status} />
                    </CardContent>
                </Card>

                {/* Status Alerts */}
                {(vendor.prequalification_status === 'pending' || vendor.prequalification_status === 'rejected') && (
                    <div className="rounded-lg border border-amber-500 bg-amber-50 p-4 dark:bg-amber-950/20">
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-amber-600" />
                            <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
                                {vendor.prequalification_status === 'pending'
                                    ? t('vendor.prequalification_pending_message')
                                    : t('vendor.prequalification_rejected_message')}
                            </p>
                        </div>
                    </div>
                )}

                {/* Document Warnings */}
                {documentWarnings.length > 0 && (
                    <div className="rounded-lg border border-amber-500 bg-amber-50 p-4 dark:bg-amber-950/20">
                        <div className="flex items-center gap-2 mb-2">
                            <AlertTriangle className="h-5 w-5 text-amber-600" />
                            <h3 className="text-sm font-semibold text-amber-800 dark:text-amber-200">
                                {t('vendor.documents_expiring_soon')}
                            </h3>
                        </div>
                        <ul className="space-y-1">
                            {documentWarnings.map((doc) => (
                                <li key={doc.id} className="text-sm text-amber-700 dark:text-amber-300">
                                    {doc.title} - {t('vendor.expires')} {formatDate(doc.expiry_date)}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {expiredDocuments.length > 0 && (
                    <div className="rounded-lg border border-destructive bg-destructive/10 p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <AlertCircle className="h-5 w-5 text-destructive" />
                            <h3 className="text-sm font-semibold text-destructive">{t('vendor.expired_documents')}</h3>
                        </div>
                        <ul className="space-y-1">
                            {expiredDocuments.map((doc) => (
                                <li key={doc.id} className="text-sm text-destructive">
                                    {doc.title} - {t('vendor.expired')} {formatDate(doc.expiry_date)}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Open Tenders */}
                <div>
                    <h2 className="mb-3 text-lg font-semibold flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        {t('vendor.open_tenders')}
                    </h2>
                    {openTenders.length === 0 ? (
                        <Card>
                            <CardContent className="p-6 text-center text-muted-foreground">
                                {t('empty.no_open_tenders')}
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {openTenders.map((tender) => (
                                <Card key={tender.id}>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-base">{tender.title_en}</CardTitle>
                                        <CardDescription>{tender.reference_number}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex items-center gap-2 text-sm">
                                            <Clock className="h-4 w-4 text-muted-foreground" />
                                            <span className={
                                                new Date(tender.submission_deadline) < new Date()
                                                    ? 'text-destructive'
                                                    : 'text-muted-foreground'
                                            }>
                                                {formatDeadline(tender.submission_deadline)}
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                {/* Submitted Bids */}
                <div>
                    <h2 className="mb-3 text-lg font-semibold flex items-center gap-2">
                        <Send className="h-5 w-5" />
                        {t('vendor.recent_bids')}
                    </h2>
                    {submittedBids.length === 0 ? (
                        <Card>
                            <CardContent className="p-6 text-center text-muted-foreground">
                                {t('empty.no_bids_submitted')}
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">{t('table.tender')}</th>
                                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">{t('table.reference')}</th>
                                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">{t('table.status')}</th>
                                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">{t('table.submitted')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {submittedBids.map((bid) => (
                                                <tr key={bid.id} className="border-b last:border-0">
                                                    <td className="px-4 py-3">{bid.tender?.title_en ?? '-'}</td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {bid.tender?.reference_number ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge status={bid.status} />
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {formatDate(bid.submitted_at)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
