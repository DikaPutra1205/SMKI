import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { deleteNotification, fetchNotifications, markAllNotificationsRead, markNotificationRead, type NotificationData } from '@/lib/notificationApi';
import { formatNotificationTime, resolveNotificationDestination, severityTone, timeAgo } from '@/lib/notifications';
import { Head, router, usePage } from '@inertiajs/react';
import { Bell, BellRing, CheckCheck, CheckCircle2, Info, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const TONE_ICON = {
    danger: 'text-rose-600 bg-rose-100 dark:text-rose-400 dark:bg-rose-950/40',
    warning: 'text-amber-600 bg-amber-100 dark:text-amber-400 dark:bg-amber-950/40',
    info: 'text-sky-600 bg-sky-100 dark:text-sky-400 dark:bg-sky-950/40',
    success: 'text-emerald-600 bg-emerald-100 dark:text-emerald-400 dark:bg-emerald-950/40',
} as const;

function ToneIcon({ tone }: { tone: keyof typeof TONE_ICON }) {
    return (
        <div className={`grid h-10 w-10 shrink-0 place-items-center rounded-full ${TONE_ICON[tone]}`}>
            {tone === 'danger' ? <Bell className="h-5 w-5" /> : tone === 'warning' ? <BellRing className="h-5 w-5" /> : <Info className="h-5 w-5" />}
        </div>
    );
}

interface NotificationsProps {
    filters?: { unread?: string };
}

export default function Notifications({ filters = {} }: NotificationsProps) {
    const page = usePage<{ auth?: { user?: { name?: string; role?: unknown } } }>();
    const authUser = page.props.auth?.user;

    const [items, setItems] = useState<NotificationData[]>([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [from, setFrom] = useState<number | null>(null);
    const [to, setTo] = useState<number | null>(null);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const [onlyUnread, setOnlyUnread] = useState(filters.unread === '1');
    const [busyId, setBusyId] = useState<string | null>(null);

    const load = useCallback(async (targetPage: number, unread: boolean) => {
        setLoading(true);
        setError(false);
        try {
            const res = await fetchNotifications({ page: targetPage, unread: unread || undefined, limit: 15 });
            setItems(res.data);
            setCurrentPage(res.pagination.current_page);
            setLastPage(res.pagination.last_page);
            setTotal(res.pagination.total);
            setFrom(res.pagination.current_page === 1 ? 1 : (res.pagination.current_page - 1) * res.pagination.per_page + 1);
            setTo(Math.min(res.pagination.total, res.pagination.current_page * res.pagination.per_page));
            setUnreadCount(res.unread_count);
        } catch {
            setError(true);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load(1, onlyUnread);
    }, [onlyUnread, load]);

    const handleFilter = (unread: boolean) => {
        setOnlyUnread(unread);
        router.get('/notifications', unread ? { unread: '1' } : {}, { preserveState: true, replace: true });
    };

    const handleOpen = async (notification: NotificationData) => {
        if (!notification.is_read) {
            setBusyId(notification.id);
            try {
                const res = await markNotificationRead(notification.id);
                setUnreadCount(res.unread_count);
                setItems((prev) => prev.map((n) => (n.id === notification.id ? { ...n, is_read: true, read_at: new Date().toISOString() } : n)));
            } catch {
                // Proceed to navigation even if marking read fails.
            } finally {
                setBusyId(null);
            }
        }
        const dest = resolveNotificationDestination(notification, {
            role: authUser?.role as string | { name?: string; label?: string } | null | undefined,
            pathname: window.location.pathname,
        });
        router.visit(dest);
    };

    const handleMarkAll = async () => {
        try {
            const res = await markAllNotificationsRead();
            setUnreadCount(res.unread_count);
            setItems((prev) => prev.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() })));
        } catch {
            // Ignore — counts will refresh on next poll.
        }
    };

    const handleDelete = async (id: string, e: React.MouseEvent) => {
        e.stopPropagation();
        if (!window.confirm(t('notificationsPage.deleteConfirm'))) return;
        setBusyId(id);
        try {
            const res = await deleteNotification(id);
            setUnreadCount(res.unread_count);
            setItems((prev) => prev.filter((n) => n.id !== id));
            setTotal((prev) => Math.max(0, prev - 1));
        } catch {
            // Ignore.
        } finally {
            setBusyId(null);
        }
    };

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/dashboard' }, { label: t('notificationsPage.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/notifications">
            <Head title={`${t('notificationsPage.title')} - Sistem Kepatuhan SMKI`} />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2.5">
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('notificationsPage.title')}</h1>
                        {unreadCount > 0 && (
                            <span className="bg-primary-50 text-primary border-primary-200 dark:bg-navy-900/60 dark:border-primary-800 dark:text-primary-200 rounded-full border px-2.5 py-0.5 text-xs font-bold">
                                {t('notificationsPage.unreadCount', unreadCount)}
                            </span>
                        )}
                    </div>
                    <p className="text-muted mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">{t('notificationsPage.subtitle')}</p>
                </div>

                <div className="flex items-center gap-2.5">
                    <div className="flex items-center rounded-xl border border-slate-200 bg-white p-1 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            onClick={() => handleFilter(false)}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                                !onlyUnread
                                    ? 'bg-primary text-white shadow-xs'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                        >
                            {t('notificationsPage.all')}
                        </button>
                        <button
                            type="button"
                            onClick={() => handleFilter(true)}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                                onlyUnread
                                    ? 'bg-primary text-white shadow-xs'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                        >
                            <BellRing className="h-3.5 w-3.5" />
                            {t('notificationsPage.unread')}
                        </button>
                    </div>

                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={handleMarkAll}
                            className="border-border text-body hover:bg-surface inline-flex items-center gap-1.5 rounded-xl border bg-white px-3 py-2 text-xs font-semibold transition-colors dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                        >
                            <CheckCheck className="h-4 w-4" />
                            {t('notificationsPage.markAllRead')}
                        </button>
                    )}
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                {loading ? (
                    <div className="space-y-2 p-4">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />
                        ))}
                    </div>
                ) : error ? (
                    <EmptyState message="Gagal memuat notifikasi. Coba muat ulang halaman." />
                ) : items.length === 0 ? (
                    <EmptyState
                        message={onlyUnread ? 'Tidak ada notifikasi yang belum dibaca.' : t('notificationsPage.emptyTitle')}
                        className="py-16"
                    />
                ) : (
                    <ul className="divide-y divide-slate-100 dark:divide-slate-800/70">
                        {items.map((notification) => {
                            const tone = severityTone(notification);
                            const isRead = notification.is_read;
                            return (
                                <li key={notification.id}>
                                    <div
                                        role="button"
                                        tabIndex={0}
                                        onClick={() => handleOpen(notification)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' || e.key === ' ') {
                                                e.preventDefault();
                                                handleOpen(notification);
                                            }
                                        }}
                                        className={`flex w-full cursor-pointer items-start gap-3.5 p-4 text-left transition-colors sm:px-5 ${
                                            isRead
                                                ? 'bg-white hover:bg-slate-50/70 dark:bg-slate-900 dark:hover:bg-slate-800/50'
                                                : 'bg-primary-50/40 hover:bg-primary-50/70 dark:bg-navy-900/20 dark:hover:bg-navy-900/40'
                                        } ${busyId === notification.id ? 'opacity-60' : ''}`}
                                    >
                                        <ToneIcon tone={tone} />

                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p
                                                        className={`text-sm ${
                                                            isRead
                                                                ? 'font-medium text-slate-800 dark:text-slate-300'
                                                                : 'font-bold text-slate-900 dark:text-white'
                                                        }`}
                                                    >
                                                        {notification.data?.title || 'Pemberitahuan'}
                                                    </p>
                                                    <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                                        {notification.data?.message || ''}
                                                    </p>
                                                </div>
                                                {!isRead && (
                                                    <span
                                                        className="bg-primary-500 mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                                        aria-label={t('notificationsPage.newChip')}
                                                    />
                                                )}
                                            </div>
                                            <div className="mt-1.5 flex items-center gap-2 text-[11px] text-slate-400 dark:text-slate-500">
                                                <span>{timeAgo(notification.created_at)}</span>
                                                <span className="h-0.5 w-0.5 rounded-full bg-current" />
                                                <span>{formatNotificationTime(notification.created_at)}</span>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1.5">
                                            {!isRead && (
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleOpen(notification);
                                                    }}
                                                    className="bg-primary-50 text-primary-700 dark:bg-navy-900 dark:text-primary-200 border-primary-200 dark:border-primary-800 inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-semibold"
                                                >
                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                    {t('notificationsPage.openAction')}
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={(e) => handleDelete(notification.id, e)}
                                                className="text-body hover:bg-danger-bg hover:text-danger grid h-7 w-7 place-items-center rounded-lg transition-colors dark:text-slate-400"
                                                aria-label={t('notificationsPage.delete')}
                                                title={t('notificationsPage.delete')}
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {!loading && !error && total > 0 && (
                    <Pagination
                        currentPage={currentPage}
                        totalPages={lastPage}
                        perPage={15}
                        totalItems={total}
                        startIndex={(from ?? 1) - 1}
                        endIndex={to ?? total}
                        onPageChange={(p) => load(p, onlyUnread)}
                    />
                )}
            </div>
        </AppLayout>
    );
}
