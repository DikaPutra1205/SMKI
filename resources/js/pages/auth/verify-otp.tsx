import AuthShell from '@/components/auth/AuthShell';
import { t } from '@/lib/i18n';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, KeyRound, Loader2, MailCheck, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Props = {
    email: string;
    /** ISO-8601 instant the mailed code stops being accepted, or null once lapsed. */
    expiresAt: string | null;
    /** ISO-8601 instant a replacement code may be requested, or null when eligible now. */
    resendAvailableAt: string | null;
};

function formatCountdown(total: number): string {
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

export default function VerifyOtp({ email, expiresAt, resendAvailableAt }: Props) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({ code: '' });

    // One ticking clock for both countdowns. Deriving them from absolute
    // deadlines rather than decrementing a counter means they stay correct when
    // the tab is backgrounded and the interval is throttled.
    const [now, setNow] = useState(() => Date.now());
    const [resending, setResending] = useState(false);
    const codeRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(id);
    }, []);

    useEffect(() => {
        codeRef.current?.focus();
    }, []);

    const secondsUntil = (iso: string | null) => (iso ? Math.max(0, Math.ceil((new Date(iso).getTime() - now) / 1000)) : 0);

    const secondsLeft = secondsUntil(expiresAt);
    const resendIn = secondsUntil(resendAvailableAt);
    const expired = secondsLeft === 0;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        clearErrors('code');
        post(route('password.verify'));
    }

    function resend() {
        setResending(true);
        router.post(
            route('password.request'),
            { email },
            {
                preserveScroll: true,
                onFinish: () => setResending(false),
            },
        );
    }

    return (
        <>
            <Head title="Verifikasi Kode - SMKI" />
            <AuthShell>
                <div className="bg-primary-50 dark:bg-primary/10 text-primary mb-4 flex h-12 w-12 items-center justify-center rounded-xl">
                    <MailCheck className="h-6 w-6" />
                </div>
                <h1 className="text-navy text-xl font-bold tracking-tight dark:text-white">{t('auth.verifyOtp.title')}</h1>
                <p className="text-muted mt-1.5 text-sm dark:text-slate-400">{t('auth.verifyOtp.subtitle')}</p>

                <p className="text-faint mt-4 rounded-[10px] bg-[#F5F8FC] px-3 py-2 text-xs dark:bg-slate-800 dark:text-slate-400">
                    {t('auth.verifyOtp.emailLabel')} <span className="text-navy font-semibold dark:text-slate-200">{email}</span>
                </p>

                {expired && (
                    <div
                        role="alert"
                        className="border-warning-border bg-warning-bg text-warning mt-4 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium dark:border-amber-800 dark:text-amber-300"
                    >
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            <strong className="block font-semibold">{t('auth.verifyOtp.expiredTitle')}</strong>
                            {t('auth.verifyOtp.expiredBody')}
                        </span>
                    </div>
                )}

                {errors.code && (
                    <div
                        role="alert"
                        className="bg-danger-bg border-danger-border text-danger mt-4 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium dark:border-red-800 dark:text-red-400"
                    >
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{errors.code}</span>
                    </div>
                )}

                <form onSubmit={submit} className="mt-5 flex flex-col gap-4">
                    <div>
                        <label htmlFor="code" className="text-navy mb-1.5 block text-xs font-semibold dark:text-white">
                            {t('auth.verifyOtp.code')}
                        </label>
                        <div className="relative">
                            <KeyRound className="text-faint pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                            <input
                                id="code"
                                ref={codeRef}
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={data.code}
                                onChange={(e) => {
                                    setData('code', e.target.value.replace(/\D/g, '').slice(0, 6));
                                    clearErrors('code');
                                }}
                                placeholder="••••••"
                                disabled={expired}
                                className={`focus:ring-primary/20 h-12 w-full rounded-xl border bg-white pr-3 pl-11 text-center font-mono text-lg font-semibold tracking-[0.55em] transition-colors focus:ring-2 focus:outline-none disabled:opacity-50 dark:bg-slate-900 dark:text-white ${
                                    errors.code
                                        ? 'border-danger focus:border-danger focus:ring-danger/20 dark:border-red-700'
                                        : 'border-border-strong focus:border-primary dark:border-slate-600'
                                }`}
                            />
                        </div>
                        <p aria-live="polite" className="text-faint mt-1.5 text-xs dark:text-slate-500">
                            {expired ? (
                                <span className="text-danger font-medium dark:text-red-400">{t('auth.verifyOtp.expiredTitle')}</span>
                            ) : (
                                <>
                                    {t('auth.verifyOtp.expiresIn')}{' '}
                                    <span className="text-navy font-semibold tabular-nums dark:text-slate-300">{formatCountdown(secondsLeft)}</span>
                                </>
                            )}
                        </p>
                    </div>

                    <button
                        type="submit"
                        disabled={processing || expired || data.code.length !== 6}
                        className="bg-primary shadow-blue hover:bg-primary-700 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold text-white transition-colors disabled:pointer-events-none disabled:opacity-60"
                    >
                        {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                        {t('auth.verifyOtp.submit')}
                    </button>

                    <div className="flex flex-col items-center gap-2">
                        <button
                            type="button"
                            onClick={resend}
                            disabled={resending || resendIn > 0}
                            className="text-primary hover:text-primary-700 disabled:text-faint dark:hover:text-primary-200 inline-flex items-center gap-1.5 text-[13px] font-semibold transition-colors disabled:pointer-events-none disabled:dark:text-slate-500"
                        >
                            {resending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
                            {resendIn > 0 ? `${t('auth.verifyOtp.resendIn')} ${formatCountdown(resendIn)}` : t('auth.verifyOtp.resend')}
                        </button>

                        <Link
                            href={route('password.request')}
                            className="text-muted hover:text-navy inline-flex items-center gap-1.5 text-[13px] font-medium transition-colors dark:text-slate-400 dark:hover:text-white"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            {t('auth.verifyOtp.changeEmail')}
                        </Link>
                    </div>
                </form>
            </AuthShell>
        </>
    );
}
