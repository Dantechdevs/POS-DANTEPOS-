/* M-Pesa STK Push for the "Pay due" modal (customer ledger / contacts).
   Reuses the same /mpesa/stkpush and /mpesa/status endpoints as the POS screen. */
(function ($) {
    'use strict';

    var FORM = '#pay_contact_due_form';
    var pollTimer = null;
    var confirmed = false;
    var BTN_HTML = '<i class="fas fa-mobile-alt"></i> Send STK Push Request';

    function amountValue($el) {
        if (typeof __read_number === 'function') { return parseFloat(__read_number($el)) || 0; }
        return parseFloat(($el.val() || '').replace(/,/g, '')) || 0;
    }

    function ensureBlock($f) {
        var $b = $f.find('#pd_mpesa_block');
        if ($b.length) { return $b; }
        var html =
            '<div class="col-md-12" id="pd_mpesa_block" style="display:none">' +
              '<div class="row">' +
                '<div class="col-md-6"><div class="form-group">' +
                  '<label for="pd_mpesa_phone">M-Pesa Phone Number</label>' +
                  '<input type="text" id="pd_mpesa_phone" name="pd_mpesa_phone" class="form-control" placeholder="07XXXXXXXX or 2547XXXXXXXX">' +
                '</div></div>' +
                '<div class="col-md-6"><div class="form-group">' +
                  '<label>&nbsp;</label>' +
                  '<button type="button" id="pd_mpesa_send" class="btn btn-success btn-block">' + BTN_HTML + '</button>' +
                '</div></div>' +
              '</div>' +
              '<input type="hidden" name="pd_mpesa_checkout_request_id" id="pd_mpesa_checkout_request_id">' +
              '<input type="hidden" name="pd_mpesa_receipt_number" id="pd_mpesa_receipt_number">' +
              '<div class="alert alert-info hide" id="pd_mpesa_status"></div>' +
            '</div><div class="clearfix"></div>';
        $f.find('textarea[name="note"]').closest('.col-md-12').before(html);
        return $f.find('#pd_mpesa_block');
    }

    function sync() {
        var $f = $(FORM);
        if (!$f.length) { return; }
        var $block = ensureBlock($f);
        var isMpesa = $f.find('select[name="method"]').val() === 'mpesa';
        var $save = $f.find('button[type="submit"]');

        $block.toggle(isMpesa);
        if (isMpesa && !confirmed) {
            $save.prop('disabled', true);
        } else {
            $save.prop('disabled', false);
        }
    }

    function reset() {
        clearInterval(pollTimer);
        confirmed = false;
        var $f = $(FORM);
        $f.find('#pd_mpesa_checkout_request_id, #pd_mpesa_receipt_number').val('');
        $f.find('.payment_amount').prop('readonly', false);
        $f.find('#pd_mpesa_send').prop('disabled', false).html(BTN_HTML);
        $f.find('#pd_mpesa_status').addClass('hide');
    }

    function fail($status, $btn, msg) {
        $status.removeClass('hide alert-info alert-success').addClass('alert-danger').text(msg);
        $btn.prop('disabled', false).html(BTN_HTML);
        $(FORM).find('.payment_amount').prop('readonly', false);
    }

    $(document).on('shown.bs.modal', function () { if ($(FORM).length) { reset(); sync(); } });
    $(document).on('hidden.bs.modal', function () { clearInterval(pollTimer); });
    $(document).on('change', FORM + ' select[name="method"]', function () { reset(); sync(); });

    $(document).on('click', '#pd_mpesa_send', function () {
        var $f = $(FORM), $btn = $(this), $status = $f.find('#pd_mpesa_status');
        var phone = $.trim($f.find('#pd_mpesa_phone').val());
        var $amt = $f.find('.payment_amount');
        var amount = amountValue($amt);

        if (phone.replace(/\D/g, '').length < 9) { toastr.warning('Enter a valid M-Pesa phone number.'); return; }
        if (amount <= 0) { toastr.warning('Amount must be greater than 0.'); return; }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        $amt.prop('readonly', true);
        $status.removeClass('hide alert-danger alert-success').addClass('alert-info').text('Sending STK push...');

        $.ajax({
            url: window.MPESA_CONFIG.base_path + '/mpesa/stkpush',
            type: 'POST',
            data: { phone: phone, amount: amount },
            headers: { 'X-CSRF-TOKEN': window.MPESA_CONFIG.csrf_token },
            success: function (r) {
                if (!r.success) { fail($status, $btn, r.message || 'Failed to send request.'); return; }
                $f.find('#pd_mpesa_checkout_request_id').val(r.checkout_request_id);
                $status.text('Waiting for customer to enter M-Pesa PIN...');
                poll(r.checkout_request_id, $btn, $status);
            },
            error: function (xhr) {
                fail($status, $btn, (xhr.responseJSON && xhr.responseJSON.message) || 'Request failed.');
            }
        });
    });

    function poll(id, $btn, $status) {
        var attempts = 0;
        clearInterval(pollTimer);
        pollTimer = setInterval(function () {
            attempts++;
            $.get(window.MPESA_CONFIG.base_path + '/mpesa/status/' + id, function (r) {
                if (!r.success) { return; }
                if (r.status === 'success') {
                    clearInterval(pollTimer);
                    confirmed = true;
                    $(FORM).find('#pd_mpesa_receipt_number').val(r.mpesa_receipt_number);
                    $status.removeClass('alert-info alert-danger').addClass('alert-success')
                           .text('Payment received. Receipt: ' + r.mpesa_receipt_number + '. You can now save.');
                    $btn.prop('disabled', true);
                    sync();
                } else if (r.status === 'failed' || r.status === 'cancelled') {
                    clearInterval(pollTimer);
                    fail($status, $btn, r.result_desc || 'Payment not completed.');
                }
            });
            if (attempts >= 40) {
                clearInterval(pollTimer);
                fail($status, $btn, 'Timed out waiting for confirmation. Try again.');
            }
        }, 3000);
    }
})(jQuery);
