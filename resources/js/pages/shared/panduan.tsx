import YoutubeEmbedCard from '@/components/panduan/YoutubeEmbedCard';
import AppLayout from '@/layouts/AppLayout';
import { getVideosForRole, PANDUAN_PDF_URL } from '@/lib/panduan';
import type { SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useMemo } from 'react';

export default function Panduan() {
    const { auth } = usePage<SharedData>().props;
    const role = (auth.user as { role?: string })?.role;
    const videos = useMemo(() => getVideosForRole(role), [role]);

    const breadcrumbs = [{ label: 'Panduan' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/panduan">
            <Head title="Panduan - SMKI" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Panduan</h1>
                    <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Video tutorial sesuai peran Anda. Unduh dokumen PDF untuk referensi offline.
                    </p>
                </div>
                <a
                    href={PANDUAN_PDF_URL}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-semibold text-slate-600 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                >
                    <FileDown className="h-4 w-4" />
                    Buka Panduan PDF
                </a>
            </div>

            {videos.length > 0 ? (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {videos.map((video) => (
                        <YoutubeEmbedCard
                            key={video.videoId}
                            videoId={video.videoId}
                            title={video.title}
                            description={video.description}
                        />
                    ))}
                </div>
            ) : (
                <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                    Belum ada video panduan untuk peran Anda.
                </div>
            )}
        </AppLayout>
    );
}
