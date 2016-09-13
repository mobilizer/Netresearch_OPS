Event.observe(window, 'load', function () {

    payment.opsAliasSuccess = false;

    payment.isInlineCcBrand = function () {
        if ($(payment.currentMethodObject.code.toUpperCase() + '_BRAND')
            && -1 < payment.currentMethodObject.brandsForAliasInterface.indexOf(
                $(payment.currentMethodObject.code.toUpperCase() + '_BRAND').value
            )
        ) {
            return true;
        } else {
            return false;
        }
    };

    payment.generateIframeUrl = function (hash) {
        var form = payment.prepareOpsForm(hash);
        payment.currentMethodObject.tokenizationFrame.src = opsUrl + '?' + form.serialize();
    };


    payment.generateHash = function () {
        return new Ajax.Request(opsHashUrl + '?' + payment.prepareOpsForm(false).serialize(), {
            method: 'post',
            onSuccess: function (transport) {
                var data = transport.responseText.evalJSON();
                payment.generateIframeUrl(data.hash);
            }
        });
    };

    payment.handleBrandChange = function () {
        payment.currentMethodObject.tokenizationFrame.src = 'about:blank';
        payment.opsAliasSuccess = false;
        if (payment.isInlineCcBrand()) {
            payment.currentMethodObject.redirectNote.style.display = 'none';
            payment.fillOpsLoader('LOAD_TOKEN');
            if (typeof checkout != 'undefined') {
                payment.toggleContinue(false);
            } else {
                toggleOrderSubmit(false);
            }
            payment.generateHash();
        } else {
            payment.fillOpsLoader();
            payment.currentMethodObject.redirectNote.style.display = 'block';
            if (typeof checkout != 'undefined') {
                payment.toggleContinue(true);
            } else {
                toggleOrderSubmit(true);
            }
        }
    };

    payment.prepareOpsForm = function (hash) {
        doc = document;
        form = doc.createElement('form');


        if ($(payment.currentMethodObject.code.toUpperCase() + '_BRAND')) {
            var brandElement = doc.createElement('input');
            brandElement.id = 'CARD.BRAND';
            brandElement.name = 'CARD.BRAND';
            brandElement.value = $(payment.currentMethodObject.code.toUpperCase() + '_BRAND').value;
            form.appendChild(brandElement);
        }

        var pspidElement = doc.createElement('input');
        pspidElement.id = 'ACCOUNT.PSPID';
        pspidElement.name = 'ACCOUNT.PSPID';
        pspidElement.value = opsPspid;

        var orderIdElement = doc.createElement('input');
        orderIdElement.name = 'ALIAS.ORDERID';
        orderIdElement.id = 'ALIAS.ORDERID';
        orderIdElement.value = opsOrderId;

        var acceptUrlElement = doc.createElement('input');
        acceptUrlElement.name = 'PARAMETERS.ACCEPTURL';
        acceptUrlElement.id = 'PARAMETERS.ACCEPTURL';
        acceptUrlElement.value = opsAcceptUrl;

        var exceptionUrlElement = doc.createElement('input');
        exceptionUrlElement.name = 'PARAMETERS.EXCEPTIONURL';
        exceptionUrlElement.id = 'PARAMETERS.EXCEPTIONURL';
        exceptionUrlElement.value = opsExceptionUrl;

        var paramplusElement = doc.createElement('input');
        paramplusElement.name = 'PARAMETERS.PARAMPLUS';
        paramplusElement.id = 'PARAMETERS.PARAMPLUS';
        paramplusElement.value = paramplus;

        var aliasElement = doc.createElement('input');
        aliasElement.name = 'ALIAS.ALIASID';
        aliasElement.id = 'ALIAS.ALIASID';
        aliasElement.value = opsAlias;

        if(payment.currentMethodObject.aliasManager) {
            var storePermanentlyElement = doc.createElement('input');
            storePermanentlyElement.name = 'ALIAS.STOREPERMANENTLY';
            storePermanentlyElement.id = 'ALIAS.STOREPERMANENTLY';
            storePermanentlyElement.value = 'N';
            form.appendChild(storePermanentlyElement);
        }

        var paymentMethodElement = doc.createElement('input');
        paymentMethodElement.name = 'Card.PaymentMethod';
        paymentMethodElement.id = 'Card.PaymentMethod';
        paymentMethodElement.value = 'CreditCard';

        var localeElement = doc.createElement('input');
        localeElement.name = 'Layout.Language';
        localeElement.id = 'Layout.Language';
        localeElement.value = locale;
        form.appendChild(localeElement);

        if (hash) {
            var hashElement = doc.createElement('input');
            hashElement.id = 'SHASIGNATURE.SHASIGN';
            hashElement.name = 'SHASIGNATURE.SHASIGN';
            hashElement.value = hash.toUpperCase();
            form.appendChild(hashElement);
        }

        form.id = 'ops_request_form';
        form.method = 'post';
        form.action = opsUrl;
        submit = document.createElement('submit');
        form.appendChild(submit);

        form.appendChild(pspidElement);
        form.appendChild(acceptUrlElement);
        form.appendChild(exceptionUrlElement);
        form.appendChild(orderIdElement);
        form.appendChild(paramplusElement);
        form.appendChild(aliasElement);

        if (transmitPaymentMethod === true) {
            form.appendChild(paymentMethodElement);
        }

        // INGD-40 fix for mobile devices from bulkpowders.com crew
        if (typeof form.serialize !== "function") {
            Element.extend(form);
        }

        return form;
    };

    payment.fillOpsLoader = function (token) {
        if (token) {
            payment.currentMethodObject.loader.innerHTML = Translator.translate(token);
            payment.currentMethodObject.loader.style.display = 'block';
            payment.currentMethodObject.tokenizationFrame.style.display = 'none';
        } else {
            payment.currentMethodObject.loader.style.display = 'none';
            payment.currentMethodObject.tokenizationFrame.style.display = 'none';
        }

    };

    payment.toggleContinue = function (active) {
        if (active) {
            checkout.setLoadWaiting('payment', false);
            checkout.setLoadWaiting(false);
        } else {
            checkout.setLoadWaiting('payment', true);
            checkout.setLoadWaiting(false, true);
        }
    };

    payment.onOpsIframeLoad = function () {
        if (payment.isInlineCcBrand() && payment.currentMethodObject.tokenizationFrame.src != 'about:blank' && !payment.opsAliasSuccess) {
            payment.currentMethodObject.loader.style.display = 'none';
            payment.currentMethodObject.tokenizationFrame.style.display = 'block';
        }
    }


});
