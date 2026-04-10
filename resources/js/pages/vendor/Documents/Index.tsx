import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Upload, Trash2 } from 'lucide-react';

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

type Props = {
    documents: VendorDocument[];
};

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const DOCUMENT_TYPES = [
    { value: 'trade_license', label: 'Trade License' },
    { value: 'tax_certificate', label: 'Tax Certificate' },
    { value: 'insurance', label: 'Insurance Certificate' },
    { value: 'financial_statement', label: 'Financial Statement' },
    { value: 'bank_reference', label: 'Bank Reference' },
    { value: 'experience_certificate', label: 'Experience Certificate' },
    { value: 'iso_certificate', label: 'ISO Certificate' },
    { value: 'other', label: 'Other' },
];

export default function Index({ documents }: Props) {
    const [deleteId, setDeleteId] = useState<string | null>(null);

    const uploadForm = useForm({
        file: null as File | null,
        document_type: '',
        title: '',
        issue_date: '',
        expiry_date: '',
    });

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post('/vendor/documents', {
            forceFormData: true,
            onSuccess: () => {
                uploadForm.reset();
            },
        });
    };

    const handleDelete = () => {
        if (!deleteId) return;
        router.delete(`/vendor/documents/${deleteId}`, {
            onSuccess: () => setDeleteId(null),
        });
    };

    return (
        <>
            <Head title="Documents" />

            <div className="space-y-6">
                <Heading title="Documents" description="Manage your company documents and certificates" />

                {/* Upload Form */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Upload className="h-5 w-5" />
                            Upload Document
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleUpload} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={uploadForm.data.title}
                                        onChange={(e) => uploadForm.setData('title', e.target.value)}
                                        placeholder="Document title"
                                    />
                                    {uploadForm.errors.title && (
                                        <p className="text-sm text-destructive">{uploadForm.errors.title}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="document_type">Document Type</Label>
                                    <Select
                                        value={uploadForm.data.document_type}
                                        onValueChange={(value) => uploadForm.setData('document_type', value)}
                                    >
                                        <SelectTrigger id="document_type">
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DOCUMENT_TYPES.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {uploadForm.errors.document_type && (
                                        <p className="text-sm text-destructive">{uploadForm.errors.document_type}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="file">File</Label>
                                    <Input
                                        id="file"
                                        type="file"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0] ?? null;
                                            uploadForm.setData('file', file);
                                        }}
                                    />
                                    {uploadForm.errors.file && (
                                        <p className="text-sm text-destructive">{uploadForm.errors.file}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="issue_date">Issue Date</Label>
                                    <Input
                                        id="issue_date"
                                        type="date"
                                        value={uploadForm.data.issue_date}
                                        onChange={(e) => uploadForm.setData('issue_date', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="expiry_date">Expiry Date</Label>
                                    <Input
                                        id="expiry_date"
                                        type="date"
                                        value={uploadForm.data.expiry_date}
                                        onChange={(e) => uploadForm.setData('expiry_date', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={uploadForm.processing}>
                                    <Upload className="mr-2 h-4 w-4" />
                                    {uploadForm.processing ? 'Uploading...' : 'Upload Document'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Documents Table */}
                <Card>
                    <CardContent className="p-0">
                        {documents.length === 0 ? (
                            <div className="p-6 text-center text-muted-foreground">
                                No documents uploaded yet. Use the form above to upload your first document.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Title</th>
                                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Status</th>
                                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Size</th>
                                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Issue Date</th>
                                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Expiry Date</th>
                                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {documents.map((doc) => (
                                            <tr key={doc.id} className="border-b last:border-0">
                                                <td className="px-4 py-3 font-medium">
                                                    {doc.title}
                                                    {doc.review_notes && (
                                                        <p className="mt-1 text-xs text-muted-foreground">{doc.review_notes}</p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="outline">
                                                        {DOCUMENT_TYPES.find((t) => t.value === doc.document_type)?.label ??
                                                            doc.document_type}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={doc.status} />
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {formatFileSize(doc.file_size)}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">{formatDate(doc.issue_date)}</td>
                                                <td className="px-4 py-3 text-muted-foreground">{formatDate(doc.expiry_date)}</td>
                                                <td className="px-4 py-3">
                                                    {doc.status === 'pending' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setDeleteId(doc.id)}
                                                        >
                                                            <Trash2 className="h-4 w-4 text-destructive" />
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={deleteId !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteId(null);
                }}
                title="Delete Document"
                description="Are you sure you want to delete this document? This action cannot be undone."
                onConfirm={handleDelete}
            />
        </>
    );
}
