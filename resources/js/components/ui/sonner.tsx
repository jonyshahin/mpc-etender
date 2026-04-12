import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();
    const isRtl = document.documentElement.dir === 'rtl';

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            dir={isRtl ? 'rtl' : 'ltr'}
            position={isRtl ? 'bottom-left' : 'bottom-right'}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
