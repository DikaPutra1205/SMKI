export type PanduanRole = 'superadmin' | 'admin_kepatuhan' | 'koordinator_smki' | 'auditor' | 'pic';

export interface PanduanVideo {
    videoId: string;
    title: string;
    description?: string;
}

const PLACEHOLDER: PanduanVideo[] = [
    {
        videoId: 'dQw4w9WgXcQ',
        title: 'Panduan umum SMKI',
        description: 'Ganti dengan ID video YouTube sebenarnya.',
    },
];

export const PANDUAN_VIDEOS: Record<PanduanRole, PanduanVideo[]> = {
    superadmin: PLACEHOLDER,
    admin_kepatuhan: PLACEHOLDER,
    koordinator_smki: PLACEHOLDER,
    auditor: PLACEHOLDER,
    pic: PLACEHOLDER,
};

export function getVideosForRole(role?: string): PanduanVideo[] {
    if (!role) return [];
    return PANDUAN_VIDEOS[role as PanduanRole] ?? [];
}

export const PANDUAN_PDF_URL = 'https://xyzcompany.supabase.co/storage/v1/object/public/panduan/panduan.pdf';
