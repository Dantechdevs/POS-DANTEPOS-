@extends('layouts.app')
@section('title', 'Supplier Payables Aging')

@section('content')
<section class="content-header">
    <h1>Supplier Payables Aging <small>What you owe, and how overdue it is</small></h1>
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
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Supplier</th><th>Phone</th><th>Bills</th><th>Oldest due</th>
                        <th class="text-right">Not yet due</th><th class="text-right">1-30 overdue</th>
                        <th class="text-right">31-60</th><th class="text-right">61-90</th><th class="text-right">90+</th>
                        <th class="text-right">Total owed</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($suppliers as $s)
                    <tr class="{{ $s['b90p'] > 0 ? 'danger' : ($s['b60'] + $s['b90'] > 0 ? 'warning' : '') }}">
                        <td>{{ $s['name'] }}</td>
                        <td>{{ $s['mobile'] }}</td>
                        <td>{{ $s['bills'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($s['oldest_due'])->format('d/m/Y') }}</td>
                        <td class="text-right">{{ number_format($s['b0'], 2) }}</td>
                        <td class="text-right">{{ number_format($s['b30'], 2) }}</td>
                        <td class="text-right">{{ number_format($s['b60'], 2) }}</td>
                        <td class="text-right">{{ number_format($s['b90'], 2) }}</td>
                        <td class="text-right">{{ number_format($s['b90p'], 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($s['total'], 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center">You owe no supplier anything.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-gray">
                        <th colspan="4">TOTAL</th>
                        <th class="text-right">{{ number_format($totals['b0'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['b30'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['b60'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['b90'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['b90p'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['total'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
            <p class="text-muted">Due date = purchase date + the bill's pay term (no pay term means due on the purchase date). Received purchases only. Supplier opening balances and purchase returns are not included.</p>
        </div>
    </div>
</section>
@endsection
