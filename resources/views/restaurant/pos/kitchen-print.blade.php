<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تذكرة المطبخ — {{ $order->order_number }}</title>
    <style>
        *{box-sizing:border-box}body{font-family:Arial,Tahoma,sans-serif;margin:0;padding:16px;color:#111}
        .receipt{width:80mm;max-width:100%;margin:0 auto}h1{text-align:center;font-size:18px;margin:0 0 6px}
        .meta{font-size:12px;line-height:1.7;border-block:1px dashed #888;padding:8px 0;margin:8px 0}
        h2{font-size:15px;margin:14px 0 5px}.item{display:flex;gap:8px;justify-content:space-between;border-bottom:1px dotted #bbb;padding:7px 0;font-size:13px}
        .notes{font-size:11px;color:#444;margin:3px 0 8px}.no-print{margin:0 auto 12px;display:block}
        @media print{body{padding:0}.no-print{display:none}.receipt{width:80mm}}
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()">طباعة التذكرة</button>
    <div class="receipt">
        <h1>تذكرة المطبخ</h1>
        <div class="meta">
            <div><strong>{{ $order->order_number }}</strong> — {{ $order->location?->name }}</div>
            <div>الوقت: {{ $order->confirmed_at?->format('Y-m-d H:i') ?? $order->created_at?->format('Y-m-d H:i') }}</div>
            @if($order->restaurantTable)<div>الطاولة: {{ $order->restaurantTable->displayName() }}</div>@endif
        </div>
        @foreach($order->kitchenTickets as $ticket)
            <h2>{{ $ticket->station?->name ?? 'المطبخ' }} · {{ $ticket->ticket_number }}</h2>
            @foreach($ticket->items as $item)
                <div class="item"><strong>{{ $item->product_name }}</strong><strong>× {{ (float) $item->quantity }}</strong></div>
                @if($item->kitchen_notes)<div class="notes">{{ $item->kitchen_notes }}</div>@endif
            @endforeach
        @endforeach
    </div>
    <script>window.addEventListener('load', () => window.setTimeout(() => window.print(), 250));</script>
</body>
</html>
