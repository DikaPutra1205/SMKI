import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export function usePageLoading(): boolean {
    const [loading, setLoading] = useState(false);
    const isInitialLoad = useRef(true);

    useEffect(() => {
        const unsubscribeStart = router.on('start', () => {
            if (isInitialLoad.current) {
                isInitialLoad.current = false;
                return;
            }
            setLoading(true);
        });
        const unsubscribeFinish = router.on('finish', () => {
            isInitialLoad.current = false;
            setLoading(false);
        });

        return () => {
            unsubscribeStart();
            unsubscribeFinish();
        };
    }, []);

    return loading;
}
