export type WorkflowStatus = 'belum_dimulai' | 'dalam_proses' | 'dalam_tinjauan' | 'selesai_diterapkan' | 'tidak_berlaku';

export function resolveWorkflow(catatan: string | null, hasBukti: boolean, naSelected: boolean): WorkflowStatus {
    if (naSelected) return 'tidak_berlaku';
    if (catatan && hasBukti) return 'dalam_tinjauan';
    if (catatan || hasBukti) return 'dalam_proses';
    return 'belum_dimulai';
}

export function isEntryComplete(status: WorkflowStatus, catatan: string | null, hasBukti: boolean, verified: boolean): boolean {
    if (status === 'selesai_diterapkan') return true;
    if (status === 'tidak_berlaku') return verified;
    return Boolean(catatan) && hasBukti;
}
