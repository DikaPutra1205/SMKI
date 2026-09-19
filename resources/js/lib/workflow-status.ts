export type WorkflowStatus = 'belum_dimulai' | 'dalam_proses' | 'dalam_tinjauan' | 'selesai_diterapkan' | 'tidak_berlaku';

export function resolveWorkflow(catatan: string | null, hasBukti: boolean, naSelected: boolean): WorkflowStatus {
    if (naSelected) return 'tidak_berlaku';
    if (catatan && hasBukti) return 'dalam_tinjauan';
    if (catatan || hasBukti) return 'dalam_proses';
    return 'belum_dimulai';
}

export function isEntryComplete(status: WorkflowStatus, catatan: string | null): boolean {
    if (status === 'selesai_diterapkan') return true;
    if (status === 'dalam_proses' || status === 'dalam_tinjauan' || status === 'tidak_berlaku') {
        return Boolean(catatan && catatan.trim() !== '');
    }
    return false;
}
