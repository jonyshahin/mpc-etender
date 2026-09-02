import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    name_en: string | null;
    name_ar: string | null;
    className?: string;
    /** Suffix rendered under the names, e.g. "already approved". */
    note?: string | null;
};

/**
 * A category's name in the reader's language, with the other language beneath.
 *
 * Stacked rather than inline. Every vendor category screen wrote
 * `{name_en}<span className="ms-2">({name_ar})</span>`, and `ms-2` resolves
 * against the span's own direction: inside a `dir="rtl"` page the margin landed
 * on the far side and the two names ran together — the same fault already fixed
 * on the admin project list.
 *
 * The secondary line carries an explicit `dir` because it is the opposite
 * script from the surrounding page; without it a trailing bracket or digit
 * jumps to the wrong end of the string.
 */
export function CategoryName({ name_en, name_ar, className, note }: Props) {
    const { locale } = useTranslation();
    const isAr = locale === 'ar';

    const primary = (isAr && name_ar) || name_en || '—';
    const secondary = isAr ? (name_ar ? name_en : null) : name_ar;

    return (
        <span className={cn('min-w-0', className)}>
            <span className="block truncate">{primary}</span>
            {secondary && (
                <span
                    className="block truncate text-xs text-muted-foreground"
                    dir={isAr ? 'ltr' : 'rtl'}
                >
                    {secondary}
                </span>
            )}
            {note && <span className="block text-xs text-muted-foreground">{note}</span>}
        </span>
    );
}
