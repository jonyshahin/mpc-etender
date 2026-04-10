import { useEffect, useRef, useState } from 'react';
import { ChevronDown, X } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

/**
 * Option shape for MultiSelect.
 */
export interface MultiSelectOption {
    value: string;
    label: string;
}

/**
 * Props for the MultiSelect component.
 */
export interface MultiSelectProps {
    options: MultiSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    disabled?: boolean;
}

/**
 * A multi-select dropdown with chip display for selected items.
 */
export function MultiSelect({
    options,
    value,
    onChange,
    placeholder = 'Select options...',
    disabled = false,
}: MultiSelectProps) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const selectedOptions = options.filter((opt) => value.includes(opt.value));

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function handleToggle(optionValue: string) {
        if (value.includes(optionValue)) {
            onChange(value.filter((v) => v !== optionValue));
        } else {
            onChange([...value, optionValue]);
        }
    }

    function handleRemove(optionValue: string, event: React.MouseEvent) {
        event.stopPropagation();
        onChange(value.filter((v) => v !== optionValue));
    }

    return (
        <div ref={containerRef} className="relative">
            <button
                type="button"
                onClick={() => !disabled && setOpen(!open)}
                disabled={disabled}
                className={cn(
                    'flex min-h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                    'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                )}
            >
                <div className="flex flex-1 flex-wrap gap-1">
                    {selectedOptions.length === 0 ? (
                        <span className="text-muted-foreground">{placeholder}</span>
                    ) : (
                        selectedOptions.map((option) => (
                            <span
                                key={option.value}
                                className="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                            >
                                {option.label}
                                <button
                                    type="button"
                                    onClick={(e) => handleRemove(option.value, e)}
                                    className="rounded-full outline-none hover:bg-secondary-foreground/20"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </span>
                        ))
                    )}
                </div>
                <ChevronDown className="h-4 w-4 shrink-0 opacity-50" />
            </button>

            {open && (
                <div className="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md">
                    <div className="max-h-60 overflow-y-auto p-1">
                        {options.length === 0 ? (
                            <div className="px-2 py-4 text-center text-sm text-muted-foreground">
                                No options available.
                            </div>
                        ) : (
                            options.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => handleToggle(option.value)}
                                    className={cn(
                                        'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none',
                                        'hover:bg-accent hover:text-accent-foreground',
                                        'cursor-pointer',
                                    )}
                                >
                                    <Checkbox
                                        checked={value.includes(option.value)}
                                        tabIndex={-1}
                                        className="pointer-events-none"
                                    />
                                    {option.label}
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
