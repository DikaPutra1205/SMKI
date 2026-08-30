@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Pembaruan Status Temuan: {$kodeKlausul} ({$toLabel})",
    'preheader' => "Status temuan {$kodeKlausul} telah diubah menjadi {$toLabel} oleh {$actorName}.",
])

@section('content')
    @php
        $statusBadge = match(strtolower($toStatus)) {
            'closed' => ['bg' => '#f0fdf4', 'border' => '#dcfce7', 'text' => '#166534'],
            'resolved' => ['bg' => '#eff6ff', 'border' => '#dbeafe', 'text' => '#1e40af'],
            'in_progress' => ['bg' => '#fffbeb', 'border' => '#fef3c7', 'text' => '#92400e'],
            default => ['bg' => '#fef2f2', 'border' => '#fee2e2', 'text' => '#991b1b'],
        };
    @endphp

    <!-- Title & Status Header -->
    <div style="margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
            <tr>
                <td style="background-color: {{ $statusBadge['bg'] }}; border: 1px solid {{ $statusBadge['border'] }}; border-radius: 4px; padding: 3px 8px;">
                    <span style="color: {{ $statusBadge['text'] }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">
                        Status: {{ $toLabel }}
                    </span>
                </td>
            </tr>
        </table>

        <h1 style="color: #0f172a; font-size: 19px; font-weight: 600; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.2px;">
            Pembaruan Status Temuan Audit
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul Kontrol: <strong style="color: #0f172a; font-weight: 600;">{{ $kodeKlausul }}</strong>
        </p>
    </div>

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
        Status penanganan temuan audit untuk kontrol <strong>{{ $kodeKlausul }}</strong> telah diperbarui oleh <strong>{{ $actorName }}</strong>.
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
                Perubahan Status
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                <span style="color: #64748b;">{{ $fromLabel }}</span> &rarr; <span style="font-weight: 600; color: {{ $statusBadge['text'] }};">{{ $toLabel }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Diperbarui Oleh
            </td>
            <td style="padding: 11px 16px; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $actorName }}
            </td>
        </tr>
    </table>

    <!-- Notes Callout (if present) -->
    @if(!empty($catatan))
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #0f172a; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 11.5px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">
                Catatan
            </p>
            <p style="margin: 0; font-size: 13.5px; color: #1e293b; line-height: 1.6;">
                {{ $catatan }}
            </p>
        </div>
    @endif

    <!-- CTA Button -->
    <div style="margin: 28px 0 20px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td style="border-radius: 6px; background-color: #0f172a;">
                    <a href="{{ $actionUrl }}" style="display: inline-block; padding: 11px 22px; font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Buka Lembar Kerja &rarr;
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <!-- Fallback Link -->
    <p style="color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;">
        Jika tombol di atas tidak dapat diklik, buka tautan berikut:<br>
        <a href="{{ $actionUrl }}" style="color: #0284c7; word-break: break-all; font-size: 11.5px; text-decoration: underline;">{{ $actionUrl }}</a>
    </p>
@endsection
