import { useTranslation } from '@/hooks/use-translation';
import { fileSizeParts } from '@/lib/filesize';

type Props = {
    bytes: number;
    className?: string;
};

/**
 * A file size, localised and bidi-safe.
 *
 * <bdi> because the pair is a number followed by a Latin unit abbreviation:
 * inside dir="rtl" the neutral space between them belongs to the surrounding
 * paragraph direction, so "1.5 MB" renders as "MB 1.5" without an isolate.
 */
export function FileSize({ bytes, className }: Props) {
    const { t, locale } = useTranslation();
    const { value, unitKey } = fileSizeParts(bytes, locale);

    return (
        <bdi className={className}>
            {value} {t(unitKey)}
        </bdi>
    );
}
