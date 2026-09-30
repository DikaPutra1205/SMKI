import AuthShell from '@/components/auth/AuthShell';
import { t } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Eye, EyeOff, Loader2 } from 'lucide-react';
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
            <Head title="Masuk - SMKI" />
            <AuthShell>
                {/* Heading */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold tracking-tight text-navy dark:text-white">
                        {t('auth.welcomeBack')}
                    </h1>
                    <p className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                        {t('auth.welcomeBackSubtitle')}
                    </p>
                </div>

                {/* Password-updated success banner */}
                {status === 'password-updated' && (
                    <div
                        role="status"
                        className="mb-6 flex items-start gap-3 rounded-lg bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400"
                    >
                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span>{t('auth.passwordUpdated')}</span>
                    </div>
                )}

                {/* Credential error banner */}
                {formError && (
                    <div
                        role="alert"
                        className="mb-6 flex items-start gap-3 rounded-lg bg-red-50 p-4 text-sm font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400"
                    >
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
                        <span>{formError}</span>
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-5">
                    {/* Email */}
                    <div>
                        <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {t('auth.login.email')}
                        </label>
                        <input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="nama@perusahaan.co.id"
                            autoFocus
                            className={`block w-full rounded-lg border px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 ${
                                emailFieldError
                                    ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/20 dark:border-red-700 dark:focus:border-red-500'
                                    : 'border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-primary/20 dark:border-slate-700 dark:focus:border-primary'
                            }`}
                        />
                        {emailFieldError && (
                            <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                                {emailFieldError}
                            </p>
                        )}
                    </div>

                    {/* Password */}
                    <div>
                        <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {t('auth.login.password')}
                        </label>
                        <div className="relative">
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                className={`block w-full rounded-lg border py-2.5 pl-4 pr-11 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 ${
                                    errors.password
                                        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/20 dark:border-red-700 dark:focus:border-red-500'
                                        : 'border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-primary/20 dark:border-slate-700 dark:focus:border-primary'
                                }`}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((v) => !v)}
                                className="absolute right-0 top-0 flex h-full items-center px-3 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
                                aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')}
                            >
                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                        {errors.password && (
                            <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    {/* Remember me + Forgot password */}
                    <div className="flex items-center justify-between pt-1">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:checked:bg-primary dark:focus:ring-offset-slate-900"
                            />
                            <span className="text-sm text-slate-600 dark:text-slate-300">
                                {t('auth.rememberMe')}
                            </span>
                        </label>
                        <Link
                            href={route('password.request')}
                            className="text-sm font-medium text-primary hover:text-primary-700 dark:hover:text-primary-400"
                        >
                            {t('auth.login.forgotPassword')}
                        </Link>
                    </div>

                    {/* Submit */}
                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-4 flex w-full justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-70 dark:focus:ring-offset-slate-900"
                    >
                        {processing ? (
                            <span className="flex items-center gap-2">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                {t('auth.login.submit')}
                            </span>
                        ) : (
                            t('auth.login.submit')
                        )}
                    </button>
                </form>
            </AuthShell>
        </>
    );
}
