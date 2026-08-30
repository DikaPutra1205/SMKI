import { isSupabaseConfigured, supabase } from '@/lib/supabase';
import { NotificationApiResponse, NotificationItem, NotificationRealtimeRow } from '@/types/notification';
import { useCallback, useEffect, useRef, useState } from 'react';

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

interface UseNotificationsReturn {
    notifications: NotificationItem[];
    unreadCount: number;
    isLoading: boolean;
    latestNotification: NotificationItem | null;
    isRealtimeConnected: boolean;
    markAsRead: (id: string) => Promise<void>;
    markAllAsRead: () => Promise<void>;
    deleteNotification: (id: string) => Promise<void>;
    refetch: () => Promise<void>;
    dismissLatestNotification: () => void;
}

export function useNotifications(userId?: number | string): UseNotificationsReturn {
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [unreadCount, setUnreadCount] = useState<number>(0);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [latestNotification, setLatestNotification] = useState<NotificationItem | null>(null);
    const [isRealtimeConnected, setIsRealtimeConnected] = useState<boolean>(false);
    const channelRef = useRef<ReturnType<NonNullable<typeof supabase>['channel']> | null>(null);

    const fetchNotifications = useCallback(async () => {
        if (!userId) {
            setIsLoading(false);
            return;
        }

        try {
            const res = await fetch('/api/v1/notifications?limit=20', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) return;

            const json: NotificationApiResponse = await res.json();
            if (json.status === 'success') {
                setNotifications(json.data);
                setUnreadCount(json.unread_count ?? 0);
            }
        } catch (error) {
            console.error('[useNotifications] Gagal memuat notifikasi:', error);
        } finally {
            setIsLoading(false);
        }
    }, [userId]);

    const fetchUnreadCount = useCallback(async () => {
        if (!userId) return;

        try {
            const res = await fetch('/api/v1/notifications/unread-count', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) return;

            const json = await res.json();
            if (json.status === 'success') {
                setUnreadCount(json.unread_count);
            }
        } catch (error) {
            console.error('[useNotifications] Gagal memuat unread count:', error);
        }
    }, [userId]);

    // Initial load
    useEffect(() => {
        fetchNotifications();
    }, [fetchNotifications]);

    // Supabase Realtime Subscription + Polling Fallback
    useEffect(() => {
        if (!userId) return;

        let pollTimer: ReturnType<typeof setInterval> | null = null;

        if (isSupabaseConfigured && supabase) {
            const channelName = `realtime-notifications-user-${userId}`;
            const channel = supabase
                .channel(channelName)
                .on(
                    'postgres_changes',
                    {
                        event: 'INSERT',
                        schema: 'public',
                        table: 'notifications',
                        filter: `notifiable_id=eq.${userId}`,
                    },
                    (payload) => {
                        const newRow = payload.new as NotificationRealtimeRow;
                        let parsedData = {};
                        if (typeof newRow.data === 'string') {
                            try {
                                parsedData = JSON.parse(newRow.data);
                            } catch {
                                parsedData = {};
                            }
                        } else if (newRow.data) {
                            parsedData = newRow.data;
                        }

                        const newItem: NotificationItem = {
                            id: newRow.id,
                            type: newRow.type,
                            data: parsedData,
                            read_at: newRow.read_at,
                            is_read: false,
                            created_at: newRow.created_at || new Date().toISOString(),
                        };

                        setNotifications((prev) => [newItem, ...prev.filter((item) => item.id !== newItem.id)]);
                        setUnreadCount((prev) => prev + 1);
                        setLatestNotification(newItem);
                    },
                )
                .on(
                    'postgres_changes',
                    {
                        event: 'UPDATE',
                        schema: 'public',
                        table: 'notifications',
                        filter: `notifiable_id=eq.${userId}`,
                    },
                    (payload) => {
                        const updatedRow = payload.new as NotificationRealtimeRow;
                        setNotifications((prev) =>
                            prev.map((item) =>
                                item.id === updatedRow.id
                                    ? {
                                          ...item,
                                          read_at: updatedRow.read_at,
                                          is_read: updatedRow.read_at !== null,
                                      }
                                    : item,
                            ),
                        );
                        fetchUnreadCount();
                    },
                )
                .on(
                    'postgres_changes',
                    {
                        event: 'DELETE',
                        schema: 'public',
                        table: 'notifications',
                    },
                    (payload) => {
                        const deletedId = (payload.old as { id?: string })?.id;
                        if (deletedId) {
                            setNotifications((prev) => prev.filter((item) => item.id !== deletedId));
                            fetchUnreadCount();
                        }
                    },
                )
                .subscribe((status) => {
                    if (status === 'SUBSCRIBED') {
                        setIsRealtimeConnected(true);
                    } else if (status === 'CHANNEL_ERROR' || status === 'CLOSED' || status === 'TIMED_OUT') {
                        setIsRealtimeConnected(false);
                    }
                });

            channelRef.current = channel;

            return () => {
                if (channelRef.current && supabase) {
                    supabase.removeChannel(channelRef.current);
                }
            };
        } else {
            // Polling interval jika Supabase belum dikonfigurasi
            setIsRealtimeConnected(false);
            pollTimer = setInterval(() => {
                fetchUnreadCount();
            }, 30000);

            return () => {
                if (pollTimer) clearInterval(pollTimer);
            };
        }
    }, [userId, fetchUnreadCount]);

    // Refetch on window focus
    useEffect(() => {
        const handleFocus = () => {
            fetchUnreadCount();
        };

        window.addEventListener('focus', handleFocus);
        return () => window.removeEventListener('focus', handleFocus);
    }, [fetchUnreadCount]);

    const markAsRead = async (id: string) => {
        // Optimistic UI update
        setNotifications((prev) => prev.map((item) => (item.id === id ? { ...item, is_read: true, read_at: new Date().toISOString() } : item)));
        setUnreadCount((prev) => Math.max(0, prev - 1));

        try {
            await fetch(`/api/v1/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
            });
        } catch (error) {
            console.error('[useNotifications] Gagal markAsRead:', error);
            fetchNotifications();
        }
    };

    const markAllAsRead = async () => {
        // Optimistic UI update
        setNotifications((prev) => prev.map((item) => ({ ...item, is_read: true, read_at: new Date().toISOString() })));
        setUnreadCount(0);

        try {
            await fetch('/api/v1/notifications/read-all', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
            });
        } catch (error) {
            console.error('[useNotifications] Gagal markAllAsRead:', error);
            fetchNotifications();
        }
    };

    const deleteNotification = async (id: string) => {
        const target = notifications.find((item) => item.id === id);
        const wasUnread = target && !target.is_read;

        // Optimistic UI update
        setNotifications((prev) => prev.filter((item) => item.id !== id));
        if (wasUnread) {
            setUnreadCount((prev) => Math.max(0, prev - 1));
        }

        try {
            await fetch(`/api/v1/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
            });
        } catch (error) {
            console.error('[useNotifications] Gagal deleteNotification:', error);
            fetchNotifications();
        }
    };

    const dismissLatestNotification = () => {
        setLatestNotification(null);
    };

    return {
        notifications,
        unreadCount,
        isLoading,
        latestNotification,
        isRealtimeConnected,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        refetch: fetchNotifications,
        dismissLatestNotification,
    };
}
