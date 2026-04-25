import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/**
 * Global Inertia validation-error backstop. Fires for any response that
 * carries a non-empty `errors` prop (422 OR redirect-back-with-errors),
 * regardless of which submit method ran (router.post / form.post /
 * router.put / etc.) and regardless of whether the caller passed an
 * onError callback.
 *
 * Sonner toast is positioned bottom-center with extended duration so it
 * survives a page repaint and is hard to miss — silent validation
 * failures (BUG-11) leave users stuck without diagnosis. Per-call onError
 * handlers still fire for component-specific behavior; this hook only
 * guarantees the user always sees *something*.
 */
export function useErrorToast(): void {
    useEffect(() => {
        return router.on('error', (event) => {
            const errors = ((event as CustomEvent).detail?.errors ?? {}) as Record<string, string>;
            const first = Object.values(errors)[0];
            if (!first) return;
            toast.error(String(first), {
                position: 'bottom-center',
                duration: 8000,
            });
        });
    }, []);
}
