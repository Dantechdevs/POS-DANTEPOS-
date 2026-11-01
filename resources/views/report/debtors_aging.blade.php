@extends('layouts.app')
@section('title', 'Debtors Aging')

@section('content')
<section class="content-header">
    <h1>Debtors Aging <small>Who owes you, and for how long</small></h1>
</section>

<section class="content">
    <div class="box box-solid">
        <div class="box-header">
            <form method="GET" class="form-inline">
                <select name="location_id" class="form-control" onchange="this.form.submit()">
                    @foreach($locations as $id => $name)
                        <option value="{{ $id }}" {{ (string)$location_id === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <a class="btn btn-success" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">
                    <i class="fa fa-download"></i> Export CSV
                </a>
                <button type="button" class="btn btn-default" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </form>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Customer</th><th>Phone</th><th>Invoices</th><th>Oldest</th>
                        <th class="text-right">0-30</th><th class="text-right">31-60</th>
                        <th class="text-right">61-90</th><th class="text-right">90+</th>
                        <th class="text-right">Total Due</th><th>WhatsApp</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($debtors as $cid => $d)
                    <tr class="{{ $d['b90'] > 0 ? 'danger' : ($d['b61'] > 0 ? 'warning' : '') }}">
                        <td>{{ $d['name'] }}</td>
                        <td>{{ $d['mobile'] }}</td>
                        <td>{{ $d['invoices'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($d['oldest'])->format('d/m/Y') }}</td>
                        <td class="text-right">{{ number_format($d['b0'], 2) }}</td>
                        <td class="text-right">{{ number_format($d['b31'], 2) }}</td>
                        <td class="text-right">{{ number_format($d['b61'], 2) }}</td>
                        <td class="text-right">{{ number_format($d['b90'], 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($d['total'], 2) }}</strong></td>
                        <td><a href="{{ route('contacts.whatsapp_statement', $cid) }}" target="_blank" class="btn btn-xs btn-success"><i class="fab fa-whatsapp"></i> Send</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center">No outstanding balances.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-gray">
                        <th colspan="4">TOTAL</th>
                        <th class="text-right">{{ number_format($totals['b0'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['b31'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['b61'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['b90'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['total'], 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
            <p class="text-muted">Age is counted from the invoice date. Customer opening balances are not included.</p>
        </div>
    </div>
</section>
@endsection
