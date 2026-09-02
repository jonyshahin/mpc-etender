/**
 * Money formatting for the app's locale, not the browser's.
 *
 * `Number(v).toLocaleString()` — which most of these screens used — reads the
 * browser's locale, so the same bid total rendered with different grouping and
 * decimal marks depending on the machine the vendor happened to sit at, and on
 * the bid list it rendered with no currency at all: a bare "1,250,000" against
 * a column headed "Total Amount". A procurement figure that does not say what
 * it is denominated in is not a figure anyone can act on.
 */

/**
 * An amount with its currency, in `locale`.
 *
 * `currency` is an ISO 4217 code carried on the tender and copied onto the bid
 * at draft time. An unrecognised code makes Intl throw, so it falls back to
 * appending the code verbatim rather than blanking the cell.
 */
export function formatMoney(
    value: string | number | null | undefined,
    currency: string | null | undefined,
    locale: string,
): string {
    const amount = typeof value === 'string' ? Number(value) : value;

    if (amount === null || amount === undefined || Number.isNaN(amount)) {
        return '—';
    }

    if (!currency) {
        return formatDecimal(amount, locale);
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${formatDecimal(amount, locale)} ${currency}`;
    }
}

/** A plain number with the locale's grouping — quantities, counts, subtotals. */
export function formatDecimal(
    value: string | number | null | undefined,
    locale: string,
    options: Intl.NumberFormatOptions = { minimumFractionDigits: 2, maximumFractionDigits: 2 },
): string {
    const amount = typeof value === 'string' ? Number(value) : value;

    if (amount === null || amount === undefined || Number.isNaN(amount)) {
        return '—';
    }

    return new Intl.NumberFormat(locale, options).format(amount);
}

/**
 * A BOQ quantity: grouped, but without forcing two decimals onto whole units.
 *
 * `quantity` is `decimal:3` on the server, so "12.500" is meaningful and "12"
 * should not render as "12.00".
 */
export function formatQuantity(value: string | number | null | undefined, locale: string): string {
    return formatDecimal(value, locale, { maximumFractionDigits: 3 });
}

