import AuthShell from '@/components/auth/AuthShell';
import { t } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, KeyRound, Loader2, Mail } from 'lucide-react';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        // On success the server redirects to the OTP step, so there is no local
        // success state to flip here.
        post(route('password.request'));
    }

    return (
        <>
            <Head title="Lupa Password - SMKI" />
            <AuthShell>
                <div className="bg-primary-50 dark:bg-primary/10 text-primary mb-4 flex h-12 w-12 items-center justify-center rounded-xl">
                    <KeyRound className="h-6 w-6" />
                </div>
                <h1 className="text-navy text-xl font-bold tracking-tight dark:text-white">{t('auth.forgot.title')}</h1>
                <p className="text-muted mt-1.5 text-sm dark:text-slate-400">{t('auth.forgot.subtitle')}</p>

                {errors.email && (
                    <div
                        role="alert"
                        className="bg-danger-bg border-danger-border text-danger mt-4 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium dark:border-red-800 dark:text-red-400"
                    >
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{errors.email}</span>
                    </div>
                )}

                <form onSubmit={submit} className="mt-5 flex flex-col gap-4">
                    <div>
                        <label htmlFor="email" className="text-navy mb-1.5 block text-xs font-semibold dark:text-white">
                            {t('auth.forgot.email')}
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
                                className={`focus:ring-primary/20 h-11 w-full rounded-xl border bg-white pr-3 pl-10 text-sm transition-colors focus:ring-2 focus:outline-none dark:bg-slate-900 dark:text-white ${
                                    errors.email
                                        ? 'border-danger focus:border-danger focus:ring-danger/20 dark:border-red-700'
                                        : 'border-border-strong focus:border-primary dark:border-slate-600'
                                }`}
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-primary shadow-blue hover:bg-primary-700 mt-1 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold text-white transition-colors disabled:pointer-events-none disabled:opacity-60"
                    >
                        {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                        {t('auth.forgot.submit')}
                    </button>

                    <Link
                        href={route('login')}
                        className="text-muted hover:text-navy text-center text-sm font-medium transition-colors dark:text-slate-400 dark:hover:text-white"
                    >
                        {t('auth.forgot.backToLogin')}
                    </Link>
                </form>
            </AuthShell>
        </>
    );
}
