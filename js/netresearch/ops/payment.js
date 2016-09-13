Event.observe(window, 'load', function () {


    if(typeof checkout != 'undefined') {
        payment.switchMethod = payment.switchMethod.wrap(function (originalMethod, method) {
            if (typeof window[method] != 'undefined') {
                payment.currentMethodObject = window[method];
                if (payment.isInlineCcBrand() && !payment.opsAliasSuccess) {
                    payment.toggleContinue(false);
                } else {
                    payment.toggleContinue(true);
                }
            } else {
                if (typeof checkout != 'undefined') {
                    payment.toggleContinue(true);
                } else {
                    toggleOrderSubmit(true);
                }
            }
            originalMethod(method);
        });
    }

    if (payment.save) {
        payment.save = payment.save.wrap(function (originalSaveMethod) {
            payment.originalSaveMethod = originalSaveMethod;
            ////this form element is always set in payment object this.form or payment.form no need to bind to specific
            //var opsValidator = new Validation(payment.form);
            //if (!opsValidator.validate()) {
            //    return;
            //}
            if ('ops_directDebit' == payment.currentMethod) {
                payment.saveOpsDirectDebit();
                return; //return as you have another call chain here
            }

            originalSaveMethod();
        });
    }

    payment.saveOpsDirectDebit = function () {
        checkout.setLoadWaiting('payment');
        var countryId = $('ops_directdebit_country_id').value;
        var accountNo = $('ops_directdebit_account_no').value;
        var bankCode = $('ops_directdebit_bank_code').value;
        var CN = $('ops_directdebit_CN').value;
        var iban = $('ops_directdebit_iban').value.replace(/\s+/g, '');
        var bic = $('ops_directdebit_bic').value.replace(/\s+/g, '');
        new Ajax.Request(opsDirectDebitUrl, {
            method: 'post',
            parameters: {country: countryId, account: accountNo, bankcode: bankCode, CN: CN, iban: iban, bic: bic},
            onSuccess: function (transport) {
                checkout.setLoadWaiting(false);
                payment.originalSaveMethod();
            },
            onFailure: function (transport) {
                checkout.setLoadWaiting(false);
                if (transport.responseText && 0 < transport.responseText.length) {
                    message = transport.responseText;
                } else {
                    message = 'Payment failed. Please select another payment method.';
                }
                alert(Translator.translate(message));
                checkout.setLoadWaiting(false);
            }
        });
    };

    payment.getSelectedAliasElement = function () {
        return $$('input[name="payment[' + payment.currentMethod + '][alias]"]:checked')[0];
    };

    payment.isStoredAliasSelected = function () {
        return payment.getSelectedAliasId() != 'new_alias_' + payment.currentMethod;
    };

    payment.getSelectedAlias = function () {
        return payment.getSelectedAliasElement().value;
    };

    payment.getSelectedAliasId = function () {
        return payment.getSelectedAliasElement().id;
    };

    payment.toggleOpsDirectDebitInputs = function (country) {
        var bankcode = 'ops_directdebit_bank_code';
        var bic = 'ops_directdebit_bic';
        var iban = 'ops_directdebit_iban';
        var showInput = function (id) {
            $$('#' + id)[0].up().show();
            if (!$(id).hasClassName('required-entry') && id != 'ops_directdebit_bic' && $('ops_directdebit_iban').value == '') {
                $(id).addClassName('required-entry');
            }
        };
        var hideInput = function (id) {
            $$('#' + id)[0].up().hide();
            $(id).removeClassName('required-entry');
        };
        if ('NL' == country) {
            hideInput(bankcode);
            showInput(bic);
            showInput(iban);
        }
        if ('DE' == country || 'AT' == country) {
            showInput(bankcode);
            hideInput(bic);
            showInput(iban);
        }
        if ('AT' == country) {
            hideInput(iban)
        }
    };

    payment.toggleCCInputfields = function (element) {
        if (element.id.indexOf('new_alias') != -1) {

            var currentMethod = element.id.replace('new_alias_', '');
            var currentMethodUC = currentMethod.toUpperCase();
            var paymenDetailsId = $('insert_payment_details_' + currentMethod).id;

            if($(currentMethod +'_stored_alias_brand') != null){
                $(currentMethod +'_stored_alias_brand').disable();
            }
            $(currentMethodUC + '_BRAND').enable();
            $(paymenDetailsId).show();

            $$('input[type="text"][name="payment[' + currentMethod + '][cvc]"]').each(function (cvcEle) {
                cvcEle.up('li').hide();
                cvcEle.disable();
            });


            $$('#' + paymenDetailsId + ' input,#' + paymenDetailsId + ' select').each(function (element) {
                element.enable();
            });
        }
        else {
            var currentMethod = element.up('ul').id.replace('payment_form_', '');
            var currentMethodUC = currentMethod.toUpperCase();
            var paymenDetailsId = $('insert_payment_details_' + currentMethod).id;
            if($(currentMethod +'_stored_alias_brand') != null) {
                $(currentMethod + '_stored_alias_brand').enable();
                $(currentMethod + '_stored_alias_brand').value = element.dataset.brand;
            }
            $(currentMethodUC + '_BRAND').disable();
            $$('input[type="text"][name="payment[' + currentMethod + '][cvc]"]').each(function (cvcEle) {
                if ($(currentMethodUC + '_CVC_' + element.id).id == cvcEle.id) {
                    cvcEle.up('li').show();
                    cvcEle.enable();
                } else {
                    cvcEle.up('li').hide();
                    cvcEle.disable();
                }
            });


            $$('#' + paymenDetailsId + ' input,#' + paymenDetailsId + ' select').each(function (element) {
                element.disable();
            });

            $(paymenDetailsId).hide()
        }
    };

    if (typeof accordion != 'undefined') {
        accordion.openSection = accordion.openSection.wrap(function (originalOpenSectionMethod, section) {

            var aliasMethods = ['ops_cc', 'ops_dc'];

            aliasMethods.each(function (method) {
                if (section.id == 'opc-payment' || section == 'opc-payment') {
                    if (typeof  $('p_method_' + method) != 'undefined') {
                        $$('input[type="radio"][name="payment[' + method + '][alias]"]').each(function (element) {
                            element.observe('click', function (event) {
                                payment.toggleCCInputfields(this);
                            })
                        });
                    }
                    if ($('new_alias_' + method)
                        && $$('input[type="radio"][name="payment[' + method + '][alias]"]').size() == 1
                    ) {
                        payment.toggleCCInputfields($('new_alias_' + method));
                    }
                }
            });

            originalOpenSectionMethod(section);
        });
    }

    payment.jumpToLoginStep = function () {
        if (typeof accordion != 'undefined') {
            accordion.openSection('opc-login');
            $('login:register').checked = true;
        }
    };

    payment.setRequiredDirectDebitFields = function (element) {

        country = $('ops_directdebit_country_id').value;
        accountNo = 'ops_directdebit_account_no';
        blz = 'ops_directdebit_bank_code';
        iban = 'ops_directdebit_iban';
        bic = 'ops_directdebit_bic';

        if ($(iban).value == '' && $(bic).value == '' && $(accountNo).value == '' && $(blz).value == '') {
            $(iban).addClassName('required-entry');
            $(accountNo).addClassName('required-entry');
            $(blz).addClassName('required-entry');
            return;
        }

        if ($(iban).value == '' && $(bic).value == '' && $(accountNo).value == '' && $(blz).value == '') {
            $(iban).addClassName('required-entry');
            $(accountNo).addClassName('required-entry');
            $(blz).addClassName('required-entry');
            return;
        }

        accountNoClasses = new Array('required-entry');
        blzClasses = new Array('required-entry');
        if (country == 'AT' || (element.id == accountNo || element.id == blz)) {

            $(iban).removeClassName('required-entry');
            $(iban).removeClassName('validation-failed');
            if ($('advice-required-entry-ops_directdebit_iban')) {
                $('advice-required-entry-ops_directdebit_iban').remove();
            }
            accountNoClasses.each(function (accountNoClass) {
                if (!$(accountNo).hasClassName(accountNoClass)) {
                    $(accountNo).addClassName(accountNoClass);
                }
            });

            if (country == 'DE' || country == 'AT') {
                blzClasses.each(function (blzClass) {
                    if (!$(blz).hasClassName(blzClass)) {
                        $(blz).addClassName(blzClass);
                    }
                });
            }


            $(accountNo).removeClassName('validation-passed');
            $(blz).removeClassName('validation-passed');

            if (country == 'NL') {
                $(blz).removeClassName('required-entry');
                $(blz).removeClassName('validation-failed');
                if ($('advice-required-entry-ops_directdebit_bank_code')) {
                    $('advice-required-entry-ops_directdebit_bank_code').remove();
                }
            }
        }
        if ((element.id == iban || element.id == bic)) {
            if (!$(iban).hasClassName('required-entry')) {
                $(iban).addClassName('required-entry')
            }
            if ($(iban).hasClassName('validation-passed')) {
                $(iban).removeClassName('validation-passed')
            }

            accountNoClasses.each(function (accountNoClass) {
                if ($(accountNo).hasClassName(accountNoClass)) {
                    $(accountNo).removeClassName(accountNoClass);
                }
            });
            if ($('advice-required-entry-ops_directdebit_account_no')) {
                $('advice-required-entry-ops_directdebit_account_no').remove();
            }
            $(accountNo).removeClassName('validation-failed');

            $(blz).removeClassName('validation-failed');
            blzClasses.each(function (blzClass) {
                if ($(blz).hasClassName(blzClass)) {
                    $(blz).removeClassName(blzClass);
                }
            });
            if ($('advice-required-entry-ops_directdebit_bank_code')) {
                $('advice-required-entry-ops_directdebit_bank_code').remove();
            }

        }
    };
});
