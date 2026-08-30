@php
    $safeKlausul = $kodeKlausul ?? 'SMKI';
    $safeJudul = $judulKontrol ?? 'Kontrol Keamanan Informasi';
    $safeRecipient = $recipientName ?? 'PIC Unit Kerja';
    $safeActor = $actorName ?? 'Admin Kepatuhan';
    $safeActionUrl = $actionUrl ?? url('/');
    $safeToStatus = strtolower($toStatus ?? 'closed');
    $safeFromStatus = strtolower($fromStatus ?? '');
    $safeToLabel = $toLabel ?? ucfirst($safeToStatus);

    $isClosed = $safeToStatus === 'closed';
    $isResolved = $safeToStatus === 'resolved';
    $isRevision = in_array($safeFromStatus, ['resolved', 'closed']) && in_array($safeToStatus, ['in_progress', 'open']);

    $statusBadge = match(true) {
        $isClosed => [
            'bg' => '#f0fdf4',
            'border' => '#dcfce7',
            'text' => '#166534',
            'label' => 'Ditutup & Terverifikasi',
            'desc' => 'Tindakan perbaikan dan bukti pemenuhan yang diserahkan telah diverifikasi serta disetujui penuh oleh Admin Kepatuhan.',
        ],
        $isRevision => [
            'bg' => '#fef2f2',
            'border' => '#fee2e2',
            'text' => '#991b1b',
            'label' => 'Perlu Revisi',
            'desc' => 'Tindak lanjut perbaikan atau dokumen bukti pendukung belum memenuhi standar dan dikembalikan ke status Dalam Penanganan.',
        ],
        $isResolved => [
            'bg' => '#eff6ff',
            'border' => '#dbeafe',
            'text' => '#1e40af',
            'label' => 'Selesai Ditindaklanjuti',
            'desc' => 'Admin Kepatuhan telah mencatatkan penyelesaian tindakan perbaikan untuk kontrol ini.',
        ],
        default => [
            'bg' => '#f8fafc',
            'border' => '#e2e8f0',
            'text' => '#475569',
            'label' => $safeToLabel,
            'desc' => "Admin Kepatuhan telah memperbarui status penanganan temuan ini menjadi {$safeToLabel}.",
        ],
    };
@endphp

@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Hasil Verifikasi Temuan: {$safeKlausul} ({$safeToLabel})",
    'preheader' => "Admin Kepatuhan ({$safeActor}) telah memperbarui status temuan {$safeKlausul}: status {$safeToLabel}.",
])

@section('content')
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
            {{ $isClosed ? 'Temuan Audit Ditutup & Terverifikasi' : ($isRevision ? 'Temuan Dikembalikan untuk Revisi' : 'Hasil Verifikasi Temuan') }}
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul Kontrol <strong style="color: #0f172a; font-weight: 600;">{{ $safeKlausul }}</strong> &bull; Status: <strong style="color: {{ $statusBadge['text'] }}; font-weight: 600;">{{ $safeToLabel }}</strong>
        </p>
    </div>

    <!-- Workflow Stepper -->
    @include('emails.components.workflow-steps', ['status' => $safeToStatus, 'fromStatus' => $safeFromStatus])

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 14px 0;">
        Yth. <strong>{{ $safeRecipient }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
        {{ $statusBadge['desc'] }}
    </p>

    <!-- Details Table -->
    <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 24px; background-color: #ffffff;">
        <tr>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; width: 34%; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Kontrol SMKI
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $safeKlausul }} &ndash; {{ $safeJudul }}
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Status Pembaruan
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: {{ $statusBadge['text'] }}; font-size: 13.5px; font-weight: 600;">
                {{ $safeToLabel }}
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Verifikator
            </td>
            <td style="padding: 11px 16px; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $safeActor }}
            </td>
        </tr>
    </table>

    <!-- Admin Evaluation / Recommendation Box -->
    @if(!empty($catatan))
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid {{ $isRevision ? '#dc2626' : '#0f172a' }}; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 11.5px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">
                Catatan Verifikator
            </p>
            <p style="margin: 0; font-size: 13.5px; color: #1e293b; line-height: 1.6;">
                {{ $catatan }}
            </p>
        </div>
    @endif

    <!-- Action Button -->
    <div style="margin: 28px 0 20px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td style="border-radius: 6px; background-color: #0f172a;">
                    <a href="{{ $safeActionUrl }}" style="display: inline-block; padding: 11px 22px; font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Buka Lembar Kerja Temuan &rarr;
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <!-- Direct Fallback Link -->
    <p style="color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;">
        Jika tombol di atas tidak dapat diklik, buka tautan berikut:<br>
        <a href="{{ $safeActionUrl }}" style="color: #0284c7; word-break: break-all; font-size: 11.5px; text-decoration: underline;">{{ $safeActionUrl }}</a>
    </p>
@endsection
