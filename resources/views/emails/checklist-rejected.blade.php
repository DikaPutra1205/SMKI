@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Evaluasi Kontrol Tidak Patuh: {$kodeKlausul}",
    'preheader' => "Entri checklist kontrol {$kodeKlausul} dinyatakan Tidak Patuh. Harap tinjau catatan verifikator dan lengkapi bukti yang sesuai.",
])

@section('content')
    <!-- Status Badge (Left-aligned, crisp, clean) -->
    <div style="margin-bottom: 14px;">
        <span style="display: inline-block; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px;">
            Tidak Patuh (Perlu Revisi)
        </span>
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            Bukti Pemenuhan Kontrol Perlu Perbaikan
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul <strong style="color: #0f172a; font-weight: 600;">{{ $kodeKlausul }}</strong> &bull; Status: <strong style="color: #b91c1c; font-weight: 700;">Ditolak Verifikator</strong>
        </p>
    </div>

    <!-- Salutation & Summary -->
    <p style="color: #334155; font-size: 14.5px; line-height: 1.65; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.65; margin: 0 0 24px 0;">
        Admin Kepatuhan telah memverifikasi bukti pemenuhan kontrol yang Anda unggah dan menetapkan status <strong>Tidak Patuh</strong>. Dokumen pendukung belum memenuhi standar kriteria kelayakan ISO/IEC 27001:2022.
    </p>

    <!-- Modern Summary Card -->
    <div style="background-color: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 18px 22px; margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; width: 34%; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Kontrol SMKI
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $kodeKlausul }} &ndash; {{ $judulKontrol }}
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Hasil Evaluasi
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #b91c1c; font-size: 14px; font-weight: 700;">
                    Tidak Patuh (Non-Compliant)
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Verifikator
                </td>
                <td style="padding: 9px 0; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $actorName }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Admin Note Callout -->
    @if(!empty($catatanAdmin))
        <div style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px;">
                Alasan Penolakan dari Admin
            </p>
            <p style="margin: 0; font-size: 14px; color: #0f172a; line-height: 1.65;">
                {{ $catatanAdmin }}
            </p>
        </div>
    @endif

    <!-- Guidance Steps with Number Badges -->
    <div style="background-color: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 18px 22px; margin-bottom: 24px;">
        <p style="margin: 0 0 12px 0; font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
            Langkah Tindak Lanjut Perbaikan:
        </p>
        <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align: top; width: 28px; padding-bottom: 10px;">
                    <div style="width: 20px; height: 20px; border-radius: 50%; background-color: #002745; color: #ffffff; font-size: 11px; font-weight: 700; text-align: center; line-height: 20px;">1</div>
                </td>
                <td style="color: #475569; font-size: 13.5px; line-height: 1.5; padding-bottom: 10px;">
                    Buka lembar kerja checklist dan pelajari catatan evaluasi verifikator.
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top; width: 28px; padding-bottom: 10px;">
                    <div style="width: 20px; height: 20px; border-radius: 50%; background-color: #002745; color: #ffffff; font-size: 11px; font-weight: 700; text-align: center; line-height: 20px;">2</div>
                </td>
                <td style="color: #475569; font-size: 13.5px; line-height: 1.5; padding-bottom: 10px;">
                    Lengkapi dokumen SOP atau berkas bukti implementasi teknis yang sesuai.
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top; width: 28px;">
                    <div style="width: 20px; height: 20px; border-radius: 50%; background-color: #002745; color: #ffffff; font-size: 11px; font-weight: 700; text-align: center; line-height: 20px;">3</div>
                </td>
                <td style="color: #475569; font-size: 13.5px; line-height: 1.5;">
                    Unggah ulang berkas perbaikan agar dapat diverifikasi kembali oleh admin.
                </td>
            </tr>
        </table>
    </div>

    <!-- Primary Action CTA Button -->
    <div style="margin: 32px 0 24px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center">
                    <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="border-radius: 10px; background-color: #196ecd; box-shadow: 0 4px 14px rgba(25, 110, 205, 0.28);">
                                <a href="{{ $actionUrl }}" style="display: inline-block; padding: 13px 28px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                                    Perbaiki & Unggah Ulang Bukti &rarr;
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- Fallback Link -->
    <p style="color: #94a3b8; font-size: 12px; line-height: 1.6; margin: 0; text-align: center;">
        Jika tombol di atas tidak berfungsi, buka tautan langsung berikut:<br>
        <a href="{{ $actionUrl }}" style="color: #196ecd; word-break: break-all; font-size: 12px; text-decoration: underline; font-weight: 500;">{{ $actionUrl }}</a>
    </p>
@endsection
