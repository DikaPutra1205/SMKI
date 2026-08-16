import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('login'));
    }

    return (
        <>
            <Head title="Login" />
            <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="w-full max-w-sm rounded-lg bg-white p-8 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                    <h1 className="mb-6 text-center text-lg font-medium">Masuk — SMKI</h1>
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
                        <div>
                            <label htmlFor="password" className="mb-1 block text-sm">Password</label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full rounded-sm border border-[#19140035] px-3 py-2 text-sm dark:border-[#3E3E3A]"
                            />
                            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm text-white hover:bg-black disabled:opacity-60 dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A]"
                        >
                            Masuk
                        </button>
                        <Link
                            href={route('password.request')}
                            className="text-center text-sm text-[#706f6c] hover:underline dark:text-[#A1A09A]"
                        >
                            Lupa password?
                        </Link>
                    </form>
                </div>
            </div>
        </>
    );
}
