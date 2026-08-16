import { ControlItem, FrameworkItem } from '@/pages/admin-kepatuhan/compliance';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { FormEvent, useEffect } from 'react';

interface ControlFormModalProps {
    open: boolean;
    mode: 'create' | 'edit';
    control?: ControlItem | null;
    frameworks: FrameworkItem[];
    onClose: () => void;
}

interface ControlFormData {
    framework_id: number | '';
    kode_klausul: string;
    judul: string;
    deskripsi: string;
    kategori: 'annex_a' | 'klausul_4_10';
}

const KATEGORI_OPTIONS: { value: ControlFormData['kategori']; label: string }[] = [
    { value: 'annex_a', label: 'Annex A' },
    { value: 'klausul_4_10', label: 'Klausul 4-10' },
];

export function ControlFormModal({ open, mode, control, frameworks, onClose }: ControlFormModalProps) {
    const isEdit = mode === 'edit';

    const form = useForm<ControlFormData>({
        framework_id: '',
        kode_klausul: '',
        judul: '',
        deskripsi: '',
        kategori: 'annex_a',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        if (isEdit && control) {
            form.setData({
                framework_id: control.framework_id ?? '',
                kode_klausul: control.code,
                judul: control.title,
                deskripsi: control.description,
                kategori: control.category === 'Annex A' ? 'annex_a' : 'klausul_4_10',
            });
        } else {
            form.setData({
                framework_id: frameworks[0]?.id ?? '',
                kode_klausul: '',
                judul: '',
                deskripsi: '',
                kategori: 'annex_a',
            });
        }

        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, control]);

    if (!open) {
        return null;
    }

    const submitForm = () => {
        const options = { onSuccess: () => onClose() };

        if (isEdit && control) {
            form.patch(route('admin.kepatuhan.controls.update', control.id), options);
        } else {
            form.post(route('admin.kepatuhan.controls.store'), options);
        }
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        submitForm();
    };

    const inputClass = (hasError?: string) =>
        `w-full rounded-lg border px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:outline-none sm:text-sm dark:text-white ${
            hasError
                ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500'
                : 'border-slate-200 bg-white focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800'
        }`;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true">
            <div className="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 className="text-base font-bold text-slate-900 sm:text-lg dark:text-white">
                            {isEdit ? 'Edit Kontrol' : 'Tambah Kontrol'}
                        </h2>
                        <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Isi form berikut untuk menyimpan kontrol.</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        aria-label="Tutup"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto p-5">
                    {/* Framework */}
                    <div>
                        <label htmlFor="framework_id" className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Framework
                        </label>
                        <select
                            id="framework_id"
                            value={form.data.framework_id === '' ? '' : String(form.data.framework_id)}
                            onChange={(e) => form.setData('framework_id', e.target.value === '' ? '' : Number(e.target.value))}
                            className={inputClass(form.errors.framework_id)}
                        >
                            {frameworks.length === 0 && <option value="">Belum ada framework</option>}
                            {frameworks.map((fw) => (
                                <option key={fw.id} value={String(fw.id)}>
                                    {fw.nama}:{fw.versi}
                                </option>
                            ))}
                        </select>
                        {form.errors.framework_id && <p className="mt-1 text-xs text-red-500">{form.errors.framework_id}</p>}
                    </div>

                    {/* Kode Klausul */}
                    <div>
                        <label htmlFor="kode_klausul" className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Kode Klausul
                        </label>
                        <input
                            id="kode_klausul"
                            type="text"
                            value={form.data.kode_klausul}
                            onChange={(e) => form.setData('kode_klausul', e.target.value)}
                            placeholder="cth. A.5.1"
                            className={inputClass(form.errors.kode_klausul)}
                        />
                        {form.errors.kode_klausul && <p className="mt-1 text-xs text-red-500">{form.errors.kode_klausul}</p>}
                    </div>

                    {/* Judul */}
                    <div>
                        <label htmlFor="judul" className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Judul
                        </label>
                        <input
                            id="judul"
                            type="text"
                            value={form.data.judul}
                            onChange={(e) => form.setData('judul', e.target.value)}
                            placeholder="cth. Kebijakan Keamanan Informasi"
                            className={inputClass(form.errors.judul)}
                        />
                        {form.errors.judul && <p className="mt-1 text-xs text-red-500">{form.errors.judul}</p>}
                    </div>

                    {/* Deskripsi */}
                    <div>
                        <label htmlFor="deskripsi" className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Deskripsi
                        </label>
                        <textarea
                            id="deskripsi"
                            rows={3}
                            value={form.data.deskripsi}
                            onChange={(e) => form.setData('deskripsi', e.target.value)}
                            placeholder="Deskripsi singkat kontrol..."
                            className={inputClass(form.errors.deskripsi)}
                        />
                        {form.errors.deskripsi && <p className="mt-1 text-xs text-red-500">{form.errors.deskripsi}</p>}
                    </div>

                    {/* Kategori */}
                    <div>
                        <label htmlFor="kategori" className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Kategori
                        </label>
                        <select
                            id="kategori"
                            value={form.data.kategori}
                            onChange={(e) => form.setData('kategori', e.target.value as ControlFormData['kategori'])}
                            className={inputClass(form.errors.kategori)}
                        >
                            {KATEGORI_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                        {form.errors.kategori && <p className="mt-1 text-xs text-red-500">{form.errors.kategori}</p>}
                    </div>
                </form>

                {/* Footer */}
                <div className="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/50">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onClick={submitForm}
                        disabled={form.processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:opacity-50 sm:text-sm"
                    >
                        {form.processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Simpan Kontrol'}
                    </button>
                </div>
            </div>
        </div>
    );
}
