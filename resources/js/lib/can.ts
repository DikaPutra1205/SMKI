import { usePage } from '@inertiajs/react';

export function useCan() {
    const permissions = usePage<{ auth?: { permissions?: string[] } }>().props.auth?.permissions ?? [];
    return (key: string) => permissions.includes(key);
}
