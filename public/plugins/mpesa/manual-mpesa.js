/* Manual M-Pesa entry in the POS payment modal (used when STK Push is not possible). */
(function ($) {
    'use strict';

    $(document).on('change', '.mpesa-manual-toggle', function () {
        var i = $(this).data('row-index');
        var on = this.checked;
        var $box = $('#mpesa_manual_box_' + i);

        $box.toggleClass('hide', !on);
        // Hide the STK button while entering by hand so the cashier does not do both.
        $(this).closest('.payment_details_div').find('.mpesa-stk-btn').closest('.form-group').toggleClass('hide', on);

        if (on) {
            // Manual entry replaces any STK result on this row.
            $('#mpesa_checkout_request_id_' + i + ', #mpesa_merchant_request_id_' + i + ', #mpesa_receipt_number_' + i).val('');
            $('#mpesa_manual_receipt_' + i).focus();
        } else {
            $('#mpesa_manual_receipt_' + i).val('');
        }
    });

    // Codes are 10 letters/digits, upper case. Ignore spaces, dashes and anything pasted along with them.
    $(document).on('input', '.mpesa-manual-receipt', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
    });


    // Light check before the form is submitted; the server checks again and is the real gate.
    $(document).on('click', '#pos-save, .pos-express-finalize', function (e) {
        var bad = null;
        $('.mpesa-manual-toggle:checked').each(function () {
            var i = $(this).data('row-index');
            var code = $('#mpesa_manual_receipt_' + i).val() || '';
            var phone = ($('#mpesa_phone_' + i).val() || '').replace(/\D/g, '');
            if (!/^(?=.*[A-Z])[A-Z0-9]{10}$/.test(code)) { bad = 'Enter the 10-character M-Pesa code, for example SGH7K2L9QX.'; }
            else if (!/^(254|0)?[17]\d{8}$/.test(phone)) { bad = 'Enter the customer\'s Safaricom number for the M-Pesa payment.'; }
        });
        if (bad) {
            e.preventDefault();
            e.stopImmediatePropagation();
            toastr.warning(bad);
        }
    });
})(jQuery);
