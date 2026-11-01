@extends('layouts.app')
@section('title', 'Reorder List')

@section('content')
<section class="content-header">
    <h1>Reorder List <small>Products at or below their alert level</small></h1>
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
                <a class="btn btn-success" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="fa fa-download"></i> Export CSV</a>
                <button type="button" class="btn btn-default" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4"><div class="well text-center"><small>Items to reorder</small><h3>{{ $itemCount }}</h3></div></div>
        <div class="col-md-4"><div class="well text-center"><small>Estimated cost at last price</small><h3>KSh {{ number_format($totalCost, 2) }}</h3></div></div>
    </div>

    @forelse($groups as $g)
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $g['supplier'] }} @if($g['mobile'])<small>{{ $g['mobile'] }}</small>@endif</h3>
            <div class="box-tools">
                @if($g['wa'])<a href="{{ $g['wa'] }}" target="_blank" class="btn btn-xs btn-success"><i class="fab fa-whatsapp"></i> Order on WhatsApp</a>@endif
                <span class="label label-default">Est. KSh {{ number_format($g['cost'], 2) }}</span>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Product</th><th>SKU</th><th>Location</th><th class="text-right">In stock</th><th class="text-right">Alert level</th><th class="text-right">Sold (30d)</th><th class="text-right">Suggested order</th><th class="text-right">Last cost</th></tr></thead>
                <tbody>
                @foreach($g['items'] as $i)
                    <tr class="{{ $i['qty'] <= 0 ? 'danger' : '' }}">
                        <td>{{ $i['name'] }}</td>
                        <td>{{ $i['sku'] }}</td>
                        <td>{{ $i['location'] }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format($i['qty'], 2, '.', ''), '0'), '.') ?: '0' }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format($i['alert'], 2, '.', ''), '0'), '.') }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format($i['sold30'], 2, '.', ''), '0'), '.') ?: '0' }}</td>
                        <td class="text-right"><strong>{{ $i['suggest'] }}</strong></td>
                        <td class="text-right">{{ $i['cost'] > 0 ? number_format($i['cost'], 2) : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @empty
        <div class="alert alert-success"><i class="fa fa-check"></i> Nothing is below its alert level.</div>
    @endforelse

    <p class="text-muted">
        Suggested order = the larger of twice the alert level and the last 30 days' sales, minus what you hold now.
        Supplier = whoever you last bought the item from. Only products with an alert quantity set appear here.
    </p>
</section>
@endsection
