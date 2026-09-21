@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Pengingat Tenggat Temuan: {$kodeKlausul}",
    'preheader' => $isOverdue
        ? "Tenggat penanganan temuan {$kodeKlausul} (lewat " . abs($daysRemaining) . " hari)."
        : "Tenggat penanganan temuan {$kodeKlausul} ({$daysRemaining} hari). Harap segera tindak lanjuti sebelum batas waktu berakhir.",
])

@section('content')
    <!-- Status Badge -->
    <div style="margin-bottom: 14px;">
        @if($isOverdue)
            <span style="display: inline-block; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px;">
                Terlambat (Overdue)
            </span>
        @else
            <span style="display: inline-block; background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">
                {{ $hLabel }}
            </span>
        @endif
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            Pengingat Tenggat Temuan Audit
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul <strong style="color: #0f172a; font-weight: 600;">{{ $kodeKlausul }}</strong> &bull; Kategori: <strong style="color: #0f172a; font-weight: 700;">{{ ucfirst($kategori) }}</strong>
        </p>
    </div>

    <!-- Salutation & Summary -->
    <p style="color: #334155; font-size: 14.5px; line-height: 1.65; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.65; margin: 0 0 24px 0;">
        Anda memiliki temuan audit yang memerlukan tindak lanjut. Tenggat penanganan akan berakhir dalam <strong>{{ $daysRemaining }} hari</strong>. Mohon segera lakukan perbaikan sesuai kriteria kelayakan ISO/IEC 27001:2022.
    </p>

    <!-- Summary Card -->
    <div style="background-color: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 18px 22px; margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; width: 34%; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Kontrol
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $kodeKlausul }} &ndash; {{ $judulKontrol }}
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Tenggat
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $deadlineStr }}
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Sisa Waktu
                </td>
                <td style="padding: 9px 0; color: {{ $isOverdue ? '#b91c1c' : '#0f172a' }}; font-size: 14px; font-weight: 700;">
                    {{ $daysRemaining }} hari
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
                                    Lihat Temuan &rarr;
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
