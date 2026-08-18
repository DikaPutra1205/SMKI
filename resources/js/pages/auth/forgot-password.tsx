import { t } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { KeyRound, Mail, Shield } from 'lucide-react';
import { useState } from 'react';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });
    const [sent, setSent] = useState(false);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('password.request'), {
            onSuccess: () => setSent(true),
        });
    }

    return (
        <>
            <Head title="Lupa Password" />
            <div className="flex min-h-screen flex-col bg-white lg:flex-row">
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

                    <p className="relative hidden text-sm leading-relaxed text-[#B9D1E6] lg:block">{t('auth.brandPanel.footerQuote')}</p>

                    <p className="relative text-xs text-[#7D9BB5]">{t('auth.brandPanel.copyright')}</p>
                </aside>

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

                            <div className="bg-primary-50 text-primary mb-4 flex h-12 w-12 items-center justify-center rounded-xl">
                                <KeyRound className="h-6 w-6" />
                            </div>
                            <h1 className="text-navy text-xl font-bold">{t('auth.forgot.title')}</h1>
                            <p className="text-muted mt-1 text-sm">{t('auth.forgot.subtitle')}</p>

                            {sent && (
                                <p className="border-success-border bg-success-bg text-success mt-4 rounded-[10px] border px-3 py-2 text-sm font-medium">
                                    {t('auth.resetSent')}
                                </p>
                            )}

                            <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                                <div>
                                    <label htmlFor="email" className="text-navy mb-1.5 block text-xs font-semibold">
                                        {t('auth.forgot.email')}
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

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-primary shadow-blue hover:bg-primary-700 inline-flex h-11 w-full items-center justify-center rounded-[10px] text-sm font-semibold text-white transition-colors disabled:opacity-60"
                                >
                                    {t('auth.forgot.submit')}
                                </button>

                                <Link href={route('login')} className="text-muted hover:text-navy text-center text-sm font-medium">
                                    {t('auth.forgot.backToLogin')}
                                </Link>
                            </form>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
