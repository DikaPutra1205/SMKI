<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject ?? 'Pemberitahuan Sistem SMKI' }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        html, body {
            margin: 0 auto !important;
            padding: 0 !important;
            height: 100% !important;
            width: 100% !important;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: #334155;
        }
        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
            box-sizing: border-box;
        }
        table, td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }
        table {
            border-spacing: 0 !important;
            border-collapse: collapse !important;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        a {
            text-decoration: none;
        }
        @media screen and (max-width: 640px) {
            .email-container {
                width: 100% !important;
                margin: auto !important;
                border-radius: 0 !important;
                border: none !important;
            }
            .mobile-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
            .mobile-header-padding {
                padding: 20px 20px 16px 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <!-- Preview Preheader Text (Hidden) -->
    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        {{ $preheader ?? 'Pemberitahuan resmi Sistem Manajemen Keamanan Informasi (SMKI).' }}
        &#847; &zwnj; &nbsp; &#8199; &shy; &#847; &zwnj; &nbsp; &#8199; &shy;
    </div>

    <!-- Outer Background Wrapper -->
    <table role="presentation" aria-hidden="true" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="background-color: #f1f5f9; padding: 36px 12px; margin: 0 auto;">
        <tr>
            <td align="center">
                <!-- Main Card Container (Max 620px) -->
                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="620">
                <tr>
                <td align="center" valign="top" width="620">
                <![endif]-->
                <table role="presentation" aria-hidden="true" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-container" style="max-width: 620px; background-color: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05); margin: 0 auto;">
                    
                    <!-- Decorative Top Gradient Line -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%); line-height: 4px; font-size: 4px;">
                            &nbsp;
                        </td>
                    </tr>

                    <!-- Elegant Minimalist Brand Header -->
                    <tr>
                        <td class="mobile-header-padding" style="padding: 22px 36px 18px 36px; border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
                            <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="left" style="vertical-align: middle;">
                                        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="vertical-align: middle;">
                                                    <span style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 15px; font-weight: 800; color: #0f172a; letter-spacing: 0.5px; display: inline-block;">SMKI</span>
                                                </td>
                                                <td style="padding: 0 10px; vertical-align: middle; color: #cbd5e1; font-size: 14px; font-weight: 300;">
                                                    |
                                                </td>
                                                <td style="vertical-align: middle;">
                                                    <span style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; font-weight: 600; color: #64748b; letter-spacing: -0.1px;">Portal Kepatuhan</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" style="vertical-align: middle;">
                                        <span style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 9999px; padding: 3px 10px; color: #64748b; font-size: 10.5px; font-weight: 600; letter-spacing: 0.3px; display: inline-block;">
                                            ISO/IEC 27001
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content Slot -->
                    <tr>
                        <td class="mobile-padding" style="padding: 32px 36px 28px 36px; background-color: #ffffff;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Discreet Security & Confidentiality Note -->
                    <tr>
                        <td class="mobile-padding" style="padding: 18px 36px; background-color: #f8fafc; border-top: 1px solid #f1f5f9;">
                            <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="color: #94a3b8; font-size: 11.5px; line-height: 1.6;">
                                        <strong style="color: #64748b; font-weight: 600;">Pemberitahuan Otomatis:</strong> Pesan ini diterbitkan secara otomatis oleh sistem untuk personel berwenang. Mohon tidak membalas langsung ke alamat email pengirim ini.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Sleek Clean Footer -->
                    <tr>
                        <td class="mobile-padding" style="background-color: #f8fafc; padding: 24px 36px 28px 36px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="color: #64748b; font-size: 12px; font-weight: 500; margin: 0 0 10px 0;">
                                &copy; {{ date('Y') }} Sistem Manajemen Keamanan Informasi (SMKI) &bull; Seluruh Hak Cipta Dilindungi
                            </p>
                            <p style="margin: 0; font-size: 12px;">
                                <a href="{{ url('/') }}" style="color: #196ecd; font-weight: 600; text-decoration: none; margin: 0 8px;">Beranda</a>
                                <span style="color: #cbd5e1;">&bull;</span>
                                <a href="{{ url('/dashboard') }}" style="color: #196ecd; font-weight: 600; text-decoration: none; margin: 0 8px;">Dashboard</a>
                                <span style="color: #cbd5e1;">&bull;</span>
                                <a href="{{ url('/login') }}" style="color: #196ecd; font-weight: 600; text-decoration: none; margin: 0 8px;">Masuk Portal</a>
                            </p>
                        </td>
                    </tr>

                </table>
                <!--[if (gte mso 9)|(IE)]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
