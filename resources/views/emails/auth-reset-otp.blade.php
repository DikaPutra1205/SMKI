@extends('emails.layouts.smki', [
    'subject' => '[SMKI] Kode Verifikasi Atur Ulang Kata Sandi',
    'preheader' => 'Masukkan kode ini di halaman pengaturan ulang kata sandi. Berlaku 5 menit dan hanya dapat dipakai satu kali.',
])

@section('content')
    <!-- Status Badge -->
    <div style="margin-bottom: 14px;">
        <span style="display: inline-block; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">
            Keamanan Akun
        </span>
    </div>

    <!-- Title & Context -->
    <div style="margin-bottom: 20px;">
        <h1 style="color: #0f172a; font-size: 20px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.35; letter-spacing: -0.3px;">
            Kode Verifikasi Anda
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
        Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda. Gunakan kode verifikasi berikut di halaman pengaturan ulang kata sandi untuk melanjutkan:
    </p>

    <!-- Primary Action: the OTP itself -->
    <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 8px 0 24px 0;">
        <tr>
            <td align="center" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 26px 20px;">
                <div style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 40px; font-weight: 700; color: #0f172a; letter-spacing: 10px; line-height: 1.2; text-align: center; word-break: break-all;">
                    {{ $code ?? '------' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Security & Expiration Info Card -->
    <div style="background-color: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 18px 22px; margin: 28px 0;">
        <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
            Catatan Keamanan:
        </p>
        <ul style="margin: 0; padding-left: 18px; color: #64748b; font-size: 13px; line-height: 1.65;">
            <li>Kode ini hanya berlaku selama <strong>{{ $count ?? 5 }} menit</strong> sejak diterbitkan.</li>
            <li>Kode hanya dapat digunakan <strong>satu kali</strong>. permintaan kode baru akan membatalkan kode ini.</li>
            <li>Kami <strong>tidak pernah</strong> meminta kode ini melalui telepon, WhatsApp, atau email lain.</li>
            <li>Jika Anda tidak merasa meminta reset kata sandi, abaikan email ini dan abaikan saja.</li>
        </ul>
    </div>

    <!-- Next Step Reminder -->
    <p style="color: #475569; font-size: 13px; line-height: 1.65; margin: 0; text-align: center;">
        Setelah kode terverifikasi, Anda akan diminta membuat kata sandi baru.<br>
        Gunakan kombinasi minimal 8 karakter dengan huruf besar, huruf kecil, angka, dan simbol.
    </p>
@endsection
