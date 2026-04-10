import { Head, Link, useForm } from '@inertiajs/react';
import {
    Calendar, Clock, FileText, Download, MessageCircle, Send,
    ArrowLeft, DollarSign, Layers, Plus,
} from 'lucide-react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/StatusBadge';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';

type Props = {
    tender: {
        id: string;
        reference_number: string;
        title_en: string;
        description_en: string | null;
        tender_type: string;
        status: string;
        estimated_value: string | null;
        currency: string;
        submission_deadline: string;
        opening_date: string;
        is_two_envelope: boolean;
        project?: { id: string; name: string; code: string };
        categories?: Array<{ id: string; name_en: string }>;
        boq_sections?: Array<{
            id: string;
            title: string;
            items: Array<{
                id: string;
                item_code: string;
                description_en: string;
                unit: string;
                quantity: string;
            }>;
        }>;
        documents?: Array<{ id: string; title: string; doc_type: string; file_size: number }>;
        addenda?: Array<{
            id: string;
            addendum_number: number;
            subject: string;
            content_en: string;
            published_at: string;
        }>;
        clarifications?: Array<{
            id: string;
            question: string;
            answer: string | null;
            is_published: boolean;
            asked_at: string;
        }>;
    };
    canBid: boolean;
    existingBidId: string | null;
};

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Show({ tender, canBid, existingBidId }: Props) {
    const clarificationForm = useForm({ question: '' });

    function submitClarification(e: React.FormEvent) {
        e.preventDefault();
        clarificationForm.post(`/vendor/tenders/${tender.id}/clarifications`, {
            preserveScroll: true,
            onSuccess: () => clarificationForm.reset('question'),
        });
    }

    return (
        <>
            <Head title={tender.title_en} />

            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/vendor/tenders">
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back
                        </Link>
                    </Button>
                </div>

                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <p className="font-mono text-sm text-muted-foreground">{tender.reference_number}</p>
                        <Heading title={tender.title_en} />
                        <StatusBadge status={tender.status} />
                    </div>

                    <div className="flex gap-2">
                        {canBid && !existingBidId && (
                            <Button asChild>
                                <Link href={`/vendor/tenders/${tender.id}/bid`}>
                                    <Plus className="mr-1 h-4 w-4" />
                                    Start Bid
                                </Link>
                            </Button>
                        )}
                        {existingBidId && (
                            <Button asChild variant="outline">
                                <Link href={`/vendor/bids/${existingBidId}`}>
                                    Continue Bid
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Overview */}
                <Card>
                    <CardHeader>
                        <CardTitle>Overview</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {tender.project && (
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Project</dt>
                                    <dd className="mt-1 text-sm">{tender.project.name} ({tender.project.code})</dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Tender Type</dt>
                                <dd className="mt-1 text-sm capitalize">{tender.tender_type.replace(/_/g, ' ')}</dd>
                            </div>
                            {tender.estimated_value && (
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Estimated Value</dt>
                                    <dd className="mt-1 flex items-center gap-1 text-sm">
                                        <DollarSign className="h-3.5 w-3.5" />
                                        {Number(tender.estimated_value).toLocaleString()} {tender.currency}
                                    </dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Submission Deadline</dt>
                                <dd className="mt-1 flex items-center gap-1 text-sm">
                                    <Calendar className="h-3.5 w-3.5" />
                                    {new Date(tender.submission_deadline).toLocaleString()}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Opening Date</dt>
                                <dd className="mt-1 flex items-center gap-1 text-sm">
                                    <Clock className="h-3.5 w-3.5" />
                                    {new Date(tender.opening_date).toLocaleString()}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Envelope Type</dt>
                                <dd className="mt-1 text-sm">
                                    {tender.is_two_envelope ? 'Two-Envelope' : 'Single Envelope'}
                                </dd>
                            </div>
                        </dl>

                        {tender.categories && tender.categories.length > 0 && (
                            <div className="mt-4">
                                <p className="text-sm font-medium text-muted-foreground">Categories</p>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {tender.categories.map((cat) => (
                                        <Badge key={cat.id} variant="secondary">{cat.name_en}</Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {tender.description_en && (
                            <div className="mt-4">
                                <p className="text-sm font-medium text-muted-foreground">Description</p>
                                <p className="mt-1 text-sm whitespace-pre-line">{tender.description_en}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* BOQ */}
                {tender.boq_sections && tender.boq_sections.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Layers className="h-5 w-5" />
                                Bill of Quantities
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {tender.boq_sections.map((section) => (
                                <div key={section.id}>
                                    <h4 className="mb-2 font-medium">{section.title}</h4>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left">
                                                    <th className="px-3 py-2">Code</th>
                                                    <th className="px-3 py-2">Description</th>
                                                    <th className="px-3 py-2">Unit</th>
                                                    <th className="px-3 py-2 text-right">Quantity</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {section.items.map((item) => (
                                                    <tr key={item.id} className="border-b">
                                                        <td className="px-3 py-2 font-mono text-xs">{item.item_code}</td>
                                                        <td className="px-3 py-2">{item.description_en}</td>
                                                        <td className="px-3 py-2">{item.unit}</td>
                                                        <td className="px-3 py-2 text-right">{Number(item.quantity).toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {/* Documents */}
                {tender.documents && tender.documents.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Documents
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {tender.documents.map((doc) => (
                                    <li key={doc.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="text-sm font-medium">{doc.title}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {doc.doc_type} &middot; {formatFileSize(doc.file_size)}
                                            </p>
                                        </div>
                                        <Button asChild variant="ghost" size="sm">
                                            <a href={`/vendor/tenders/${tender.id}/documents/${doc.id}/download`}>
                                                <Download className="mr-1 h-4 w-4" />
                                                Download
                                            </a>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {/* Addenda */}
                {tender.addenda && tender.addenda.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Addenda</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {tender.addenda.map((addendum) => (
                                <div key={addendum.id} className="rounded-md border p-4">
                                    <div className="flex items-center justify-between">
                                        <h4 className="font-medium">
                                            Addendum #{addendum.addendum_number}: {addendum.subject}
                                        </h4>
                                        <span className="text-xs text-muted-foreground">
                                            {new Date(addendum.published_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm whitespace-pre-line">{addendum.content_en}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {/* Clarifications */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MessageCircle className="h-5 w-5" />
                            Clarifications
                        </CardTitle>
                        <CardDescription>Ask questions about this tender</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {tender.clarifications && tender.clarifications.length > 0 && (
                            <div className="space-y-3">
                                {tender.clarifications.map((c) => (
                                    <div key={c.id} className="rounded-md border p-4">
                                        <p className="text-sm font-medium">Q: {c.question}</p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Asked {new Date(c.asked_at).toLocaleDateString()}
                                        </p>
                                        {c.answer ? (
                                            <p className="mt-2 text-sm text-green-700">A: {c.answer}</p>
                                        ) : (
                                            <p className="mt-2 text-sm italic text-muted-foreground">
                                                Awaiting response
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}

                        <form onSubmit={submitClarification} className="space-y-3 border-t pt-4">
                            <Label htmlFor="question">Ask a Question</Label>
                            <textarea
                                id="question"
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                placeholder="Type your question here..."
                                value={clarificationForm.data.question}
                                onChange={(e) => clarificationForm.setData('question', e.target.value)}
                            />
                            {clarificationForm.errors.question && (
                                <p className="text-sm text-destructive">{clarificationForm.errors.question}</p>
                            )}
                            <Button
                                type="submit"
                                disabled={clarificationForm.processing || !clarificationForm.data.question.trim()}
                            >
                                <Send className="mr-1 h-4 w-4" />
                                Submit Question
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
