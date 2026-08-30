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
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            table-layout: fixed !important;
            margin: 0 auto !important;
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
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: auto !important;
                border-radius: 0 !important;
                border-left: none !important;
                border-right: none !important;
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
<body style="margin: 0; padding: 0; background-color: #f8fafc; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <!-- Preview Preheader Text (Hidden) -->
    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        {{ $preheader ?? 'Pemberitahuan resmi Sistem Manajemen Keamanan Informasi (SMKI).' }}
        &#847; &zwnj; &nbsp; &#8199; &shy; &#847; &zwnj; &nbsp; &#8199; &shy;
    </div>

    <!-- Outer Background Wrapper -->
    <table role="presentation" aria-hidden="true" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="background-color: #f8fafc; table-layout: fixed; padding: 40px 12px;">
        <tr>
            <td align="center">
                <!-- Main Card Container (Max 580px) -->
                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="580">
                <tr>
                <td align="center" valign="top" width="580">
                <![endif]-->
                <table role="presentation" aria-hidden="true" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-container" style="max-width: 580px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                    
                    <!-- Clean Elegant Header -->
                    <tr>
                        <td class="mobile-header-padding" style="padding: 24px 32px 20px 32px; border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
                            <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="left" style="vertical-align: middle;">
                                        <table role="presentation" aria-hidden="true" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="background-color: #0f172a; border-radius: 4px; padding: 4px 8px; vertical-align: middle;">
                                                    <span style="color: #ffffff; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;">SMKI</span>
                                                </td>
                                                <td style="padding-left: 10px; vertical-align: middle;">
                                                    <span style="color: #0f172a; font-size: 13.5px; font-weight: 600; letter-spacing: -0.2px;">Portal Kepatuhan</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" style="vertical-align: middle;">
                                        <span style="color: #94a3b8; font-size: 11.5px; font-weight: 500;">
                                            ISO/IEC 27001
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content Slot -->
                    <tr>
                        <td class="mobile-padding" style="padding: 32px 32px 28px 32px; background-color: #ffffff;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Discreet Confidentiality Note -->
                    <tr>
                        <td class="mobile-padding" style="padding: 16px 32px; background-color: #ffffff; border-top: 1px solid #f8fafc;">
                            <p style="margin: 0; color: #94a3b8; font-size: 11.5px; line-height: 1.5;">
                                <strong style="color: #64748b; font-weight: 600;">Kerahasiaan:</strong> Pesan ini ditujukan otomatis untuk personel berwenang dalam lingkup audit Sistem Manajemen Keamanan Informasi.
                            </p>
                        </td>
                    </tr>

                    <!-- Minimalist Footer -->
                    <tr>
                        <td class="mobile-padding" style="background-color: #f8fafc; padding: 24px 32px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="color: #64748b; font-size: 12px; font-weight: 500; margin: 0 0 8px 0;">
                                &copy; {{ date('Y') }} Sistem Manajemen Keamanan Informasi (SMKI)
                            </p>
                            <p style="margin: 0;">
                                <a href="{{ url('/') }}" style="color: #64748b; font-size: 12px; font-weight: 500; text-decoration: none; margin: 0 8px;">Beranda</a>
                                <span style="color: #cbd5e1;">&bull;</span>
                                <a href="{{ url('/admin/kepatuhan/dashboard') }}" style="color: #64748b; font-size: 12px; font-weight: 500; text-decoration: none; margin: 0 8px;">Dashboard</a>
                                <span style="color: #cbd5e1;">&bull;</span>
                                <a href="{{ url('/login') }}" style="color: #64748b; font-size: 12px; font-weight: 500; text-decoration: none; margin: 0 8px;">Masuk Akun</a>
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
