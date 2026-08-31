import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    /** Pre-formatted — the tile does no number formatting of its own. */
    value: string;
    hint?: string | null;
    icon: LucideIcon;
    /** Renders large. Reserve it for the one figure the page leads with. */
    emphasis?: boolean;
};

/**
 * A single headline figure.
 *
 * A stat tile rather than a one-bar chart: for a current value with no
 * comparison, the number *is* the visualisation and a chart around it only adds
 * ink. The icon is decorative — the label carries the meaning, so it is hidden
 * from assistive tech.
 */
export function StatTile({ label, value, hint, icon: Icon, emphasis = false }: Props) {
    // py-0 cancels Card's own py-6: with CardContent's padding on top, the
    // tile stacked to ~330px on mobile, mostly empty space.
    return (
        <Card className="overflow-hidden py-0">
            <CardContent className="flex items-start justify-between gap-3 p-5">
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {label}
                    </p>
                    {/* tabular-nums keeps digits from shifting the layout as
                        values change width. */}
                    <p
                        className={cn(
                            'mt-2 font-semibold tabular-nums tracking-tight',
                            emphasis ? 'text-3xl' : 'text-2xl',
                        )}
                    >
                        {value}
                    </p>
                    {hint && (
                        <p className="mt-1 truncate text-xs text-muted-foreground">{hint}</p>
                    )}
                </div>
                <span className="rounded-lg bg-muted p-2 text-muted-foreground">
                    <Icon className="size-4" aria-hidden="true" />
                </span>
            </CardContent>
        </Card>
    );
}
