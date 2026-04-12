import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Props for the StatusBadge component.
 */
export interface StatusBadgeProps {
    status: string;
    colorMap?: Record<string, string>;
}

const defaultColorMap: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    qualified: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    draft: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    under_review: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    inactive: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    suspended: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    on_hold: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
};

const defaultColor = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';

/**
 * A colored badge component that displays a status string as a styled pill.
 */
export function StatusBadge({ status, colorMap }: StatusBadgeProps) {
    const { t } = useTranslation();
    const mergedMap = { ...defaultColorMap, ...colorMap };
    const colorClasses = mergedMap[status] ?? defaultColor;

    const statusKey = `status.${status}`;
    const translated = t(statusKey);
    const displayText = translated !== statusKey
        ? translated
        : status.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                colorClasses,
            )}
        >
            {displayText}
        </span>
    );
}
