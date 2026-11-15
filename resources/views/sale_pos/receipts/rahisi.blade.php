<style>
@page { size: 80mm auto; margin: 0; }
.rr { width: 72mm; margin: 0 auto; padding: 2mm 0; font-family: "Courier New", monospace; font-size: 11px; line-height: 1.25; color: #000; }
.rr .c { text-align: center; } .rr .b { font-weight: bold; }
.rr .hr { border-top: 1px dashed #000; margin: 3px 0; }
.rr table { width: 100%; border-collapse: collapse; }
.rr td, .rr th { padding: 0; vertical-align: top; }
.rr .r { text-align: right; }
.rr img.logo { max-height: 55px; max-width: 55px; filter: grayscale(1) contrast(200%); }
</style>
<div class="rr">
  <div class="c">
    @if(!empty($receipt_details->logo) && !empty($receipt_details->show_logo))
      <img class="logo" src="{{ $receipt_details->logo }}"><br>
    @endif
    <span class="b" style="font-size:14px">{{ 'RAHISI SUPERMARKET' }}</span><br>
    @if(!empty($receipt_details->address)){!! $receipt_details->address !!}<br>@endif
    @if(!empty($receipt_details->contact))Tel: {{ trim(preg_replace('/^\s*Mobile:\s*/i', '', strip_tags($receipt_details->contact))) }}<br>@endif
    @if(!empty($receipt_details->tax_info1) && $receipt_details->tax_info1 !== 'n/a'){{ $receipt_details->tax_label1 ?? '' }} {{ $receipt_details->tax_info1 }}<br>@endif
    @if(!empty($receipt_details->tax_info2) && $receipt_details->tax_info2 !== 'n/a'){{ $receipt_details->tax_label2 ?? '' }} {{ $receipt_details->tax_info2 }}<br>@endif
  </div>
  <div class="hr"></div>
  <table>
    <tr><td>Receipt #{{ $receipt_details->invoice_no }}</td><td class="r">{{ $receipt_details->invoice_date ?? '' }}</td></tr>
    @if(!empty($receipt_details->sales_person))<tr><td colspan="2">Served by: {{ $receipt_details->sales_person }}</td></tr>@endif
    @if(!empty($receipt_details->customer_name))<tr><td colspan="2">Customer: {{ $receipt_details->customer_name }}</td></tr>@endif
  </table>
  <div class="hr"></div>
  <table>
    <tr class="b"><td>ITEM</td><td class="r">QTY</td><td class="r">PRICE</td><td class="r">AMT</td></tr>
    @foreach($receipt_details->lines as $line)
      <tr><td colspan="4">{{ $line['name'] ?? '' }}</td></tr>
      <tr><td></td><td class="r">{{ $line['quantity'] ?? '' }}</td><td class="r">{{ $line['unit_price_inc_tax'] ?? '' }}</td><td class="r">{{ $line['line_total'] ?? '' }}</td></tr>
    @endforeach
  </table>
  <div class="hr"></div>
  <table>
    @if(!empty($receipt_details->discount))<tr><td>Discount</td><td class="r">{{ $receipt_details->discount }}</td></tr>@endif
    <tr class="b" style="font-size:13px"><td>TOTAL</td><td class="r">{{ $receipt_details->total ?? '' }}</td></tr>
    @if(!empty($receipt_details->payments))
      @foreach($receipt_details->payments as $p)
        <tr><td>{{ $p['method'] ?? '' }}</td><td class="r">{{ $p['amount'] ?? '' }}</td></tr>
      @endforeach
    @endif
  </table>
  @if(!empty($receipt_details->pd_points_name) && ((float) ($receipt_details->pd_points_earned ?? 0) > 0 || (float) ($receipt_details->pd_points_balance ?? 0) > 0))
    <div class="hr"></div>
    @if((float) ($receipt_details->pd_points_earned ?? 0) > 0)
      <div class="c b">{{ $receipt_details->pd_points_name }} earned: {{ $receipt_details->pd_points_earned }}</div>
    @endif
    <div class="c b">{{ $receipt_details->pd_points_name }} balance: {{ $receipt_details->pd_points_balance }}</div>
    @if(!empty($receipt_details->pd_points_expiry))
      <div class="c">Points expire {{ $receipt_details->pd_points_expiry }}</div>
    @endif
  @endif
  <div class="hr"></div>
  <div class="c">
    Prices inclusive of VAT where applicable<br>
    {{ $receipt_details->pd_footer_base ?? ($receipt_details->footer_text ?? 'Thank you for shopping at Rahisi Supermarket') }}
  </div>
  <div class="c" style="margin-top:4px;text-align:center">
    <img style="height:28px;max-width:70mm;display:block;margin:0 auto" src="data:image/png;base64,{{ DNS1D::getBarcodePNG($receipt_details->invoice_no, 'C128', 1.5, 28) }}">
  </div>
</div>
