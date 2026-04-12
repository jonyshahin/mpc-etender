import { Head, Link, router } from '@inertiajs/react';
import { Search, Calendar, Tag, ArrowRight } from 'lucide-react';
import { useState, FormEvent } from 'react';
import Heading from '@/components/heading';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
};

type TenderItem = {
    id: string;
    title_en: string;
    reference_number: string;
    submission_deadline: string;
    project?: { id: string; name: string };
    categories?: Array<{ id: string; name_en: string }>;
    bids_count: number;
    status: string;
};

type Props = {
    tenders: PaginatedData<TenderItem>;
    filters: { search?: string };
};

function daysRemaining(deadline: string): number {
    const now = new Date();
    const end = new Date(deadline);
    const diff = end.getTime() - now.getTime();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

function deadlineLabel(deadline: string): { text: string; className: string } {
    const days = daysRemaining(deadline);
    if (days < 0) return { text: 'Closed', className: 'text-muted-foreground' };
    if (days === 0) return { text: 'Due today', className: 'text-destructive font-semibold' };
    if (days <= 3) return { text: `${days} day${days > 1 ? 's' : ''} left`, className: 'text-destructive font-semibold' };
    if (days <= 7) return { text: `${days} days left`, className: 'text-orange-600 font-medium' };
    return { text: `${days} days left`, className: 'text-muted-foreground' };
}

export default function Browse({ tenders, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(e: FormEvent) {
        e.preventDefault();
        router.get('/vendor/tenders', { search: search || undefined }, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Browse Tenders" />

            <div className="space-y-6">
                <Heading title={t('pages.vendor.browse_tenders')} />

                <form onSubmit={handleSearch} className="flex items-center gap-3 max-w-lg">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder={t('tender.search_placeholder')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-10"
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        {t('btn.search')}
                    </Button>
                </form>

                {tenders.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            {t('empty.no_tenders_found')}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {tenders.data.map((tender) => {
                                const deadline = deadlineLabel(tender.submission_deadline);
                                return (
                                    <Card key={tender.id} className="flex flex-col">
                                        <CardHeader>
                                            <CardDescription className="font-mono text-xs">
                                                {tender.reference_number}
                                            </CardDescription>
                                            <CardTitle className="text-base leading-snug">
                                                {tender.title_en}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="flex flex-1 flex-col gap-3">
                                            {tender.project && (
                                                <p className="text-sm text-muted-foreground">
                                                    {tender.project.name}
                                                </p>
                                            )}

                                            <div className="flex items-center gap-2 text-sm">
                                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                                <span>
                                                    {new Date(tender.submission_deadline).toLocaleDateString()}
                                                </span>
                                                <span className={deadline.className}>
                                                    ({deadline.text})
                                                </span>
                                            </div>

                                            {tender.categories && tender.categories.length > 0 && (
                                                <div className="flex flex-wrap gap-1">
                                                    {tender.categories.map((cat) => (
                                                        <Badge key={cat.id} variant="secondary" className="text-xs">
                                                            <Tag className="mr-1 h-3 w-3" />
                                                            {cat.name_en}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            )}

                                            <div className="mt-auto pt-3">
                                                <Button asChild variant="outline" className="w-full">
                                                    <Link href={`/vendor/tenders/${tender.id}`}>
                                                        {t('btn.view_details')}
                                                        <ArrowRight className="ml-2 h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>

                        {tenders.last_page > 1 && (
                            <nav className="flex items-center justify-center gap-1">
                                {tenders.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        asChild={!!link.url}
                                    >
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                preserveState
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : (
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
                                    </Button>
                                ))}
                            </nav>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
