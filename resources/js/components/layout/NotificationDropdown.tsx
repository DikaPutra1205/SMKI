import { useNotifications } from '@/hooks/useNotifications';
import { cn, formatTimeAgoIndonesian } from '@/lib/utils';
import { NotificationItem, NotificationSeverity } from '@/types/notification';
import { router } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, Bell, CheckCheck, CheckCircle2, ExternalLink, Info, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface NotificationDropdownProps {
    userId?: number | string;
}

export function NotificationDropdown({ userId }: NotificationDropdownProps) {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    const {
        notifications,
        unreadCount,
        isLoading,
        latestNotification,
        isRealtimeConnected,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        dismissLatestNotification,
    } = useNotifications(userId);

    // Close on click outside or Escape
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
            document.addEventListener('keydown', handleKeyDown);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen]);

    // Auto dismiss live toast after 6 seconds
    useEffect(() => {
        if (latestNotification) {
            const timer = setTimeout(() => {
                dismissLatestNotification();
            }, 6000);
            return () => clearTimeout(timer);
        }
    }, [latestNotification, dismissLatestNotification]);

    const handleItemClick = async (item: NotificationItem) => {
        if (!item.is_read) {
            await markAsRead(item.id);
        }

        const targetUrl = item.data?.url;
        if (targetUrl) {
            setIsOpen(false);
            router.visit(targetUrl);
        }
    };

    const getSeverityBadge = (severity?: NotificationSeverity) => {
        switch (severity) {
            case 'danger':
                return {
                    icon: AlertTriangle,
                    containerClass: 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400 border-red-200 dark:border-red-900/50',
                };
            case 'warning':
                return {
                    icon: AlertCircle,
                    containerClass: 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400 border-amber-200 dark:border-amber-900/50',
                };
            case 'success':
                return {
                    icon: CheckCircle2,
                    containerClass:
                        'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/50',
                };
            case 'info':
            default:
                return {
                    icon: Info,
                    containerClass: 'bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400 border-sky-200 dark:border-sky-900/50',
                };
        }
    };

    return (
        <>
            <div className="relative" ref={dropdownRef}>
                {/* Bell Icon Trigger */}
                <button
                    type="button"
                    onClick={() => setIsOpen(!isOpen)}
                    className={cn(
                        'relative flex h-9 w-9 items-center justify-center rounded-[10px] border shadow-xs transition-all duration-150',
                        isOpen
                            ? 'border-primary/50 bg-primary/10 text-primary dark:border-primary/60 dark:bg-primary/20 dark:text-primary'
                            : 'border-border text-body hover:bg-surface hover:text-navy bg-white dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
                    )}
                    aria-label="Pusat Notifikasi"
                    aria-expanded={isOpen}
                >
                    <Bell className="h-4.5 w-4.5" />

                    {/* Unread Badge Counter */}
                    {unreadCount > 0 && (
                        <span className="bg-danger animate-in zoom-in-50 absolute -top-1 -right-1 flex min-h-4.5 min-w-4.5 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-[#001a30]">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </button>

                {/* Dropdown Panel */}
                {isOpen && (
                    <div
                        className="animate-in fade-in slide-in-from-top-2 absolute right-0 z-[1000] mt-2 w-[340px] rounded-2xl border border-slate-200 bg-white/95 shadow-2xl backdrop-blur-md sm:w-[380px] dark:border-slate-800 dark:bg-[#00223d]/95"
                        role="dialog"
                        aria-label="Daftar Notifikasi"
                    >
                        {/* Header */}
                        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/10">
                            <div className="flex items-center gap-2">
                                <h3 className="text-navy text-sm font-semibold dark:text-white">Notifikasi</h3>
                                {unreadCount > 0 && (
                                    <span className="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:bg-red-950/80 dark:text-red-300">
                                        {unreadCount} baru
                                    </span>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                {isRealtimeConnected && (
                                    <span
                                        className="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                                        title="Koneksi Supabase Realtime aktif"
                                    >
                                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                                        Live
                                    </span>
                                )}

                                {unreadCount > 0 && (
                                    <button
                                        type="button"
                                        onClick={markAllAsRead}
                                        className="text-primary inline-flex items-center gap-1 text-[11px] font-medium hover:underline dark:text-sky-400"
                                        title="Tandai semua telah dibaca"
                                    >
                                        <CheckCheck className="h-3.5 w-3.5" />
                                        <span>Tandai dibaca</span>
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* List Content */}
                        <div className="max-h-[380px] divide-y divide-slate-100 overflow-y-auto overscroll-contain dark:divide-white/5">
                            {isLoading ? (
                                <div className="space-y-3 p-4">
                                    {[1, 2, 3].map((n) => (
                                        <div key={n} className="flex animate-pulse gap-3">
                                            <div className="h-9 w-9 shrink-0 rounded-xl bg-slate-200 dark:bg-slate-800" />
                                            <div className="flex-1 space-y-1.5">
                                                <div className="h-3.5 w-3/4 rounded bg-slate-200 dark:bg-slate-800" />
                                                <div className="h-3 w-1/2 rounded bg-slate-200 dark:bg-slate-800" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : notifications.length === 0 ? (
                                <div className="flex flex-col items-center justify-center px-4 py-10 text-center">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-white/5">
                                        <Bell className="h-6 w-6 text-slate-400 dark:text-slate-500" />
                                    </div>
                                    <p className="text-navy mt-3 text-xs font-semibold dark:text-white">Belum Ada Notifikasi</p>
                                    <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                        Pemberitahuan terkait temuan audit & checklist akan muncul di sini.
                                    </p>
                                </div>
                            ) : (
                                notifications.map((item) => {
                                    const { icon: SeverityIcon, containerClass } = getSeverityBadge(item.data?.severity);

                                    return (
                                        <div
                                            key={item.id}
                                            onClick={() => handleItemClick(item)}
                                            className={cn(
                                                'group relative flex cursor-pointer items-start gap-3 p-3.5 text-left transition-colors',
                                                item.is_read
                                                    ? 'hover:bg-slate-50 dark:hover:bg-white/5'
                                                    : 'bg-primary/5 hover:bg-primary/10 dark:bg-sky-950/30 dark:hover:bg-sky-950/40',
                                            )}
                                        >
                                            {/* Severity Icon */}
                                            <div
                                                className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border', containerClass)}
                                            >
                                                <SeverityIcon className="h-4 w-4" />
                                            </div>

                                            {/* Body */}
                                            <div className="min-w-0 flex-1">
                                                <h4
                                                    className={cn(
                                                        'truncate text-xs',
                                                        item.is_read
                                                            ? 'font-medium text-slate-700 dark:text-slate-300'
                                                            : 'text-navy font-semibold dark:text-white',
                                                    )}
                                                >
                                                    {item.data?.title || 'Pemberitahuan Sistem'}
                                                </h4>

                                                <p className="mt-0.5 line-clamp-2 text-[11px] text-slate-600 dark:text-slate-400">
                                                    {item.data?.message || item.data?.catatan || 'Klik untuk membuka rincian.'}
                                                </p>

                                                {item.data?.actor_name && (
                                                    <div className="mt-1 flex items-center gap-1.5 text-[10px] text-slate-400 dark:text-slate-500">
                                                        <span>Oleh: {item.data.actor_name}</span>
                                                        {item.data?.kategori && (
                                                            <>
                                                                <span>•</span>
                                                                <span className="capitalize">{item.data.kategori}</span>
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Right Column: Timestamp on top-right, Unread Dot centered in middle-right, Trash on bottom */}
                                            <div className="flex min-h-[52px] shrink-0 flex-col items-end justify-between self-stretch py-0.5 pl-2">
                                                <span className="text-[10px] font-medium text-slate-400 dark:text-slate-500">
                                                    {formatTimeAgoIndonesian(item.created_at)}
                                                </span>

                                                <div className="my-auto flex items-center justify-center">
                                                    {!item.is_read && (
                                                        <span className="bg-primary ring-primary/20 h-2 w-2 rounded-full ring-2 dark:bg-sky-400" />
                                                    )}
                                                </div>

                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        deleteNotification(item.id);
                                                    }}
                                                    className="p-0.5 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400"
                                                    title="Hapus notifikasi"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        {/* Footer */}
                        <div className="flex items-center justify-between rounded-b-2xl border-t border-slate-100 bg-slate-50/50 px-4 py-2 dark:border-white/10 dark:bg-black/20">
                            <span className="text-[11px] text-slate-500 dark:text-slate-400">Total {notifications.length} notifikasi</span>
                            {unreadCount > 0 && (
                                <span className="text-primary text-[11px] font-medium dark:text-sky-400">{unreadCount} belum dibaca</span>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Floating Live Real-Time Toast Banner */}
            {latestNotification && (
                <aside
                    aria-label="Pemberitahuan Baru"
                    onClick={() => {
                        const targetUrl = latestNotification.data?.url;
                        markAsRead(latestNotification.id);
                        dismissLatestNotification();
                        if (targetUrl) {
                            router.visit(targetUrl);
                        } else {
                            setIsOpen(true);
                        }
                    }}
                    className="border-primary/20 animate-in slide-in-from-top-4 hover:border-primary/50 fixed top-20 right-4 z-[1050] max-w-sm cursor-pointer rounded-2xl border bg-white/95 p-4 shadow-2xl backdrop-blur-md duration-300 dark:border-sky-500/30 dark:bg-[#00223d]/95 dark:hover:border-sky-500/60"
                >
                    <div className="flex items-start gap-3">
                        <div className="bg-primary/10 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-xl dark:bg-sky-500/20 dark:text-sky-400">
                            <Bell className="h-5 w-5 animate-bounce" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center justify-between gap-2">
                                <h4 className="text-navy text-xs font-bold dark:text-white">{latestNotification.data?.title || 'Notifikasi Baru'}</h4>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        dismissLatestNotification();
                                    }}
                                    className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"
                                    aria-label="Tutup notifikasi"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <p className="mt-1 line-clamp-2 text-xs text-slate-600 dark:text-slate-300">
                                {latestNotification.data?.message || 'Ada pembaruan status atau temuan baru.'}
                            </p>
                            <div className="mt-2.5 flex items-center gap-2">
                                <span className="bg-primary hover:bg-primary/90 inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-semibold text-white shadow-xs transition-colors">
                                    <span>Buka Rincian</span>
                                    <ExternalLink className="h-3 w-3" />
                                </span>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        markAsRead(latestNotification.id);
                                        dismissLatestNotification();
                                    }}
                                    className="rounded-lg px-2 py-1 text-[11px] font-medium text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/10"
                                >
                                    Tandai Dibaca
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            )}
        </>
    );
}
