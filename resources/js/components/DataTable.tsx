import { type ReactNode, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { nextSortDirection, resolveTableFilters } from '@/lib/table';
import { cn } from '@/lib/utils';

/**
 * Column definition for the DataTable component.
 */
export interface DataTableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    render?: (value: any, row: any) => ReactNode;
}

/**
 * Inertia pagination link object.
 */
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Inertia pagination data object.
 */
interface PaginationData<T = any> {
    data: T[];
    links?: PaginationLink[];
    meta?: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        links: PaginationLink[];
    };
    current_page?: number;
    last_page?: number;
    from?: number | null;
    to?: number | null;
    total?: number;
    per_page?: number;
}

/**
 * Props for the DataTable component.
 */
export interface DataTableProps<T = any> {
    columns: DataTableColumn[];
    data: PaginationData<T>;
    filters?: Record<string, any>;
    searchable?: boolean;
    searchPlaceholder?: string;
    onSearch?: (value: string) => void;
    actions?: (row: T) => ReactNode;
}

/**
 * A reusable sortable, filterable, paginated data table for Inertia.js.
 */
export function DataTable<T = any>({
    columns,
    data,
    filters = {},
    searchable = false,
    searchPlaceholder,
    onSearch,
    actions,
}: DataTableProps<T>) {
    const { t } = useTranslation();
    const [searchValue, setSearchValue] = useState(
        (filters.search as string) ?? '',
    );

    const currentPage = data.meta?.current_page ?? data.current_page ?? 1;
    const lastPage = data.meta?.last_page ?? data.last_page ?? 1;
    const from = data.meta?.from ?? data.from ?? null;
    const to = data.meta?.to ?? data.to ?? null;
    const total = data.meta?.total ?? data.total ?? 0;
    const paginationLinks = data.meta?.links ?? data.links ?? [];

    function handleSort(key: string) {
        // Falls back to the query string when the caller omitted `filters`.
        // Six of the ten list pages had, and each sorted with an empty set:
        // the active search and filters were wiped, and the toggle below —
        // comparing against an always-undefined sort — could never reach
        // descending.
        const active = resolveTableFilters(filters, window.location.search);

        router.get(
            window.location.pathname,
            {
                ...active,
                sort: key,
                direction: nextSortDirection(key, active.sort, active.direction),
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleSearchChange(value: string) {
        setSearchValue(value);
        onSearch?.(value);
    }

    function getNestedValue(obj: any, path: string): any {
        return path.split('.').reduce((acc, part) => acc?.[part], obj);
    }

    return (
        <div className="space-y-4">
            {searchable && (
                <div className="flex items-center">
                    <Input
                        type="text"
                        placeholder={searchPlaceholder ?? t('btn.search')}
                        value={searchValue}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        className="max-w-sm"
                    />
                </div>
            )}

            <div className="rounded-md border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/50">
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    className={cn(
                                        // text-start, not text-left: under
                                        // dir="rtl" every header was pinned to
                                        // the left of its own column while the
                                        // cells beneath it read from the right.
                                        'px-4 py-3 text-start font-medium text-muted-foreground',
                                        column.sortable && 'cursor-pointer select-none',
                                    )}
                                    onClick={
                                        column.sortable
                                            ? () => handleSort(column.key)
                                            : undefined
                                    }
                                >
                                    <div className="flex items-center gap-1">
                                        {column.label}
                                        {column.sortable && (
                                            <ArrowUpDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </th>
                            ))}
                            {actions && (
                                <th className="px-4 py-3 text-start font-medium text-muted-foreground">
                                    {t('table.actions')}
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {data.data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length + (actions ? 1 : 0)}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {t('empty.no_results')}
                                </td>
                            </tr>
                        ) : (
                            data.data.map((row: any, rowIndex: number) => (
                                <tr
                                    key={row.id ?? rowIndex}
                                    className="border-b transition-colors hover:bg-muted/50"
                                >
                                    {columns.map((column) => (
                                        <td key={column.key} className="px-4 py-3">
                                            {column.render
                                                ? column.render(
                                                      getNestedValue(row, column.key),
                                                      row,
                                                  )
                                                : getNestedValue(row, column.key)}
                                        </td>
                                    ))}
                                    {actions && (
                                        <td className="px-4 py-3">{actions(row)}</td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {lastPage > 1 && (
                <div className="flex items-center justify-between">
                    {/* <bdi>: the summary mixes translated words with a digit
                        range, and the surrounding paragraph direction would
                        otherwise be free to reorder the two around each other. */}
                    <p className="text-sm text-muted-foreground">
                        <bdi>
                            {from !== null && to !== null
                                ? t('table.showing_range', { from, to, total })
                                : t('table.showing_total', { total })}
                        </bdi>
                    </p>

                    <div className="flex items-center gap-1">
                        {paginationLinks.map((link, index) => {
                            if (index === 0) {
                                return (
                                    <Button
                                        key="prev"
                                        variant="outline"
                                        size="sm"
                                        disabled={!link.url}
                                        asChild={!!link.url}
                                    >
                                        {link.url ? (
                                            <Link href={link.url} preserveState>
                                                <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                                            </Link>
                                        ) : (
                                            <span>
                                                <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                                            </span>
                                        )}
                                    </Button>
                                );
                            }

                            if (index === paginationLinks.length - 1) {
                                return (
                                    <Button
                                        key="next"
                                        variant="outline"
                                        size="sm"
                                        disabled={!link.url}
                                        asChild={!!link.url}
                                    >
                                        {link.url ? (
                                            <Link href={link.url} preserveState>
                                                <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                                            </Link>
                                        ) : (
                                            <span>
                                                <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                                            </span>
                                        )}
                                    </Button>
                                );
                            }

                            return (
                                <Button
                                    key={index}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    asChild={!!link.url}
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveState
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    )}
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
