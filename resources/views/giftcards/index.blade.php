@extends('layouts.app')
@section('title', 'Gift Cards')

@section('content')
<section class="content-header">
    <h1>Gift Cards <small>Vouchers and prepaid balances</small></h1>
</section>

<section class="content">
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @if (! $methodSlot)
        <div class="alert alert-warning">
            Gift cards can be issued, but they cannot be used at the till yet. Set <code>GIFT_CARD_METHOD=custom_pay_1</code> in <code>.env</code>, label that payment method "Gift Card", then run <code>php artisan config:clear</code>.
        </div>
    @endif

    <div class="row">
        <div class="col-md-3"><div class="well text-center"><small>Owed to customers (usable)</small><h3>KSh {{ number_format($tiles['outstanding'], 2) }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Redeemed today</small><h3>KSh {{ number_format($tiles['redeemed_today'], 2) }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Expired, unused</small><h3 class="text-muted">KSh {{ number_format($tiles['expired_value'], 2) }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Sold today</small>
            @forelse ($soldToday as $s)
                <div>{{ $methods[$s->issued_via] ?? $s->issued_via }}: <b>KSh {{ number_format($s->total, 2) }}</b> ({{ $s->n }})</div>
            @empty
                <h3>None</h3>
            @endforelse
        </div></div>
    </div>
    <p class="text-muted">
        Money received for gift cards is <b>not</b> in your sales reports or cash register. When you close the till, add today's cash gift card sales above to the expected cash.
        The sale is counted once, when the card is used.
    </p>

    <div class="row">
        <div class="col-md-5">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Issue gift cards</h3></div>
                <div class="box-body">
                    <form method="POST" action="{{ url('/gift-cards') }}">
                        @csrf
                        <div class="form-group"><label>Amount (KSh) each</label><input type="number" name="amount" step="any" min="1" class="form-control" value="{{ old('amount') }}" required></div>
                        <div class="form-group"><label>How many</label><input type="number" name="quantity" min="1" max="100" class="form-control" value="{{ old('quantity', 1) }}" required></div>
                        <div class="form-group"><label>Paid by</label>
                            <select name="issued_via" class="form-control">
                                @foreach ($methods as $k => $label)<option value="{{ $k }}" @selected(old('issued_via', 'cash') === $k)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group"><label>Expires on (optional)</label><input type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}"></div>
                        <div class="form-group"><label>Recipient name (optional)</label><input type="text" name="recipient_name" class="form-control" maxlength="100" value="{{ old('recipient_name') }}"></div>
                        <div class="form-group"><label>Recipient phone (optional, single card)</label><input type="text" name="recipient_phone" class="form-control" placeholder="0712345678" value="{{ old('recipient_phone') }}"></div>
                        <div class="checkbox"><label><input type="checkbox" name="send_sms" value="1"> SMS the code to the recipient</label></div>
                        <div class="form-group"><label>Note (optional)</label><input type="text" name="note" class="form-control" maxlength="255" value="{{ old('note') }}"></div>
                        <button class="btn btn-primary">Issue</button>
                    </form>
                </div>
            </div>
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Check a balance</h3></div>
                <div class="box-body">
                    <div class="input-group">
                        <input type="text" id="checkCode" class="form-control" placeholder="Gift card code">
                        <span class="input-group-btn"><button type="button" class="btn btn-default" id="checkBtn">Check</button></span>
                    </div>
                    <p id="checkResult" style="margin-top:10px"></p>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="box box-solid">
                <div class="box-body">
                    <form method="GET" class="form-inline">
                        <input type="text" name="q" value="{{ $term }}" class="form-control" placeholder="Code, name or phone">
                        <select name="status" class="form-control">
                            <option value="">All</option>
                            @foreach (['active' => 'Usable', 'used' => 'Fully used', 'expired' => 'Expired', 'void' => 'Cancelled'] as $k => $label)
                                <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-default">Filter</button>
                    </form>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Code</th><th>Recipient</th><th class="text-right">Balance</th><th class="text-right">Issued</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($cards as $c)
                            @php $st = $svc->status($c); @endphp
                            <tr>
                                <td><code>{{ $c->code }}</code></td>
                                <td>{{ $c->recipient_name }} {{ $c->recipient_phone }}</td>
                                <td class="text-right">{{ number_format($c->balance, 2) }}</td>
                                <td class="text-right">{{ number_format($c->initial_amount, 2) }}</td>
                                <td>{{ $c->expires_at ? \Carbon\Carbon::parse($c->expires_at)->format('d/m/Y') : '' }}</td>
                                <td>
                                    @if ($st === 'active')<span class="label label-success">Usable</span>
                                    @elseif ($st === 'used')<span class="label label-default">Used</span>
                                    @elseif ($st === 'expired')<span class="label label-warning">Expired</span>
                                    @else<span class="label label-danger">Cancelled</span>@endif
                                </td>
                                <td><a class="btn btn-xs btn-info" href="{{ url('/gift-cards/'.$c->id) }}">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No gift cards found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @if ($cards->count() === 200)<p class="text-muted">Showing the latest 200. Use the filter to narrow down.</p>@endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    function giftCheck() {
        var code = $('#checkCode').val();
        $.getJSON("{{ url('/gift-cards/check') }}", {code: code}, function (r) {
            if (!r.success) { $('#checkResult').html('<span class="text-red"></span>').find('span').text(r.message); return; }
            var label = r.usable ? 'Usable' : r.status;
            $('#checkResult').text(r.code + ': KSh ' + r.balance + ' (' + label + (r.expires ? ', expires ' + r.expires : '') + ')');
        });
    }
    $('#checkBtn').on('click', giftCheck);
    $('#checkCode').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); giftCheck(); } });
</script>
@endsection
