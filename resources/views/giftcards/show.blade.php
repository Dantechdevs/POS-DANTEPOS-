@extends('layouts.app')
@section('title', 'Gift Card '.$card->code)

@section('content')
<section class="content-header">
    <h1>Gift Card <code>{{ $card->code }}</code>
        @if ($status === 'active')<span class="label label-success">Usable</span>
        @elseif ($status === 'used')<span class="label label-default">Fully used</span>
        @elseif ($status === 'expired')<span class="label label-warning">Expired</span>
        @else<span class="label label-danger">Cancelled</span>@endif
    </h1>
</section>

<section class="content">
    <p><a href="{{ url('/gift-cards') }}">&larr; All gift cards</a></p>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="box box-solid">
                <div class="box-body">
                    <h2 style="margin-top:0">KSh {{ number_format($card->balance, 2) }}</h2>
                    <p class="text-muted">of KSh {{ number_format($card->initial_amount, 2) }} issued</p>
                    <table class="table table-condensed">
                        <tr><td>Paid by</td><td>{{ $methods[$card->issued_via] ?? $card->issued_via }}</td></tr>
                        <tr><td>Issued</td><td>{{ substr($card->created_at, 0, 16) }}</td></tr>
                        <tr><td>Expires</td><td>{{ $card->expires_at ? \Carbon\Carbon::parse($card->expires_at)->format('d/m/Y') : 'Never' }}</td></tr>
                        <tr><td>Recipient</td><td>{{ $card->recipient_name }} {{ $card->recipient_phone }}</td></tr>
                        <tr><td>Note</td><td>{{ $card->note }}</td></tr>
                    </table>
                    <a class="btn btn-default btn-sm" href="{{ url('/gift-cards/print?ids='.$card->id) }}" target="_blank">Print voucher</a>
                    @if ($card->recipient_phone)
                        <form method="POST" action="{{ url('/gift-cards/'.$card->id.'/sms') }}" style="display:inline">@csrf<button class="btn btn-default btn-sm">Resend SMS</button></form>
                    @endif
                </div>
            </div>

            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Change expiry</h3></div>
                <div class="box-body">
                    <form method="POST" action="{{ url('/gift-cards/'.$card->id.'/extend') }}" class="form-inline">
                        @csrf
                        <input type="date" name="expires_at" class="form-control" value="{{ $card->expires_at }}">
                        <button class="btn btn-default">Save</button>
                    </form>
                    <p class="text-muted" style="margin-top:6px">Leave empty for no expiry.</p>
                </div>
            </div>

            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Adjust balance</h3></div>
                <div class="box-body">
                    <form method="POST" action="{{ url('/gift-cards/'.$card->id.'/adjust') }}">
                        @csrf
                        <div class="form-group"><input type="number" name="amount" step="any" class="form-control" placeholder="+100 to add, -100 to remove" required></div>
                        <div class="form-group"><input type="text" name="reason" class="form-control" placeholder="Reason (required)" maxlength="200" required></div>
                        <button class="btn btn-warning">Adjust</button>
                    </form>
                </div>
            </div>

            @if ($card->status === 'active')
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title">Cancel this card</h3></div>
                <div class="box-body">
                    <form method="POST" action="{{ url('/gift-cards/'.$card->id.'/void') }}" onsubmit="return confirm('Cancel this gift card? Its balance becomes zero.');">
                        @csrf
                        <div class="form-group"><input type="text" name="reason" class="form-control" placeholder="Reason (required)" maxlength="200" required></div>
                        <button class="btn btn-danger">Cancel card</button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-8">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">History</h3></div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>When</th><th>What</th><th class="text-right">Amount</th><th class="text-right">Balance</th><th>Invoice</th><th>By</th><th>Note</th></tr></thead>
                        <tbody>
                        @foreach ($ledger as $l)
                            <tr>
                                <td>{{ substr($l->created_at, 0, 16) }}</td>
                                <td>{{ ucfirst($l->type) }}</td>
                                <td class="text-right {{ $l->amount < 0 ? 'text-red' : 'text-green' }}">{{ $l->amount > 0 ? '+' : '' }}{{ number_format($l->amount, 2) }}</td>
                                <td class="text-right">{{ number_format($l->balance_after, 2) }}</td>
                                <td>{{ $l->invoice_no }}</td>
                                <td>{{ $l->user_name }}</td>
                                <td>{{ $l->note }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
