import { Head, Link, router } from '@inertiajs/react';
import { Search, Filter } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '@/components/ui/select';

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Vendor = {
    id: string;
    company_name: string;
    email: string;
    prequalification_status: string;
    qualified_at: string | null;
    city: string | null;
    country: string | null;
    created_at: string;
    categories?: Array<{ id: string; name_en: string }>;
};

type Props = {
    vendors: PaginatedData<Vendor>;
    filters: {
        search?: string;
        status?: string;
        category_id?: string;
        sort?: string;
        direction?: string;
    };
};

export default function Index({ vendors, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status || 'all');

    function handleFilter() {
        router.get('/admin/vendors', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter') {
            handleFilter();
        }
    }

    const columns = [
        {
            key: 'company_name',
            label: t('table.company_name'),
            sortable: true,
            render: (value: string) => (
                <span className="font-medium">{value}</span>
            ),
        },
        {
            key: 'email',
            label: t('table.email'),
            sortable: true,
        },
        {
            key: 'prequalification_status',
            label: t('table.status'),
            sortable: true,
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'categories',
            label: t('table.categories'),
            render: (_value: unknown, row: Vendor) =>
                row.categories && row.categories.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {row.categories.map((cat) => (
                            <Badge key={cat.id} variant="secondary">
                                {cat.name_en}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-muted-foreground">--</span>
                ),
        },
        {
            key: 'city',
            label: t('table.city'),
            sortable: true,
            render: (value: string | null) => value ?? '--',
        },
        {
            key: 'created_at',
            label: t('table.registered'),
            sortable: true,
            render: (value: string) =>
                new Date(value).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                }),
        },
        {
            key: 'actions',
            label: '',
            render: (_value: unknown, row: Vendor) => (
                <Link
                    href={`/admin/vendors/${row.id}`}
                    className="text-sm font-medium text-primary hover:underline"
                >
                    {t('btn.view')}
                </Link>
            ),
        },
    ];

    return (
        <>
            <Head title="Vendors" />

            <div className="space-y-6">
                <Heading
                    title={t('pages.admin.vendors')}
                    description={t('pages.admin.vendors_description')}
                />

                <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <Input
                            placeholder={t('form.search_vendors')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={handleKeyDown}
                            className="max-w-sm"
                        />
                    </div>

                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder={t('form.all_statuses')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('form.all_statuses')}</SelectItem>
                            <SelectItem value="pending">{t('status.pending')}</SelectItem>
                            <SelectItem value="qualified">{t('status.qualified')}</SelectItem>
                            <SelectItem value="rejected">{t('status.rejected')}</SelectItem>
                            <SelectItem value="suspended">{t('status.suspended')}</SelectItem>
                        </SelectContent>
                    </Select>

                    <Button onClick={handleFilter}>
                        <Search className="mr-2 h-4 w-4" />
                        {t('btn.search')}
                    </Button>
                </div>

                <DataTable columns={columns} data={vendors} />
            </div>
        </>
    );
}
