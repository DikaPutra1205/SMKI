import { useCallback, useEffect, useState } from 'react';

export type ThemeMode = 'light' | 'dark' | 'system';
export type ResolvedTheme = 'light' | 'dark';

const STORAGE_KEY = 'theme';
const QUERY = '(prefers-color-scheme: dark)';

function readStoredMode(): ThemeMode {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
}

function systemPrefersDark(): boolean {
    return window.matchMedia(QUERY).matches;
}

function resolve(mode: ThemeMode): ResolvedTheme {
    if (mode === 'system') {
        return systemPrefersDark() ? 'dark' : 'light';
    }
    return mode;
}

function applyToDocument(resolved: ResolvedTheme): void {
    document.documentElement.classList.toggle('dark', resolved === 'dark');
}

/**
 * Theme state with localStorage persistence and OS-following `system` default.
 *
 * The `.dark` class on <html> is also applied by an inline script in app.blade.php
 * before first paint; this hook keeps it in sync afterwards.
 */
export function useTheme() {
    const [mode, setModeState] = useState<ThemeMode>(() => readStoredMode());
    const [resolvedTheme, setResolvedTheme] = useState<ResolvedTheme>(() => resolve(readStoredMode()));

    useEffect(() => {
        const resolved = resolve(mode);
        setResolvedTheme(resolved);
        applyToDocument(resolved);

        if (mode !== 'system') {
            return;
        }

        const media = window.matchMedia(QUERY);
        const onChange = () => {
            const next = media.matches ? 'dark' : 'light';
            setResolvedTheme(next);
            applyToDocument(next);
        };
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [mode]);

    const setMode = useCallback((next: ThemeMode) => {
        const nextResolved = resolve(next);
        localStorage.setItem(STORAGE_KEY, next);
        setModeState(next);
        setResolvedTheme(nextResolved);
        applyToDocument(nextResolved);
    }, []);

    const toggle = useCallback(() => {
        const current = resolve(mode);
        const next: ThemeMode = current === 'dark' ? 'light' : 'dark';
        setMode(next);
    }, [mode, setMode]);

    return { mode, resolvedTheme, setMode, toggle };
}
