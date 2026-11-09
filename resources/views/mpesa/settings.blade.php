@extends('layouts.app')
@section('title', 'M-Pesa Settings')


@section('content')
<section class="content-header"><h1>M-Pesa Settings <small>Paybill or Till</small></h1></section>

<section class="content">
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="box box-solid">
        <div class="box-body">
            <form method="POST" action="{{ url('/mpesa/settings') }}">
                @csrf
                @php $type = old('account_type', $s->account_type ?? 'till'); @endphp

                <div class="form-group">
                    <label>How do customers pay you?</label>
                    <select name="account_type" id="acct_type" class="form-control" style="max-width:320px">
                        <option value="till" @selected($type === 'till')>Till number (Buy Goods)</option>
                        <option value="paybill" @selected($type === 'paybill')>Paybill (account number)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Shortcode used for STK Push (<span class="acct-till">store / head-office number</span><span class="acct-paybill">the Paybill number</span>)</label>
                    <input type="text" name="shortcode" class="form-control" style="max-width:320px" value="{{ old('shortcode', $s->shortcode ?? $envShortcode) }}" required>
                    <small class="text-muted">This is the shortcode on your Daraja app together with your passkey. The Daraja keys themselves stay in <code>.env</code>.</small>
                </div>

                <div class="form-group acct-till">
                    <label>Till number customers pay</label>
                    <input type="text" name="till_number" class="form-control" style="max-width:320px" value="{{ old('till_number', $s->till_number ?? '') }}">
                    <small class="text-muted">Shown to customers who pay by hand. If your till has a separate store number, put the store number above and the till number here.</small>

                </div>

                <div class="form-group acct-paybill">
                    <label>Account number customers type</label>
                    <input type="text" name="account_hint" class="form-control" style="max-width:320px" value="{{ old('account_hint', $s->account_hint ?? '') }}" placeholder="e.g. the shop name">
                </div>

                <hr>
                <div class="checkbox">
                    <label><input type="checkbox" name="manual_enabled" value="1" @checked(old('manual_enabled', $s->manual_enabled ?? 1))> Allow cashiers to enter an M-Pesa code by hand when STK Push is not possible</label>
                </div>
                <div class="form-group">
                    <label>Most unverified manual payments one cashier can have open (KSh, 0 = no limit)</label>
                    <input type="number" name="manual_unverified_cap" step="any" min="0" class="form-control" style="max-width:320px" value="{{ old('manual_unverified_cap', $s->manual_unverified_cap ?? 20000) }}">
                    <small class="text-muted">When a cashier reaches it, new manual payments are refused until you verify the earlier ones.</small>
                </div>

                <button class="btn btn-primary">Save</button>
                <a href="{{ url('/reports/mpesa-reconciliation') }}" class="btn btn-default">Back</a>
            </form>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    function acctToggle() {
        var t = $('#acct_type').val();
        $('.acct-till').toggle(t === 'till');
        $('.acct-paybill').toggle(t === 'paybill');
    }

    $('#acct_type').on('change', acctToggle); acctToggle();
</script>
@endsection
