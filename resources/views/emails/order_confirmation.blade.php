<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;">
                            <h1 style="margin:0 0 8px 0;font-size:22px;font-weight:600;">Thanks for your order</h1>
                            <p style="margin:0;color:#666;font-size:14px;">
                                Order ID: <code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;">{{ $order['order_id'] }}</code>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px;">
                            <h2 style="margin:0 0 12px 0;font-size:16px;font-weight:600;border-bottom:1px solid #eee;padding-bottom:8px;">Items</h2>
                            @if (count($order['items']) === 0)
                                <p style="margin:0;color:#666;">No items recorded.</p>
                            @else
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                    @foreach ($order['items'] as $item)
                                        <tr>
                                            <td style="padding:8px 0;font-size:14px;">
                                                {{ $item['name'] }}
                                                <span style="color:#666;">× {{ $item['quantity'] }}</span>
                                            </td>
                                            <td style="padding:8px 0;font-size:14px;text-align:right;font-variant-numeric:tabular-nums;">
                                                {{ $item['price'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td style="padding:12px 0 0 0;font-size:15px;font-weight:600;border-top:1px solid #eee;">Total</td>
                                        <td style="padding:12px 0 0 0;font-size:15px;font-weight:600;text-align:right;border-top:1px solid #eee;font-variant-numeric:tabular-nums;">
                                            {{ $order['total'] }}
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    @if (! empty($order['shipping_address']))
                        <tr>
                            <td style="padding:16px 32px;">
                                <h2 style="margin:0 0 12px 0;font-size:16px;font-weight:600;border-bottom:1px solid #eee;padding-bottom:8px;">Shipping to</h2>
                                <p style="margin:0;font-size:14px;line-height:1.6;">
                                    {{ $order['shipping_address']['name'] }}<br>
                                    {{ $order['shipping_address']['line1'] }}
                                    @if (! empty($order['shipping_address']['line2']))
                                        <br>{{ $order['shipping_address']['line2'] }}
                                    @endif
                                    <br>
                                    {{ trim($order['shipping_address']['city'] . ', ' . $order['shipping_address']['state'] . ' ' . $order['shipping_address']['postal_code'], ', ') }}<br>
                                    {{ $order['shipping_address']['country'] }}
                                </p>
                            </td>
                        </tr>
                    @endif

                    @if (! empty($order['recommendations']))
                        <tr>
                            <td style="padding:16px 32px 32px 32px;">
                                <h2 style="margin:0 0 12px 0;font-size:16px;font-weight:600;border-bottom:1px solid #eee;padding-bottom:8px;">You might also like</h2>
                                <p style="margin:0 0 16px 0;color:#666;font-size:13px;">Picked for you based on this order.</p>
                                @foreach ($order['recommendations'] as $rec)
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;background:#fafafa;border-radius:6px;">
                                        <tr>
                                            <td style="padding:12px 16px;">
                                                <div style="font-size:14px;font-weight:600;margin-bottom:4px;">
                                                    {{ $rec['name'] }}
                                                    <span style="float:right;font-weight:500;color:#1a1a1a;">{{ $rec['price'] }}</span>
                                                </div>
                                                <div style="font-size:13px;color:#444;line-height:1.5;margin-bottom:4px;">
                                                    {{ $rec['description'] }}
                                                </div>
                                                @if (! empty($rec['reason']))
                                                    <div style="font-size:12px;color:#666;font-style:italic;">
                                                        Why: {{ $rec['reason'] }}
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                @endforeach
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:16px 32px 32px 32px;text-align:center;color:#888;font-size:12px;border-top:1px solid #eee;">
                            Questions? Reply to this email and we'll get back to you.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
