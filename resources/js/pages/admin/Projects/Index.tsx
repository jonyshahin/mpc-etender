import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Link } from '@inertiajs/react';

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Project = {
    id: string;
    name: string;
    code: string;
    location: string | null;
    status: string;
    start_date: string | null;
    end_date: string | null;
    tenders_count: number;
    users_count: number;
    created_at: string;
};

type Props = {
    projects: PaginatedData<Project>;
    filters: {
        search?: string;
        status?: string;
        sort?: string;
        direction?: string;
    };
};

function CreateProjectDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        name: '',
        name_ar: '',
        code: '',
        description: '',
        location: '',
        client_name: '',
        status: 'active',
        start_date: '',
        end_date: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post('/admin/projects', {
            onSuccess: () => onClose(),
        });
    };

    const errorEntries = Object.entries(form.errors).filter(([, msg]) => Boolean(msg));

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New Project</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {errorEntries.length > 0 && (
                        <div
                            role="alert"
                            className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            <p className="font-medium">Please correct the following:</p>
                            <ul className="mt-1 list-inside list-disc">
                                {errorEntries.map(([field, msg]) => (
                                    <li key={field}>
                                        <span className="font-medium">{field}:</span> {msg}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="create-name">Name</Label>
                        <Input
                            id="create-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">{form.errors.name}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="create-name_ar">Name (Arabic)</Label>
                        <Input
                            id="create-name_ar"
                            dir="rtl"
                            value={form.data.name_ar}
                            onChange={(e) => form.setData('name_ar', e.target.value)}
                        />
                        {form.errors.name_ar && (
                            <p className="text-sm text-destructive">{form.errors.name_ar}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="create-code">Code</Label>
                        <Input
                            id="create-code"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                        />
                        {form.errors.code && (
                            <p className="text-sm text-destructive">{form.errors.code}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="create-description">Description</Label>
                        <Input
                            id="create-description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        {form.errors.description && (
                            <p className="text-sm text-destructive">{form.errors.description}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="create-location">Location</Label>
                        <Input
                            id="create-location"
                            value={form.data.location}
                            onChange={(e) => form.setData('location', e.target.value)}
                        />
                        {form.errors.location && (
                            <p className="text-sm text-destructive">{form.errors.location}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="create-client_name">Client Name</Label>
                        <Input
                            id="create-client_name"
                            value={form.data.client_name}
                            onChange={(e) => form.setData('client_name', e.target.value)}
                        />
                        {form.errors.client_name && (
                            <p className="text-sm text-destructive">{form.errors.client_name}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="create-start_date">Start Date</Label>
                            <Input
                                id="create-start_date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(e) => form.setData('start_date', e.target.value)}
                            />
                            {form.errors.start_date && (
                                <p className="text-sm text-destructive">{form.errors.start_date}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="create-end_date">End Date</Label>
                            <Input
                                id="create-end_date"
                                type="date"
                                value={form.data.end_date}
                                onChange={(e) => form.setData('end_date', e.target.value)}
                            />
                            {form.errors.end_date && (
                                <p className="text-sm text-destructive">{form.errors.end_date}</p>
                            )}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-4">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Create Project
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Index({ projects, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [showCreateDialog, setShowCreateDialog] = useState(false);

    function applyFilters(newFilters: Record<string, string>) {
        router.get('/admin/projects', { ...filters, ...newFilters }, { preserveState: true, replace: true });
    }

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        applyFilters({ search });
    }

    function handleStatusFilter(value: string) {
        applyFilters({ status: value === 'all' ? '' : value });
    }

    const columns = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (value: string) => <span className="font-mono text-sm">{value}</span>,
        },
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (value: string) => <span className="font-medium">{value}</span>,
        },
        {
            key: 'location',
            label: 'Location',
            render: (value: string | null) => value ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            render: (value: string) => <StatusBadge status={value} />,
        },
        { key: 'tenders_count', label: 'Tenders' },
        { key: 'users_count', label: 'Team Size' },
    ];

    return (
        <>
            <Head title="Projects" />

            <div className="flex items-center justify-between">
                <Heading title="Projects" description="Manage construction projects." />
                <Button onClick={() => setShowCreateDialog(true)}>
                    <Plus className="mr-2 h-4 w-4" />
                    New Project
                </Button>
            </div>

            <div className="mt-4 flex items-center gap-4">
                <form onSubmit={handleSearch} className="flex-1">
                    <Input
                        placeholder="Search projects..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </form>
                <Select
                    defaultValue={filters.status ?? 'all'}
                    onValueChange={handleStatusFilter}
                >
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem value="draft">Draft</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="on_hold">On Hold</SelectItem>
                        <SelectItem value="completed">Completed</SelectItem>
                        <SelectItem value="cancelled">Cancelled</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div className="mt-4">
                <DataTable
                    data={projects}
                    columns={columns}
                    filters={filters}
                    actions={(project: Project) => (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/admin/projects/${project.id}/edit`}>Edit</Link>
                        </Button>
                    )}
                />
            </div>

            <CreateProjectDialog
                open={showCreateDialog}
                onClose={() => setShowCreateDialog(false)}
            />
        </>
    );
}
