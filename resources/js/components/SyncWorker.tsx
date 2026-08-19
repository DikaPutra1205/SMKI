import { assessmentStore } from '@/stores/assessmentStore';
import { useEffect, useRef } from 'react';

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function SyncWorker({ sessionId }: { sessionId: number }) {
    const syncingRef = useRef(false);

    useEffect(() => {
        const interval = setInterval(async () => {
            if (syncingRef.current) return;

            const dirty = assessmentStore.getDirtyEntries();
            if (dirty.length === 0) return;

            syncingRef.current = true;
            try {
                const res = await fetch(`/admin/pic/checklist-entries/batch`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({ session_id: sessionId, entries: dirty }),
                });

                if (res.ok) {
                    assessmentStore.clearDirty(dirty.map((e) => e.id));
                }
            } catch {
                // Silent — will retry on next interval
            } finally {
                syncingRef.current = false;
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [sessionId]);

    return null;
}
