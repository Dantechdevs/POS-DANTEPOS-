@extends('layouts.app')
@section('title', 'Gift Card')

@section('content')
<section class="content-header">
    <h1>Gift Card <code>{{ \App\Services\GiftCardService::format($card->code) }}</code>
        @if ($card->status === 'void')<span class="label label-default">Void</span>@endif
    </h1>
</section>

<section class="content">
    <p><a href="{{ url('/gift-cards') }}">&larr; All gift cards</a></p>

    <div class="row">
        <div class="col-md-4"><div class="well text-center"><small>Balance</small><h2>KSh {{ number_format($card->balance, 2) }}</h2><small>of KSh {{ number_format($card->initial_amount, 2) }} issued</small></div></div>
        <div class="col-md-8">
            <div class="well">
                <strong>Recipient:</strong> {{ $card->customer_name ?: '-' }} {{ $card->customer_phone }}<br>
                <strong>Expires:</strong> {{ $card->expires_at ? date('d/m/Y', strtotime($card->expires_at)) : 'Never' }}<br>
                <strong>Issued:</strong> {{ substr($card->created_at, 0, 16) }}
            </div>
            @if ($card->status === 'active')
            <form method="POST" action="{{ url('/gift-cards/'.$card->id.'/topup') }}" class="form-inline" style="display:inline">
                @csrf
                <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="Top up (KSh)" required>
                <select name="received_via" class="form-control"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="card">Card</option></select>
                <button class="btn btn-success">Top up</button>
            </form>
            @if ($card->customer_phone)
            <form method="POST" action="{{ url('/gift-cards/'.$card->id.'/sms') }}" style="display:inline">@csrf<button class="btn btn-default"><i class="fa fa-sms"></i> SMS code</button></form>
            @endif
            @if ($wa)<a class="btn btn-success" target="_blank" href="{{ $wa }}"><i class="fab fa-whatsapp"></i> WhatsApp code</a>@endif
            <form method="POST" action="{{ url('/gift-cards/'.$card->id.'/void') }}" style="display:inline" onsubmit="return confirm('Void this card? Its remaining balance becomes 0.');">
                @csrf<button class="btn btn-danger">Void</button>
            </form>
            @endif
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">History</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Type</th><th class="text-right">Amount</th><th class="text-right">Balance after</th><th>Sale</th><th>By</th><th>Note</th></tr></thead>
                <tbody>
                @foreach ($ledger as $l)
                    <tr>
                        <td>{{ substr($l->created_at, 0, 16) }}</td>
                        <td>{{ ucfirst($l->type) }}</td>
                        <td class="text-right {{ $l->amount < 0 ? 'text-red' : 'text-green' }}">{{ $l->amount > 0 ? '+' : '' }}{{ number_format($l->amount, 2) }}</td>
                        <td class="text-right">{{ number_format($l->balance_after, 2) }}</td>
                        <td>{{ $l->invoice_no }}</td>
                        <td>{{ $l->first_name }}</td>
                        <td>{{ $l->note }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
