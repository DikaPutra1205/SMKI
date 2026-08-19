export interface ControlData {
    id: number;
    framework_id: number;
    kode_klausul: string;
    judul: string;
    deskripsi: string | null;
    kategori: string;
    framework_name: string;
    framework_versi: string;
}

export interface EvidenceData {
    id: number;
    checklist_entry_id: number;
    version_number: number;
    file_url: string;
    nama_file: string;
    is_active: boolean;
}

export interface EntryItem {
    id: number;
    control_id: number;
    status: string;
    catatan: string | null;
    catatan_admin: string | null;
    tanggal_input: string | null;
    tanggal_verifikasi: string | null;
    control: ControlData;
    active_evidence: EvidenceData | null;
}

export interface PageMeta {
    index: number;
    framework_name: string;
    kategori: string;
    entry_count: number;
}

type Listener = () => void;

class AssessmentStore {
    private _entries = new Map<number, EntryItem>();
    private _dirtyIds = new Set<number>();
    private _pages = new Map<number, EntryItem[]>();
    private _pageMeta: PageMeta[] = [];
    private _currentPage = 0;
    private _totalEntries = 0;
    private _listeners = new Set<Listener>();
    private _sessionId: number | null = null;
    private _cachedAllEntries: EntryItem[] = [];

    get sessionId(): number | null {
        return this._sessionId;
    }

    get currentPage(): number {
        return this._currentPage;
    }

    get totalPages(): number {
        return this._pageMeta.length;
    }

    get pageMeta(): PageMeta[] {
        return this._pageMeta;
    }

    get totalEntries(): number {
        return this._totalEntries;
    }

    get dirtyCount(): number {
        return this._dirtyIds.size;
    }

    initialize(sessionId: number, entries: EntryItem[], pageMeta: PageMeta[], totalEntries: number) {
        this._sessionId = sessionId;
        this._totalEntries = totalEntries;
        this._pageMeta = pageMeta;

        for (const entry of entries) {
            this._entries.set(entry.id, entry);
        }
        this._rebuildCache();
        this._notify();
    }

    setCurrentPage(page: number) {
        this._currentPage = page;
        this._notify();
    }

    setPageEntries(page: number, entries: EntryItem[]) {
        this._pages.set(page, entries);
        for (const entry of entries) {
            if (!this._entries.has(entry.id)) {
                this._entries.set(entry.id, entry);
            }
        }
        this._rebuildCache();
        this._notify();
    }

    getPageEntries(page: number): EntryItem[] | undefined {
        const pageEntries = this._pages.get(page);
        if (!pageEntries) return undefined;
        return pageEntries.map((e) => this._entries.get(e.id) ?? e);
    }

    hasPage(page: number): boolean {
        return this._pages.has(page);
    }

    getEntry(id: number): EntryItem | undefined {
        return this._entries.get(id);
    }

    getAllEntries(): EntryItem[] {
        return this._cachedAllEntries;
    }

    updateEntry(id: number, changes: Partial<EntryItem>) {
        const entry = this._entries.get(id);
        if (!entry) return;

        this._entries.set(id, { ...entry, ...changes });
        this._dirtyIds.add(id);
        this._rebuildCache();
        this._notify();
    }

    updateEvidence(id: number, evidence: EvidenceData) {
        const entry = this._entries.get(id);
        if (!entry) return;

        this._entries.set(id, { ...entry, active_evidence: evidence });
        this._rebuildCache();
        this._notify();
    }

    getDirtyEntries(): { id: number; status: string; catatan: string | null }[] {
        const result: { id: number; status: string; catatan: string | null }[] = [];
        for (const id of this._dirtyIds) {
            const entry = this._entries.get(id);
            if (entry) {
                result.push({ id: entry.id, status: entry.status, catatan: entry.catatan });
            }
        }
        return result;
    }

    clearDirty(ids: number[]) {
        for (const id of ids) {
            this._dirtyIds.delete(id);
        }
        this._notify();
    }

    async flushDirty(sessionId: number): Promise<void> {
        const dirty = this.getDirtyEntries();
        if (dirty.length === 0) return;

        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const csrf = match ? decodeURIComponent(match[1]) : '';

        const res = await fetch('/admin/pic/checklist-entries/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ session_id: sessionId, entries: dirty }),
        });

        if (res.ok) {
            this.clearDirty(dirty.map((e) => e.id));
        }
    }

    computeProgress(): {
        completed: number;
        total: number;
        percentage: number;
        invalidCount: number;
        compliantCount: number;
        nonCompliantCount: number;
        naCount: number;
        pendingCount: number;
    } {
        const entries = Array.from(this._entries.values());
        const total = entries.length;
        const compliantCount = entries.filter((e) => e.status === 'compliant').length;
        const nonCompliantCount = entries.filter((e) => e.status === 'non_compliant').length;
        const naCount = entries.filter((e) => e.status === 'na').length;
        const pendingCount = total - compliantCount - nonCompliantCount - naCount;
        const completed = entries.filter(
            (e) =>
                e.status === 'compliant' ||
                (e.status === 'non_compliant' && e.catatan && e.catatan.trim()) ||
                (e.status === 'na' && e.catatan && e.catatan.trim()),
        ).length;
        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
        const invalidCount = entries
            .filter((e) => !e.status || e.status === 'non_compliant' || e.status === 'na')
            .filter((e) => !e.catatan || !e.catatan.trim()).length;

        return { completed, total, percentage, invalidCount, compliantCount, nonCompliantCount, naCount, pendingCount };
    }

    subscribe(listener: Listener): () => void {
        this._listeners.add(listener);
        return () => this._listeners.delete(listener);
    }

    private _notify() {
        for (const listener of this._listeners) {
            listener();
        }
    }

    private _rebuildCache() {
        this._cachedAllEntries = Array.from(this._entries.values());
    }

    reset() {
        this._entries.clear();
        this._dirtyIds.clear();
        this._pages.clear();
        this._pageMeta = [];
        this._currentPage = 0;
        this._totalEntries = 0;
        this._sessionId = null;
        this._cachedAllEntries = [];
        this._notify();
    }
}

export const assessmentStore = new AssessmentStore();
