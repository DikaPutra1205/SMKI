@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Pembaruan Status Temuan: {$kodeKlausul} ({$toLabel})",
    'preheader' => "Status temuan {$kodeKlausul} telah diubah menjadi {$toLabel} oleh {$actorName}.",
])

@section('content')
    @php
        $statusBadge = match(strtolower($toStatus)) {
            'closed' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#065f46', 'dot' => '#10b981'],
            'resolved' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e40af', 'dot' => '#3b82f6'],
            'in_progress' => ['bg' => '#fffbeb', 'border' => '#fef3c7', 'text' => '#b45309', 'dot' => '#f59e0b'],
            default => ['bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b', 'dot' => '#ef4444'],
        };
    @endphp

    <!-- Status Badge (Left-aligned, crisp, clean) -->
    <div style="margin-bottom: 14px;">
        <span style="display: inline-block; background-color: {{ $statusBadge['bg'] }}; border: 1px solid {{ $statusBadge['border'] }}; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: {{ $statusBadge['text'] }}; text-transform: uppercase; letter-spacing: 0.5px;">
            Status: {{ $toLabel }}
        </span>
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            Pembaruan Status Temuan Audit
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul Kontrol: <strong style="color: #0f172a; font-weight: 600;">{{ $kodeKlausul }}</strong>
        </p>
    </div>

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14.5px; line-height: 1.65; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.65; margin: 0 0 24px 0;">
        Status penanganan temuan audit untuk kontrol <strong>{{ $kodeKlausul }}</strong> telah diperbarui oleh <strong>{{ $actorName }}</strong>.
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
                    Perubahan Status
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    <span style="color: #64748b;">{{ $fromLabel }}</span> &rarr; <span style="font-weight: 700; color: {{ $statusBadge['text'] }};">{{ $toLabel }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Diperbarui Oleh
                </td>
                <td style="padding: 9px 0; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $actorName }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Notes Callout (if present) -->
    @if(!empty($catatan))
        <div style="background-color: #f0f9ff; border: 1px solid #e0f2fe; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">
                Catatan
            </p>
            <p style="margin: 0; font-size: 14px; color: #0f172a; line-height: 1.65;">
                {{ $catatan }}
            </p>
        </div>
    @endif

    <!-- Primary Action CTA Button -->
    <div style="margin: 32px 0 24px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center">
                    <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="border-radius: 10px; background-color: #196ecd; box-shadow: 0 4px 14px rgba(25, 110, 205, 0.28);">
                                <a href="{{ $actionUrl }}" style="display: inline-block; padding: 13px 28px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                                    Buka Lembar Kerja &rarr;
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
