<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FrameworkDocumentService
{
    public const DISK = 'frameworks';

    /**
     * Simpan dokumen standar ke bucket frameworks-documents dan kembalikan
     * object key yang akan disimpan di kolom url_file.
     */
    public function store(UploadedFile $file): string
    {
        $filename = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        return $file->storeAs('frameworks', $filename, self::DISK);
    }

    /**
     * Hapus objek lama dari storage. URL eksternal (data lama hasil import)
     * dibiarkan apa adanya karena bukan milik bucket kita.
     */
    public function deleteExisting(?string $rawPath): void
    {
        if (! $rawPath || filter_var($rawPath, FILTER_VALIDATE_URL)) {
            return;
        }

        try {
            Storage::disk(self::DISK)->delete($rawPath);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
