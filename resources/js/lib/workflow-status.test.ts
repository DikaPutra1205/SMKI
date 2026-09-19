import { describe, expect, it } from 'vitest';
import { isEntryComplete, resolveWorkflow } from './workflow-status';

describe('resolveWorkflow', () => {
    it('returns tidak_berlaku when naSelected', () => {
        expect(resolveWorkflow(null, false, true)).toBe('tidak_berlaku');
        expect(resolveWorkflow('note', true, true)).toBe('tidak_berlaku');
    });

    it('returns dalam_tinjauan when both catatan and bukti', () => {
        expect(resolveWorkflow('note', true, false)).toBe('dalam_tinjauan');
    });

    it('returns dalam_proses when only catatan', () => {
        expect(resolveWorkflow('note', false, false)).toBe('dalam_proses');
    });

    it('returns dalam_proses when only bukti', () => {
        expect(resolveWorkflow(null, true, false)).toBe('dalam_proses');
    });

    it('returns belum_dimulai when neither', () => {
        expect(resolveWorkflow(null, false, false)).toBe('belum_dimulai');
    });
});

describe('isEntryComplete', () => {
    it('selesai_diterapkan always complete', () => {
        expect(isEntryComplete('selesai_diterapkan', null)).toBe(true);
        expect(isEntryComplete('selesai_diterapkan', 'note')).toBe(true);
    });

    it('tidak_berlaku complete when catatan present (backend parity)', () => {
        expect(isEntryComplete('tidak_berlaku', 'justifikasi')).toBe(true);
        expect(isEntryComplete('tidak_berlaku', null)).toBe(false);
        expect(isEntryComplete('tidak_berlaku', '')).toBe(false);
    });

    it('dalam_tinjauan complete when catatan present', () => {
        expect(isEntryComplete('dalam_tinjauan', 'note')).toBe(true);
        expect(isEntryComplete('dalam_tinjauan', null)).toBe(false);
        expect(isEntryComplete('dalam_tinjauan', '')).toBe(false);
    });

    it('dalam_proses complete when catatan present', () => {
        expect(isEntryComplete('dalam_proses', 'note')).toBe(true);
        expect(isEntryComplete('dalam_proses', null)).toBe(false);
    });

    it('belum_dimulai incomplete', () => {
        expect(isEntryComplete('belum_dimulai', null)).toBe(false);
        expect(isEntryComplete('belum_dimulai', 'note')).toBe(false);
    });
});
