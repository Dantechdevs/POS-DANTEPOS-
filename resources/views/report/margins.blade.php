@extends('layouts.app')
@section('title', 'Profit Margins')

@section('content')
<section class="content-header">
    <h1>Profit Margins <small>What each product really earns</small></h1>
</section>

<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <form method="GET" class="form-inline">
                <select name="location_id" class="form-control">
                    @foreach($locations as $id => $name)
                        <option value="{{ $id }}" {{ (string)$locationId === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"> to
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
                <button class="btn btn-primary">Filter</button>
                <a class="btn btn-success" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="fa fa-download"></i> Export CSV</a>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3"><div class="well text-center"><small>Revenue (ex VAT)</small><h3>KSh {{ number_format($sum['revenue'], 2) }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Cost of goods</small><h3>KSh {{ number_format($sum['cost'], 2) }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Gross profit</small><h3 class="{{ $sum['profit'] < 0 ? 'text-red' : 'text-green' }}">KSh {{ number_format($sum['profit'], 2) }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Margin</small><h3>{{ number_format($sum['margin'], 1) }}%</h3></div></div>
    </div>

    @if(count($below))
    <div class="box box-danger">
        <div class="box-header with-border"><h3 class="box-title">Sold below cost ({{ count($below) }})</h3>
            <div class="box-tools"><span class="label label-danger">Loss KSh {{ number_format(array_sum(array_column($below, 'loss')), 2) }}</span></div></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Invoice</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Sold at</th><th class="text-right">Cost</th><th class="text-right">Loss</th></tr></thead>
                <tbody>
                @foreach($below as $b)
                    <tr>
                        <td>{{ substr($b['date'], 0, 16) }}</td><td>{{ $b['invoice'] }}</td><td>{{ $b['name'] }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format($b['qty'], 2, '.', ''), '0'), '.') }}</td>
                        <td class="text-right">{{ number_format($b['price'], 2) }}</td>
                        <td class="text-right">{{ number_format($b['cost'], 2) }}</td>
                        <td class="text-right text-red">{{ number_format($b['loss'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">By product</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Product</th><th>SKU</th><th class="text-right">Qty</th><th class="text-right">Revenue</th><th class="text-right">Cost</th><th class="text-right">Profit</th><th class="text-right">Margin</th></tr></thead>
                <tbody>
                @forelse($products as $p)
                    <tr class="{{ $p['profit'] < 0 ? 'danger' : '' }}">
                        <td>{{ $p['name'] }} @if($p['no_cost'])<span class="label label-warning" title="No purchase price set, so profit is overstated">no cost</span>@endif</td>
                        <td>{{ $p['sku'] }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format($p['qty'], 2, '.', ''), '0'), '.') }}</td>
                        <td class="text-right">{{ number_format($p['revenue'], 2) }}</td>
                        <td class="text-right">{{ number_format($p['cost'], 2) }}</td>
                        <td class="text-right">{{ number_format($p['profit'], 2) }}</td>
                        <td class="text-right">{{ $p['margin'] === null ? '-' : number_format($p['margin'], 1).'%' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="text-muted">Cost uses each product's current purchase price, so figures are estimates (not exact FIFO). Revenue is after discounts and before VAT. Products with no purchase price show as "no cost" and look more profitable than they are.</p>
        </div>
    </div>
</section>
@endsection
