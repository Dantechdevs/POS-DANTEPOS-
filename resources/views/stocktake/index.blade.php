@extends('layouts.app')
@section('title', 'Stocktake')

@section('content')
<section class="content-header">
    <h1>Stocktake <small>Count your shelves and correct stock differences</small></h1>
</section>

<section class="content">
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">Start a new stocktake</h3></div>
        <div class="box-body">
            <form method="POST" action="{{ url('/stocktakes') }}" class="form-inline">
                @csrf
                <select name="location_id" class="form-control" required>
                    <option value="">Location</option>
                    @foreach ($locations as $locId => $locName)
                        <option value="{{ $locId }}" @selected(old('location_id') == $locId)>{{ $locName }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="form-control">
                    <option value="">All categories</option>
                    @foreach ($categories as $catId => $catName)
                        <option value="{{ $catId }}" @selected(old('category_id') == $catId)>{{ $catName }}</option>
                    @endforeach
                </select>
                <input type="text" name="name" class="form-control" maxlength="120" placeholder="Name (optional), e.g. Month-end" value="{{ old('name') }}">
                <button class="btn btn-primary">Start stocktake</button>
            </form>
            <p class="text-muted" style="margin-top:10px">
                Tip: count one category or shelf at a time. Items you don't count are never changed.
            </p>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">Stocktakes</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr><th>Ref</th><th>Name</th><th>Location</th><th>Progress</th><th>Status</th><th>Started</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($stocktakes as $s)
                    <tr>
                        <td>{{ $s->ref_no }}</td>
                        <td>{{ $s->name }}</td>
                        <td>{{ $s->location_name }}</td>
                        <td>{{ $s->counted_lines }} / {{ $s->total_lines }} counted</td>
                        <td>
                            @if ($s->status === 'applied')<span class="label label-success">Applied</span>
                            @elseif ($s->status === 'cancelled')<span class="label label-default">Cancelled</span>
                            @else<span class="label label-warning">Counting</span>@endif
                        </td>
                        <td>{{ substr($s->created_at, 0, 16) }}</td>
                        <td><a class="btn btn-xs btn-info" href="{{ url('/stocktakes/'.$s->id) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No stocktakes yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
