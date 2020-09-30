<?php

Yii::app()->language = ( isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'it' );
Yii::app()->sourceLanguage = ( isset($_COOKIE['langSource']) ? $_COOKIE['langSource'] : 'it_it' );

new JsTrans('js',Yii::app()->language); // javascript translation

//assegno valori del POS per l'invoice creation
$BtcPayServerIDPOS = Yii::app()->user->objUser['id_pos'];
$pos=Pos::model()->findByPk($BtcPayServerIDPOS);

//RICERCA GATEWAY PER INVIARE COMANDI PERSONALIZZATI
$merchants=Merchants::model()->findByPk($pos->id_merchant);
$settings=Settings::loadUser($merchants->id_user);
$gateways=Gateways::model()->findByPk($settings->id_gateway);

// url creazione invoices
$urlController = Yii::app()->createUrl('invoices/'.$gateways->action_controller); // controller che crea la transazione
$urlTokenController = Yii::app()->createUrl('invoices/token'); // controller che crea la transazione per il token

// CERCO L'INDIRIZZO DEL TOKEN. SE PRESENTE AUTORIZZO LA CREAZIONE DELL'INVOICE, ALTRIMENTI NISBA...
$tokenAuth = false;
$wallet_address = '';
if (isset($settings->id_wallet)){
    $wallets=Wallets::model()->findByPk($settings->id_wallet);
    if (null !== $wallets){
        $wallet_address = $wallets->wallet_address;
        $tokenAuth = true;
    }
}

$myPos = <<<JS

    show_easy_numpad();

    var ajax_loader_url = 'css/images/loading.gif';
    var tokenAuth = '{$tokenAuth}';

    var bitcoinInvoice = document.querySelector('#done');
    var tokenInvoice = document.querySelector('#token');

    var countDecimals = function(value) {
        // console.log('[countDecimals]',Math.floor(value),value);
        if (Math.floor(value) != value)
            return value.toString().split(".")[1].length || 0;
        return 0;
    }

    function showError(message){
      $('.error-message').text(message);
      $('.error-header').addClass("bg-danger").show();
      $('.easy-numpad-output-container-content').hide();
      setTimeout(function(){
        $('.error-header').removeClass("bg-danger").hide();
        $('.easy-numpad-output-container-content').show();

      }, 5000);
    }

    tokenInvoice.addEventListener('click', function(){
        event.preventDefault();
        if (tokenAuth == false){
          showError('Indirizzo wallet non trovato. Non è possibile ricevere Token!');
          return false;
        }

        var amount_val = $('#easy-numpad-output').text();
        if (amount_val == 0 || amount_val == '')
            return false;

        if (countDecimals(amount_val) > 2){
          showError('Errore. Utilizzare massimo 2 cifre decimali');
          return false;
        }

        $.ajax({
            url:'{$urlTokenController}',
            type: "POST",
            beforeSend: function() {
                $('#waiting_span-token').hide();
                $('#waiting_span-token').after('<div class="waiting_span"><center><img width=25 src="'+ajax_loader_url+'"></center></div>');
            },
            data:{
                'wallet_address': '{$wallet_address}',
                'id_pos'		: '{$BtcPayServerIDPOS}',
                'amount'		: amount_val,
            },
            dataType: "json",
            success:function(data){
                $('.waiting_span').remove();
                $('#waiting_span-token').show();

                if (data.error){
                  showError(data.error);
                  return false;
                }else{
                  window.location.href = data.url;
                }
            },
            error: function(j){
                var json = jQuery.parseJSON(j.responseText);
                $('#waiting_span-token').show();
                $('.waiting_span').remove();
                showError(json.error);
            }
        });
    });

    bitcoinInvoice.addEventListener('click', function(){
        event.preventDefault();
        var amount_val = $('#easy-numpad-output').text();
        if (amount_val == 0 || amount_val == '')
            return false;

        if (countDecimals(amount_val) > 2){
          showError('Errore. Utilizzare massimo 2 cifre decimali');
          return false;
        }

        $.ajax({
            url:'{$urlController}',
            type: "POST",
            beforeSend: function() {
                $('#waiting_span-btc').hide();
                $('#waiting_span-btc').after('<div class="waiting_span"><center><img width=25 src="'+ajax_loader_url+'"></center></div>');
            },
            data:{
                'id_pos'		: '{$BtcPayServerIDPOS}',
                'amount'		: amount_val,
            },
            dataType: "json",
            success:function(data){
                $('.waiting_span').remove();
                $('#waiting_span-btc').show();
                console.log(data);

                if (data.error){
                  showError(data.error);
                  return false;
                }else{
                  if ('{$gateways->action_controller}' != 'Bitpay')
                    window.location.href = data.url;
                  else
                    top.location = data.url;
                }
            },
            error: function(j){
              var json = jQuery.parseJSON(j.responseText);
              $('#waiting_span-btc').show();
              $('.waiting_span').remove();
              showError(json.error);
            }
        });
    });



JS;
Yii::app()->clientScript->registerScript('myPos', $myPos, CClientScript::POS_END);
