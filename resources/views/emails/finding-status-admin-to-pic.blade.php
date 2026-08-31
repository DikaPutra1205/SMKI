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
            'bg' => '#ecfdf5',
            'border' => '#a7f3d0',
            'text' => '#065f46',
            'dot' => '#10b981',
            'label' => 'Ditutup & Terverifikasi',
            'desc' => 'Tindakan perbaikan dan bukti pemenuhan yang diserahkan telah diverifikasi serta disetujui penuh oleh Admin Kepatuhan.',
        ],
        $isRevision => [
            'bg' => '#fef2f2',
            'border' => '#fecaca',
            'text' => '#991b1b',
            'dot' => '#ef4444',
            'label' => 'Perlu Revisi',
            'desc' => 'Tindak lanjut perbaikan atau dokumen bukti pendukung belum memenuhi standar dan dikembalikan ke status Dalam Penanganan.',
        ],
        $isResolved => [
            'bg' => '#eff6ff',
            'border' => '#bfdbfe',
            'text' => '#1e40af',
            'dot' => '#3b82f6',
            'label' => 'Selesai Ditindaklanjuti',
            'desc' => 'Admin Kepatuhan telah mencatatkan penyelesaian tindakan perbaikan untuk kontrol ini.',
        ],
        default => [
            'bg' => '#f8fafc',
            'border' => '#e2e8f0',
            'text' => '#334155',
            'dot' => '#64748b',
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
    <!-- Status Badge (Left-aligned, crisp, clean) -->
    <div style="margin-bottom: 14px;">
        <span style="display: inline-block; background-color: {{ $statusBadge['bg'] }}; border: 1px solid {{ $statusBadge['border'] }}; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: {{ $statusBadge['text'] }}; text-transform: uppercase; letter-spacing: 0.5px;">
            {{ $statusBadge['label'] }}
        </span>
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            {{ $isClosed ? 'Temuan Audit Ditutup & Terverifikasi' : ($isRevision ? 'Temuan Dikembalikan untuk Revisi' : 'Hasil Verifikasi Temuan') }}
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul Kontrol <strong style="color: #0f172a; font-weight: 600;">{{ $safeKlausul }}</strong> &bull; Status: <strong style="color: {{ $statusBadge['text'] }}; font-weight: 700;">{{ $safeToLabel }}</strong>
        </p>
    </div>

    <!-- Stepper Timeline -->
    @include('emails.components.workflow-steps', ['status' => $safeToStatus, 'fromStatus' => $safeFromStatus])

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14.5px; line-height: 1.65; margin: 0 0 14px 0;">
        Yth. <strong>{{ $safeRecipient }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.65; margin: 0 0 24px 0;">
        {{ $statusBadge['desc'] }}
    </p>

    <!-- Modern Summary Card -->
    <div style="background-color: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 18px 22px; margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; width: 34%; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Kontrol SMKI
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $safeKlausul }} &ndash; {{ $safeJudul }}
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Status Pembaruan
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: {{ $statusBadge['text'] }}; font-size: 14px; font-weight: 700;">
                    {{ $safeToLabel }}
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Auditor / Verifikator
                </td>
                <td style="padding: 9px 0; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $safeActor }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Admin Evaluation / Recommendation Box -->
    @if(!empty($catatan))
        <div style="background-color: {{ $isRevision ? '#fef2f2' : '#f0f9ff' }}; border: 1px solid {{ $isRevision ? '#fecaca' : '#e0f2fe' }}; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: {{ $isRevision ? '#991b1b' : '#0369a1' }}; text-transform: uppercase; letter-spacing: 0.5px;">
                Catatan Verifikator
            </p>
            <p style="margin: 0; font-size: 14px; color: #0f172a; line-height: 1.65;">
                {{ $catatan }}
            </p>
        </div>
    @endif

    <!-- Primary Action Button -->
    <div style="margin: 32px 0 24px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center">
                    <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="border-radius: 10px; background-color: #196ecd; box-shadow: 0 4px 14px rgba(25, 110, 205, 0.28);">
                                <a href="{{ $safeActionUrl }}" style="display: inline-block; padding: 13px 28px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                                    Buka Lembar Kerja Temuan &rarr;
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
        <a href="{{ $safeActionUrl }}" style="color: #196ecd; word-break: break-all; font-size: 12px; text-decoration: underline; font-weight: 500;">{{ $safeActionUrl }}</a>
    </p>
@endsection
