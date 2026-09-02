/**
 * Labels for the audit vocabulary.
 *
 * `action` and `auditable_type` are open sets. `action` holds AuditAction
 * values, lowercased HTTP methods from the LogAuditTrail middleware, and
 * one-off event names written inline by services; `auditable_type` holds model
 * class names plus the literal 'http_request'. Neither is an enum on the way
 * out of the database, so neither can be exhaustively translated — and the
 * page used to render both raw, so an auditor read
 * `App\Models\VendorCategoryRequest` and `vendor_category_request_review_started`.
 *
 * Every value the app is known to write has a key in lang/en.json. Anything
 * else falls back to a humanised form rather than the raw string, so a new
 * event name added by a future service is legible on the day it first appears.
 */

/** What the controller substitutes for a value too sensitive to serialise. */
export const REDACTED = '__redacted__';

type Translate = (key: string, replacements?: Record<string, string | number>) => string;

/** 'vendor_category_request_submitted' -> 'Vendor category request submitted'. */
export function humanise(value: string): string {
    const spaced = value.replace(/_/g, ' ').trim();

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

/** `App\Models\VendorCategoryRequest` -> 'vendor_category_request'. */
export function entitySlug(type: string): string {
    return type
        .replace(/^App\\Models\\/, '')
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .toLowerCase();
}

/**
 * A missing key comes back from t() as the key itself, so that is the test.
 * Same fallback StatusBadge uses.
 */
function translated(t: Translate, key: string, fallback: string): string {
    const value = t(key);

    return value === key ? fallback : value;
}

export function actionLabel(t: Translate, action: string): string {
    return translated(t, `audit.action.${action}`, humanise(action));
}

export function entityLabel(t: Translate, type: string): string {
    const slug = entitySlug(type);

    return translated(t, `audit.entity.${slug}`, humanise(slug));
}

/**
 * Whether an id looks like the UUID the column usually holds.
 *
 * It does not always: the request middleware stores the route name, or the
 * path when a route is unnamed. Truncating those to eight characters — which
 * the page did to everything — turned `admin.projects.index` into
 * `admin.pr…`, hiding the only part that identified the record.
 */
export function isUuid(value: string): boolean {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value);
}

/** A UUID shortened to its first block; anything else left whole. */
export function shortEntityId(value: string): string {
    return isUuid(value) ? value.slice(0, 8) : value;
}
