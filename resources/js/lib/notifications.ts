import type { NotificationData } from '@/lib/notificationApi';

export type NotificationTone = 'danger' | 'warning' | 'info' | 'success';

export interface RoleContext {
    role?: string | { name?: string; label?: string } | null;
    unitId?: number | null;
    pathname?: string;
}

function normalizeRole(role: RoleContext['role']): string {
    if (!role) return '';
    if (typeof role === 'string') return role;
    return role.name || role.label || '';
}

function defaultHrefFor(role: string): string {
    switch (role) {
        case 'superadmin':
        case 'admin_kepatuhan':
        case 'koordinator_smki':
            return '/dashboard';
        case 'pic':
            return '/admin/pic/checklist';
        case 'auditor':
            return '/dashboard';
        default:
            return '/dashboard';
    }
}

export function resolveNotificationDestination(notification: NotificationData, ctx: RoleContext): string {
    const role = normalizeRole(ctx.role);
    const nType = notification.data?.type ?? notification.type;
    const rawUrl = notification.data?.url;

    // Explicit URL from the backend — trust it when it exists and is local.
    if (rawUrl && rawUrl.startsWith('/')) {
        return rawUrl;
    }

    // Fallback by notification type to a role-appropriate destination.
    if (nType?.includes('finding')) {
        if (role === 'pic') return '/admin/pic/checklist';
        return '/admin/kepatuhan/temuan';
    }
    if (nType?.includes('checklist')) {
        return '/admin/pic/checklist';
    }
    return defaultHrefFor(role);
}

export function severityTone(notification: NotificationData): NotificationTone {
    const s = notification.data?.severity;
    if (s === 'danger' || s === 'warning' || s === 'info' || s === 'success') return s;
    if (s === 'success') return 'success';
    return 'info';
}

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

export function timeAgo(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) return 'Baru saja';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} hari lalu`;

    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

export function formatNotificationTime(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
