import { t } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Lock, Mail, Shield, TrendingUp } from 'lucide-react';
import { useState } from 'react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const [showPassword, setShowPassword] = useState(false);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('login'));
    }

    return (
        <>
            <Head title="Masuk - SMKI" />
            <div className="flex min-h-screen flex-col bg-white lg:flex-row">
                {/* Left brand panel */}
                <aside className="from-navy relative flex flex-col justify-between overflow-hidden bg-gradient-to-b to-[#001A30] px-8 py-8 text-white lg:w-[44%] lg:px-14 lg:py-12">
                    <div
                        className="absolute inset-0 opacity-40"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle at 20% 10%, rgba(25,110,205,.35) 0, transparent 45%), radial-gradient(circle at 85% 90%, rgba(25,110,205,.22) 0, transparent 40%)',
                        }}
                    />

                    <div className="relative flex items-center gap-3">
                        <div className="bg-primary shadow-blue flex h-11 w-11 items-center justify-center rounded-xl text-white">
                            <Shield className="h-6 w-6 fill-white/20" />
                        </div>
                        <div>
                            <strong className="block text-[17px] font-bold">{t('layout.brand')}</strong>
                            <span className="text-primary-200 block text-[11px] font-semibold tracking-[.12em] uppercase">{t('layout.suite')}</span>
                        </div>
                    </div>

                    <div className="relative hidden lg:block">
                        <h2 className="text-2xl leading-snug font-bold">
                            {t('auth.brandPanel.headlineBefore')}{' '}
                            <em className="text-primary-200 not-italic">{t('auth.brandPanel.headlineHighlight')}</em>
                        </h2>
                        <p className="mt-3 max-w-md text-sm leading-relaxed text-[#B9D1E6]">{t('auth.brandPanel.description')}</p>

                        <div className="mt-8 flex flex-wrap gap-2">
                            {[
                                { icon: TrendingUp, label: t('auth.brandPanel.features.auditable') },
                                { icon: Shield, label: t('auth.brandPanel.features.centralized') },
                                { icon: Lock, label: t('auth.brandPanel.features.notifications') },
                            ].map((f) => (
                                <span
                                    key={f.label}
                                    className="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-medium text-[#B9D1E6]"
                                >
                                    <f.icon className="h-3.5 w-3.5" />
                                    {f.label}
                                </span>
                            ))}
                        </div>
                    </div>

                    <p className="relative text-xs text-[#7D9BB5]">{t('auth.brandPanel.copyright')}</p>
                </aside>

                {/* Right form panel */}
                <main className="bg-surface flex flex-1 items-center justify-center px-6 py-10">
                    <div className="w-full max-w-md">
                        <div className="border-border rounded-[18px] border bg-white p-8 shadow-md">
                            <div className="mb-6 flex items-center gap-3 lg:hidden">
                                <div className="bg-primary flex h-10 w-10 items-center justify-center rounded-xl text-white">
                                    <Shield className="h-5 w-5 fill-white/20" />
                                </div>
                                <div>
                                    <strong className="text-navy block text-[15px] font-bold">{t('layout.brand')}</strong>
                                    <span className="text-faint block text-[11px] tracking-[.12em] uppercase">{t('layout.suite')}</span>
                                </div>
                            </div>

                            <h1 className="text-navy text-xl font-bold">{t('auth.welcomeBack')}</h1>
                            <p className="ac-sub text-muted mt-1 text-sm">{t('auth.welcomeBackSubtitle')}</p>

                            <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                                <div>
                                    <label htmlFor="email" className="text-navy mb-1.5 block text-xs font-semibold">
                                        {t('auth.login.email')}
                                    </label>
                                    <div className="relative">
                                        <Mail className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="nama@perusahaan.co.id"
                                            autoFocus
                                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-11 w-full rounded-[10px] border bg-white pr-3 pl-10 text-sm focus:ring-2 focus:outline-none"
                                        />
                                    </div>
                                    {errors.email && <p className="text-danger mt-1 text-xs font-medium">{errors.email}</p>}
                                </div>

                                <div>
                                    <label htmlFor="password" className="text-navy mb-1.5 block text-xs font-semibold">
                                        {t('auth.login.password')}
                                    </label>
                                    <div className="relative">
                                        <Lock className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="••••••••"
                                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-11 w-full rounded-[10px] border bg-white pr-10 pl-10 text-sm focus:ring-2 focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((v) => !v)}
                                            className="text-faint hover:text-muted absolute top-1/2 right-3 -translate-y-1/2"
                                            aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')}
                                        >
                                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                    {errors.password && <p className="text-danger mt-1 text-xs font-medium">{errors.password}</p>}
                                </div>

                                <div className="flex items-center justify-between">
                                    <label className="text-body flex items-center gap-2 text-[13px]">
                                        <input type="checkbox" className="border-border-strong accent-primary h-4 w-4 rounded" />
                                        {t('auth.rememberMe')}
                                    </label>
                                    <Link href={route('password.request')} className="text-primary hover:text-primary-700 text-[13px] font-semibold">
                                        {t('auth.login.forgotPassword')}
                                    </Link>
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-primary shadow-blue hover:bg-primary-700 mt-1 inline-flex h-11 w-full items-center justify-center gap-2 rounded-[10px] text-sm font-semibold text-white transition-colors disabled:opacity-60"
                                >
                                    {t('auth.login.submit')}
                                    <svg
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
                                </button>
                            </form>
                        </div>

                        <p className="text-muted mt-5 text-center text-[13px]">
                            {t('auth.noAccount')}{' '}
                            <a href="#" className="text-primary font-semibold">
                                {t('auth.contactAdmin')}
                            </a>
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
