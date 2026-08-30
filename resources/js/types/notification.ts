export type NotificationSeverity = 'danger' | 'warning' | 'info' | 'success';

export interface NotificationData {
    type?: string;
    title?: string;
    message?: string;
    url?: string;
    severity?: NotificationSeverity;
    actor_id?: number;
    actor_name?: string;
    finding_id?: number;
    kategori?: string;
    deadline?: string | null;
    catatan?: string;
    old_status?: string;
    new_status?: string;
    entry_id?: number;
    session_id?: number;
    control_id?: number;
    catatan_admin?: string | null;
    [key: string]: unknown;
}

export interface NotificationItem {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    is_read: boolean;
    created_at: string;
}

export interface NotificationApiResponse {
    status: 'success' | 'error';
    data: NotificationItem[];
    unread_count: number;
    pagination?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    message?: string;
}

export interface NotificationRealtimeRow {
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number | string;
    data: string | NotificationData;
    read_at: string | null;
    created_at: string;
    updated_at: string;
}
