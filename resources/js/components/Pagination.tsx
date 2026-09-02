import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    links: PaginationLink[];
    className?: string;
};

/**
 * Inertia pagination links, translated and RTL-aware.
 *
 * The first and last entries render as chevrons: Laravel labels them with the
 * HTML entities "&laquo; Previous" and "Next &raquo;", which pages were
 * injecting through dangerouslySetInnerHTML and which are English whatever the
 * reader's locale. The arrow flips under dir="rtl", where "next" points left.
 *
 * Card lists below `md` need this too — DataTable carries its own pagination,
 * but it is hidden at that width, which left the mobile view of every such list
 * stranded on page one.
 */
export function Pagination({ links, className }: Props) {
    const { t } = useTranslation();

    if (links.length <= 3) {
        return null;
    }

    const lastIndex = links.length - 1;

    return (
        <nav className={className} aria-label={t('ui.pagination')}>
            <ul className="flex items-center justify-center gap-1">
                {links.map((link, index) => {
                    const isFirst = index === 0;
                    const isLast = index === lastIndex;
                    const isEdge = isFirst || isLast;
                    const label = isFirst
                        ? t('btn.previous')
                        : isLast
                          ? t('btn.next')
                          : link.label;

                    const content = isEdge ? (
                        <>
                            {isFirst ? (
                                <ChevronLeft className="size-4 rtl:rotate-180" aria-hidden="true" />
                            ) : (
                                <ChevronRight
                                    className="size-4 rtl:rotate-180"
                                    aria-hidden="true"
                                />
                            )}
                            <span className="sr-only">{label}</span>
                        </>
                    ) : (
                        <span className="tabular-nums">{label}</span>
                    );

                    return (
                        <li key={index}>
                            {link.url ? (
                                <Button
                                    asChild
                                    size="sm"
                                    variant={link.active ? 'default' : 'outline'}
                                >
                                    <Link
                                        href={link.url}
                                        preserveState
                                        preserveScroll
                                        aria-label={isEdge ? label : undefined}
                                        aria-current={link.active ? 'page' : undefined}
                                    >
                                        {content}
                                    </Link>
                                </Button>
                            ) : (
                                <Button size="sm" variant="outline" disabled>
                                    {content}
                                </Button>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
