@extends('layouts.app')
@section('title', 'Expiry Watch')

@section('content')
<section class="content-header">
    <h1>Expiry Watch <small>Stock that is expired or about to expire</small></h1>
</section>

<section class="content">
    <div class="box box-solid">
        <div class="box-header">
            <form method="GET" class="form-inline">
                <select name="location_id" class="form-control" onchange="this.form.submit()">
                    @foreach($locations as $id => $name)
                        <option value="{{ $id }}" {{ (string)$locationId === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="days" class="form-control" onchange="this.form.submit()">
                    @foreach([30, 60, 90, 180] as $d)
                        <option value="{{ $d }}" {{ $horizon === $d ? 'selected' : '' }}>Next {{ $d }} days</option>
                    @endforeach
                </select>
                <a class="btn btn-success" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="fa fa-download"></i> Export CSV</a>
                <button type="button" class="btn btn-default" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </form>
        </div>
    </div>

    @php $any = false; @endphp
    @foreach($sections as $s)
        @if(count($s['rows']))
        @php $any = true; @endphp
        <div class="box {{ $s['class'] }}">
            <div class="box-header with-border">
                <h3 class="box-title">{{ $s['title'] }} ({{ count($s['rows']) }})</h3>
                <div class="box-tools"><span class="label label-default">KSh {{ number_format($s['value'], 2) }} at cost</span></div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Product</th><th>SKU</th><th>Lot</th><th>Location</th><th>Expiry date</th><th class="text-right">Days</th><th class="text-right">Qty left</th><th class="text-right">Value at cost</th></tr></thead>
                    <tbody>
                    @foreach($s['rows'] as $r)
                        <tr>
                            <td>{{ $r['name'] }}</td>
                            <td>{{ $r['sku'] }}</td>
                            <td>{{ $r['lot'] }}</td>
                            <td>{{ $r['location'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($r['exp'])->format('d/m/Y') }}</td>
                            <td class="text-right">{{ $r['days'] < 0 ? abs($r['days']).' ago' : $r['days'] }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format($r['qty'], 2, '.', ''), '0'), '.') ?: '0' }}</td>
                            <td class="text-right">{{ number_format($r['value'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach

    @unless($any)
        <div class="alert alert-success"><i class="fa fa-check"></i> Nothing expires in this window.</div>
    @endunless

    <p class="text-muted">
        Only stock received with an expiry date appears here. Turn on Product Expiry in Business Settings, then enter the date when you receive a purchase.
        Expired stock still counts in your stock figures until you write it off with a Stock Adjustment.
    </p>
</section>
@endsection
