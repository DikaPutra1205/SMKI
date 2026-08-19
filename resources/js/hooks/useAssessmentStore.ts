import { assessmentStore, type EntryItem, type PageMeta } from '@/stores/assessmentStore';
import { useCallback, useSyncExternalStore } from 'react';

export function useAssessmentEntry(id: number): EntryItem | undefined {
    return useSyncExternalStore(
        useCallback((cb) => assessmentStore.subscribe(cb), []),
        () => assessmentStore.getEntry(id),
    );
}

export function useAssessmentStore() {
    const entries = useSyncExternalStore(
        useCallback((cb) => assessmentStore.subscribe(cb), []),
        () => assessmentStore.getAllEntries(),
    );

    const currentPage = useSyncExternalStore(
        useCallback((cb) => assessmentStore.subscribe(cb), []),
        () => assessmentStore.currentPage,
    );

    const totalPages = useSyncExternalStore(
        useCallback((cb) => assessmentStore.subscribe(cb), []),
        () => assessmentStore.totalPages,
    );

    const pageMeta = useSyncExternalStore(
        useCallback((cb) => assessmentStore.subscribe(cb), []),
        () => assessmentStore.pageMeta,
    );

    const dirtyCount = useSyncExternalStore(
        useCallback((cb) => assessmentStore.subscribe(cb), []),
        () => assessmentStore.dirtyCount,
    );

    const totalEntries = useSyncExternalStore(
        useCallback((cb) => assessmentStore.subscribe(cb), []),
        () => assessmentStore.totalEntries,
    );

    const updateEntry = useCallback((id: number, changes: Partial<EntryItem>) => {
        assessmentStore.updateEntry(id, changes);
    }, []);

    const updateEvidence = useCallback((id: number, evidence: EntryItem['active_evidence']) => {
        if (evidence) {
            assessmentStore.updateEvidence(id, evidence);
        }
    }, []);

    const initialize = useCallback((sessionId: number, initialEntries: EntryItem[], pgMeta: PageMeta[], total: number) => {
        if (assessmentStore.sessionId !== sessionId) {
            assessmentStore.initialize(sessionId, initialEntries, pgMeta, total);
        }
    }, []);

    const setCurrentPage = useCallback((page: number) => {
        assessmentStore.setCurrentPage(page);
    }, []);

    const setPageEntries = useCallback((page: number, pageEntries: EntryItem[]) => {
        assessmentStore.setPageEntries(page, pageEntries);
    }, []);

    const getPageEntries = useCallback((page: number) => {
        return assessmentStore.getPageEntries(page);
    }, []);

    const hasPage = useCallback((page: number) => {
        return assessmentStore.hasPage(page);
    }, []);

    const computeProgress = useCallback(() => {
        return assessmentStore.computeProgress();
    }, []);

    return {
        entries,
        currentPage,
        totalPages,
        pageMeta,
        dirtyCount,
        totalEntries,
        updateEntry,
        updateEvidence,
        initialize,
        setCurrentPage,
        setPageEntries,
        getPageEntries,
        hasPage,
        computeProgress,
    };
}
