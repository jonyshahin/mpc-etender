import { useId, useLayoutEffect, useRef, useState } from 'react';

export type TrendPoint = {
    /** Machine key, e.g. `2026-08`. */
    month: string;
    /** Short axis label, e.g. `Aug`. */
    label: string;
    total: number;
};

type Props = {
    data: TrendPoint[];
    formatValue: (value: number) => string;
    emptyLabel: string;
    /** Axis caption for the tooltip, e.g. "Awarded". */
    seriesLabel: string;
};

// Tall enough that the trend card matches the seven-row pipeline card beside
// it, instead of leaving dead space under the plot.
const HEIGHT = 268;
const PAD = { top: 12, right: 8, bottom: 24, left: 8 };

/**
 * Twelve-month value trend as a single-series area chart.
 *
 * Area rather than bars because the months form a continuous timeline — the
 * shape between points carries meaning. One hue, no legend: with a single
 * series the card title already names it, and a legend box would be pure ink.
 *
 * Width is measured rather than handed to a scaling viewBox, so the 2px stroke
 * stays 2px at every breakpoint instead of being squashed with the geometry.
 */
export function TrendArea({ data, formatValue, emptyLabel, seriesLabel }: Props) {
    const gradientId = useId();
    const wrapRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(0);
    const [active, setActive] = useState<number | null>(null);

    useLayoutEffect(() => {
        const el = wrapRef.current;

        if (!el) {
            return;
        }

        // Measure once, synchronously. ResizeObserver reports *changes*, and
        // in some embedded browser contexts it never fires at all — leaving
        // the chart stuck on its placeholder forever. The observer below is
        // for later resizes only; first paint must not depend on it.
        setWidth(el.getBoundingClientRect().width);

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            setWidth(entry.contentRect.width);
        });
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    const hasValues = data.some((d) => d.total > 0);

    // Render the shell on the first pass so the observer has a box to measure.
    if (width === 0) {
        return <div ref={wrapRef} style={{ height: HEIGHT }} aria-hidden="true" />;
    }

    if (!hasValues) {
        return (
            <div
                ref={wrapRef}
                className="flex items-center justify-center text-sm text-muted-foreground"
                style={{ height: HEIGHT }}
            >
                {emptyLabel}
            </div>
        );
    }

    const plotW = Math.max(width - PAD.left - PAD.right, 1);
    const plotH = HEIGHT - PAD.top - PAD.bottom;
    const max = Math.max(...data.map((d) => d.total), 1);

    const x = (i: number) =>
        PAD.left + (data.length === 1 ? plotW / 2 : (i / (data.length - 1)) * plotW);
    const y = (v: number) => PAD.top + plotH - (v / max) * plotH;

    const line = data.map((d, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(d.total)}`).join(' ');
    const area = `${line} L${x(data.length - 1)},${PAD.top + plotH} L${x(0)},${PAD.top + plotH} Z`;

    const point = active === null ? null : data[active];

    return (
        <div ref={wrapRef} className="relative">
            <svg
                width={width}
                height={HEIGHT}
                role="img"
                aria-label={`${seriesLabel}, ${data.length} months`}
                onMouseLeave={() => setActive(null)}
                onMouseMove={(e) => {
                    const box = e.currentTarget.getBoundingClientRect();
                    const rel = e.clientX - box.left - PAD.left;
                    const step = data.length === 1 ? plotW : plotW / (data.length - 1);
                    const i = Math.round(rel / step);
                    setActive(Math.min(Math.max(i, 0), data.length - 1));
                }}
            >
                <defs>
                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="var(--chart-brand)" stopOpacity="0.28" />
                        <stop offset="100%" stopColor="var(--chart-brand)" stopOpacity="0.02" />
                    </linearGradient>
                </defs>

                {/* Recessive gridlines — present for reading values off, never
                    competing with the data. */}
                {[0, 0.5, 1].map((f) => (
                    <line
                        key={f}
                        x1={PAD.left}
                        x2={width - PAD.right}
                        y1={PAD.top + plotH * f}
                        y2={PAD.top + plotH * f}
                        className="stroke-border"
                        strokeWidth={1}
                    />
                ))}

                <path d={area} fill={`url(#${gradientId})`} />
                <path
                    d={line}
                    fill="none"
                    stroke="var(--chart-brand)"
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />

                {point && (
                    <>
                        <line
                            x1={x(active!)}
                            x2={x(active!)}
                            y1={PAD.top}
                            y2={PAD.top + plotH}
                            className="stroke-border"
                            strokeWidth={1}
                        />
                        {/* Surface-coloured ring so the marker reads on top of
                            the area fill rather than merging into it. */}
                        <circle
                            cx={x(active!)}
                            cy={y(point.total)}
                            r={5}
                            fill="var(--chart-brand)"
                            stroke="var(--color-card)"
                            strokeWidth={2}
                        />
                    </>
                )}

                {data.map((d, i) => {
                    // Label every other month: twelve labels collide on a
                    // narrow card, and the tooltip carries the exact month.
                    const show = i % 2 === data.length % 2;

                    return show ? (
                        <text
                            key={d.month}
                            x={x(i)}
                            y={HEIGHT - 6}
                            textAnchor="middle"
                            className="fill-muted-foreground text-[10px]"
                        >
                            {d.label}
                        </text>
                    ) : null;
                })}
            </svg>

            {point && (
                <div
                    className="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md"
                    style={{
                        // Clamped so the tooltip never hangs off either edge.
                        left: Math.min(Math.max(x(active!), 60), width - 60),
                    }}
                >
                    <p className="font-medium tabular-nums">{formatValue(point.total)}</p>
                    <p className="text-muted-foreground">{point.month}</p>
                </div>
            )}
        </div>
    );
}
