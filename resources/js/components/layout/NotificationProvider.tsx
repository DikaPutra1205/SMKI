import { t } from '@/lib/i18n';
import { fetchNotifications, fetchUnreadCount } from '@/lib/notificationApi';
import { resolveNotificationDestination, severityTone, type NotificationTone } from '@/lib/notifications';
import { router, usePage } from '@inertiajs/react';
import { Bell, BellRing, CheckCircle2, Info, X } from 'lucide-react';
import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';

interface NotificationContextValue {
    unreadCount: number;
    refresh: () => Promise<void>;
}

const NotificationContext = createContext<NotificationContextValue>({ unreadCount: 0, refresh: async () => {} });

export function useNotifications() {
    return useContext(NotificationContext);
}

const POLL_INTERVAL_MS = 5 * 60 * 1000; // 5 minutes
const TOAST_DURATION_MS = 8000;

const TOAST_ICON = {
    danger: <Bell className="h-5 w-5" />,
    warning: <BellRing className="h-5 w-5" />,
    info: <Info className="h-5 w-5" />,
    success: <CheckCircle2 className="h-5 w-5" />,
} as const;

interface ActiveToast {
    id?: string;
    title?: string;
    message?: string;
    tone: NotificationTone;
    url?: string;
}

export default function NotificationProvider({ children }: { children: ReactNode }) {
    const page = usePage<{ auth?: { user?: { role?: unknown } } }>();
    const authUser = page.props.auth?.user;
    const prevUnreadRef = useRef<number | null>(null);
    const mountedRef = useRef(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const [toast, setToast] = useState<ActiveToast | null>(null);
    const toastTimer = useRef<ReturnType<typeof setTimeout>>(null);

    const refresh = useCallback(async () => {
        try {
            const res = await fetchUnreadCount();
            setUnreadCount(res.unread_count);
        } catch {
            // Silent — retry on next poll.
        }
    }, []);

    const showToast = useCallback((next: ActiveToast) => {
        if (toastTimer.current) clearTimeout(toastTimer.current);
        setToast({ ...next, id: next.id ?? `${Date.now()}` });
        toastTimer.current = setTimeout(() => setToast(null), TOAST_DURATION_MS);
    }, []);

    const dismissToast = useCallback(() => {
        setToast(null);
        if (toastTimer.current) {
            clearTimeout(toastTimer.current);
            toastTimer.current = null;
        }
    }, []);

    // On mount (fresh login / full page load): show summary of unread count.
    useEffect(() => {
        if (mountedRef.current) return;
        mountedRef.current = true;

        (async () => {
            try {
                const res = await fetchUnreadCount();
                setUnreadCount(res.unread_count);
                prevUnreadRef.current = res.unread_count;
                if (res.unread_count > 0) {
                    showToast({
                        title: t('notificationsPage.toastUnreadTitle'),
                        message: t('notificationsPage.toastUnreadMessage', res.unread_count),
                        tone: 'info',
                        url: '/notifications',
                    });
                }
            } catch {
                // Silent.
            }
        })();
    }, [showToast]);

    const runPoll = useCallback(async () => {
        try {
            const res = await fetchUnreadCount();
            setUnreadCount(res.unread_count);

            const prev = prevUnreadRef.current;
            const increased = prev !== null && res.unread_count > prev;

            if (increased) {
                try {
                    const list = await fetchNotifications({ limit: 1 });
                    const latest = list.data[0];
                    if (latest) {
                        showToast({
                            title: t('notificationsPage.toastNewTitle'),
                            message: latest.data?.message || '',
                            tone: severityTone(latest),
                            url: resolveNotificationDestination(latest, {
                                role: authUser?.role as string | { name?: string; label?: string } | null | undefined,
                                pathname: window.location.pathname,
                            }),
                        });
                    }
                } catch {
                    // Toast is optional — badge already updated.
                }
            }

            prevUnreadRef.current = res.unread_count;
        } catch {
            // Silent.
        }
    }, [authUser, showToast]);

    useEffect(() => {
        refresh();
        const interval = setInterval(runPoll, POLL_INTERVAL_MS);
        return () => {
            clearInterval(interval);
            if (toastTimer.current) clearTimeout(toastTimer.current);
        };
    }, [refresh, runPoll]);

    return (
        <NotificationContext.Provider value={{ unreadCount, refresh }}>
            {children}

            {toast && (
                <div
                    key={toast.id ?? `${toast.title}-${toast.tone}`}
                    role="status"
                    onClick={() => {
                        const dest = toast.url;
                        dismissToast();
                        if (dest) router.visit(dest);
                    }}
                    className="fixed top-4 right-4 z-[1200] w-[min(28rem,calc(100vw-2rem))] cursor-pointer overflow-hidden rounded-2xl border border-sky-300/60 bg-white/70 text-black shadow-xl shadow-black/10 backdrop-blur-xl dark:border-sky-400/30 dark:bg-slate-900/70 dark:text-slate-100"
                >
                    <div className="flex items-start gap-3 p-4 pb-3">
                        <span className="mt-0.5 shrink-0 text-sky-500 dark:text-sky-300">{TOAST_ICON.info}</span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-sm font-bold">{toast.title}</span>
                            {toast.message && (
                                <span className="mt-0.5 block text-xs leading-relaxed text-slate-800 dark:text-slate-200">{toast.message}</span>
                            )}
                            {toast.url && (
                                <span className="mt-1.5 block text-[11px] font-semibold text-sky-600 underline dark:text-sky-300">Buka</span>
                            )}
                        </span>
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                dismissToast();
                            }}
                            aria-label="Tutup"
                            className="shrink-0 rounded-md p-1 text-slate-600 transition-opacity hover:opacity-100 dark:text-slate-300"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="h-1 w-full bg-sky-500/10 dark:bg-sky-400/10">
                        <div
                            className="h-full origin-left bg-sky-500/70 dark:bg-sky-300/80"
                            style={{
                                animation: `toast-progress ${TOAST_DURATION_MS}ms linear forwards`,
                            }}
                        />
                    </div>
                </div>
            )}
        </NotificationContext.Provider>
    );
}
