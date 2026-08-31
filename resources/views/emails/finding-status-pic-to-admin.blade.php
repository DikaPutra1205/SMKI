@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Tindak Lanjut Temuan: {$kodeKlausul} ({$toLabel}) - {$unitName}",
    'preheader' => "PIC {$unitName} ({$actorName}) telah memperbarui status temuan {$kodeKlausul} menjadi {$toLabel}.",
])

@section('content')
    @php
        $isReopened = in_array(strtolower($fromStatus ?? ''), ['resolved', 'closed']) && in_array(strtolower($toStatus ?? ''), ['in_progress', 'open']);
        $isClosed = strtolower($toStatus ?? '') === 'closed';
        $isResolved = strtolower($toStatus ?? '') === 'resolved';

        $statusBadge = match(true) {
            $isClosed => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#065f46', 'dot' => '#10b981', 'label' => 'Ditutup oleh PIC'],
            $isResolved => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e40af', 'dot' => '#3b82f6', 'label' => 'Selesai Ditindaklanjuti'],
            $isReopened => ['bg' => '#fffbeb', 'border' => '#fef3c7', 'text' => '#b45309', 'dot' => '#f59e0b', 'label' => 'Status Dikembalikan'],
            default => ['bg' => '#f0f9ff', 'border' => '#e0f2fe', 'text' => '#0369a1', 'dot' => '#0ea5e9', 'label' => 'Dalam Penanganan'],
        };
    @endphp

    <!-- Status Badge (Left-aligned, crisp, clean) -->
    <div style="margin-bottom: 14px;">
        <span style="display: inline-block; background-color: {{ $statusBadge['bg'] }}; border: 1px solid {{ $statusBadge['border'] }}; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: {{ $statusBadge['text'] }}; text-transform: uppercase; letter-spacing: 0.5px;">
            {{ $statusBadge['label'] }}
        </span>
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            {{ $isReopened ? 'Status Temuan Dikembalikan oleh PIC' : 'Laporan Tindak Lanjut Temuan' }}
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul <strong style="color: #0f172a; font-weight: 600;">{{ $kodeKlausul }}</strong> &bull; Satuan Kerja <strong style="color: #0f172a; font-weight: 600;">{{ $unitName }}</strong>
        </p>
    </div>

    <!-- Stepper Timeline -->
    @include('emails.components.workflow-steps', ['status' => $toStatus ?? 'in_progress', 'fromStatus' => $fromStatus ?? ''])

    <!-- Salutation & Summary -->
    <p style="color: #334155; font-size: 14.5px; line-height: 1.65; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.65; margin: 0 0 24px 0;">
        PIC Unit Kerja <strong>{{ $unitName }}</strong> telah mengunggah pembaruan status dan tindak lanjut perbaikan temuan audit. Silakan meninjau rincian penanganan berikut:
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
                    Unit Kerja
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $unitName }}
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Status
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    <span style="color: #64748b;">{{ $fromLabel }}</span> &rarr; <span style="font-weight: 700; color: {{ $statusBadge['text'] }};">{{ $toLabel }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Pelapor (PIC)
                </td>
                <td style="padding: 9px 0; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $actorName }}
                </td>
            </tr>
        </table>
    </div>

    <!-- PIC Note Callout -->
    @if(!empty($catatan))
        <div style="background-color: #f0f9ff; border: 1px solid #e0f2fe; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">
                Catatan Tindak Lanjut PIC
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
                                    Tinjau & Verifikasi Temuan &rarr;
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- Direct Fallback Link -->
    <p style="color: #94a3b8; font-size: 12px; line-height: 1.6; margin: 0; text-align: center;">
        Jika tombol di atas tidak berfungsi, buka tautan langsung berikut:<br>
        <a href="{{ $actionUrl }}" style="color: #196ecd; word-break: break-all; font-size: 12px; text-decoration: underline; font-weight: 500;">{{ $actionUrl }}</a>
    </p>
@endsection
