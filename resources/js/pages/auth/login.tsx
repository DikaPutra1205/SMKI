import AuthShell from '@/components/auth/AuthShell';
import { t } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, Check, CheckCircle2, Eye, EyeOff, Loader2, Lock, Mail } from 'lucide-react';
import { useState } from 'react';

type Props = {
    /** Set to `password-updated` by the reset flow so we can confirm the change. */
    status?: string | null;
};

export default function Login({ status }: Props) {
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
            <Head title="Masuk - SIPATUH" />
            <AuthShell>
                <h1 className="text-navy text-2xl font-bold tracking-tight dark:text-white">{t('auth.welcomeBack')}</h1>
                <p className="text-muted mt-1.5 text-sm dark:text-slate-400">{t('auth.welcomeBackSubtitle')}</p>

                {status === 'password-updated' && (
                    <div
                        role="status"
                        className="border-success-border bg-success-bg text-success mt-5 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium dark:border-emerald-800 dark:text-emerald-400"
                    >
                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{t('auth.passwordUpdated')}</span>
                    </div>
                )}

                {formError && (
                    <div
                        role="alert"
                        className="animate-in fade-in slide-in-from-bottom-2 bg-danger-bg border-danger-border text-danger mt-5 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium dark:border-red-800 dark:text-red-400"
                    >
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{formError}</span>
                    </div>
                )}

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <div>
                        <label htmlFor="email" className="text-navy mb-1.5 block text-xs font-semibold dark:text-white">
                            {t('auth.login.email')}
                        </label>
                        <div className="relative">
                            <Mail className="text-faint pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                            <input
                                id="email"
                                type="email"
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="nama@perusahaan.co.id"
                                autoFocus
                                className={`focus:ring-primary/20 h-11 w-full rounded-xl border bg-white pr-3 pl-10 text-sm transition-colors focus:ring-2 focus:outline-none dark:bg-slate-900 ${
                                    emailFieldError
                                        ? 'border-danger focus:border-danger focus:ring-danger/20 dark:border-red-700 dark:focus:border-red-500 dark:focus:ring-red-500/20'
                                        : 'border-border-strong focus:border-primary dark:border-slate-600'
                                }`}
                            />
                        </div>
                        {emailFieldError && <p className="text-danger mt-1.5 text-xs font-medium dark:text-red-400">{emailFieldError}</p>}
                    </div>

                    <div>
                        <label htmlFor="password" className="text-navy mb-1.5 block text-xs font-semibold dark:text-white">
                            {t('auth.login.password')}
                        </label>
                        <div className="relative">
                            <Lock className="text-faint pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                className={`focus:ring-primary/20 h-11 w-full rounded-xl border bg-white pr-11 pl-10 text-sm transition-colors focus:ring-2 focus:outline-none dark:bg-slate-900 ${
                                    errors.password
                                        ? 'border-danger focus:border-danger focus:ring-danger/20 dark:border-red-700 dark:focus:border-red-500 dark:focus:ring-red-500/20'
                                        : 'border-border-strong focus:border-primary dark:border-slate-600'
                                }`}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((v) => !v)}
                                className="text-faint hover:text-muted absolute top-1/2 right-3 -translate-y-1/2 transition-colors dark:text-slate-500 dark:hover:text-slate-300"
                                aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')}
                            >
                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                        {errors.password && <p className="text-danger mt-1.5 text-xs font-medium dark:text-red-400">{errors.password}</p>}
                    </div>

                    <div className="flex items-center justify-between pt-1">
                        <label className="text-body group flex cursor-pointer items-center gap-2.5 text-[13px] select-none dark:text-slate-300">
                            <input type="checkbox" className="peer sr-only" />
                            <span className="border-border-strong group-focus-within:ring-primary/30 group-has-checked:border-primary group-has-checked:bg-primary dark:group-has-checked:border-primary dark:group-has-checked:bg-primary flex h-[18px] w-[18px] items-center justify-center rounded-[6px] border bg-white transition-all group-focus-within:ring-2 dark:border-slate-600 dark:bg-slate-800">
                                <Check
                                    className="h-3 w-3 shrink-0 text-white opacity-0 transition-opacity group-has-checked:opacity-100"
                                    strokeWidth={3.5}
                                />
                            </span>
                            {t('auth.rememberMe')}
                        </label>
                        <Link
                            href={route('password.request')}
                            className="text-primary hover:text-primary-700 dark:hover:text-primary-200 text-[13px] font-semibold transition-colors"
                        >
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
            </AuthShell>
        </>
    );
}
