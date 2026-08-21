import { useTheme } from '@/hooks/useTheme';
import { t } from '@/lib/i18n';
import { Moon, Sun } from 'lucide-react';

export function ThemeToggle() {
    const { resolvedTheme, toggle } = useTheme();
    const isDark = resolvedTheme === 'dark';

    return (
        <button
            type="button"
            onClick={toggle}
            className="border-border text-body hover:bg-surface hover:text-navy dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white relative flex h-9 w-9 items-center justify-center rounded-[10px] border bg-white shadow-sm transition-colors dark:bg-white/5"
            aria-label={t('layout.toggleTheme')}
            title={t('layout.toggleTheme')}
        >
            <Sun className={`absolute h-4.5 w-4.5 transition-all duration-200 ${isDark ? 'scale-0 rotate-90 opacity-0' : 'scale-100 rotate-0 opacity-100'}`} />
            <Moon className={`absolute h-4.5 w-4.5 transition-all duration-200 ${isDark ? 'scale-100 rotate-0 opacity-100' : 'scale-0 -rotate-90 opacity-0'}`} />
        </button>
    );
}
