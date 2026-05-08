Thanks for your order

Order ID: {{ $order['order_id'] }}

Items:
@foreach ($order['items'] as $item)
- {{ $item['name'] }} × {{ $item['quantity'] }} - {{ $item['price'] }}
@endforeach

Total: {{ $order['total'] }}

@if (! empty($order['shipping_address']))
Shipping to:
{{ $order['shipping_address']['name'] }}
{{ $order['shipping_address']['line1'] }}
@if (! empty($order['shipping_address']['line2']))
{{ $order['shipping_address']['line2'] }}
@endif
{{ trim($order['shipping_address']['city'] . ', ' . $order['shipping_address']['state'] . ' ' . $order['shipping_address']['postal_code'], ', ') }}
{{ $order['shipping_address']['country'] }}

@endif
@if (! empty($order['recommendations']))
You might also like:
@foreach ($order['recommendations'] as $rec)
- {{ $rec['name'] }} ({{ $rec['price'] }})
  {{ $rec['description'] }}
@if (! empty($rec['reason']))
  Why: {{ $rec['reason'] }}
@endif
@endforeach

@endif
Questions? Reply to this email and we'll get back to you.
