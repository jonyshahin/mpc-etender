import { useState } from 'react';

export type BarDatum = {
    key: string;
    label: string;
    value: number;
};

type Props = {
    data: BarDatum[];
    /** Rendered after the value in the tooltip, e.g. "tenders". */
    unit?: string;
    emptyLabel: string;
};

/**
 * Horizontal magnitude bars in a single hue.
 *
 * Sequential rather than categorical on purpose: the reader's job here is
 * comparing sizes across pipeline stages, not telling seven identities apart.
 * Seven categorical hues would sit past the point where adjacent colours stay
 * distinguishable under colour-vision deficiency, and would add nothing — each
 * row is already named in text beside it.
 *
 * Horizontal because the labels are long ("Submission closed", "Under
 * evaluation"); vertical columns would force them to rotate.
 */
export function BarList({ data, unit, emptyLabel }: Props) {
    const [hovered, setHovered] = useState<string | null>(null);
    const max = Math.max(...data.map((d) => d.value), 1);
    const total = data.reduce((sum, d) => sum + d.value, 0);

    if (total === 0) {
        return <p className="py-8 text-center text-sm text-muted-foreground">{emptyLabel}</p>;
    }

    return (
        <ul className="space-y-2.5">
            {data.map((datum) => {
                // Zero must stay visibly zero, so an empty stage renders no
                // track fill at all rather than a misleading sliver.
                const pct = datum.value === 0 ? 0 : Math.max((datum.value / max) * 100, 2);
                const active = hovered === datum.key;

                return (
                    <li
                        key={datum.key}
                        className="group"
                        onMouseEnter={() => setHovered(datum.key)}
                        onMouseLeave={() => setHovered(null)}
                    >
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="truncate text-muted-foreground">{datum.label}</span>
                            <span className="shrink-0 font-medium tabular-nums">
                                {datum.value}
                                {unit && active && (
                                    <span className="ms-1 text-xs font-normal text-muted-foreground">
                                        {unit}
                                    </span>
                                )}
                            </span>
                        </div>
                        {/* The track is the same hue at low alpha, so the bar
                            reads as a filled portion of a whole. */}
                        <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-[var(--chart-brand-soft)]">
                            <div
                                className="h-full rounded-full bg-[var(--chart-brand)] transition-[width,opacity] duration-500 ease-out"
                                style={{ width: `${pct}%`, opacity: active ? 1 : 0.85 }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
