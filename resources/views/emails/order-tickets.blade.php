<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tus entradas</title>
</head>
@php
    $buyerName = trim($order->buyer->name.' '.$order->buyer->last_name);
    $venue = $order->presentation->season->venue;
    $firstItem = $order->items->first();
    $unitPrice = $firstItem && $firstItem->quantity > 0
        ? ((float) $firstItem->total_amount) / $firstItem->quantity
        : 0;
    $ticketTypeName = $firstItem?->name ?? 'Entrada';
    $venueAddress = $venue?->address ?? $venue?->name ?? '-';
@endphp
<body style="margin: 0; padding: 0; background: #f6f7f9; color: #1f2933; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f6f7f9; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width: 640px; max-width: 100%; background: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td>
                            @include('emails.partials.brand-header')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px 32px; border-bottom: 1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align: top;">
                                        <h1 style="margin: 0; font-size: 24px; line-height: 32px;">
                                            Tu compra para "{{ $order->presentation->season->show->title }}" está confirmada!!
                                        </h1>
                                    </td>
                                    <td align="right" style="width: 54px; vertical-align: top;">
                                        <span style="display: inline-block; width: 42px; height: 42px; border-radius: 50%; background: #16a34a; color: #ffffff; font-size: 28px; line-height: 42px; text-align: center; font-weight: 700;">✓</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 4px 0; width: 42%; color: #6b7280; font-size: 14px; font-weight: 700;">Comprador:</td>
                                    <td style="padding: 4px 0; color: #111827; font-size: 15px;">{{ $buyerName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280; font-size: 14px; font-weight: 700;">Código de reserva:</td>
                                    <td style="padding: 4px 0; color: #1d4ed8; font-size: 18px; font-weight: 700;">{{ $order->code }}</td>
                                </tr>
                            </table>

                            <div style="height: 1px; margin: 0 0 24px; background: #e5e7eb;"></div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 4px 0; width: 42%; color: #6b7280; font-size: 14px; font-weight: 700;">Fecha:</td>
                                    <td style="padding: 4px 0; color: #111827; font-size: 15px;">{{ $order->presentation->starts_at->format('d/m/Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280; font-size: 14px; font-weight: 700;">Lugar:</td>
                                    <td style="padding: 4px 0; color: #111827; font-size: 15px;">{{ $venueAddress }}</td>
                                </tr>
                            </table>

                            <div style="height: 1px; margin: 0 0 24px; background: #e5e7eb;"></div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 4px 0; width: 42%; color: #6b7280; font-size: 14px; font-weight: 700;">Cantidad de localidades:</td>
                                    <td style="padding: 4px 0; color: #111827; font-size: 15px;">{{ $order->total_quantity }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280; font-size: 14px; font-weight: 700;">Precio por localidad:</td>
                                    <td style="padding: 4px 0; color: #111827; font-size: 15px;">$ {{ number_format((float) $unitPrice, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; color: #6b7280; font-size: 14px; font-weight: 700;">Tipo de entrada:</td>
                                    <td style="padding: 4px 0; color: #111827; font-size: 15px;">{{ $ticketTypeName }}</td>
                                </tr>
                            </table>

                            <div style="height: 1px; margin: 0 0 24px; background: #e5e7eb;"></div>

                            <!-- <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; text-transform: uppercase;">Entrada</th>
                                        <th align="left" style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; text-transform: uppercase;">Codigo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->tickets as $ticket)
                                        <tr>
                                            <td style="padding: 14px 0; border-bottom: 1px solid #f0f2f5;">
                                                {{ $ticket->presentationTicketType?->name ?? 'Entrada' }}
                                            </td>
                                            <td style="padding: 14px 0; border-bottom: 1px solid #f0f2f5; font-weight: 700;">
                                                {{ $ticket->code }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table> -->

                            <p style="margin: 24px 0 0; color: #6b7280; font-size: 14px; line-height: 20px;">
                                Tené presente este email al momento de ingresar al espectáculo.
                            </p>
                            <p style="margin: 24px 0 0; color: #6b7280; font-size: 14px; line-height: 20px;">
                                ¡Muchas gracias por tu compra y que disfrutes la función!
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
