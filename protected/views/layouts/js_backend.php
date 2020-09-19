<?php
/**
 * nuova gestione delle notifiche
 */


$urlBackend = Yii::app()->createUrl('backend/notify');
$urlNewsRead = Yii::app()->createUrl('backend/updateNews');
$urlNewsReadAll = Yii::app()->createUrl('backend/updateAllNews');
$fileNotify = Yii::app()->request->baseUrl.'/css/sounds/notify.mp3';
$urlCheckBlockchain = Yii::app()->createUrl('backend/checkBlockchain');

$myBackendScript = <<<JS

	var urlBackend = '{$urlBackend}';
  var urlNewsRead = '{$urlNewsRead}';
	var urlNewsReadAll = '{$urlNewsReadAll}';
  var urlSound = '{$fileNotify}';

	$(function(){
    backend = {
			// verifica le notifiche in atto
      checkNotify: function()
			{
          $.ajax({
						url:urlBackend,
						type: "POST",
          	data: { 'countedNews' : $('#countedNews').val() },
        		dataType: 'json',
          	success: function(response) {
    					backend.handleResponse(response);
              setTimeout(function(){ backend.checkNotify() }, 5000);
    				},
    				error: function(data) {
              setTimeout(function(){ backend.checkNotify() }, 5000);
    				}
					});
      },

			// gestisce la risposta e aggiorna il menù notifiche
      handleResponse: function(response)
			{
        if (response.playSound == true){
      		backend.playSound();
        	//VERIFICO QUESTE ULTIME 3 TRANSAZIONI PER AGGIORNARE IN REAL-TIME LO STATO (IN CASO CI SI TROVA SULLA PAGINA TRANSACTIONS)
          for (var key in response.status) {
            var status = response.status[key];
          }
        }

        $("#notifiche_dropdown").fadeIn(1000).css("display","");
        $('#quantity_notify').html(response.countedUnread);
        $('#notifiche__contenuto').html(response.htmlTitle);
        $('#notifiche__contenuto').append(response.htmlContent);
        if (response.countedUnread > 0){
          $("#quantity_circle").fadeIn(1000).css("display","");
        	$("#quantity_circle").css("background","#ff4b5a");
        }else{
          $("#quantity_circle").fadeIn(1000).css("display","none");
        }
      },

			// emette un suono
      playSound: function(){
        navigator.vibrate = navigator.vibrate || navigator.webkitVibrate || navigator.mozVibrate || navigator.msVibrate;
        if (navigator.vibrate) {
            navigator.vibrate(60);
        }
        $('embed').remove();
        $('body').append("<embed src='"+urlSound+"' autostart='true' hidden='true' loop='false'>");
      },

			// segna la notifica come già letta
      openEnvelope: function(id_notification){
          event.preventDefault();
          event.stopPropagation();
          var submitUrl = $('#news_'+id_notification).attr('href');

          // metto a read il valore del messaggio
          $.ajax({
						url:urlNewsRead,
						type: "POST",
            data: { 'id_notification' : id_notification },
            dataType: 'json',
            success: function(response) {
              location.href = submitUrl;
    				},
    				error: function(data) {
              console.log(data);
    				}
					});
      },
			openAllEnvelopes: function(){
	      event.preventDefault();
	      event.stopPropagation();
	      var submitUrl = $('#seeAllMessages').attr('href');
	      $.ajax({
					url:urlNewsReadAll,
					type: "POST",
	        data: { },
	       	dataType: 'json',
	        success: function(response) {
						if (response.success)
	            location.href = submitUrl;
	    			},
	    			error: function(data) {
	         		console.log(data);
	    			},
					});
				},

			// verifica il funzionamento della blockchain
			sync: function(){
	            console.log('[verify blockchain]');
	            $.ajax({
	                url:'{$urlCheckBlockchain}',
	                type: "GET",
	                dataType: "json",
	                success:function(data){
						if (data.success){
							$('.pulse-button').removeClass('pulse-button-offline');
						}else{
							$('.pulse-button').addClass('pulse-button-offline');
						}
						// richiama la funzione ogni 5 secondi
						setTimeout(function(){ backend.sync() }, 5000);

	                },
	                error: function(j){
	                    console.log(j);
	                    $('.pulse-button').addClass('pulse-button-offline');
	                    setTimeout(function(){ backend.sync() }, 15000);
	                }
	            });
			},
        }
    	//funzioni da richiamare all'avvio
        setTimeout(function(){ backend.checkNotify() }, 500);
		setTimeout(function(){ backend.sync() }, 5000);
    });



JS;
Yii::app()->clientScript->registerScript('myBackendScript', $myBackendScript, CClientScript::POS_HEAD);
?>
