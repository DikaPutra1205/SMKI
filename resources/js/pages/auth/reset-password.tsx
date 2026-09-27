import AuthShell from '@/components/auth/AuthShell';
import { t } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Check, Eye, EyeOff, Loader2, Lock, ShieldCheck, X } from 'lucide-react';
import { useMemo, useState } from 'react';

type Props = {
    email: string;
    token: string;
};

const RULES = [
    { key: 'length', test: (v: string) => v.length >= 8 },
    { key: 'mixedCase', test: (v: string) => /[a-z]/.test(v) && /[A-Z]/.test(v) },
    { key: 'numbers', test: (v: string) => /\d/.test(v) },
    { key: 'symbols', test: (v: string) => /[^A-Za-z0-9]/.test(v) },
] as const;

export default function ResetPassword({ email, token }: Props) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        email,
        token,
        password: '',
        password_confirmation: '',
    });

    const [showPassword, setShowPassword] = useState(false);

    const results = useMemo(() => RULES.map((r) => ({ key: r.key, passed: r.test(data.password) })), [data.password]);
    const passedCount = results.filter((r) => r.passed).length;

    const allRulesMet = passedCount === RULES.length;
    const confirmationMismatch = data.password_confirmation.length > 0 && data.password !== data.password_confirmation;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('password.update'));
    }

    function fieldError(name: 'password' | 'password_confirmation') {
        return errors[name] ?? null;
    }

    return (
        <>
            <Head title="Kata Sandi Baru - SMKI" />
            <AuthShell>
                <div className="bg-primary-50 dark:bg-primary/10 text-primary mb-4 flex h-12 w-12 items-center justify-center rounded-xl">
                    <ShieldCheck className="h-6 w-6" />
                </div>
                <h1 className="text-navy text-xl font-bold tracking-tight dark:text-white">{t('auth.resetPassword.title')}</h1>
                <p className="text-muted mt-1.5 text-sm dark:text-slate-400">{t('auth.resetPassword.subtitle')}</p>

                <p className="text-faint mt-4 rounded-[10px] bg-[#F5F8FC] px-3 py-2 text-xs dark:bg-slate-800 dark:text-slate-400">
                    {t('auth.resetPassword.emailLabel')} <span className="text-navy font-semibold dark:text-slate-200">{email}</span>
                </p>

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
                        <label htmlFor="password" className="text-navy mb-1.5 block text-xs font-semibold dark:text-white">
                            {t('auth.resetPassword.password')}
                        </label>
                        <div className="relative">
                            <Lock className="text-faint pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => {
                                    setData('password', e.target.value);
                                    clearErrors('password', 'password_confirmation');
                                }}
                                className={`focus:ring-primary/20 h-11 w-full rounded-xl border bg-white pr-11 pl-10 text-sm transition-colors focus:ring-2 focus:outline-none dark:bg-slate-900 dark:text-white ${
                                    fieldError('password')
                                        ? 'border-danger focus:border-danger focus:ring-danger/20 dark:border-red-700'
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
                    </div>

                    {/* Rule checklist */}
                    <div className="rounded-[10px] bg-[#F5F8FC] px-3 py-2.5 dark:bg-slate-800">
                        <p className="text-faint mb-1.5 text-[11px] font-semibold tracking-wide uppercase dark:text-slate-400">
                            {t('auth.resetPassword.requirements')}
                        </p>
                        <ul className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                            {results.map((rule) => (
                                <li
                                    key={rule.key}
                                    className={`flex items-center gap-1.5 text-[11.5px] transition-colors ${
                                        rule.passed ? 'text-success dark:text-emerald-400' : 'text-faint dark:text-slate-500'
                                    }`}
                                >
                                    {rule.passed ? <Check className="h-3 w-3 shrink-0" strokeWidth={3.5} /> : <X className="h-3 w-3 shrink-0" />}
                                    {t(`auth.resetPassword.rules.${rule.key}`)}
                                </li>
                            ))}
                        </ul>
                    </div>

                    {fieldError('password') && <p className="text-danger text-xs font-medium dark:text-red-400">{fieldError('password')}</p>}

                    <div>
                        <label htmlFor="password_confirmation" className="text-navy mb-1.5 block text-xs font-semibold dark:text-white">
                            {t('auth.resetPassword.confirmPassword')}
                        </label>
                        <div className="relative">
                            <Lock className="text-faint pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                            <input
                                id="password_confirmation"
                                type={showPassword ? 'text' : 'password'}
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => {
                                    setData('password_confirmation', e.target.value);
                                    clearErrors('password');
                                }}
                                className={`focus:ring-primary/20 h-11 w-full rounded-xl border bg-white pr-3 pl-10 text-sm transition-colors focus:ring-2 focus:outline-none dark:bg-slate-900 dark:text-white ${
                                    confirmationMismatch || fieldError('password')
                                        ? 'border-danger focus:border-danger focus:ring-danger/20 dark:border-red-700'
                                        : 'border-border-strong focus:border-primary dark:border-slate-600'
                                }`}
                            />
                        </div>
                        {confirmationMismatch && (
                            <p className="text-danger mt-1.5 text-xs font-medium dark:text-red-400">{t('auth.resetPassword.mismatch')}</p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing || !allRulesMet || confirmationMismatch}
                        className="bg-primary shadow-blue hover:bg-primary-700 mt-1 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold text-white transition-colors disabled:pointer-events-none disabled:opacity-60"
                    >
                        {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                        {t('auth.resetPassword.submit')}
                    </button>

                    <Link
                        href={route('password.verify')}
                        className="text-muted hover:text-navy inline-flex items-center justify-center gap-1.5 text-[13px] font-medium transition-colors dark:text-slate-400 dark:hover:text-white"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        {t('auth.resetPassword.backToVerify')}
                    </Link>
                </form>
            </AuthShell>
        </>
    );
}
