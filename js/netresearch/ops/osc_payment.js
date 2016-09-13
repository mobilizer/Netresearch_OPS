Event.observe(window, 'load', function() {

    // check if we are dealing with OneStepCheckout
    payment.isOneStepCheckout = $$('.onestepcheckout-place-order');
    payment.formOneStepCheckout = $('onestepcheckout-form');
    payment.holdOneStepCheckout = true;

    if(payment.isOneStepCheckout){

        //set the form element
        payment.form = payment.formOneStepCheckout;

         //bind event handlers to buttons
        payment.isOneStepCheckout.each(function(elem){
            elem.observe('click', function(e){

                Event.stop(e);
                if(!payment.holdOneStepCheckout){
                    return;
                }

                if ('ops_directDebit' == payment.currentMethod && payment.holdOneStepCheckout) {
                    window.already_placing_order = true;
                }

                if ('ops_cc' == payment.currentMethod && payment.holdOneStepCheckout) {
                    window.already_placing_order = true;
                }
                //normally this is not called
                payment.save();
            });
        });


         //add new method to restore the palce order state when failure
        payment.toggleOneStepCheckout =  function(action){
            submitelement = $('onestepcheckout-place-order');
            loaderelement = $$('.onestepcheckout-place-order-loading');

            if(action === 'payment'){

                window.already_placing_order = true;
                /* Disable button to avoid multiple clicks */
                submitelement.removeClassName('orange').addClassName('grey');
                submitelement.disabled = true;
                payment.holdOneStepCheckout = true;
            }

            if(action === 'remove'){

                submitelement.removeClassName('grey').addClassName('orange');
                submitelement.disabled = false;

                if(loaderelement){
                    loaderelement = loaderelement[0];
                    if(loaderelement){
                        loaderelement.remove();
                    }
                }

                window.already_placing_order = false;
                payment.holdOneStepCheckout = false;
            }


            return;
        };

        //wrapp save before ogone
        payment.save = payment.save.wrap(function(originalSaveMethod) {
            $('onestepcheckout-place-order').click();
            return;
        });

        //wrap this to toggle the buttons in OneStepCheckout.
        checkout.setLoadWaiting = checkout.setLoadWaiting.wrap(function(originalSetLoadWaiting, param1){

            if(!param1){
                payment.toggleOneStepCheckout('remove');
            }
            originalSetLoadWaiting(param1);
        });
    }
    // check if we are dealing with OneStepCheckout end

});
