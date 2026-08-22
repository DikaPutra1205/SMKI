import { useTheme } from '@/hooks/useTheme';
import { t } from '@/lib/i18n';
import { Moon, Sun } from 'lucide-react';
import React, { useEffect, useState } from 'react';

export function ThemeToggle({ className }: { className?: string }) {
    const { resolvedTheme, setMode } = useTheme();
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return <div className={`h-9 w-9 rounded-[10px] bg-slate-100 dark:bg-white/5 ${className ?? ''}`} />;
    }

    const isDark = resolvedTheme === 'dark';

    const handleToggle = (e: React.MouseEvent<HTMLButtonElement>) => {
        const nextTheme = isDark ? 'light' : 'dark';

        if (typeof document === 'undefined' || !('startViewTransition' in document)) {
            setMode(nextTheme);
            return;
        }

        const rect = e.currentTarget.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        const endRadius = Math.hypot(
            Math.max(x, window.innerWidth - x),
            Math.max(y, window.innerHeight - y)
        );

        // Light -> Dark: expands outwards. Dark -> Light: shrinks inwards into the button.
        const isShrinking = isDark;

        if (isShrinking) {
            document.documentElement.classList.add('theme-transition-shrink');
        } else {
            document.documentElement.classList.remove('theme-transition-shrink');
        }

        const transition = (document as unknown as {
            startViewTransition: (cb: () => void) => { ready: Promise<void>; finished?: Promise<void> };
        }).startViewTransition(() => {
            setMode(nextTheme);
        });

        transition.ready.then(() => {
            const clipPath = isShrinking
                ? [
                      `circle(${endRadius}px at ${x}px ${y}px)`,
                      `circle(0px at ${x}px ${y}px)`,
                  ]
                : [
                      `circle(0px at ${x}px ${y}px)`,
                      `circle(${endRadius}px at ${x}px ${y}px)`,
                  ];

            const anim = document.documentElement.animate(
                {
                    clipPath: clipPath,
                },
                {
                    duration: 650,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    pseudoElement: isShrinking ? '::view-transition-old(root)' : '::view-transition-new(root)',
                }
            );

            anim.onfinish = () => {
                document.documentElement.classList.remove('theme-transition-shrink');
            };
        });

        if (transition.finished) {
            transition.finished.finally(() => {
                document.documentElement.classList.remove('theme-transition-shrink');
            });
        }
    };

    return (
        <button
            type="button"
            onClick={handleToggle}
            className={`border-border text-body hover:bg-surface hover:text-navy relative flex h-9 w-9 items-center justify-center rounded-[10px] border bg-white shadow-xs transition-transform duration-200 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white ${className ?? ''}`}
            aria-label={t('layout.toggleTheme')}
            title={t('layout.toggleTheme')}
        >
            <Sun
                className={`absolute h-4.5 w-4.5 text-amber-500 transition-all duration-500 ease-out ${
                    isDark
                        ? 'scale-0 rotate-90 opacity-0'
                        : 'scale-100 rotate-0 opacity-100'
                }`}
            />
            <Moon
                className={`absolute h-4.5 w-4.5 text-indigo-400 transition-all duration-500 ease-out ${
                    isDark
                        ? 'scale-100 rotate-0 opacity-100'
                        : 'scale-0 -rotate-90 opacity-0'
                }`}
            />
        </button>
    );
}
