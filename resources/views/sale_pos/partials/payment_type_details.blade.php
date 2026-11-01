<div class="payment_details_div @if( $payment_line['method'] !== 'card' ) {{ 'hide' }} @endif" data-type="card" >
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_number_$row_index", __('lang_v1.card_no')) !!}
			{!! Form::text("payment[$row_index][card_number]", $payment_line['card_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.card_no'), 'id' => "card_number_$row_index"]); !!}
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_holder_name_$row_index", __('lang_v1.card_holder_name')) !!}
			{!! Form::text("payment[$row_index][card_holder_name]", $payment_line['card_holder_name'], ['class' => 'form-control', 'placeholder' => __('lang_v1.card_holder_name'), 'id' => "card_holder_name_$row_index"]); !!}
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_transaction_number_$row_index",__('lang_v1.card_transaction_no')) !!}
			{!! Form::text("payment[$row_index][card_transaction_number]", $payment_line['card_transaction_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.card_transaction_no'), 'id' => "card_transaction_number_$row_index"]); !!}
		</div>
	</div>
	<div class="clearfix"></div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_type_$row_index", __('lang_v1.card_type')) !!}
			{!! Form::select("payment[$row_index][card_type]", ['credit' => 'Credit Card', 'debit' => 'Debit Card','visa' => 'Visa', 'master' => 'MasterCard'], $payment_line['card_type'],['class' => 'form-control', 'id' => "card_type_$row_index" ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_month_$row_index", __('lang_v1.month')) !!}
			{!! Form::text("payment[$row_index][card_month]", $payment_line['card_month'], ['class' => 'form-control', 'placeholder' => __('lang_v1.month'),
			'id' => "card_month_$row_index" ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_year_$row_index", __('lang_v1.year')) !!}
			{!! Form::text("payment[$row_index][card_year]", $payment_line['card_year'], ['class' => 'form-control', 'placeholder' => __('lang_v1.year'), 'id' => "card_year_$row_index" ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_security_$row_index",__('lang_v1.security_code')) !!}
			{!! Form::text("payment[$row_index][card_security]", $payment_line['card_security'], ['class' => 'form-control', 'placeholder' => __('lang_v1.security_code'), 'id' => "card_security_$row_index"]); !!}
		</div>
	</div>
	<div class="clearfix"></div>
</div>

<div class="payment_details_div @if( $payment_line['method'] !== 'cheque' ) {{ 'hide' }} @endif" data-type="cheque" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("cheque_number_$row_index",__('lang_v1.cheque_no')) !!}
			{!! Form::text("payment[$row_index][cheque_number]", $payment_line['cheque_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.cheque_no'), 'id' => "cheque_number_$row_index"]); !!}
		</div>
	</div>
</div>

<div class="payment_details_div @if( $payment_line['method'] !== 'bank_transfer' ) {{ 'hide' }} @endif" data-type="bank_transfer" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("bank_account_number_$row_index",__('lang_v1.bank_account_number')) !!}
			{!! Form::text( "payment[$row_index][bank_account_number]", $payment_line['bank_account_number'], ['class' => 'form-control', 'placeholder' => __('lang_v1.bank_account_number'), 'id' => "bank_account_number_$row_index"]); !!}
		</div>
	</div>
</div>


{{-- ===================================================== --}}
{{-- M-PESA STK PUSH PAYMENT DETAILS --}}
{{-- ===================================================== --}}
<div class="payment_details_div @if( $payment_line['method'] !== 'mpesa' ) {{ 'hide' }} @endif" data-type="mpesa" >
	<div class="col-md-6">
		<div class="form-group">
			<label for="mpesa_phone_{{ $row_index }}">M-Pesa Phone Number</label>
			<input type="text"
				   name="payment[{{ $row_index }}][mpesa_phone]"
				   id="mpesa_phone_{{ $row_index }}"
				   class="form-control"
				   placeholder="2547XXXXXXXX"
				   value="">
			<small class="text-muted">
				Enter customer phone number in format: <b>2547XXXXXXXX</b>
			</small>
		</div>
	</div>

	<div class="col-md-6">
		<div class="form-group">
			<label for="mpesa_ref_{{ $row_index }}">M-Pesa Reference</label>
			<input type="text"
				   name="payment[{{ $row_index }}][mpesa_reference]"
				   id="mpesa_ref_{{ $row_index }}"
				   class="form-control"
				   placeholder="Invoice / Order Reference"
				   value="">
		</div>
	</div>

	<div class="clearfix"></div>

	<div class="col-md-12">
		<div class="form-group">
			<button type="button"
					class="btn btn-success btn-block mpesa-stk-btn"
					data-row-index="{{ $row_index }}">
				<i class="fas fa-mobile-alt"></i> Send STK Push Request
			</button>

			<input type="hidden" name="payment[{{ $row_index }}][mpesa_checkout_request_id]" id="mpesa_checkout_request_id_{{ $row_index }}">
			<input type="hidden" name="payment[{{ $row_index }}][mpesa_merchant_request_id]" id="mpesa_merchant_request_id_{{ $row_index }}">
			<input type="hidden" name="payment[{{ $row_index }}][mpesa_receipt_number]" id="mpesa_receipt_number_{{ $row_index }}">
			<input type="hidden" name="payment[{{ $row_index }}][mpesa_transaction_date]" id="mpesa_transaction_date_{{ $row_index }}">
		</div>

		<div class="alert alert-info hide" id="mpesa_status_{{ $row_index }}">
			Waiting for customer confirmation...
		</div>
	</div>

	<div class="clearfix"></div>
</div>


@for ($i = 1; $i < 8; $i++)
<div class="payment_details_div @if( $payment_line['method'] !== 'custom_pay_' . $i ) {{ 'hide' }} @endif" data-type="custom_pay_{{$i}}" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("transaction_no_{$i}_{$row_index}", __('lang_v1.transaction_no')) !!}
			{!! Form::text("payment[$row_index][transaction_no_{$i}]", $payment_line['transaction_no'], ['class' => 'form-control', 'placeholder' => __('lang_v1.transaction_no'), 'id' => "transaction_no_{$i}_{$row_index}"]); !!}
		</div>
	</div>
</div>
@endfor
