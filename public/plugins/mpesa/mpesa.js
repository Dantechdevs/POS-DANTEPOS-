(function ($) {
    let mpesaConfirmed = false;
    let mpesaPollTimer = null;

    // Capture phase so this runs BEFORE pos.js's own express-finalize click handler.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.pos-express-finalize[data-pay_method="mpesa"]');
        if (!btn) return;

        if (!mpesaConfirmed) {
            e.preventDefault();
            e.stopImmediatePropagation();

            if ($('table#pos_table tbody').find('.product_row').length <= 0) {
                toastr.warning(LANG.no_products_added);
                return;
            }

            // Show the Multiple Pay modal and switch the first row to M-Pesa.
            $('#modal_payment').modal('show');
            const dropdown = $('#payment_rows_div').find('.payment_types_dropdown').first();
            dropdown.val('mpesa').change();

            setTimeout(function () {
                $('#mpesa_phone_0').focus();
            }, 400);
        }
        // If already confirmed, let the click pass through to pos.js's real handler untouched.
    }, true);

    $(document).on('click', '.mpesa-stk-btn', function () {
        const rowIndex = $(this).data('row-index');
        const phone = $('#mpesa_phone_' + rowIndex).val().trim();
        const amount = parseFloat($('#amount_' + rowIndex).val()) || 0;
        const $btn = $(this);
        const $status = $('#mpesa_status_' + rowIndex);

        if (!phone || phone.length < 9) {
            toastr.warning('Enter a valid M-Pesa phone number.');
            return;
        }
        if (amount <= 0) {
            toastr.warning('Payment amount must be greater than 0.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        $status.removeClass('hide alert-danger alert-success').addClass('alert-info').text('Sending STK push...');

        $.ajax({
            url: window.MPESA_CONFIG.base_path + '/mpesa/stkpush',
            type: 'POST',
            data: { phone: phone, amount: amount },
            headers: { 'X-CSRF-TOKEN': window.MPESA_CONFIG.csrf_token },
            success: function (response) {
                if (response.success) {
                    $('#mpesa_checkout_request_id_' + rowIndex).val(response.checkout_request_id);
                    $status.text('Waiting for customer to enter M-Pesa PIN...');
                    pollMpesaStatus(rowIndex, response.checkout_request_id, $btn, $status);
                } else {
                    $status.removeClass('alert-info').addClass('alert-danger').text(response.message || 'Failed to send request.');
                    $btn.prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send STK Push Request');
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Request failed.';
                $status.removeClass('alert-info').addClass('alert-danger').text(msg);
                $btn.prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send STK Push Request');
            }
        });
    });

    function pollMpesaStatus(rowIndex, checkoutRequestId, $btn, $status) {
        let attempts = 0;
        const maxAttempts = 40;

        mpesaPollTimer = setInterval(function () {
            attempts++;

            $.get(window.MPESA_CONFIG.base_path + '/mpesa/status/' + checkoutRequestId, function (response) {
                if (!response.success) return;

                if (response.status === 'success') {
                    clearInterval(mpesaPollTimer);
                    $('#mpesa_receipt_number_' + rowIndex).val(response.mpesa_receipt_number);
                    $status.removeClass('alert-info').addClass('alert-success').text('Payment received. Receipt: ' + response.mpesa_receipt_number);

                    mpesaConfirmed = true;
                    setTimeout(function () {
                        document.querySelector('.pos-express-finalize[data-pay_method="mpesa"]').click();
                    }, 800);
                } else if (response.status === 'failed' || response.status === 'cancelled') {
                    clearInterval(mpesaPollTimer);
                    $status.removeClass('alert-info').addClass('alert-danger').text(response.result_desc || 'Payment not completed.');
                    $btn.prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send STK Push Request');
                }
            });

            if (attempts >= maxAttempts) {
                clearInterval(mpesaPollTimer);
                $status.removeClass('alert-info').addClass('alert-danger').text('Timed out waiting for confirmation. Try again.');
                $btn.prop('disabled', false).html('<i class="fas fa-mobile-alt"></i> Send STK Push Request');
            }
        }, 3000);
    }
})(jQuery);
