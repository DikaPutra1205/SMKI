export interface NotificationData {
    id: string;
    type: string;
    data: {
        type?: string;
        title?: string;
        message?: string;
        severity?: 'danger' | 'warning' | 'info' | 'success';
        url?: string;
        finding_id?: number;
        [key: string]: unknown;
    };
    read_at: string | null;
    is_read: boolean;
    created_at: string | null;
}

export interface NotificationsResponse {
    status: string;
    data: NotificationData[];
    unread_count: number;
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface UnreadCountResponse {
    status: string;
    unread_count: number;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function buildQuery(params: Record<string, string | number | boolean | undefined>): string {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') {
            search.set(key, String(value));
        }
    }
    const qs = search.toString();
    return qs ? `?${qs}` : '';
}

async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const headers = new Headers(options.headers);
    if (options.method && options.method !== 'GET') {
        headers.set('X-Requested-With', 'XMLHttpRequest');
        headers.set('X-XSRF-TOKEN', getCsrfToken());
        headers.set('Accept', 'application/json');
    }
    const res = await fetch(url, { ...options, headers });
    if (!res.ok) {
        throw new Error(`Notification request failed: ${res.status}`);
    }
    return (await res.json()) as T;
}

export function fetchNotifications(params: { page?: number; unread?: boolean; limit?: number } = {}): Promise<NotificationsResponse> {
    return request<NotificationsResponse>(
        `/api/v1/notifications${buildQuery({
            page: params.page,
            unread: params.unread ? '1' : undefined,
            limit: params.limit,
        })}`,
    );
}

export function fetchUnreadCount(): Promise<UnreadCountResponse> {
    return request<UnreadCountResponse>('/api/v1/notifications/unread-count');
}

export function markNotificationRead(id: string): Promise<{ status: string; unread_count: number }> {
    return request<{ status: string; unread_count: number }>(`/api/v1/notifications/${id}/read`, { method: 'POST' });
}

export function markAllNotificationsRead(): Promise<{ status: string; unread_count: number }> {
    return request<{ status: string; unread_count: number }>('/api/v1/notifications/read-all', { method: 'POST' });
}

export function deleteNotification(id: string): Promise<{ status: string; unread_count: number }> {
    return request<{ status: string; unread_count: number }>(`/api/v1/notifications/${id}`, { method: 'DELETE' });
}
