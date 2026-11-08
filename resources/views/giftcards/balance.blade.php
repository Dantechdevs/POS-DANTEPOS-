@extends('layouts.app')
@section('title', 'Gift Card Balance')

@section('content')
<section class="content-header"><h1>Gift Card Balance</h1></section>
<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <p>Check a customer's card before they pay. To use it at the till, choose <b>Gift Card</b> as the payment method and type the code in <b>Transaction No.</b></p>
            <div class="input-group" style="max-width:420px">
                <input type="text" id="checkCode" class="form-control" placeholder="Gift card code" autofocus>
                <span class="input-group-btn"><button type="button" class="btn btn-primary" id="checkBtn">Check</button></span>
            </div>
            <h3 id="checkResult" style="margin-top:20px"></h3>
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
