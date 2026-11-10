@extends('layouts.app')
@section('title', 'Payment Configurations')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Payment Configurations
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Choose, rename and set the default payment methods</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @if (session('dantepos_msg'))
        <div class="alert alert-success">{{ session('dantepos_msg') }}</div>
    @endif
    @if (session('dantepos_error'))
        <div class="alert alert-danger">{{ session('dantepos_error') }}</div>
    @endif

    {!! Form::open(['url' => route('dantepos.payments.update'), 'method' => 'post', 'id' => 'dante_payments_form']) !!}
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Payment methods'])
        <p class="text-muted">
            Untick a method to remove it from the POS and sales screens. Reports still show old payments.
            Ticking a method for the first time also makes it available at every business location.
        </p>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width:90px;" class="text-center">Enabled</th>
                        <th>Method</th>
                        <th>Name shown to staff (optional)</th>
                        <th>Locations</th>
                        <th style="width:90px;" class="text-center">Default</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($methods as $key => $name)
                    <tr>
                        <td class="text-center"><input type="checkbox" name="enabled[]" value="{{ $key }}" {{ in_array($key, $enabled) ? 'checked' : '' }}></td>
                        <td>
                            {{ $name }}
                            @if (strpos($key, 'custom_pay_') === 0)
                                <span class="label label-default">Custom</span>
                            @endif
                        </td>
                        <td><input type="text" class="form-control" name="label[{{ $key }}]" value="{{ $labels[$key] ?? '' }}" placeholder="{{ $name }}" maxlength="40"></td>
                        <td>
                            @php($n = $coverage[$key] ?? 0)
                            @if ($total > 0 && $n >= $total)
                                <span class="label label-success">All locations</span>
                            @elseif ($n == 0)
                                <span class="label label-warning">No locations</span>
                            @else
                                <span class="label label-info">{{ $n }} of {{ $total }}</span>
                            @endif
                        </td>
                        <td class="text-center"><input type="radio" name="default" value="{{ $key }}" {{ $default === $key ? 'checked' : '' }}></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endcomponent

    <div class="row">
        <div class="col-sm-12 text-center">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-lg">@lang('messages.save')</button>
        </div>
    </div>
    {!! Form::close() !!}

    <div class="row" style="margin-top:15px;">
        <div class="col-sm-12 text-center">
            <form method="POST" action="{{ route('dantepos.payments.kenya') }}" style="display:inline;" onsubmit="return confirm('Enable only Cash and M-Pesa, with Cash as the default? Other methods will be hidden from the POS.');">
                @csrf
                <button type="submit" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-primary">Kenya preset: Cash and M-Pesa only</button>
            </form>
        </div>
    </div>
    @include('dantepos_settings.payment_channels')
    @include('dantepos_settings.mpesa_settings')
</section>
@endsection
