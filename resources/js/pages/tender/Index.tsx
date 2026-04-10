import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Eye } from 'lucide-react';
import Heading from '@/components/heading';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useState, FormEvent } from 'react';

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type TenderRow = {
    id: string;
    project_id: string;
    created_by: string;
    reference_number: string;
    title_en: string;
    status: string;
    submission_deadline: string;
    created_at: string;
    bids_count: number;
    project?: { id: string; name: string; code: string };
    creator?: { id: string; name: string };
};

type Props = {
    tenders: PaginatedData<TenderRow>;
    filters: {
        search?: string;
        status?: string;
        sort?: string;
        direction?: string;
    };
};

const STATUS_TABS = [
    { label: 'All', value: '' },
    { label: 'Draft', value: 'draft' },
    { label: 'Published', value: 'published' },
    { label: 'Under Evaluation', value: 'under_evaluation' },
    { label: 'Awarded', value: 'awarded' },
];

const columns = [
    { key: 'reference_number', label: 'Reference', sortable: true },
    { key: 'title_en', label: 'Title', sortable: true },
    {
        key: 'project',
        label: 'Project',
        sortable: false,
        render: (_value: any, row: TenderRow) =>
            row.project ? (
                <span className="text-sm">
                    <span className="font-medium">{row.project.code}</span>
                    <span className="text-muted-foreground ml-1">— {row.project.name}</span>
                </span>
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
        render: (value: string) => <StatusBadge status={value} />,
    },
    {
        key: 'submission_deadline',
        label: 'Deadline',
        sortable: true,
        render: (value: string) => (
            <span className="text-sm">{new Date(value).toLocaleDateString()}</span>
        ),
    },
    {
        key: 'bids_count',
        label: 'Bids',
        sortable: true,
        render: (value: number) => (
            <span className="inline-flex items-center justify-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                {value}
            </span>
        ),
    },
    {
        key: 'actions',
        label: 'Actions',
        render: (_value: any, row: TenderRow) => (
            <Link href={`/tenders/${row.id}`}>
                <Button variant="ghost" size="sm">
                    <Eye className="mr-1 h-4 w-4" />
                    View
                </Button>
            </Link>
        ),
    },
];

export default function Index({ tenders, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const activeStatus = filters.status ?? '';

    function handleSearch(e: FormEvent) {
        e.preventDefault();
        router.get('/tenders', { search, status: activeStatus }, { preserveState: true });
    }

    function handleStatusChange(status: string) {
        router.get('/tenders', { search, status }, { preserveState: true });
    }

    return (
        <>
            <Head title="Tenders" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading title="Tenders" />
                    <Link href="/tenders/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Tender
                        </Button>
                    </Link>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder="Search tenders..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 w-64"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </form>

                    <div className="flex gap-1 rounded-lg border p-1">
                        {STATUS_TABS.map((tab) => (
                            <button
                                key={tab.value}
                                onClick={() => handleStatusChange(tab.value)}
                                className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    activeStatus === tab.value
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={{
                        data: tenders.data,
                        links: tenders.links,
                        current_page: tenders.current_page,
                        last_page: tenders.last_page,
                        total: tenders.total,
                    }}
                />
            </div>
        </>
    );
}
