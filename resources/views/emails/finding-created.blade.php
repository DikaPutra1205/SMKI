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
        'major' => ['bg' => '#fef2f2', 'border' => '#fee2e2', 'text' => '#b91c1c', 'dot' => '#ef4444', 'label' => 'Ketidaksesuaian Mayor'],
        'minor' => ['bg' => '#fffbeb', 'border' => '#fef3c7', 'text' => '#b45309', 'dot' => '#f59e0b', 'label' => 'Ketidaksesuaian Minor'],
        default => ['bg' => '#f0f9ff', 'border' => '#e0f2fe', 'text' => '#0369a1', 'dot' => '#0ea5e9', 'label' => 'Observasi / Rekomendasi'],
    };
@endphp

@extends('emails.layouts.smki', [
    'subject' => "[SMKI] Temuan Audit Baru ({$safeKategoriLabel}): {$safeKlausul}",
    'preheader' => "Temuan audit baru diterbitkan untuk klausul {$safeKlausul} ({$safeKategoriLabel}). Batas SLA penyelesaian: {$safeDeadline}.",
])

@section('content')
    <!-- Category & Severity Badge (Left-aligned, crisp, clean) -->
    <div style="margin-bottom: 14px;">
        <span style="display: inline-block; background-color: {{ $sevBadge['bg'] }}; border: 1px solid {{ $sevBadge['border'] }}; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: {{ $sevBadge['text'] }}; text-transform: uppercase; letter-spacing: 0.5px;">
            {{ $sevBadge['label'] }}
        </span>
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            Temuan Audit Baru Diterbitkan
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Klausul <strong style="color: #0f172a; font-weight: 600;">{{ $safeKlausul }}</strong> &bull; Batas Waktu SLA: <strong style="color: #0f172a; font-weight: 600;">{{ $safeDeadline }}</strong>
        </p>
    </div>

    <!-- Stepper Timeline -->
    @include('emails.components.workflow-steps', ['status' => 'open'])

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14.5px; line-height: 1.65; margin: 0 0 14px 0;">
        Yth. <strong>{{ $safeRecipient }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.65; margin: 0 0 24px 0;">
        Auditor Kepatuhan telah mencatatkan temuan audit baru pada kontrol unit kerja Anda. Mohon segera meninjau temuan, melakukan analisis akar masalah (*root cause*), dan menyusun tindakan korektif sebelum batas SLA berakhir.
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
                    Tingkat Keparahan
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: {{ $sevBadge['text'] }}; font-size: 14px; font-weight: 700;">
                    {{ $safeKategoriLabel }}
                </td>
            </tr>
            <tr>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    Target SLA
                </td>
                <td style="padding: 9px 0; border-bottom: 1px solid #edf2f7; color: #0f172a; font-size: 14px; font-weight: 600;">
                    {{ $safeDeadline }}
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

    <!-- Description Callout Box -->
    @if(!empty($catatan))
        <div style="background-color: #f0f9ff; border: 1px solid #e0f2fe; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
            <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">
                Uraian Temuan Awal
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
                                    Tindak Lanjuti Temuan &rarr;
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
