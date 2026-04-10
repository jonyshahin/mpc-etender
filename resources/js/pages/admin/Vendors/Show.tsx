import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    CheckCircle,
    XCircle,
    Ban,
    Download,
    ExternalLink,
    FileText,
    Globe,
    Phone,
    Mail,
    MapPin,
    User,
    Building2,
} from 'lucide-react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
    CardDescription,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';

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
};

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
}

function formatDate(value: string | null): string {
    if (!value) return '--';
    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function Show({ vendor, documentUrls }: Props) {
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [suspendDialogOpen, setSuspendDialogOpen] = useState(false);

    const rejectForm = useForm({ rejection_reason: '' });
    const suspendForm = useForm({ rejection_reason: '' });

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
                    Back to Vendors
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
                                Approve
                            </Button>
                        )}
                        {vendor.prequalification_status !== 'rejected' && (
                            <Button
                                variant="destructive"
                                onClick={() => setRejectDialogOpen(true)}
                            >
                                <XCircle className="mr-2 h-4 w-4" />
                                Reject
                            </Button>
                        )}
                        {vendor.prequalification_status !== 'suspended' && (
                            <Button
                                variant="destructive"
                                onClick={() => setSuspendDialogOpen(true)}
                            >
                                <Ban className="mr-2 h-4 w-4" />
                                Suspend
                            </Button>
                        )}
                    </div>
                </div>

                {/* Rejection / Suspension reason */}
                {showRejectionReason && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                        <p className="font-medium">
                            {vendor.prequalification_status === 'rejected'
                                ? 'Rejection Reason'
                                : 'Suspension Reason'}
                        </p>
                        <p className="mt-1">{vendor.rejection_reason}</p>
                    </div>
                )}

                {/* Company Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            Company Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <Label className="text-muted-foreground">Company Name</Label>
                                <p className="font-medium">{vendor.company_name}</p>
                            </div>
                            {vendor.company_name_ar && (
                                <div>
                                    <Label className="text-muted-foreground">Company Name (Arabic)</Label>
                                    <p className="font-medium" dir="rtl">{vendor.company_name_ar}</p>
                                </div>
                            )}
                            <div>
                                <Label className="text-muted-foreground">Trade License No.</Label>
                                <p className="font-medium">{vendor.trade_license_no}</p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">Contact Person</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    {vendor.contact_person}
                                </p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">Email</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    {vendor.email}
                                </p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">Phone</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    {vendor.phone}
                                </p>
                            </div>
                            {vendor.whatsapp_number && (
                                <div>
                                    <Label className="text-muted-foreground">WhatsApp</Label>
                                    <p className="font-medium">{vendor.whatsapp_number}</p>
                                </div>
                            )}
                            <div className="sm:col-span-2">
                                <Label className="text-muted-foreground">Address</Label>
                                <p className="flex items-center gap-1 font-medium">
                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                    {vendor.address}
                                </p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">City</Label>
                                <p className="font-medium">{vendor.city}</p>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">Country</Label>
                                <p className="font-medium">{vendor.country}</p>
                            </div>
                            {vendor.website && (
                                <div>
                                    <Label className="text-muted-foreground">Website</Label>
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
                                    <Label className="text-muted-foreground">Qualified At</Label>
                                    <p className="font-medium">{formatDate(vendor.qualified_at)}</p>
                                </div>
                            )}
                            {vendor.qualified_by && (
                                <div>
                                    <Label className="text-muted-foreground">Qualified By</Label>
                                    <p className="font-medium">{vendor.qualified_by.name}</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Categories */}
                <Card>
                    <CardHeader>
                        <CardTitle>Categories</CardTitle>
                        <CardDescription>
                            Vendor's registered procurement categories.
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
                                No categories assigned.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Documents */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Documents
                        </CardTitle>
                        <CardDescription>
                            Uploaded vendor qualification documents.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {vendor.documents.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="pb-2 pr-4 font-medium">Title</th>
                                            <th className="pb-2 pr-4 font-medium">Type</th>
                                            <th className="pb-2 pr-4 font-medium">Status</th>
                                            <th className="pb-2 pr-4 font-medium">Issue Date</th>
                                            <th className="pb-2 pr-4 font-medium">Expiry Date</th>
                                            <th className="pb-2 pr-4 font-medium">Size</th>
                                            <th className="pb-2 font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vendor.documents.map((doc) => (
                                            <tr key={doc.id} className="border-b last:border-0">
                                                <td className="py-3 pr-4 font-medium">
                                                    {doc.title}
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
                                                    {documentUrls[doc.id] && (
                                                        <a
                                                            href={documentUrls[doc.id]}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1 text-primary hover:underline"
                                                        >
                                                            <ExternalLink className="h-3.5 w-3.5" />
                                                            View
                                                        </a>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No documents uploaded.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Reject Dialog */}
            <Dialog open={rejectDialogOpen} onOpenChange={setRejectDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Vendor</DialogTitle>
                        <DialogDescription>
                            Provide a reason for rejecting {vendor.company_name}. This will be
                            visible to the vendor.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <Label htmlFor="reject-reason">Reason</Label>
                        <textarea
                            id="reject-reason"
                            className="mt-1.5 flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Enter rejection reason..."
                            value={rejectForm.data.rejection_reason}
                            onChange={(e) =>
                                rejectForm.setData('rejection_reason', e.target.value)
                            }
                        />
                        {rejectForm.errors.rejection_reason && (
                            <p className="mt-1 text-sm text-destructive">
                                {rejectForm.errors.rejection_reason}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejectDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={rejectForm.processing}
                        >
                            Reject Vendor
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Suspend Dialog */}
            <Dialog open={suspendDialogOpen} onOpenChange={setSuspendDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Suspend Vendor</DialogTitle>
                        <DialogDescription>
                            Provide a reason for suspending {vendor.company_name}. This will be
                            visible to the vendor.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <Label htmlFor="suspend-reason">Reason</Label>
                        <textarea
                            id="suspend-reason"
                            className="mt-1.5 flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Enter suspension reason..."
                            value={suspendForm.data.rejection_reason}
                            onChange={(e) =>
                                suspendForm.setData('rejection_reason', e.target.value)
                            }
                        />
                        {suspendForm.errors.rejection_reason && (
                            <p className="mt-1 text-sm text-destructive">
                                {suspendForm.errors.rejection_reason}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setSuspendDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleSuspend}
                            disabled={suspendForm.processing}
                        >
                            Suspend Vendor
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
