@extends('layouts.app')
@section('title', 'M-Pesa Reconciliation')

@section('content')
<section class="content-header">
    <h1>M-Pesa Reconciliation <small>Money received vs sales recorded</small></h1>
</section>

<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <form method="GET" class="form-inline">
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
                to
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
                <button class="btn btn-primary">Filter</button>
                <a class="btn btn-success" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="fa fa-download"></i> Export CSV</a>
            </form>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-{{ session('status')['success'] ? 'success' : 'danger' }}">{{ session('status')['msg'] }}</div>
    @endif

    <div class="box box-solid">
        <div class="box-body">
            <form method="POST" action="{{ url('/mpesa/statement-import') }}" enctype="multipart/form-data" class="form-inline">
                @csrf
                <label>Verify manual payments from your M-Pesa statement (CSV):</label>
                <input type="file" name="statement" accept=".csv,text/csv" class="form-control" required>
                <button class="btn btn-warning">Upload and verify</button>
                @can('business_settings.access')
                    <a href="{{ url('/mpesa/settings') }}" class="btn btn-default pull-right"><i class="fa fa-cog"></i> Paybill / Till settings</a>
                @endcan
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-2"><div class="well text-center"><small>Received (confirmed)</small><h3>KSh {{ number_format($summary['received'], 2) }}</h3></div></div>
        <div class="col-md-2"><div class="well text-center"><small>Unmatched</small><h3 class="text-red">KSh {{ number_format($summary['unmatched'], 2) }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Manual, unverified</small><h3 class="{{ $summary['manual'] ? 'text-orange' : '' }}">{{ $summary['manual'] }} <small>(KSh {{ number_format($summary['manual_amount'], 2) }})</small></h3></div></div>
        <div class="col-md-2"><div class="well text-center"><small>Needs review</small><h3>{{ $summary['review'] }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Stuck pending</small><h3>{{ $summary['stale'] }}</h3></div></div>
    </div>

    @foreach($sections as $s)
    <div class="box {{ $s['class'] }}">
        <div class="box-header with-border"><h3 class="box-title">{{ $s['title'] }} ({{ count($s['rows']) }})</h3></div>
        <div class="box-body table-responsive">
            <p class="text-muted">{{ $s['help'] }}</p>
            @if(count($s['rows']))
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Phone</th><th class="text-right">Amount</th><th>Receipt</th><th>Detail</th>@if($s['recheck'] || !empty($s['verify']))<th></th>@endif</tr></thead>
                <tbody>
                @foreach($s['rows'] as $r)
                    <tr>
                        <td>{{ substr($r['date'], 0, 16) }}</td>
                        <td>{{ $r['phone'] }}</td>
                        <td class="text-right">{{ number_format($r['amount'], 2) }}</td>
                        <td>{{ $r['receipt'] }}</td>
                        <td>{{ $r['detail'] }}</td>
                        @if($s['recheck'])
                        <td><button type="button" class="btn btn-xs btn-info mpesa-recheck" data-id="{{ $r['checkout'] }}">Re-check</button></td>
                        @endif
                        @if(!empty($s['verify']))
                        <td style="white-space:nowrap">
                            <form method="POST" action="{{ url('/mpesa/manual/'.$r['id'].'/verify') }}" style="display:inline">@csrf<button class="btn btn-xs btn-success">Verify</button></form>
                            <form method="POST" action="{{ url('/mpesa/manual/'.$r['id'].'/reject') }}" style="display:inline" onsubmit="var r = prompt('Why is this payment rejected?', 'Not on the M-Pesa statement'); if (r === null) { return false; } this.reason.value = r; return true;">@csrf<input type="hidden" name="reason"><button class="btn btn-xs btn-danger">Reject</button></form>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
            @else
                <p><i class="fa fa-check text-green"></i> Nothing to reconcile.</p>
            @endif
        </div>
    </div>
    @endforeach
</section>
@endsection

@section('javascript')
<script>
    $(document).on('click', '.mpesa-recheck', function () {
        $(this).prop('disabled', true).text('Checking...');
        $.get("{{ url('/mpesa/status') }}/" + $(this).data('id')).always(function () { location.reload(); });
    });
</script>
@endsection
