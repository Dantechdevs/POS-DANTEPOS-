<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Gift vouchers</title>
@verbatim
<style>
body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;padding:16px;background:#f3f4f6}
.bar{margin-bottom:12px}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.v{background:#fff;border:2px dashed #1e3a8a;border-radius:12px;padding:18px;text-align:center;page-break-inside:avoid}
.v h2{margin:0 0 4px;color:#1e3a8a;font-size:18px}
.v .amt{font-size:34px;font-weight:800;margin:6px 0}
.v .code{font:700 22px/1.2 monospace;letter-spacing:2px;margin:8px 0}
.v small{color:#555}
.v svg{max-width:100%;height:48px}
@media print{body{background:#fff;padding:0}.bar{display:none}}
</style>
@endverbatim
</head>
<body>
<div class="bar"><button onclick="window.print()">Print</button> <a href="{{ url('/gift-cards') }}">Back to gift cards</a></div>
<div class="grid">
@foreach ($cards as $c)
    @php
        try { $svg = (new \Milon\Barcode\DNS1D())->getBarcodeSVG($c->code_key, 'C128', 1.4, 40); } catch (\Throwable $e) { $svg = null; }
    @endphp
    <div class="v">
        <h2>{{ $business }}</h2>
        <div>Gift Voucher</div>
        <div class="amt">{{ $currency }} {{ number_format($c->initial_amount, 0) }}</div>
        <div class="code">{{ $c->code }}</div>
        @if ($svg){!! $svg !!}@endif
        <div><small>{{ $c->recipient_name ? 'For '.$c->recipient_name.'. ' : '' }}{{ $c->expires_at ? 'Valid until '.\Carbon\Carbon::parse($c->expires_at)->format('d/m/Y').'.' : 'No expiry.' }}</small></div>
        <div><small>Show this code at the till.</small></div>
    </div>
@endforeach
</div>
</body>
</html>
