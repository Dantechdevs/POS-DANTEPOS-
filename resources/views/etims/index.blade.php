@extends('layouts.app')
@section('title', 'eTIMS Invoices')

@section('content')
<section class="content-header">
    <h1>eTIMS Invoices <small>KRA submission queue</small></h1>
</section>

<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <div class="row" style="margin-bottom:12px">
                <div class="col-md-8">
                    <div class="btn-group">
                        <a href="{{ route('etims.index') }}" class="btn btn-default {{ empty($status) ? 'active' : '' }}">
                            All ({{ $counts->sum() }})
                        </a>
                        <a href="{{ route('etims.index', ['status' => 'pending']) }}" class="btn btn-default {{ $status === 'pending' ? 'active' : '' }}">
                            Pending ({{ $counts['pending'] ?? 0 }})
                        </a>
                        <a href="{{ route('etims.index', ['status' => 'submitted']) }}" class="btn btn-default {{ $status === 'submitted' ? 'active' : '' }}">
                            Submitted ({{ $counts['submitted'] ?? 0 }})
                        </a>
                        <a href="{{ route('etims.index', ['status' => 'failed']) }}" class="btn btn-default {{ $status === 'failed' ? 'active' : '' }}">
                            Failed ({{ $counts['failed'] ?? 0 }})
                        </a>
                    </div>
                    @if (($counts['failed'] ?? 0) > 0)
                        <form method="POST" action="{{ route('etims.retryFailed') }}" style="display:inline-block;margin-left:8px">
                            @csrf
                            <button type="submit" class="btn btn-warning">Retry all failed</button>
                        </form>
                    @endif
                </div>
                <div class="col-md-4">
                    <form method="GET" action="{{ route('etims.index') }}">
                        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                        <div class="input-group">
                            <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Invoice no. or CU receipt no.">
                            <span class="input-group-btn"><button class="btn btn-primary" type="submit">Search</button></span>
                        </div>
                    </form>
                </div>
            </div>

            <p class="text-muted">
                Sales are queued here and sent to KRA when <code>php artisan etims:submit</code> runs.
                Retry puts a failed invoice back in the queue.
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>eTIMS no.</th>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th class="text-right">Total (KSh)</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>CU receipt no.</th>
                            <th>Submitted</th>
                            <th>Last error</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>{{ $r->invoice_no }}</td>
                            <td>{{ \Carbon\Carbon::parse($r->transaction_date)->format('d/m/Y H:i') }}</td>
                            <td class="text-right">{{ number_format($r->final_total, 2) }}</td>
                            <td>
                                @php
                                    $cls = ['pending' => 'warning', 'submitted' => 'success', 'failed' => 'danger'][$r->status] ?? 'default';
                                @endphp
                                <span class="label label-{{ $cls }}">{{ ucfirst($r->status) }}</span>
                            </td>
                            <td>{{ $r->attempts }}</td>
                            <td>{{ $r->cu_receipt_no ?: '-' }}</td>
                            <td>{{ $r->submitted_at ? \Carbon\Carbon::parse($r->submitted_at)->format('d/m/Y H:i') : '-' }}</td>
                            <td title="{{ $r->last_error }}">{{ \Illuminate\Support\Str::limit($r->last_error, 70) ?: '-' }}</td>
                            <td>
                                @if ($r->status === 'failed')
                                    <form method="POST" action="{{ route('etims.retry', $r->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-warning">Retry</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                No eTIMS invoices yet. Sales appear here once eTIMS is switched on and a sale is made.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $rows->links() }}
        </div>
    </div>
</section>
@endsection
