@extends('layouts.app')
@section('title', 'Gift Cards')

@section('content')
<section class="content-header">
    <h1>Gift Cards <small>Issue, top up and track vouchers</small></h1>
</section>

<section class="content">
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="row">
        <div class="col-md-4"><div class="well text-center"><small>Unredeemed balance (goods you still owe)</small><h3>KSh {{ number_format($liability, 2) }}</h3></div></div>
        <div class="col-md-4"><div class="well text-center"><small>Active cards</small><h3>{{ $activeCount }}</h3></div></div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">Issue a gift card</h3></div>
        <div class="box-body">
            <form method="POST" action="{{ url('/gift-cards') }}" class="form-inline">
                @csrf
                <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="Amount (KSh)" value="{{ old('amount') }}" required>
                <select name="received_via" class="form-control">
                    <option value="cash">Paid by cash</option>
                    <option value="mpesa">Paid by M-Pesa</option>
                    <option value="card">Paid by card</option>
                    <option value="bank">Paid by bank</option>
                </select>
                <input type="text" name="customer_name" class="form-control" placeholder="Recipient name (optional)" value="{{ old('customer_name') }}">
                <input type="text" name="customer_phone" class="form-control" placeholder="Recipient phone (optional)" value="{{ old('customer_phone') }}">
                <input type="date" name="expires_at" class="form-control" title="Expiry (optional)" value="{{ old('expires_at') }}">
                <label><input type="checkbox" name="send_sms" value="1"> SMS the code</label>
                <button class="btn btn-primary">Issue card</button>
            </form>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <form method="GET" class="form-inline">
                <input type="text" name="q" class="form-control" placeholder="Search code, name or phone" value="{{ request('q') }}">
                <button class="btn btn-default">Search</button>
            </form>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Code</th><th>Recipient</th><th class="text-right">Initial</th><th class="text-right">Balance</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($cards as $c)
                    @php $expired = $c->expires_at && $c->expires_at < date('Y-m-d'); @endphp
                    <tr>
                        <td><code>{{ \App\Services\GiftCardService::format($c->code) }}</code></td>
                        <td>{{ $c->customer_name }} {{ $c->customer_phone }}</td>
                        <td class="text-right">{{ number_format($c->initial_amount, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($c->balance, 2) }}</strong></td>
                        <td>{{ $c->expires_at ? date('d/m/Y', strtotime($c->expires_at)) : '-' }}</td>
                        <td>
                            @if ($c->status === 'void')<span class="label label-default">Void</span>
                            @elseif ($expired)<span class="label label-danger">Expired</span>
                            @elseif ($c->balance <= 0)<span class="label label-default">Used up</span>
                            @else<span class="label label-success">Active</span>@endif
                        </td>
                        <td><a class="btn btn-xs btn-info" href="{{ url('/gift-cards/'.$c->id) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No gift cards yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
