/**
 * The label to show for a role.
 *
 * Role names live in the `roles` table, and that column holds English only —
 * the seeder writes "Procurement Officer" and nothing translates it — so the
 * users list and the user form were both showing English role names inside an
 * otherwise Arabic page.
 *
 * The slug carries the translation instead: `role.procurement_officer` is a
 * key like any other. Roles an administrator has added since are not in the
 * language files, so those fall back to the stored name rather than to the
 * bare key, which is what `t()` returns when it finds nothing.
 */
export function roleLabel(
    t: (key: string) => string,
    role: { name: string; slug: string } | null | undefined,
): string {
    if (!role) {
        return '—';
    }

    const key = `role.${role.slug}`;
    const translated = t(key);

    return translated === key ? role.name : translated;
}
