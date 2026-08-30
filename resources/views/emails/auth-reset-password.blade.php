@extends('emails.layouts.smki', [
    'subject' => '[SMKI] Permintaan Atur Ulang Kata Sandi',
    'preheader' => 'Gunakan tautan aman ini untuk mengatur ulang kata sandi akun portal SMKI Anda (Berlaku 60 menit).',
])

@section('content')
    <!-- Title & Status Header -->
    <div style="margin-bottom: 24px;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
            <tr>
                <td style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 3px 8px;">
                    <span style="color: #475569; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">
                        Keamanan Akun
                    </span>
                </td>
            </tr>
        </table>

        <h1 style="color: #0f172a; font-size: 19px; font-weight: 600; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.2px;">
            Atur Ulang Kata Sandi
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Permintaan perubahan kata sandi untuk akun Portal SMKI
        </p>
    </div>

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName ?? 'Pengguna SMKI' }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
        Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda. Silakan klik tombol di bawah ini untuk membuat kata sandi baru:
    </p>

    <!-- Action Button -->
    <div style="margin: 24px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td style="border-radius: 6px; background-color: #0f172a;">
                    <a href="{{ $resetUrl }}" style="display: inline-block; padding: 11px 24px; font-size: 13px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Atur Ulang Kata Sandi &rarr;
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <!-- Security & Expiration Info -->
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; margin: 24px 0;">
        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 600; color: #334155;">
            Catatan Keamanan:
        </p>
        <ul style="margin: 0; padding-left: 18px; color: #64748b; font-size: 12.5px; line-height: 1.6;">
            <li>Tautan ini hanya berlaku selama <strong>{{ $count ?? 60 }} menit</strong> sejak email diterbitkan.</li>
            <li>Gunakan kombinasi minimal 8 karakter dengan huruf kapital, angka, dan simbol.</li>
            <li>Jika Anda tidak merasa mengajukan permintaan ini, abaikan email ini.</li>
        </ul>
    </div>

    <!-- Direct Fallback Link -->
    <p style="color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;">
        Jika tombol di atas tidak dapat diklik, buka tautan berikut:<br>
        <a href="{{ $resetUrl }}" style="color: #0284c7; word-break: break-all; font-size: 11.5px; text-decoration: underline;">{{ $resetUrl }}</a>
    </p>
@endsection
