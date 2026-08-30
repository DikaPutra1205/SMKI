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
            $isClosed => ['bg' => '#f0fdf4', 'border' => '#dcfce7', 'text' => '#166534', 'label' => 'Ditutup oleh PIC'],
            $isResolved => ['bg' => '#eff6ff', 'border' => '#dbeafe', 'text' => '#1e40af', 'label' => 'Selesai Ditindaklanjuti'],
            $isReopened => ['bg' => '#fffbeb', 'border' => '#fef3c7', 'text' => '#92400e', 'label' => 'Status Dikembalikan'],
            default => ['bg' => '#f8fafc', 'border' => '#e2e8f0', 'text' => '#475569', 'label' => 'Dalam Penanganan'],
        };
    @endphp

    <!-- Title & Status Header -->
    <div style="margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
            <tr>
                <td style="background-color: {{ $statusBadge['bg'] }}; border: 1px solid {{ $statusBadge['border'] }}; border-radius: 4px; padding: 3px 8px;">
                    <span style="color: {{ $statusBadge['text'] }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">
                        {{ $statusBadge['label'] }}
                    </span>
                </td>
            </tr>
        </table>

        <h1 style="color: #0f172a; font-size: 19px; font-weight: 600; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.2px;">
            {{ $isReopened ? 'Status Temuan Dikembalikan oleh PIC' : 'Laporan Tindak Lanjut Temuan' }}
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul <strong style="color: #0f172a; font-weight: 600;">{{ $kodeKlausul }}</strong> &bull; Satuan Kerja <strong style="color: #0f172a; font-weight: 600;">{{ $unitName }}</strong>
        </p>
    </div>

    <!-- Workflow Stepper -->
    @include('emails.components.workflow-steps', ['status' => $toStatus ?? 'in_progress', 'fromStatus' => $fromStatus ?? ''])

    <!-- Salutation & Summary -->
    <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
        PIC Unit Kerja <strong>{{ $unitName }}</strong> telah mengunggah pembaruan status dan tindak lanjut temuan audit. Silakan memeriksa rincian penanganan berikut:
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
                Unit Kerja
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $unitName }}
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Status
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                <span style="color: #64748b;">{{ $fromLabel }}</span> &rarr; <span style="font-weight: 600; color: {{ $statusBadge['text'] }};">{{ $toLabel }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Pelapor (PIC)
            </td>
            <td style="padding: 11px 16px; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $actorName }}
            </td>
        </tr>
    </table>

    <!-- PIC Note Callout -->
    @if(!empty($catatan))
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #0f172a; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 11.5px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">
                Catatan Tindak Lanjut PIC
            </p>
            <p style="margin: 0; font-size: 13.5px; color: #1e293b; line-height: 1.6;">
                {{ $catatan }}
            </p>
        </div>
    @endif

    <!-- Action CTA Button -->
    <div style="margin: 28px 0 20px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td style="border-radius: 6px; background-color: #0f172a;">
                    <a href="{{ $actionUrl }}" style="display: inline-block; padding: 11px 22px; font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Tinjau & Verifikasi Temuan &rarr;
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
