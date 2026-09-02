import { Head, Link, router } from '@inertiajs/react';
import {
    Briefcase,
    LogIn,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { StatTile } from '@/components/dashboard/StatTile';
import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/datetime';
import { roleLabel } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { UserFormDialog } from './Form';

type Role = { id: string; name: string; slug: string };

type UserRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    is_2fa_enabled: boolean;
    last_login_at: string | null;
    created_at: string;
    projects_count: number;
    role: Role | null;
    /** From UserPolicy, per row — a super admin is not editable by an admin. */
    can_edit: boolean;
    can_deactivate: boolean;
};

type PaginatedData<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Filters = {
    search: string | null;
    role_id: string | null;
    status: string | null;
    sort: string;
    direction: string;
};

type Props = {
    users: PaginatedData<UserRow>;
    roles: Role[];
    filters: Filters;
    statusCounts: Record<string, number>;
    summary: {
        total: number;
        active: number;
        never_signed_in: number;
        without_2fa: number;
    };
    /**
     * Sent by the server rather than listed here, so the tabs and the values
     * the controller accepts cannot drift the way the projects filter did.
     */
    statusOptions: Array<{ value: string; labelKey: string }>;
};

/** Sentinel: Radix Select cannot take an empty string as an item value. */
const ANY_ROLE = 'all';

export default function Index({
    users,
    roles,
    filters,
    statusCounts,
    summary,
    statusOptions,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [deactivateUser, setDeactivateUser] = useState<UserRow | null>(null);
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const firstRender = useRef(true);

    // Every navigation carries the whole filter set — rebuilding the query from
    // the one key that changed drops the sort and direction.
    const navigate = (next: Partial<Filters>) => {
        router.get(
            '/admin/users',
            {
                search: search || undefined,
                role_id: filters.role_id || undefined,
                status: filters.status || undefined,
                sort: filters.sort,
                direction: filters.direction,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Debounced: the old page searched only on submit, with nothing on screen
    // to say that typing alone did nothing.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => navigate({ search: search || undefined }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    // The list rendered role.name straight from the database, which holds
    // English only — an Arabic reader was shown "Procurement Officer".
    const label = (role: Role | null) => roleLabel(t, role);

    const lastLogin = (value: string | null) =>
        value ? formatDateTime(value, locale) : t('table.never');

    const columns = [
        {
            key: 'name',
            label: t('table.name'),
            sortable: true,
            render: (value: string) => <span className="font-medium">{value}</span>,
        },
        {
            key: 'email',
            label: t('table.email'),
            sortable: true,
            render: (value: string) => (
                <span className="break-all text-sm text-muted-foreground">{value}</span>
            ),
        },
        {
            key: 'role.name',
            label: t('table.role'),
            render: (_value: string, row: UserRow) => (
                <Badge variant="outline" className="whitespace-nowrap">
                    {label(row.role)}
                </Badge>
            ),
        },
        {
            key: 'is_active',
            label: t('table.status'),
            sortable: true,
            render: (value: boolean) => <StatusBadge status={value ? 'active' : 'inactive'} />,
        },
        {
            key: 'is_2fa_enabled',
            label: t('user.two_factor'),
            render: (value: boolean) => <TwoFactorFlag enabled={value} />,
        },
        {
            // A user assigned to no project sees no tenders at all — the same
            // isolation rule that governs their dashboard — so a zero here
            // explains an empty screen the user would otherwise report as a bug.
            key: 'projects_count',
            label: t('table.projects'),
            render: (value: number) => <Count value={value} warn={value === 0} />,
        },
        {
            key: 'last_login_at',
            label: t('table.last_login'),
            sortable: true,
            render: (value: string | null) => (
                <span className="whitespace-nowrap text-sm text-muted-foreground">
                    {lastLogin(value)}
                </span>
            ),
        },
    ];

    const hasFilters = Boolean(filters.search || filters.role_id || filters.status);

    const clearAll = () => {
        setSearch('');
        navigate({ search: undefined, role_id: undefined, status: undefined });
    };

    const confirmDeactivate = () => {
        if (!deactivateUser) {
            return;
        }

        router.delete(`/admin/users/${deactivateUser.id}`, {
            preserveScroll: true,
            onFinish: () => setDeactivateUser(null),
        });
    };

    return (
        <>
            <Head title={t('pages.admin.users')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('pages.admin.users')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.admin.users_description')}
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateDialog(true)}>
                        <Plus className="me-2 size-4" />
                        {t('btn.add_user')}
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('pages.admin.users')}
                        value={String(summary.total)}
                        hint={t('user.on_the_roster')}
                        icon={Users}
                    />
                    <StatTile
                        label={t('status.active')}
                        value={String(summary.active)}
                        hint={t('user.can_sign_in')}
                        icon={UserCheck}
                    />
                    <StatTile
                        label={t('user.never_signed_in')}
                        value={String(summary.never_signed_in)}
                        hint={t('user.account_never_used')}
                        icon={LogIn}
                    />
                    {/* 2FA is mandatory for internal staff, so this tile counts
                        standing exceptions rather than preferences. */}
                    <StatTile
                        label={t('user.without_two_factor')}
                        value={String(summary.without_2fa)}
                        hint={t('user.two_factor_required')}
                        icon={ShieldAlert}
                    />
                </div>

                <div className="space-y-3">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="relative max-w-sm flex-1">
                            <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder={t('form.search_users')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="ps-9"
                                aria-label={t('form.search_users')}
                            />
                        </div>

                        <Select
                            value={filters.role_id ?? ANY_ROLE}
                            onValueChange={(value) =>
                                navigate({ role_id: value === ANY_ROLE ? undefined : value })
                            }
                        >
                            <SelectTrigger className="w-full sm:w-56">
                                <SelectValue placeholder={t('form.all_roles')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY_ROLE}>{t('form.all_roles')}</SelectItem>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={role.id}>
                                        {label(role)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                        <FilterTab
                            label={t('form.all_statuses')}
                            count={summary.total}
                            active={!filters.status}
                            onSelect={() => navigate({ status: undefined })}
                        />
                        {statusOptions.map((option) => (
                            <FilterTab
                                key={option.value}
                                label={t(option.labelKey)}
                                count={statusCounts[option.value] ?? 0}
                                active={filters.status === option.value}
                                onSelect={() => navigate({ status: option.value })}
                            />
                        ))}
                    </div>
                </div>

                {users.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed py-16 text-center">
                        <Users className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
                        <p className="mt-3 font-medium">
                            {hasFilters ? t('user.no_matches') : t('user.none_yet')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {hasFilters ? t('user.try_clearing') : t('user.add_first')}
                        </p>
                        {hasFilters && (
                            <Button variant="outline" className="mt-4" onClick={clearAll}>
                                <X className="me-2 size-4" />
                                {t('tender.clear_filters')}
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Seven columns do not survive a phone; the same rows
                            render as cards below md. */}
                        <ul className="space-y-2 md:hidden">
                            {users.data.map((user) => (
                                <li key={user.id}>
                                    <UserCard
                                        user={user}
                                        roleName={label(user.role)}
                                        lastLogin={lastLogin(user.last_login_at)}
                                        onDeactivate={() => setDeactivateUser(user)}
                                    />
                                </li>
                            ))}
                        </ul>

                        <div className="hidden md:block">
                            <DataTable
                                columns={columns}
                                data={users}
                                // Without this the table sorts with an empty
                                // filter set: it wiped the search and the role,
                                // and could never toggle to descending.
                                filters={filters}
                                actions={(row: UserRow) => (
                                    <div className="flex items-center justify-end gap-3 whitespace-nowrap">
                                        {row.can_edit && (
                                            <Link
                                                href={`/admin/users/${row.id}/edit`}
                                                className="text-sm font-medium text-primary hover:underline"
                                            >
                                                {t('btn.edit')}
                                            </Link>
                                        )}
                                        {row.can_deactivate && (
                                            <button
                                                type="button"
                                                onClick={() => setDeactivateUser(row)}
                                                className="text-sm font-medium text-destructive hover:underline"
                                            >
                                                {t('btn.deactivate')}
                                            </button>
                                        )}
                                        {!row.can_edit && !row.can_deactivate && (
                                            <span className="text-sm text-muted-foreground">—</span>
                                        )}
                                    </div>
                                )}
                            />
                        </div>
                    </>
                )}
            </div>

            <ConfirmDialog
                open={deactivateUser !== null}
                onOpenChange={(open: boolean) => !open && setDeactivateUser(null)}
                onConfirm={confirmDeactivate}
                confirmLabel={t('btn.deactivate')}
                title={t('pages.admin.deactivate_user')}
                // The button said Delete while the endpoint deactivates. Naming
                // the account and saying what survives puts the difference where
                // the decision is actually made.
                description={
                    deactivateUser
                        ? t('pages.admin.deactivate_user_confirm', { name: deactivateUser.name })
                        : ''
                }
            />

            {/* Named import: the default export of ./Form is the full-page edit
                screen, so importing it as UserFormDialog rendered a second form
                inline beneath the table instead of opening a dialog. */}
            <UserFormDialog
                roles={roles}
                open={showCreateDialog}
                onClose={() => setShowCreateDialog(false)}
            />
        </>
    );
}

/** One row as a card, for the phone layout. */
function UserCard({
    user,
    roleName,
    lastLogin,
    onDeactivate,
}: {
    user: UserRow;
    roleName: string;
    lastLogin: string;
    onDeactivate: () => void;
}) {
    const { t } = useTranslation();

    const body = (
        <>
            <div className="flex items-start justify-between gap-3">
                <span className="min-w-0">
                    <span className="block truncate font-medium">{user.name}</span>
                    <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                </span>
                <span className="shrink-0 whitespace-nowrap">
                    <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
                </span>
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span>{roleName}</span>
                {/* Icon plus number rather than ":count projects": t() does
                    plain substitution with no plural forms, so that reads
                    "1 projects" — and Arabic has six forms a two-key English
                    workaround would not cover. */}
                <span
                    className={cn(
                        'inline-flex items-center gap-1',
                        user.projects_count === 0 && 'text-amber-600 dark:text-amber-500',
                    )}
                    title={user.projects_count === 0 ? t('user.no_projects') : t('table.projects')}
                >
                    <Briefcase className="size-3" aria-hidden="true" />
                    <span className="tabular-nums">{user.projects_count}</span>
                    <span className="sr-only">{t('table.projects')}</span>
                </span>
                <span className="inline-flex items-center gap-1">
                    <LogIn className="size-3" aria-hidden="true" />
                    <span className="sr-only">{t('table.last_login')}</span>
                    {lastLogin}
                </span>
                <TwoFactorFlag enabled={user.is_2fa_enabled} />
            </div>
        </>
    );

    return (
        <div className="rounded-xl border bg-card p-4">
            {user.can_edit ? (
                <Link
                    href={`/admin/users/${user.id}/edit`}
                    className="block rounded-lg transition-colors hover:bg-accent"
                >
                    {body}
                </Link>
            ) : (
                body
            )}
            {user.can_deactivate && (
                <div className="mt-3 flex justify-end border-t pt-3">
                    <Button variant="outline" size="sm" onClick={onDeactivate}>
                        {t('btn.deactivate')}
                    </Button>
                </div>
            )}
        </div>
    );
}

/**
 * Whether an account has 2FA on.
 *
 * Text beside the icon rather than the icon alone: colour and outline are the
 * only difference between the two states otherwise, and this is the column an
 * administrator is scanning for exceptions.
 */
function TwoFactorFlag({ enabled }: { enabled: boolean }) {
    const { t } = useTranslation();
    const Icon = enabled ? ShieldCheck : ShieldAlert;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 whitespace-nowrap text-xs font-medium',
                enabled ? 'text-muted-foreground' : 'text-amber-600 dark:text-amber-500',
            )}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            <span className="sr-only">{t('user.two_factor')}: </span>
            {enabled ? t('user.two_factor_on') : t('user.two_factor_off')}
        </span>
    );
}

/** A count as a pill, so a column of zeroes does not read as empty cells. */
function Count({ value, warn = false }: { value: number; warn?: boolean }) {
    return (
        <span
            className={cn(
                'inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium tabular-nums',
                warn
                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                    : 'bg-muted',
            )}
        >
            {value}
        </span>
    );
}

function FilterTab({
    label,
    count,
    active,
    onSelect,
}: {
    label: string;
    count: number;
    active: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={active}
            className={cn(
                'flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors',
                active
                    ? 'border-transparent bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted',
            )}
        >
            {label}
            <span
                className={cn(
                    'rounded-full px-1.5 text-xs tabular-nums',
                    active ? 'bg-primary-foreground/20' : 'bg-muted',
                )}
            >
                {count}
            </span>
        </button>
    );
}
