import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Returns true while Inertia is navigating TO a URL whose pathname starts with `forPath`.
 *
 * Fix for the skeleton bug: the old hook fired `loading=true` on every navigation
 * start, so the OLD page (the one being navigated away from) would see `isLoading=true`
 * and flash its own skeleton layout during the transition — the user saw the wrong
 * skeleton (source page's) instead of the destination page's.
 *
 * Now each page passes its own canonical path (e.g. "/admin/pic/assessments") so the
 * skeleton only activates when this page IS the destination, never when navigating away.
 *
 * @param forPath  - The pathname (or prefix) this page lives at.
 *                   e.g. "/admin/pic/assessments"
 *                   Pass nothing (or "") to get the old global behaviour (avoid this).
 */
export function usePageLoading(forPath?: string): boolean {
    const [loading, setLoading] = useState(false);
    const isInitialLoad = useRef(true);
    // Track the destination URL captured in the 'before' event
    const destinationRef = useRef<string | null>(null);

    useEffect(() => {
        // 'before' fires synchronously before the XHR, carrying the visit target
        const unsubscribeBefore = router.on('before', (event) => {
            const dest: string = (event as unknown as { detail: { visit: { url: { pathname: string } } } })?.detail?.visit?.url?.pathname ?? '';
            destinationRef.current = dest;
        });

        const unsubscribeStart = router.on('start', () => {
            if (isInitialLoad.current) {
                isInitialLoad.current = false;
                return;
            }
            // Only show THIS page's skeleton if we're navigating TO this page
            if (!forPath || (destinationRef.current ?? '').startsWith(forPath)) {
                setLoading(true);
            }
        });

        const unsubscribeFinish = router.on('finish', () => {
            isInitialLoad.current = false;
            destinationRef.current = null;
            setLoading(false);
        });

        return () => {
            unsubscribeBefore();
            unsubscribeStart();
            unsubscribeFinish();
        };
    }, [forPath]);

    return loading;
}
