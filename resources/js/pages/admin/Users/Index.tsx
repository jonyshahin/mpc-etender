import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import UserFormDialog from './Form';

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type User = {
    id: string;
    name: string;
    email: string;
    role_id: string;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
    role?: { id: string; name: string; slug: string };
};

type Props = {
    users: PaginatedData<User>;
    roles: Array<{ id: string; name: string; slug: string }>;
    filters: {
        search?: string;
        role_id?: string;
        is_active?: string;
        sort?: string;
        direction?: string;
    };
};

export default function Index({ users, roles, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteUserId, setDeleteUserId] = useState<string | null>(null);
    const [showCreateDialog, setShowCreateDialog] = useState(false);

    function applyFilters(newFilters: Record<string, string>) {
        router.get('/admin/users', { ...filters, ...newFilters }, { preserveState: true, replace: true });
    }

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        applyFilters({ search });
    }

    function handleRoleFilter(value: string) {
        applyFilters({ role_id: value === 'all' ? '' : value });
    }

    function handleDelete() {
        if (!deleteUserId) return;
        router.delete(`/admin/users/${deleteUserId}`, {
            onSuccess: () => setDeleteUserId(null),
        });
    }

    const columns = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (value: string) => <span className="font-medium">{value}</span>,
        },
        { key: 'email', label: 'Email', sortable: true },
        {
            key: 'role.name',
            label: 'Role',
            render: (value: string) => <Badge variant="outline">{value ?? '—'}</Badge>,
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (value: boolean) => <StatusBadge status={value ? 'active' : 'inactive'} />,
        },
        {
            key: 'last_login_at',
            label: 'Last Login',
            sortable: true,
            render: (value: string | null) => value ? new Date(value).toLocaleDateString() : 'Never',
        },
    ];

    return (
        <>
            <Head title="Users" />

            <div className="flex items-center justify-between">
                <Heading title="Users" description="Manage system users and their roles." />
                <Button onClick={() => setShowCreateDialog(true)}>
                    <Plus className="mr-2 h-4 w-4" />
                    Add User
                </Button>
            </div>

            <div className="mt-4 flex items-center gap-4">
                <form onSubmit={handleSearch} className="flex-1">
                    <Input
                        placeholder="Search users..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </form>
                <Select
                    defaultValue={filters.role_id ?? 'all'}
                    onValueChange={handleRoleFilter}
                >
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="All Roles" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Roles</SelectItem>
                        {roles.map((role) => (
                            <SelectItem key={role.id} value={role.id}>
                                {role.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="mt-4">
                <DataTable
                    data={users}
                    columns={columns}
                    filters={filters}
                    actions={(user: User) => (
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={`/admin/users/${user.id}/edit`}>Edit</Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                onClick={() => setDeleteUserId(user.id)}
                            >
                                Delete
                            </Button>
                        </div>
                    )}
                />
            </div>

            <ConfirmDialog
                open={deleteUserId !== null}
                onOpenChange={(open: boolean) => !open && setDeleteUserId(null)}
                onConfirm={handleDelete}
                title="Delete User"
                description="Are you sure you want to delete this user? This action cannot be undone."
            />

            {showCreateDialog && (
                <UserFormDialog
                    roles={roles}
                    open={showCreateDialog}
                    onClose={() => setShowCreateDialog(false)}
                />
            )}
        </>
    );
}
