import { t } from '@/lib/i18n';
import { Shield, TrendingUp, Lock } from 'lucide-react';

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
        <div className="flex min-h-screen flex-col bg-white lg:flex-row dark:bg-[#00101f]">
            {/* ── Left brand panel ─────────────────────────────────────── */}
            <aside className="from-navy relative hidden flex-col justify-between overflow-hidden bg-gradient-to-b to-[#001A30] px-8 py-8 text-white lg:flex lg:w-[46%] lg:px-14 lg:py-12">
                {/* 
                    Orbs (Flashing circles) - Toned down to be less "AI"
                    Lower opacity, slower animations, deeper colors 
                */}
                <div
                    aria-hidden
                    className="absolute inset-0"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 18% 8%, rgba(25,110,205,.15) 0, transparent 50%), radial-gradient(circle at 85% 92%, rgba(25,110,205,.12) 0, transparent 50%)',
                    }}
                />
                
                <div
                    aria-hidden
                    className="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-blue-500/10 blur-3xl"
                    style={{ animation: 'pulse 8s ease-in-out infinite' }}
                />
                <div
                    aria-hidden
                    className="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl"
                    style={{ animation: 'pulse 12s ease-in-out infinite' }}
                />

                {/* Crossed lines (Grid texture) */}
                <div
                    aria-hidden
                    className="absolute inset-0 opacity-[0.03]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px)',
                        backgroundSize: '44px 44px',
                    }}
                />

                {/* Brand logo - Cleaned up */}
                <div className="relative flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary text-white">
                        <Shield className="h-6 w-6" />
                    </div>
                    <div>
                        <strong className="block text-[17px] font-bold tracking-tight">{t('layout.brand')}</strong>
                        <span className="block text-[11px] font-semibold tracking-[0.18em] text-primary-200 uppercase opacity-80">
                            {t('layout.suite')}
                        </span>
                    </div>
                </div>

                {/* Hero content */}
                <div className="relative my-10 hidden lg:block">
                    {/* The pill above the heading has been deliberately removed */}
                    
                    <h2 className="mt-5 max-w-lg text-[32px] font-bold leading-snug tracking-tight text-white">
                        {t('auth.brandPanel.headlineBefore')}{' '}
                        {/* Replaced the bright AI gradient text with a solid, professional accent color */}
                        <span className="text-primary-300">
                            {t('auth.brandPanel.headlineHighlight')}
                        </span>
                    </h2>

                    <p className="mt-4 max-w-sm text-[14px] leading-relaxed text-slate-300">
                        {t('auth.brandPanel.footerQuote')}
                    </p>

                    {/* Feature list - Removed glass borders/blur for a cleaner, flatter look */}
                    <div className="mt-10 flex max-w-md flex-col gap-4">
                        {[
                            { icon: TrendingUp, label: t('auth.brandPanel.features.auditable') },
                            { icon: Shield, label: t('auth.brandPanel.features.centralized') },
                            { icon: Lock, label: t('auth.brandPanel.features.notifications') },
                        ].map((f, i) => (
                            <div
                                key={f.label}
                                className="animate-in fade-in slide-in-from-bottom-2 flex items-center gap-4"
                                style={{ animationDelay: `${150 + i * 90}ms`, animationFillMode: 'backwards' }}
                            >
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white">
                                    <f.icon className="h-4 w-4" />
                                </div>
                                <span className="text-sm font-medium text-slate-200">{f.label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="relative">
                    <p className="text-xs text-slate-400">{t('auth.brandPanel.copyright')}</p>
                </div>
            </aside>

            {/* ── Right form panel ──────────────────────────────────────── */}
            <main className="relative flex flex-1 items-center justify-center overflow-hidden bg-[#f8fafc] px-6 py-10 dark:bg-[#00101f]">
                {/* Subtle map/dot background to make right side less plain */}
                <div
                    aria-hidden
                    className="absolute inset-0 opacity-[0.4] dark:opacity-[0.1]"
                    style={{
                        backgroundImage: 'radial-gradient(#196ecd 1px, transparent 1px)',
                        backgroundSize: '32px 32px',
                    }}
                />

                <div className="relative w-full max-w-md">
                    {/* The Form Card */}
                    <div className="animate-in fade-in slide-in-from-bottom-4 rounded-[20px] border border-white/60 bg-white/80 p-8 shadow-xl shadow-slate-200/50 backdrop-blur-xl ring-1 ring-slate-900/5 duration-500 sm:p-10 dark:border-white/10 dark:bg-slate-900/80 dark:shadow-2xl dark:shadow-black/50 dark:ring-white/10">
                        {/* Mobile brand */}
                        <div className="mb-6 flex items-center gap-3 lg:hidden">
                            <div className="bg-primary shadow-blue flex h-10 w-10 items-center justify-center rounded-xl text-white">
                                <Shield className="h-5 w-5 fill-white/20" />
                            </div>
                            <div>
                                <strong className="text-navy block text-[15px] font-bold tracking-tight dark:text-white">
                                    {t('layout.brand')}
                                </strong>
                                <span className="text-faint block text-[11px] tracking-[.12em] uppercase dark:text-slate-500">
                                    {t('layout.suite')}
                                </span>
                            </div>
                        </div>

                        {children}
                    </div>

                    <p className="mt-6 flex items-center justify-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <Lock className="h-3 w-3" />
                        {t('auth.secureNote')}
                    </p>
                </div>
            </main>
        </div>
    );
}
