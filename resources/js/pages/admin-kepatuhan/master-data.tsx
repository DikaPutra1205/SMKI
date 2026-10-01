import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';
import { Database, Download, FileUp, Loader2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface FrameworkRow {
    id: number;
    nama: string;
    versi: string;
    controls_count: number;
}

interface MasterDataProps {
    frameworks: FrameworkRow[];
}

interface ChangeItem {
    field: string;
    from: unknown;
    to: unknown;
}

interface ControlDetail {
    kode_klausul: string;
    judul: string;
    framework_nama?: string;
    framework_versi?: string;
    changes?: ChangeItem[];
}

interface FrameworkDetail {
    nama: string;
    versi: string;
    changes?: ChangeItem[];
}

interface PreviewSummary {
    frameworks: {
        created: number;
        created_detail: FrameworkDetail[];
        updated: number;
        updated_detail: FrameworkDetail[];
        deleted: number;
        deleted_detail: FrameworkDetail[];
    };
    controls: {
        created: number;
        created_detail: ControlDetail[];
        updated: number;
        updated_detail: ControlDetail[];
        deleted: number;
        deleted_detail: ControlDetail[];
    };
}

const PREVIEW_URL = '/admin/kepatuhan/master-data/import/preview';
const IMPORT_URL = '/admin/kepatuhan/master-data/import';
const EXPORT_URL = '/admin/kepatuhan/master-data/export';
const PREVIEW_LIST_LIMIT = 10;

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function MasterData({ frameworks = [] }: MasterDataProps) {
    const can = useCan();
    const canImport = can('control.import');
    const canExport = can('control.export');

    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const timer = setTimeout(() => setFlashVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [preview, setPreview] = useState<PreviewSummary | null>(null);
    const [previewError, setPreviewError] = useState('');
    const [previewOpen, setPreviewOpen] = useState(false);
    const [importing, setImporting] = useState(false);

    function resetFileInput() {
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    function closePreview() {
        setPreviewOpen(false);
        setPreview(null);
        setPreviewError('');
        setSelectedFile(null);
        resetFileInput();
    }

    async function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setPreview(null);
        setPreviewError('');

        if (!file) return;

        if (!/\.xlsx?$/i.test(file.name)) {
            setPreviewError(t('masterData.invalidFileType'));
            setPreviewOpen(true);
            setSelectedFile(null);
            resetFileInput();
            return;
        }

        setSelectedFile(file);
        setPreviewLoading(true);
        setPreviewOpen(true);

        try {
            const formData = new FormData();
            formData.append('file', file);

            const res = await fetch(PREVIEW_URL, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            const data = (await res.json()) as PreviewSummary & { message?: string };

            if (!res.ok) {
                setPreviewError(typeof data.message === 'string' ? data.message : t('masterData.previewLoadFail'));
                return;
            }

            setPreview(data);
        } catch {
            setPreviewError(t('masterData.previewLoadFail'));
        } finally {
            setPreviewLoading(false);
        }
    }

    function handleConfirmImport() {
        if (!selectedFile || importing) return;

        setImporting(true);
        router.post(
            IMPORT_URL,
            { file: selectedFile },
            {
                onSuccess: () => closePreview(),
                onError: (errors) => {
                    const fileError = (errors as Record<string, string>).file;
                    if (fileError) setPreviewError(fileError);
                },
                onFinish: () => setImporting(false),
            },
        );
    }

    const totalChanges = preview
        ? preview.frameworks.created +
          preview.frameworks.updated +
          preview.frameworks.deleted +
          preview.controls.created +
          preview.controls.updated +
          preview.controls.deleted
        : 0;

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/dashboard' }, { label: t('masterData.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/master-data">
            <Head title={t('masterData.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('masterData.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm dark:text-slate-400">{t('masterData.subtitle')}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {canExport && (
                        <a
                            href={EXPORT_URL}
                            className="border-border-strong text-navy hover:bg-surface inline-flex items-center gap-2 rounded-[10px] border bg-white px-4 py-2 text-xs font-semibold transition-colors sm:text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800"
                        >
                            <Download className="h-4 w-4" />
                            <span>{t('masterData.exportExcel')}</span>
                        </a>
                    )}
                    {canImport && (
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white transition-colors sm:text-sm"
                        >
                            <Upload className="h-4 w-4" />
                            <span>{t('masterData.importExcel')}</span>
                        </button>
                    )}
                </div>
            </div>

            <input ref={fileInputRef} type="file" accept=".xlsx,.xls" className="hidden" onChange={handleFileSelect} />

            <p className="text-muted mt-3 text-[11px] sm:text-xs dark:text-slate-400">{t('masterData.hintSingleSheet')}</p>

            <div className="border-border mt-4 overflow-hidden rounded-[14px] border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                {frameworks.length > 0 ? (
                    <div className="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                        {frameworks.map((item, idx) => (
                            <div
                                key={item.id}
                                className={`border-border hover:border-primary/40 flex flex-col rounded-[14px] border p-5 transition-all hover:shadow-md dark:border-slate-700 ${
                                    idx % 2 === 0 ? 'bg-surface/40 dark:bg-slate-900/40' : 'bg-surface/70 dark:bg-slate-900/60'
                                }`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="bg-primary shadow-blue flex h-11 w-11 items-center justify-center rounded-[12px] text-white">
                                        <Database className="h-5 w-5" />
                                    </div>
                                    <span className="bg-primary-50 dark:bg-primary/10 text-primary rounded-[6px] px-2.5 py-1 text-xs font-semibold">
                                        v{item.versi}
                                    </span>
                                </div>
                                <h3 className="text-navy mt-4 text-base font-bold dark:text-white">{item.nama}</h3>
                                <div className="mt-3 flex items-center justify-between">
                                    <span className="text-muted text-xs dark:text-slate-400">
                                        {item.controls_count} {t('masterData.controlsCount')}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState message={t('masterData.noFrameworks')} />
                )}
            </div>

            <Toast
                visible={flashVisible}
                tone={flash?.type === 'success' ? 'success' : 'error'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            <Modal
                open={previewOpen}
                title={t('masterData.previewTitle')}
                description={t('masterData.previewDescription')}
                onClose={closePreview}
                maxWidth="xl"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={closePreview}
                            disabled={importing}
                            className="border-border-strong text-body hover:bg-surface rounded-[10px] border bg-white px-4 py-2 text-sm font-medium transition-colors disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            {t('masterData.cancel')}
                        </button>
                        {preview && totalChanges > 0 && canImport && (
                            <button
                                type="button"
                                onClick={handleConfirmImport}
                                disabled={importing || !selectedFile}
                                className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-5 py-2 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                            >
                                {importing && <Loader2 className="h-4 w-4 animate-spin" />}
                                {importing ? t('masterData.importing') : t('masterData.confirmImport')}
                            </button>
                        )}
                    </>
                }
            >
                {previewLoading ? (
                    <div className="text-muted flex items-center justify-center gap-2 py-10 text-sm dark:text-slate-400">
                        <Loader2 className="h-5 w-5 animate-spin" />
                        {t('masterData.readingFile')}
                    </div>
                ) : previewError ? (
                    <p className="text-danger py-6 text-center text-sm font-medium dark:text-red-400">{previewError}</p>
                ) : preview ? (
                    totalChanges === 0 ? (
                        <p className="text-muted py-6 text-center text-sm dark:text-slate-400">{t('masterData.previewEmpty')}</p>
                    ) : (
                        <div className="space-y-4">
                            <PreviewSection
                                title={t('masterData.frameworksCreated')}
                                count={preview.frameworks.created}
                                items={preview.frameworks.created_detail.map((f) => `${f.nama} ${f.versi}`)}
                            />
                            <PreviewSection
                                title={t('masterData.frameworksUpdated')}
                                count={preview.frameworks.updated}
                                items={preview.frameworks.updated_detail.map(
                                    (f) => `${f.nama} ${f.versi} — ${(f.changes ?? []).map((c) => c.field).join(', ')}`,
                                )}
                            />
                            <PreviewSection
                                title={t('masterData.frameworksDeleted')}
                                count={preview.frameworks.deleted}
                                items={preview.frameworks.deleted_detail.map((f) => `${f.nama} ${f.versi}`)}
                                danger
                            />
                            <PreviewSection
                                title={t('masterData.controlsCreated')}
                                count={preview.controls.created}
                                items={preview.controls.created_detail.map((c) => `${c.kode_klausul} — ${c.judul}`)}
                            />
                            <PreviewSection
                                title={t('masterData.controlsUpdated')}
                                count={preview.controls.updated}
                                items={preview.controls.updated_detail.map(
                                    (c) => `${c.kode_klausul} — ${(c.changes ?? []).map((ch) => ch.field).join(', ')}`,
                                )}
                            />
                            <PreviewSection
                                title={t('masterData.controlsDeleted')}
                                count={preview.controls.deleted}
                                items={preview.controls.deleted_detail.map((c) => `${c.kode_klausul} — ${c.judul}`)}
                                danger
                            />
                        </div>
                    )
                ) : null}
            </Modal>
        </AppLayout>
    );
}

function PreviewSection({ title, count, items, danger = false }: { title: string; count: number; items: string[]; danger?: boolean }) {
    if (count === 0) return null;

    const shown = items.slice(0, PREVIEW_LIST_LIMIT);
    const rest = items.length - shown.length;

    return (
        <div className="border-border rounded-[10px] border p-3 dark:border-slate-700">
            <div className="flex items-center gap-2 text-sm font-semibold">
                <FileUp className={`h-4 w-4 ${danger ? 'text-danger dark:text-red-400' : 'text-primary'}`} />
                <span className="text-navy dark:text-white">{title}</span>
                <span
                    className={`rounded-[6px] px-2 py-0.5 text-xs font-bold ${
                        danger ? 'bg-danger-bg text-danger dark:text-red-400' : 'bg-primary-50 dark:bg-primary/10 text-primary'
                    }`}
                >
                    {count}
                </span>
            </div>
            <ul className="text-body mt-2 max-h-40 space-y-1 overflow-y-auto text-xs dark:text-slate-300">
                {shown.map((item, idx) => (
                    <li key={`${item}-${idx}`} className="truncate" title={item}>
                        {item}
                    </li>
                ))}
                {rest > 0 && <li className="text-muted dark:text-slate-400">{t('masterData.andMore', rest)}</li>}
            </ul>
        </div>
    );
}
