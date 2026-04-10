import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '@/components/ui/select';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import { ChevronRight, ChevronDown, Filter, Search } from 'lucide-react';

type AuditLog = {
    id: string;
    user_id: string | null;
    auditable_type: string;
    auditable_id: string;
    action: string;
    old_values: Record<string, any> | null;
    new_values: Record<string, any> | null;
    ip_address: string | null;
    created_at: string;
    user?: { id: string; name: string };
};

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    logs: PaginatedData<AuditLog>;
    filters: {
        user_id?: string;
        action?: string;
        entity_type?: string;
        from?: string;
        to?: string;
    };
};

const ACTION_OPTIONS = ['created', 'updated', 'deleted', 'viewed', 'exported', 'imported', 'approved', 'rejected'];

function shortEntityType(type: string): string {
    return type.replace(/^App\\Models\\/, '');
}

function truncateUuid(uuid: string): string {
    return uuid.length > 8 ? uuid.substring(0, 8) + '...' : uuid;
}

function ValueDiff({ label, values, color }: { label: string; values: Record<string, any> | null; color: 'red' | 'green' }) {
    if (!values || Object.keys(values).length === 0) return null;

    const colorClasses = color === 'red'
        ? 'bg-red-50 border-red-200 text-red-800 dark:bg-red-950 dark:border-red-800 dark:text-red-200'
        : 'bg-green-50 border-green-200 text-green-800 dark:bg-green-950 dark:border-green-800 dark:text-green-200';

    return (
        <div className="space-y-1">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
            <div className={`rounded-md border p-3 text-sm ${colorClasses}`}>
                {Object.entries(values).map(([key, value]) => (
                    <div key={key} className="flex gap-2">
                        <span className="font-mono font-medium">{key}:</span>
                        <span className="font-mono">{JSON.stringify(value)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function ExpandableRow({ log }: { log: AuditLog }) {
    const [open, setOpen] = useState(false);
    const hasDetails = log.old_values || log.new_values;

    if (!hasDetails) return null;

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger asChild>
                <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                    {open ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-2 grid grid-cols-1 gap-3 rounded-md border bg-muted/30 p-3 sm:grid-cols-2">
                    <ValueDiff label="Old Values" values={log.old_values} color="red" />
                    <ValueDiff label="New Values" values={log.new_values} color="green" />
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

export default function Index({ logs, filters }: Props) {
    const [localFilters, setLocalFilters] = useState({
        action: filters.action ?? '',
        entity_type: filters.entity_type ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    function applyFilters() {
        const params: Record<string, string> = {};
        if (localFilters.action) params.action = localFilters.action;
        if (localFilters.entity_type) params.entity_type = localFilters.entity_type;
        if (localFilters.from) params.from = localFilters.from;
        if (localFilters.to) params.to = localFilters.to;
        router.get('/admin/audit-logs', params, { preserveState: true });
    }

    function clearFilters() {
        setLocalFilters({ action: '', entity_type: '', from: '', to: '' });
        router.get('/admin/audit-logs', {}, { preserveState: true });
    }

    const columns = [
        {
            key: 'created_at',
            label: 'Timestamp',
            render: (value: string) => (
                <span className="text-sm text-muted-foreground">
                    {new Date(value).toLocaleString()}
                </span>
            ),
        },
        {
            key: 'user.name',
            label: 'User',
            render: (value: string | null) => (
                <span className="text-sm">{value ?? 'System'}</span>
            ),
        },
        {
            key: 'action',
            label: 'Action',
            render: (value: string) => <StatusBadge status={value} />,
        },
        {
            key: 'auditable_type',
            label: 'Entity Type',
            render: (value: string) => (
                <span className="font-mono text-xs">{shortEntityType(value)}</span>
            ),
        },
        {
            key: 'auditable_id',
            label: 'Entity ID',
            render: (value: string) => (
                <span className="font-mono text-xs" title={value}>
                    {truncateUuid(value)}
                </span>
            ),
        },
        {
            key: 'ip_address',
            label: 'IP Address',
            render: (value: string | null) => (
                <span className="font-mono text-xs text-muted-foreground">
                    {value ?? '—'}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Audit Logs" />

            <div className="space-y-6">
                <Heading
                    title="Audit Logs"
                    description="Read-only audit trail of all system actions."
                />

                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3 rounded-md border p-4">
                    <div className="space-y-2">
                        <Label>Action</Label>
                        <Select
                            value={localFilters.action}
                            onValueChange={(value) =>
                                setLocalFilters((prev) => ({ ...prev, action: value }))
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All actions" />
                            </SelectTrigger>
                            <SelectContent>
                                {ACTION_OPTIONS.map((action) => (
                                    <SelectItem key={action} value={action}>
                                        {action}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="entity-type">Entity Type</Label>
                        <Input
                            id="entity-type"
                            value={localFilters.entity_type}
                            onChange={(e) =>
                                setLocalFilters((prev) => ({ ...prev, entity_type: e.target.value }))
                            }
                            placeholder="e.g. Tender"
                            className="w-40"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="filter-from">From</Label>
                        <Input
                            id="filter-from"
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((prev) => ({ ...prev, from: e.target.value }))
                            }
                            className="w-40"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="filter-to">To</Label>
                        <Input
                            id="filter-to"
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((prev) => ({ ...prev, to: e.target.value }))
                            }
                            className="w-40"
                        />
                    </div>

                    <Button onClick={applyFilters}>
                        <Search className="mr-2 h-4 w-4" />
                        Apply
                    </Button>
                    <Button variant="outline" onClick={clearFilters}>
                        Clear
                    </Button>
                </div>

                {/* Data Table */}
                <DataTable
                    columns={columns}
                    data={logs}
                    filters={filters}
                    actions={(log: AuditLog) => <ExpandableRow log={log} />}
                />
            </div>
        </>
    );
}
