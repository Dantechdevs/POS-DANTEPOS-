<div class="pos-tab-content">
<div class="row well">
    <div class="col-sm-4">
        <div class="form-group">
            <div class="checkbox">
                <label>
                {!! Form::checkbox('enable_rp', 1, $business->enable_rp , 
                [ 'class' => 'input-icheck', 'id' => 'enable_rp']); !!} {{ __( 'lang_v1.enable_rp' ) }}
                </label>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('rp_name', __('lang_v1.rp_name') . ':') !!}
            {!! Form::text('rp_name', $business->rp_name, ['class' => 'form-control','placeholder' => __('lang_v1.rp_name')]); !!}
        </div>
    </div>

    <div class="clearfix"></div>
    <div class="col-sm-12">
        <h4>@lang('lang_v1.earning_points_setting'):</h4>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('amount_for_unit_rp', __('lang_v1.amount_for_unit_rp') . ':') !!} @show_tooltip(__('lang_v1.amount_for_unit_rp_tooltip'))
            {!! Form::text('amount_for_unit_rp', @num_format($business->amount_for_unit_rp), ['class' => 'form-control input_number','placeholder' => __('lang_v1.amount_for_unit_rp')]); !!}
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('min_order_total_for_rp', __('lang_v1.min_order_total_for_rp') . ':') !!} @show_tooltip(__('lang_v1.min_order_total_for_rp_tooltip'))
            {!! Form::text('min_order_total_for_rp', @num_format($business->min_order_total_for_rp), ['class' => 'form-control input_number','placeholder' => __('lang_v1.min_order_total_for_rp')]); !!}
        </div>
    </div>
    
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('max_rp_per_order', __('lang_v1.max_rp_per_order') . ':') !!} @show_tooltip(__('lang_v1.max_rp_per_order_tooltip'))
            {!! Form::number('max_rp_per_order', $business->max_rp_per_order, ['class' => 'form-control','placeholder' => __('lang_v1.max_rp_per_order')]); !!}
        </div>
    </div>
   </div>
<div class="row well">
    <div class="col-sm-12">
        <h4>Festive season points</h4>
        <p class="help-block">Award points only during a festive season. If "Points per 1,000 spent" is empty, the "Amount spent for one point" setting above is used instead.</p>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            <label for="rp_points_per_1000">Points per 1,000 spent:</label>
            <input type="number" step="0.01" min="0" name="rp_points_per_1000" id="rp_points_per_1000" class="form-control" value="{{ $business->rp_points_per_1000 }}" placeholder="e.g. 5">
        </div>
    </div>
    <div class="col-sm-8">
        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="rp_season_only" value="1" @if(!empty($business->rp_season_only)) checked @endif> Award points only during the festive season
                </label>
            </div>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-4">
        <div class="form-group">
            <label for="rp_season_start">Season starts:</label>
            <input type="date" name="rp_season_start" id="rp_season_start" class="form-control" value="{{ !empty($business->rp_season_start) ? \Carbon::parse($business->rp_season_start)->format('Y-m-d') : '' }}">
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            <label for="rp_season_end">Season ends:</label>
            <input type="date" name="rp_season_end" id="rp_season_end" class="form-control" value="{{ !empty($business->rp_season_end) ? \Carbon::parse($business->rp_season_end)->format('Y-m-d') : '' }}">
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            <label for="rp_expiry_date">Points expire on:</label>
            <input type="date" name="rp_expiry_date" id="rp_expiry_date" class="form-control" value="{{ !empty($business->rp_expiry_date) ? \Carbon::parse($business->rp_expiry_date)->format('Y-m-d') : '' }}">
            <p class="help-block">Unspent points are cleared the day after this date. Set it again each year.</p>
        </div>
    </div>
</div>
   <div class="row well">
    <div class="col-sm-12">
        <h4>@lang('lang_v1.redeem_points_setting'):</h4>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('redeem_amount_per_unit_rp', __('lang_v1.redeem_amount_per_unit_rp') . ':') !!} @show_tooltip(__('lang_v1.redeem_amount_per_unit_rp_tooltip'))
            {!! Form::text('redeem_amount_per_unit_rp', @num_format($business->redeem_amount_per_unit_rp), ['class' => 'form-control input_number','placeholder' => __('lang_v1.redeem_amount_per_unit_rp')]); !!}
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('min_order_total_for_redeem', __('lang_v1.min_order_total_for_redeem') . ':') !!} @show_tooltip(__('lang_v1.min_order_total_for_redeem_tooltip'))
            {!! Form::text('min_order_total_for_redeem', @num_format($business->min_order_total_for_redeem), ['class' => 'form-control input_number','placeholder' => __('lang_v1.min_order_total_for_redeem')]); !!}
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('min_redeem_point', __('lang_v1.min_redeem_point') . ':') !!} @show_tooltip(__('lang_v1.min_redeem_point_tooltip'))
            {!! Form::number('min_redeem_point', $business->min_redeem_point, ['class' => 'form-control','placeholder' => __('lang_v1.min_redeem_point')]); !!}
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('max_redeem_point', __('lang_v1.max_redeem_point') . ':') !!} @show_tooltip(__('lang_v1.max_redeem_point_tooltip'))
            {!! Form::number('max_redeem_point', $business->max_redeem_point, ['class' => 'form-control', 'placeholder' => __('lang_v1.max_redeem_point')]); !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('rp_expiry_period', __('lang_v1.rp_expiry_period') . ':') !!} @show_tooltip(__('lang_v1.rp_expiry_period_tooltip'))
            <div class="input-group">
                {!! Form::number('rp_expiry_period', $business->rp_expiry_period, ['class' => 'form-control','placeholder' => __('lang_v1.rp_expiry_period')]); !!}
                <span class="input-group-addon">-</span>
                {!! Form::select('rp_expiry_type', ['month' => __('lang_v1.month'), 'year' => __('lang_v1.year')], $business->rp_expiry_type, ['class' => 'form-control']); !!}
            </div>
        </div>
    </div>
    </div>
</div>