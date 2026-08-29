import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    XCircle,
    Ban,
    Copy,
    Download,
    ExternalLink,
    FileText,
    Globe,
    MoreHorizontal,
    Trash2,
    Upload,
    KeyRound,
    Phone,
    Mail,
    MapPin,
    User,
    Building2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
    CardDescription,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { formatDate as formatDateInZone } from '@/lib/datetime';

type VendorDocument = {
    id: string;
    document_type: string;
    title: string;
    file_path: string;
    file_size: number;
    mime_type: string;
    issue_date: string | null;
    expiry_date: string | null;
    status: string;
    review_notes: string | null;
    created_at: string;
    /** Null when the vendor uploaded it themselves through the portal. */
    uploader?: { id: string; name: string } | null;
};

type Vendor = {
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
    prequalification_status: string;
    qualified_at: string | null;
    rejection_reason: string | null;
    created_at: string;
    documents: VendorDocument[];
    categories: Array<{ id: string; name_en: string; name_ar: string | null }>;
    qualified_by?: { id: string; name: string };
};

type Props = {
    vendor: Vendor;
    documentUrls: Record<string, string>;
    /** Backed by the vendors.review_docs permission. */
    canReviewDocuments: boolean;
    documentTypes: Array<{ value: string; labelKey: string }>;
};

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
}

function formatDate(value: string | null): string {
    if (!value) return '--';
    return formatDateInZone(value, 'en-US');
}

export default function Show({ vendor, documentUrls, canReviewDocuments, documentTypes }: Props) {
    const { t } = useTranslation();
    const page = usePage();
    const temporaryPassword = (page.props as { flash?: { temporary_password?: string } }).flash?.temporary_password;

    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [suspendDialogOpen, setSuspendDialogOpen] = useState(false);
    const [confirmSendOpen, setConfirmSendOpen] = useState(false);
    const [confirmTempOpen, setConfirmTempOpen] = useState(false);
    const [tempModalOpen, setTempModalOpen] = useState(false);

    // Flash prop arrives once on the request following forceTemporaryPassword();
    // openingness is driven off it so a refresh does NOT re-open the modal.
    useEffect(() => {
        if (temporaryPassword) setTempModalOpen(true);
    }, [temporaryPassword]);

    const rejectForm = useForm({ reason: '' });

    const [uploadOpen, setUploadOpen] = useState(false);
    const [rejectDocId, setRejectDocId] = useState<string | null>(null);
    const [deleteDocId, setDeleteDocId] = useState<string | null>(null);

    const uploadForm = useForm({
        file: null as File | null,
        document_type: '',
        title: '',
        issue_date: '',
        expiry_date: '',
    });
    const docRejectForm = useForm({ reason: '' });

    // forceFormData because the payload carries a File; Inertia would
    // otherwise serialise it as JSON and drop the upload.
    const submitUpload = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post(`/admin/vendors/${vendor.id}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                setUploadOpen(false);
            },
        });
    };

    const approveDoc = (id: string) => {
        router.put(`/admin/vendors/${vendor.id}/documents/${id}/approve`, {}, { preserveScroll: true });
    };

    const submitDocReject = () => {
        if (!rejectDocId) return;
        docRejectForm.put(`/admin/vendors/${vendor.id}/documents/${rejectDocId}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                docRejectForm.reset();
                setRejectDocId(null);
            },
        });
    };

    const deleteDoc = () => {
        if (!deleteDocId) return;
        router.delete(`/admin/vendors/${vendor.id}/documents/${deleteDocId}`, {
            preserveScroll: true,
            onFinish: () => setDeleteDocId(null),
        });
    };
    const suspendForm = useForm({ reason: '' });

    function handleSendPasswordReset() {
        router.post(`/admin/vendors/${vendor.id}/send-password-reset`, {}, {
            preserveScroll: true,
            onFinish: () => setConfirmSendOpen(false),
        });
    }

    function handleForceTempPassword() {
        router.post(`/admin/vendors/${vendor.id}/force-temporary-password`, {}, {
            preserveScroll: true,
            onFinish: () => setConfirmTempOpen(false),
        });
    }

    async function copyTempPassword() {
        if (!temporaryPassword) return;
        await navigator.clipboard.writeText(temporaryPassword);
        toast.success(t('messages.temp_password_copied'));
    }

    function handleApprove() {
        router.put(`/admin/vendors/${vendor.id}/prequalify`);
    }

    function handleReject() {
        rejectForm.put(`/admin/vendors/${vendor.id}/reject`, {
            onSuccess: () => setRejectDialogOpen(false),
        });
    }

    function handleSuspend() {
        suspendForm.put(`/admin/vendors/${vendor.id}/suspend`, {
            onSuccess: () => setSuspendDialogOpen(false),
        });
    }

    const showRejectionReason =
        (vendor.prequalification_status === 'rejected' ||
            vendor.prequalification_status === 'suspended') &&
        vendor.rejection_reason;

    return (
        <>
            <Head title={vendor.company_name} />

            <div className="space-y-6">
                {/* Back link */}
                <Link
                    href="/admin/vendors"
                    className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    {t('btn.back_to_vendors')}
                </Link>

                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Heading
                            title={vendor.company_name}
                            description={
                                vendor.company_name_ar
                                    ? vendor.company_name_ar
                                    : `Registered ${formatDate(vendor.created_at)}`
                            }
                        />
                        <StatusBadge status={vendor.prequalification_status} />
                    </div>

                    <div className="flex gap-2">
                        {vendor.prequalification_status !== 'qualified' && (
                            <Button
                                onClick={handleApprove}
                                variant="default"
                                className="bg-green-600 hover:bg-green-700"
                            >
                                <CheckCircle className="mr-2 h-4 w-4" />
                                {t('btn.approve')}
                            </Button>
                        )}
                        {vendor.prequalification_status !== 'rejected' && (
                            <Button
                                variant="destructive"
                                onClick={() => setRejectDialogOpen(true)}
                            >
                                <XCircle className="mr-2 h-4 w-4" />
                                {t('btn.reject')}
                            </Button>
                        )}
                        {vendor.prequalification_status !== 'suspended' && (
                            <Button
                                variant="destructive"
                                onClick={() => setSuspendDialogOpen(true)}
                            >
                                <Ban className="mr-2 h-4 w-4" />
                                {t('btn.suspend')}
                            </Button>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <KeyRound className="me-2 h-4 w-4" />
                                    {t('btn.reset_password')}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onSelect={() => setConfirmSendOpen(true)}>
                                    {t('btn.send_reset_email')}
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => setConfirmTempOpen(true)}>
                                    {t('btn.generate_temp_password')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* Rejection / Suspension reason */}
                {showRejectionReason && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                        <p className="font-medium">
                            {vendor.prequalification_status === 'rejected'
                                ? t('pages.admin.rejection_reason')
                                : t('pages.admin.suspension_reason')}
                        </p>
                        <p className="mt-1">{vendor.rejection_reason}</p>
                    </div>
                )}

                {/* Company Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            {t('pages.admin.company_information')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <Label className="text-muted-foreground">{t('form.company_name')}</Label>
                                <p className="font-medium">{vendor.company_name}</p>
                            </div>
                            {vendor.company_name_ar && (
                                <div>
                                    <Label className="text-muted-foreground">{t('form.company_name_arabic')}</Label>
                                    <p className="font-medium" dir="rtl">{vendor.company_name_ar}</p>
                                </div>
                            )}
                            <div>
                                <Label className="text-muted-foreground">{t('form.trade_license_no')}</Label>
                                <p className="font-medium">{vendor.trade_license_no}</p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">{t('form.contact_person')}</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    {vendor.contact_person}
                                </p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">{t('form.email')}</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    {vendor.email}
                                </p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">{t('form.phone')}</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    {vendor.phone}
                                </p>
                            </div>
                            {vendor.whatsapp_number && (
                                <div>
                                    <Label className="text-muted-foreground">{t('form.whatsapp')}</Label>
                                    <p className="font-medium">{vendor.whatsapp_number}</p>
                                </div>
                            )}
                            <div className="sm:col-span-2">
                                <Label className="text-muted-foreground">{t('form.address')}</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                    {vendor.address}
                                </p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">{t('form.city')}</Label>
                                <p className="font-medium">{vendor.city}</p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">{t('form.country')}</Label>
                                <p className="font-medium">{vendor.country}</p>
                            </div>
                            {vendor.website && (
                                <div>
                                    <Label className="text-muted-foreground">{t('form.website')}</Label>
                                    <p className="flex items-center gap-1 font-medium">
                                        <Globe className="h-4 w-4 text-muted-foreground" />
                                        <a
                                            href={vendor.website}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            {vendor.website}
                                        </a>
                                    </p>
                                </div>
                            )}
                            {vendor.qualified_at && (
                                <div>
                                    <Label className="text-muted-foreground">{t('form.qualified_at')}</Label>
                                    <p className="font-medium">{formatDate(vendor.qualified_at)}</p>
                                </div>
                            )}
                            {vendor.qualified_by && (
                                <div>
                                    <Label className="text-muted-foreground">{t('form.qualified_by')}</Label>
                                    <p className="font-medium">{vendor.qualified_by.name}</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Categories */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('pages.admin.categories')}</CardTitle>
                        <CardDescription>
                            {t('pages.admin.vendor_categories_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {vendor.categories.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {vendor.categories.map((cat) => (
                                    <Badge key={cat.id} variant="secondary">
                                        {cat.name_en}
                                        {cat.name_ar && (
                                            <span className="ml-1 text-muted-foreground" dir="rtl">
                                                ({cat.name_ar})
                                            </span>
                                        )}
                                    </Badge>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('empty.no_categories')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Documents */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    {t('pages.admin.documents')}
                                </CardTitle>
                                <CardDescription>
                                    {t('pages.admin.vendor_documents_description')}
                                </CardDescription>
                            </div>
                            {canReviewDocuments && (
                                <Button size="sm" onClick={() => setUploadOpen(true)}>
                                    <Upload className="me-2 h-4 w-4" />
                                    {t('btn.upload_document')}
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {vendor.documents.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="pb-2 pr-4 font-medium">{t('table.title')}</th>
                                            <th className="pb-2 pr-4 font-medium">{t('table.type')}</th>
                                            <th className="pb-2 pr-4 font-medium">{t('table.status')}</th>
                                            <th className="pb-2 pr-4 font-medium">{t('table.issue_date')}</th>
                                            <th className="pb-2 pr-4 font-medium">{t('table.expiry_date')}</th>
                                            <th className="pb-2 pr-4 font-medium">{t('table.size')}</th>
                                            <th className="pb-2 font-medium">{t('table.actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vendor.documents.map((doc) => (
                                            <tr key={doc.id} className="border-b last:border-0">
                                                <td className="py-3 pr-4 font-medium">
                                                    {doc.title}
                                                    {/* Absent for a vendor's own upload, which is the
                                                        default and needs no annotation. */}
                                                    {doc.uploader && (
                                                        <span className="block text-xs font-normal text-muted-foreground">
                                                            {t('vendor.doc_filed_by', { name: doc.uploader.name })}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 capitalize">
                                                    {doc.document_type.replace(/_/g, ' ')}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <StatusBadge status={doc.status} />
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {formatDate(doc.issue_date)}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {formatDate(doc.expiry_date)}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {formatFileSize(doc.file_size)}
                                                </td>
                                                <td className="py-3">
                                                    <div className="flex items-center gap-2">
                                                        {documentUrls[doc.id] && (
                                                            <a
                                                                href={documentUrls[doc.id]}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center gap-1 text-primary hover:underline"
                                                            >
                                                                <ExternalLink className="h-3.5 w-3.5" />
                                                                {t('btn.view')}
                                                            </a>
                                                        )}
                                                        {canReviewDocuments && (
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-7 w-7"
                                                                        aria-label={t('table.actions')}
                                                                    >
                                                                        <MoreHorizontal className="h-4 w-4" />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end">
                                                                    {doc.status !== 'approved' && (
                                                                        <DropdownMenuItem onSelect={() => approveDoc(doc.id)}>
                                                                            <CheckCircle className="me-2 h-4 w-4" />
                                                                            {t('btn.approve')}
                                                                        </DropdownMenuItem>
                                                                    )}
                                                                    {doc.status !== 'rejected' && (
                                                                        <DropdownMenuItem onSelect={() => setRejectDocId(doc.id)}>
                                                                            <XCircle className="me-2 h-4 w-4" />
                                                                            {t('btn.reject')}
                                                                        </DropdownMenuItem>
                                                                    )}
                                                                    <DropdownMenuItem
                                                                        onSelect={() => setDeleteDocId(doc.id)}
                                                                        className="text-destructive focus:text-destructive"
                                                                    >
                                                                        <Trash2 className="me-2 h-4 w-4" />
                                                                        {t('btn.delete')}
                                                                    </DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('empty.no_documents')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Reject Dialog */}
            <Dialog open={rejectDialogOpen} onOpenChange={setRejectDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('pages.admin.reject_vendor')}</DialogTitle>
                        <DialogDescription>
                            {t('pages.admin.reject_vendor_description', { name: vendor.company_name })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <Label htmlFor="reject-reason">{t('form.reason')}</Label>
                        <textarea
                            id="reject-reason"
                            className="mt-1.5 flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder={t('form.enter_rejection_reason')}
                            value={rejectForm.data.reason}
                            onChange={(e) =>
                                rejectForm.setData('reason', e.target.value)
                            }
                        />
                        {rejectForm.errors.reason && (
                            <p className="mt-1 text-sm text-destructive">
                                {rejectForm.errors.reason}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejectDialogOpen(false)}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={rejectForm.processing}
                        >
                            {t('btn.reject_vendor')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Suspend Dialog */}
            <Dialog open={suspendDialogOpen} onOpenChange={setSuspendDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('pages.admin.suspend_vendor')}</DialogTitle>
                        <DialogDescription>
                            {t('pages.admin.suspend_vendor_description', { name: vendor.company_name })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <Label htmlFor="suspend-reason">{t('form.reason')}</Label>
                        <textarea
                            id="suspend-reason"
                            className="mt-1.5 flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder={t('form.enter_suspension_reason')}
                            value={suspendForm.data.reason}
                            onChange={(e) =>
                                suspendForm.setData('reason', e.target.value)
                            }
                        />
                        {suspendForm.errors.reason && (
                            <p className="mt-1 text-sm text-destructive">
                                {suspendForm.errors.reason}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setSuspendDialogOpen(false)}
                        >
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleSuspend}
                            disabled={suspendForm.processing}
                        >
                            {t('btn.suspend_vendor')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Confirm: send password reset email */}
            <Dialog open={confirmSendOpen} onOpenChange={setConfirmSendOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('dialog.send_reset_email_title')}</DialogTitle>
                        <DialogDescription>
                            {t('dialog.send_reset_email_desc', { email: vendor.email })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmSendOpen(false)}>
                            {t('btn.cancel')}
                        </Button>
                        <Button onClick={handleSendPasswordReset}>
                            {t('btn.send')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Confirm: generate temporary password */}
            <Dialog open={confirmTempOpen} onOpenChange={setConfirmTempOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('dialog.generate_temp_title')}</DialogTitle>
                        <DialogDescription>{t('dialog.generate_temp_desc')}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmTempOpen(false)}>
                            {t('btn.cancel')}
                        </Button>
                        <Button variant="destructive" onClick={handleForceTempPassword}>
                            {t('btn.generate')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* One-time temporary password display */}
            <Dialog open={tempModalOpen} onOpenChange={setTempModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('dialog.temp_password_title')}</DialogTitle>
                        <DialogDescription>{t('dialog.temp_password_desc')}</DialogDescription>
                    </DialogHeader>

                    <div className="my-4 flex items-center gap-2">
                        <code className="flex-1 rounded bg-muted px-3 py-2 font-mono text-lg">
                            {temporaryPassword}
                        </code>
                        <Button size="icon" variant="outline" onClick={copyTempPassword} aria-label={t('btn.copy_to_clipboard')}>
                            <Copy className="h-4 w-4" />
                        </Button>
                    </div>

                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>{t('alert.temp_password_one_time')}</AlertDescription>
                    </Alert>

                    <DialogFooter>
                        <Button onClick={() => setTempModalOpen(false)}>{t('btn.done')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            {/* Filing a document the vendor handed over. Recorded as approved
                with the filer named — see VendorDocumentService::uploadForVendor. */}
            <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
                <DialogContent>
                    <form onSubmit={submitUpload}>
                        <DialogHeader>
                            <DialogTitle>{t('dialog.upload_vendor_document')}</DialogTitle>
                            <DialogDescription>
                                {t('dialog.upload_vendor_document_desc', { name: vendor.company_name })}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <div>
                                <Label htmlFor="doc-type">{t('form.document_type')}</Label>
                                <Select
                                    value={uploadForm.data.document_type}
                                    onValueChange={(value) => uploadForm.setData('document_type', value)}
                                >
                                    <SelectTrigger id="doc-type" className="mt-1.5">
                                        <SelectValue placeholder={t('form.select_document_type')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {documentTypes.map((dt) => (
                                            <SelectItem key={dt.value} value={dt.value}>
                                                {t(dt.labelKey)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {uploadForm.errors.document_type && (
                                    <p className="mt-1 text-sm text-destructive">{uploadForm.errors.document_type}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="doc-title">{t('form.title')}</Label>
                                <Input
                                    id="doc-title"
                                    className="mt-1.5"
                                    value={uploadForm.data.title}
                                    onChange={(e) => uploadForm.setData('title', e.target.value)}
                                />
                                {uploadForm.errors.title && (
                                    <p className="mt-1 text-sm text-destructive">{uploadForm.errors.title}</p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="doc-issue">{t('form.issue_date')}</Label>
                                    <Input
                                        id="doc-issue"
                                        type="date"
                                        className="mt-1.5"
                                        value={uploadForm.data.issue_date}
                                        onChange={(e) => uploadForm.setData('issue_date', e.target.value)}
                                    />
                                    {uploadForm.errors.issue_date && (
                                        <p className="mt-1 text-sm text-destructive">{uploadForm.errors.issue_date}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="doc-expiry">{t('form.expiry_date')}</Label>
                                    <Input
                                        id="doc-expiry"
                                        type="date"
                                        className="mt-1.5"
                                        value={uploadForm.data.expiry_date}
                                        onChange={(e) => uploadForm.setData('expiry_date', e.target.value)}
                                    />
                                    {uploadForm.errors.expiry_date && (
                                        <p className="mt-1 text-sm text-destructive">{uploadForm.errors.expiry_date}</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="doc-file">{t('form.file')}</Label>
                                <Input
                                    id="doc-file"
                                    type="file"
                                    accept="application/pdf"
                                    className="mt-1.5"
                                    onChange={(e) => uploadForm.setData('file', e.target.files?.[0] ?? null)}
                                />
                                <p className="mt-1 text-xs text-muted-foreground">{t('form.pdf_only_hint')}</p>
                                {uploadForm.errors.file && (
                                    <p className="mt-1 text-sm text-destructive">{uploadForm.errors.file}</p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setUploadOpen(false)}>
                                {t('btn.cancel')}
                            </Button>
                            <Button type="submit" disabled={uploadForm.processing}>
                                {uploadForm.processing ? t('btn.uploading') : t('btn.upload')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={rejectDocId !== null} onOpenChange={(open) => !open && setRejectDocId(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('dialog.reject_document')}</DialogTitle>
                        <DialogDescription>{t('dialog.reject_document_desc')}</DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <Label htmlFor="doc-reject-reason">{t('form.reason')}</Label>
                        <textarea
                            id="doc-reject-reason"
                            className="mt-1.5 flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder={t('form.enter_rejection_reason')}
                            value={docRejectForm.data.reason}
                            onChange={(e) => docRejectForm.setData('reason', e.target.value)}
                        />
                        {docRejectForm.errors.reason && (
                            <p className="mt-1 text-sm text-destructive">{docRejectForm.errors.reason}</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRejectDocId(null)}>
                            {t('btn.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitDocReject}
                            disabled={docRejectForm.processing}
                        >
                            {t('btn.reject')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleteDocId !== null}
                onOpenChange={(open) => !open && setDeleteDocId(null)}
                title={t('dialog.delete_document')}
                description={t('dialog.delete_document_desc')}
                confirmLabel={t('btn.delete')}
                variant="destructive"
                onConfirm={deleteDoc}
            />
        </>
    );
}
