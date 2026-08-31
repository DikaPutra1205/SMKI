@extends('emails.layouts.smki', [
    'subject' => '[SMKI] Permintaan Atur Ulang Kata Sandi',
    'preheader' => 'Gunakan tautan aman ini untuk mengatur ulang kata sandi akun portal SMKI Anda (Berlaku 60 menit).',
])

@section('content')
    <!-- Status Badge (Left-aligned, crisp, clean) -->
    <div style="margin-bottom: 14px;">
        <span style="display: inline-block; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">
            Keamanan Akun
        </span>
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            Atur Ulang Kata Sandi Akun
        </h1>
        <p style="color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;">
            Permintaan perubahan kata sandi untuk akun Portal SMKI
        </p>
    </div>

    <!-- Salutation & Context -->
    <p style="color: #334155; font-size: 14.5px; line-height: 1.65; margin: 0 0 14px 0;">
        Yth. <strong>{{ $recipientName ?? 'Pengguna SMKI' }}</strong>,
    </p>
    <p style="color: #475569; font-size: 14px; line-height: 1.65; margin: 0 0 24px 0;">
        Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda. Silakan klik tombol di bawah ini untuk membuat kata sandi baru:
    </p>

    <!-- Primary Action CTA Button -->
    <div style="margin: 32px 0 24px 0;">
        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center">
                    <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="border-radius: 10px; background-color: #196ecd; box-shadow: 0 4px 14px rgba(25, 110, 205, 0.28);">
                                <a href="{{ $resetUrl }}" style="display: inline-block; padding: 13px 28px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                                    Atur Ulang Kata Sandi &rarr;
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- Security & Expiration Info Card -->
    <div style="background-color: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 18px 22px; margin: 28px 0;">
        <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
            Catatan Keamanan:
        </p>
        <ul style="margin: 0; padding-left: 18px; color: #64748b; font-size: 13px; line-height: 1.65;">
            <li>Tautan ini hanya berlaku selama <strong>{{ $count ?? 60 }} menit</strong> sejak diterbitkan.</li>
            <li>Gunakan kombinasi minimal 8 karakter dengan huruf besar, huruf kecil, angka, dan simbol.</li>
            <li>Jika Anda tidak merasa meminta reset kata sandi, Anda dapat mengabaikan email ini.</li>
        </ul>
    </div>

    <!-- Fallback Link -->
    <p style="color: #94a3b8; font-size: 12px; line-height: 1.6; margin: 0; text-align: center;">
        Jika tombol di atas tidak berfungsi, buka tautan langsung berikut:<br>
        <a href="{{ $resetUrl }}" style="color: #196ecd; word-break: break-all; font-size: 12px; text-decoration: underline; font-weight: 500;">{{ $resetUrl }}</a>
    </p>
@endsection
