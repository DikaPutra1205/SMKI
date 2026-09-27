import { t } from '@/lib/i18n';
import { Lock, Shield, ShieldCheck, TrendingUp } from 'lucide-react';

type AuthShellProps = {
    children: React.ReactNode;
};

/**
 * Split-panel frame shared by every guest auth page: branded gradient rail on
 * the left, form card on the right. Keeps the four password-reset screens
 * visually identical to the login screen without duplicating the rail markup.
 */
export default function AuthShell({ children }: AuthShellProps) {
    return (
        <div className="flex min-h-screen flex-col bg-white lg:flex-row dark:bg-slate-900">
            <aside className="from-navy relative hidden flex-col justify-between overflow-hidden bg-gradient-to-b to-[#001A30] px-8 py-8 text-white lg:flex lg:w-[46%] lg:px-14 lg:py-12">
                <div
                    aria-hidden
                    className="absolute inset-0"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 18% 8%, rgba(25,110,205,.38) 0, transparent 45%), radial-gradient(circle at 85% 92%, rgba(25,110,205,.24) 0, transparent 42%)',
                    }}
                />
                <div
                    aria-hidden
                    className="absolute inset-0 opacity-[0.05]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px)',
                        backgroundSize: '44px 44px',
                    }}
                />
                <div aria-hidden className="bg-primary/25 absolute -top-28 -right-28 h-80 w-80 rounded-full blur-3xl" />

                <div className="animate-in fade-in relative flex items-center gap-3 duration-500">
                    <div className="bg-primary shadow-blue flex h-11 w-11 items-center justify-center rounded-xl text-white ring-1 ring-white/25 ring-inset">
                        <Shield className="h-6 w-6 fill-white/20" />
                    </div>
                    <strong className="block text-[17px] font-bold tracking-tight">{t('layout.brand')}</strong>
                </div>

                <div className="relative my-10 hidden lg:block">
                    <span className="text-primary-100 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-[11px] font-bold tracking-[0.18em] uppercase">
                        <ShieldCheck className="h-3.5 w-3.5" />
                        {t('auth.brandPanel.eyebrow')}
                    </span>

                    <h2 className="mt-5 max-w-lg text-[32px] leading-[1.15] font-extrabold tracking-tight text-white">
                        {t('auth.brandPanel.headlineBefore')}{' '}
                        <em className="from-primary-200 bg-gradient-to-r to-sky-300 bg-clip-text text-transparent not-italic">
                            {t('auth.brandPanel.headlineHighlight')}
                        </em>
                    </h2>

                    <div className="mt-8 flex max-w-md flex-col gap-3">
                        {[
                            { icon: TrendingUp, label: t('auth.brandPanel.features.auditable') },
                            { icon: Shield, label: t('auth.brandPanel.features.centralized') },
                            { icon: Lock, label: t('auth.brandPanel.features.notifications') },
                        ].map((f, i) => (
                            <div
                                key={f.label}
                                className="animate-in fade-in slide-in-from-bottom-2 flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm"
                                style={{ animationDelay: `${150 + i * 90}ms`, animationFillMode: 'backwards' }}
                            >
                                <div className="bg-primary/25 text-primary-200 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                                    <f.icon className="h-4 w-4" />
                                </div>
                                <span className="text-sm font-medium text-white/90">{f.label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="relative">
                    <p className="text-xs text-[#7D9BB5]">{t('auth.brandPanel.copyright')}</p>
                </div>
            </aside>

            <main className="bg-surface relative flex flex-1 items-center justify-center overflow-hidden px-6 py-10 dark:bg-slate-900">
                <div
                    aria-hidden
                    className="absolute inset-0"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 82% -5%, rgba(25,110,205,.09) 0, transparent 42%), radial-gradient(circle at 0% 105%, rgba(25,110,205,.07) 0, transparent 45%)',
                    }}
                />

                <div className="relative w-full max-w-md">
                    <div className="animate-in fade-in slide-in-from-bottom-4 ring-navy/5 rounded-[20px] border bg-white p-8 shadow-lg ring-1 duration-500 sm:p-10 dark:bg-slate-900 dark:ring-white/10">
                        <div className="mb-6 flex items-center gap-3 lg:hidden">
                            <div className="bg-primary shadow-blue flex h-10 w-10 items-center justify-center rounded-xl text-white">
                                <Shield className="h-5 w-5 fill-white/20" />
                            </div>
                            <strong className="text-navy block text-[15px] font-bold tracking-tight dark:text-white">{t('layout.brand')}</strong>
                        </div>

                        {children}
                    </div>

                    <p className="text-faint mt-6 flex items-center justify-center gap-1.5 text-xs dark:text-slate-500">
                        <Lock className="h-3 w-3" />
                        {t('auth.secureNote')}
                    </p>
                </div>
            </main>
        </div>
    );
}
