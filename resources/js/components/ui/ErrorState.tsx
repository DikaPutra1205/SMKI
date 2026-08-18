import { AlertOctagon, ShieldX, TriangleAlert } from 'lucide-react';

interface ErrorStateProps {
    code?: 403 | 404 | 500;
    title?: string;
    message?: string;
    className?: string;
}

const config: Record<number, { icon: typeof TriangleAlert; title: string; message: string }> = {
    403: {
        icon: ShieldX,
        title: 'Akses Ditolak',
        message: 'Anda tidak memiliki izin untuk mengakses halaman ini.',
    },
    404: {
        icon: AlertOctagon,
        title: 'Halaman Tidak Ditemukan',
        message: 'Halaman yang Anda cari tidak tersedia atau telah dipindahkan.',
    },
    500: {
        icon: TriangleAlert,
        title: 'Terjadi Kesalahan',
        message: 'Terjadi kesalahan pada server. Silakan coba lagi.',
    },
};

export function ErrorState({ code = 404, title, message, className }: ErrorStateProps) {
    const cfg = config[code] ?? config[404];
    const Icon = cfg.icon;

    return (
        <div className={`flex flex-col items-center justify-center gap-4 py-20 text-center ${className ?? ''}`}>
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-danger-bg text-danger">
                <Icon className="h-8 w-8" />
            </div>
            <div>
                <h2 className="text-lg font-bold text-navy">{title ?? cfg.title}</h2>
                <p className="mt-1 max-w-md text-sm text-muted">{message ?? cfg.message}</p>
            </div>
        </div>
    );
}