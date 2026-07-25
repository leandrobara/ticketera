<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dejá tu comentario</title>
</head>
<body style="margin:0;background:#f4f4f6;font-family:Arial,sans-serif;color:#18151d;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #dedce2;border-radius:8px;">
                    <tr>
                        <td>
                            @include('emails.partials.brand-header')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;">¿Qué te pareció {{ $commentToken->show->title }}?</h1>
                            <p style="margin:0 0 22px;color:#625d69;line-height:1.6;">
                                Tu opinión ayuda a otras personas a descubrir la obra y acompaña al teatro independiente.
                            </p>
                            <a href="{{ $commentUrl }}" style="display:inline-block;padding:13px 22px;border-radius:6px;background:#f52d65;color:#ffffff;font-weight:700;text-decoration:none;">
                                Dejar mi comentario
                            </a>
                            <p style="margin:24px 0 0;color:#77717e;font-size:13px;line-height:1.5;">
                                Este enlace es personal, puede utilizarse una sola vez y vence en {{ config('comments.token_expiration_hours') }} horas.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
