@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Evaluasi Kontrol Tidak Patuh: {$kodeKlausul}",
    'preheader' => "Entri checklist kontrol {$kodeKlausul} dinyatakan Tidak Patuh. Harap tinjau catatan verifikator dan lengkapi bukti yang sesuai.",
])

@section('content')
    <!-- Title & Status Header -->
    <div style="margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
            <tr>
                <td style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 4px; padding: 3px 8px;">
                    <span style="color: #991b1b; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">
                        Tidak Patuh (Perlu Revisi)
                    </span>
                </td>
            </tr>
        </table>

        <h1 style="color: #0f172a; font-size: 19px; font-weight: 600; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.2px;">
            Bukti Pemenuhan Kontrol Perlu Perbaikan
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul <strong style="color: #0f172a; font-weight: 600;">{{ $kodeKlausul }}</strong> &bull; Status: <strong style="color: #991b1b; font-weight: 600;">Ditolak Verifikator</strong>
        </p>
    </div>

    <!-- Salutation & Summary -->
    <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
        Admin Kepatuhan telah memverifikasi bukti pemenuhan kontrol yang Anda unggah dan menetapkan status <strong>Tidak Patuh</strong>. Dokumen pendukung belum memenuhi standar kriteria kelayakan ISO/IEC 27001:2022.
    </p>

    <!-- Details Table -->
    <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 24px; background-color: #ffffff;">
        <tr>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; width: 34%; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Kontrol SMKI
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $kodeKlausul }} &ndash; {{ $judulKontrol }}
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Hasil Evaluasi
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #991b1b; font-size: 13.5px; font-weight: 600;">
                Tidak Patuh (Non-Compliant)
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Verifikator
            </td>
            <td style="padding: 11px 16px; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $actorName }}
            </td>
        </tr>
    </table>

    <!-- Admin Note -->
    @if(!empty($catatanAdmin))
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #dc2626; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 11.5px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">
                Alasan Penolakan dari Admin
            </p>
            <p style="margin: 0; font-size: 13.5px; color: #1e293b; line-height: 1.6;">
                {{ $catatanAdmin }}
            </p>
        </div>
    @endif

    <!-- Guidance Steps -->
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 24px;">
        <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.3px;">
            Langkah Tindak Lanjut:
        </p>
        <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align: top; width: 20px; color: #64748b; font-size: 13px; font-weight: 600; padding-bottom: 8px;">1.</td>
                <td style="color: #475569; font-size: 13px; line-height: 1.5; padding-bottom: 8px;">Buka lembar kerja checklist dan pelajari kriteria evaluasi klausul.</td>
            </tr>
            <tr>
                <td style="vertical-align: top; width: 20px; color: #64748b; font-size: 13px; font-weight: 600; padding-bottom: 8px;">2.</td>
                <td style="color: #475569; font-size: 13px; line-height: 1.5; padding-bottom: 8px;">Lengkapi berkas SOP atau bukti implementasi teknis yang diminta.</td>
            </tr>
            <tr>
                <td style="vertical-align: top; width: 20px; color: #64748b; font-size: 13px; font-weight: 600;">3.</td>
                <td style="color: #475569; font-size: 13px; line-height: 1.5;">Unggah ulang bukti perbaikan untuk diverifikasi kembali.</td>
            </tr>
        </table>
    </div>

    <!-- Action Button -->
    <div style="margin: 28px 0 20px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td style="border-radius: 6px; background-color: #0f172a;">
                    <a href="{{ $actionUrl }}" style="display: inline-block; padding: 11px 22px; font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Perbaiki & Unggah Ulang Bukti &rarr;
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <!-- Direct Fallback Link -->
    <p style="color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;">
        Jika tombol di atas tidak dapat diklik, buka tautan berikut:<br>
        <a href="{{ $actionUrl }}" style="color: #0284c7; word-break: break-all; font-size: 11.5px; text-decoration: underline;">{{ $actionUrl }}</a>
    </p>
@endsection
