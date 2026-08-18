import { id, type Strings } from '@/strings/id';

type Leaf = string | ((...args: never[]) => string);

type FlattenKeys<T, P extends string = ''> = {
    [K in keyof T]: T[K] extends Leaf
        ? P extends ''
            ? K
            : `${P}.${K & string}`
        : FlattenKeys<T[K], P extends '' ? K & string : `${P}.${K & string}`>;
}[keyof T];

export type TranslationKey = FlattenKeys<Strings>;

function resolve(obj: unknown, path: string): unknown {
    return path.split('.').reduce<unknown>((acc, key) => {
        if (acc && typeof acc === 'object' && key in acc) {
            return (acc as Record<string, unknown>)[key];
        }
        return undefined;
    }, obj);
}

/**
 * Resolve a translation key against the Indonesian strings resource.
 * Function leaves accept placeholder arguments (e.g. `t('compliance.deleteConfirm', code, title)`).
 */
export function t<T extends TranslationKey>(path: T, ...args: unknown[]): string {
    const value = resolve(id, path);

    if (typeof value === 'function') {
        return (value as (...a: unknown[]) => string)(...args);
    }

    if (typeof value === 'string') {
        return value;
    }

    return path;
}
