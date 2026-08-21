import { t } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, Eye, EyeOff, Loader2, Lock, Mail, Shield, ShieldCheck, TrendingUp } from 'lucide-react';
import { useState } from 'react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const [showPassword, setShowPassword] = useState(false);

    // Backend attaches the combined credential failure ("Email atau password salah.")
    // to the `email` key — surface it as a form-level alert, not an email-field error.
    const formError = errors.email?.toLowerCase().includes('password') ? errors.email : null;
    const emailFieldError = formError ? null : errors.email;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('login'));
    }

    return (
        <>
            <Head title="Masuk - SMKI" />
            <div className="flex min-h-screen flex-col bg-white dark:bg-slate-900 lg:flex-row">
                {/* Left brand panel */}
                <aside className="from-navy relative flex flex-col justify-between overflow-hidden bg-gradient-to-b to-[#001A30] px-8 py-8 text-white lg:w-[46%] lg:px-14 lg:py-12">
                    {/* Ambient glows */}
                    <div
                        aria-hidden
                        className="absolute inset-0"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle at 18% 8%, rgba(25,110,205,.38) 0, transparent 45%), radial-gradient(circle at 85% 92%, rgba(25,110,205,.24) 0, transparent 42%)',
                        }}
                    />
                    {/* Grid texture */}
                    <div
                        aria-hidden
                        className="absolute inset-0 opacity-[0.05]"
                        style={{
                            backgroundImage:
                                'linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px)',
                            backgroundSize: '44px 44px',
                        }}
                    />
                    {/* Floating orb */}
                    <div aria-hidden className="bg-primary/25 absolute -top-28 -right-28 h-80 w-80 rounded-full blur-3xl" />

                    <div className="animate-in fade-in relative flex items-center gap-3 duration-500">
                        <div className="bg-primary shadow-blue ring-white/25 flex h-11 w-11 items-center justify-center rounded-xl text-white ring-1 ring-inset">
                            <Shield className="h-6 w-6 fill-white/20" />
                        </div>
                        <strong className="block text-[17px] font-bold tracking-tight">{t('layout.brand')}</strong>
                    </div>

                    <div className="relative my-10 hidden lg:block">
                        <span className="border-white/15 text-primary-100 inline-flex items-center gap-2 rounded-full border bg-white/5 px-3 py-1.5 text-[11px] font-bold tracking-[0.18em] uppercase">
                            <ShieldCheck className="h-3.5 w-3.5" />
                            {t('auth.brandPanel.eyebrow')}
                        </span>

                        <h2 className="mt-5 max-w-lg text-[32px] leading-[1.15] font-extrabold tracking-tight text-white">
                            {t('auth.brandPanel.headlineBefore')}{' '}
                            <em className="from-primary-200 to-sky-300 bg-gradient-to-r bg-clip-text not-italic text-transparent">
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
                                    className="animate-in fade-in slide-in-from-bottom-2 border-white/10 flex items-center gap-3 rounded-xl border bg-white/[0.06] px-4 py-3 backdrop-blur-sm"
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

                {/* Right form panel */}
                <main className="bg-surface dark:bg-slate-900 relative flex flex-1 items-center justify-center overflow-hidden px-6 py-10">
                    <div
                        aria-hidden
                        className="absolute inset-0"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle at 82% -5%, rgba(25,110,205,.09) 0, transparent 42%), radial-gradient(circle at 0% 105%, rgba(25,110,205,.07) 0, transparent 45%)',
                        }}
                    />

                    <div className="relative w-full max-w-md">
                        <div className="animate-in fade-in slide-in-from-bottom-4 ring-navy/5 dark:ring-white/10 duration-500 rounded-[20px] border bg-white dark:bg-slate-900 p-8 shadow-lg ring-1 sm:p-10">
                            {/* Mobile brand */}
                            <div className="mb-6 flex items-center gap-3 lg:hidden">
                                <div className="bg-primary shadow-blue flex h-10 w-10 items-center justify-center rounded-xl text-white">
                                    <Shield className="h-5 w-5 fill-white/20" />
                                </div>
                                <strong className="text-navy dark:text-white block text-[15px] font-bold tracking-tight">{t('layout.brand')}</strong>
                            </div>

                            <h1 className="text-navy dark:text-white text-2xl font-bold tracking-tight">{t('auth.welcomeBack')}</h1>
                            <p className="text-muted dark:text-slate-400 mt-1.5 text-sm">{t('auth.welcomeBackSubtitle')}</p>

                            {formError && (
                                <div
                                    role="alert"
                                    className="animate-in fade-in slide-in-from-bottom-2 bg-danger-bg border-danger-border dark:border-red-800 text-danger dark:text-red-400 mt-5 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium"
                                >
                                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{formError}</span>
                                </div>
                            )}

                            <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                                <div>
                                    <label htmlFor="email" className="text-navy dark:text-white mb-1.5 block text-xs font-semibold">
                                        {t('auth.login.email')}
                                    </label>
                                    <div className="relative">
                                        <Mail className="text-faint dark:text-slate-500 pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2" />
                                        <input
                                            id="email"
                                            type="email"
                                            autoComplete="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="nama@perusahaan.co.id"
                                            autoFocus
                                            className={`focus:ring-primary/20 h-11 w-full rounded-xl border bg-white dark:bg-slate-900 pr-3 pl-10 text-sm transition-colors focus:ring-2 focus:outline-none ${
                                                emailFieldError
                                                    ? 'border-danger dark:border-red-700 focus:border-danger dark:focus:border-red-500 focus:ring-danger/20 dark:focus:ring-red-500/20'
                                                    : 'border-border-strong dark:border-slate-600 focus:border-primary'
                                            }`}
                                        />
                                    </div>
                                    {emailFieldError && <p className="text-danger dark:text-red-400 mt-1.5 text-xs font-medium">{emailFieldError}</p>}
                                </div>

                                <div>
                                    <label htmlFor="password" className="text-navy dark:text-white mb-1.5 block text-xs font-semibold">
                                        {t('auth.login.password')}
                                    </label>
                                    <div className="relative">
                                        <Lock className="text-faint dark:text-slate-500 pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2" />
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            autoComplete="current-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="••••••••"
                                            className={`focus:ring-primary/20 h-11 w-full rounded-xl border bg-white dark:bg-slate-900 pr-11 pl-10 text-sm transition-colors focus:ring-2 focus:outline-none ${
                                                errors.password
                                                    ? 'border-danger dark:border-red-700 focus:border-danger dark:focus:border-red-500 focus:ring-danger/20 dark:focus:ring-red-500/20'
                                                    : 'border-border-strong dark:border-slate-600 focus:border-primary'
                                            }`}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((v) => !v)}
                                            className="text-faint dark:text-slate-500 hover:text-muted dark:hover:text-slate-300 absolute top-1/2 right-3 -translate-y-1/2 transition-colors"
                                            aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')}
                                        >
                                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                    {errors.password && <p className="text-danger dark:text-red-400 mt-1.5 text-xs font-medium">{errors.password}</p>}
                                </div>

                                <div className="flex items-center justify-between pt-1">
                                    <label className="text-body dark:text-slate-300 flex cursor-pointer items-center gap-2 text-[13px] select-none">
                                        <input type="checkbox" className="border-border-strong dark:border-slate-600 accent-primary h-4 w-4 rounded" />
                                        {t('auth.rememberMe')}
                                    </label>
                                    <Link href={route('password.request')} className="text-primary hover:text-primary-700 dark:hover:text-primary-200 text-[13px] font-semibold transition-colors">
                                        {t('auth.login.forgotPassword')}
                                    </Link>
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="group bg-primary shadow-blue hover:bg-primary-700 mt-2 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold text-white transition-all hover:brightness-105 active:scale-[0.99] disabled:pointer-events-none disabled:opacity-60"
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            {t('auth.login.submit')}
                                        </>
                                    ) : (
                                        <>
                                            {t('auth.login.submit')}
                                            <svg
                                                className="transition-transform group-hover:translate-x-0.5"
                                                width="16"
                                                height="16"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth="2"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                            >
                                                <line x1="5" x2="19" y1="12" y2="12" />
                                                <polyline points="12 5 19 12 12 19" />
                                            </svg>
                                        </>
                                    )}
                                </button>
                            </form>
                        </div>

                        <p className="text-faint dark:text-slate-500 mt-6 flex items-center justify-center gap-1.5 text-xs">
                            <Lock className="h-3 w-3" />
                            {t('auth.secureNote')}
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
