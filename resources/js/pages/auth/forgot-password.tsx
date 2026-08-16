import { Head, Link, useForm } from '@inertiajs/react';
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
            <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="w-full max-w-sm rounded-lg bg-white p-8 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                    <h1 className="mb-2 text-lg font-medium">Lupa password</h1>
                    <p className="mb-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        Masukkan email untuk permintaan reset password.
                    </p>
                    {sent && (
                        <p className="mb-4 rounded-sm bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                            Permintaan terkirim (fitur reset masih dalam pengembangan).
                        </p>
                    )}
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div>
                            <label htmlFor="email" className="mb-1 block text-sm">Email</label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full rounded-sm border border-[#19140035] px-3 py-2 text-sm dark:border-[#3E3E3A]"
                                autoFocus
                            />
                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm text-white hover:bg-black disabled:opacity-60 dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
                        >
                            Kirim permintaan
                        </button>
                        <Link
                            href={route('login')}
                            className="text-center text-sm text-[#706f6c] hover:underline dark:text-[#A1A09A]"
                        >
                            Kembali ke login
                        </Link>
                    </form>
                </div>
            </div>
        </>
    );
}
