@php
    $safeKategori = $kategori ?? 'major';
    $safeKategoriLabel = $kategoriLabel ?? (strtolower($safeKategori) === 'major' ? 'Mayor' : (strtolower($safeKategori) === 'minor' ? 'Minor' : 'Observasi'));
    $safeKlausul = $kodeKlausul ?? 'SMKI';
    $safeJudul = $judulKontrol ?? 'Kontrol Keamanan Informasi';
    $safeDeadline = $deadlineStr ?? 'Sesuai SLA 14 Hari';
    $safeRecipient = $recipientName ?? 'PIC Unit Kerja';
    $safeActor = $actorName ?? 'Admin Kepatuhan';
    $safeActionUrl = $actionUrl ?? url('/');

    $sevBadge = match(strtolower($safeKategori)) {
        'major' => ['bg' => '#fef2f2', 'border' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Ketidaksesuaian Mayor'],
        'minor' => ['bg' => '#fffbeb', 'border' => '#fef3c7', 'text' => '#92400e', 'label' => 'Ketidaksesuaian Minor'],
        default => ['bg' => '#f8fafc', 'border' => '#e2e8f0', 'text' => '#475569', 'label' => 'Observasi / Rekomendasi'],
    };
@endphp

@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Temuan Audit Baru ({$safeKategoriLabel}): {$safeKlausul}",
    'preheader' => "Temuan audit baru diterbitkan untuk klausul {$safeKlausul} ({$safeKategoriLabel}). Batas SLA penyelesaian: {$safeDeadline}.",
])

@section('content')
    <!-- Title & Severity Header -->
    <div style="margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
            <tr>
                <td style="background-color: {{ $sevBadge['bg'] }}; border: 1px solid {{ $sevBadge['border'] }}; border-radius: 4px; padding: 3px 8px;">
                    <span style="color: {{ $sevBadge['text'] }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">
                        {{ $sevBadge['label'] }}
                    </span>
                </td>
            </tr>
        </table>

        <h1 style="color: #0f172a; font-size: 19px; font-weight: 600; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.2px;">
            Temuan Audit Baru Diterbitkan
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul <strong style="color: #0f172a; font-weight: 600;">{{ $safeKlausul }}</strong> &bull; Batas Waktu SLA: <strong style="color: #0f172a; font-weight: 600;">{{ $safeDeadline }}</strong>
        </p>
    </div>

    <!-- Stepper -->
    @include('emails.components.workflow-steps', ['status' => 'open'])

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 14px 0;">
        Yth. <strong>{{ $safeRecipient }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
        Auditor Kepatuhan telah menerbitkan temuan audit baru pada kontrol unit kerja Anda. Mohon segera melakukan analisis akar masalah (*root cause*) dan menyusun tindakan korektif sebelum batas waktu SLA.
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
                Tingkat Keparahan
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: {{ $sevBadge['text'] }}; font-size: 13.5px; font-weight: 600;">
                {{ $safeKategoriLabel }}
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Target SLA
            </td>
            <td style="padding: 11px 16px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $safeDeadline }}
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 16px; color: #64748b; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                Auditor
            </td>
            <td style="padding: 11px 16px; color: #0f172a; font-size: 13.5px; font-weight: 500;">
                {{ $safeActor }}
            </td>
        </tr>
    </table>

    <!-- Description Box -->
    @if(!empty($catatan))
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #0f172a; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 11.5px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">
                Uraian Temuan Awal
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
                        Tindak Lanjuti Temuan &rarr;
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
