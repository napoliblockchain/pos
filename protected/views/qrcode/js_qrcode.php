<?php
$myScript = <<<JS
	function calcolaPerc(s,m) {
		seconds = s + m*60;
		perc = 100 - seconds/{$totalseconds}*100;
		return perc.toFixed(0);
	}

	// questa funzione fa il check dell'avvenuto o meno pagamento
	function checkPayment(perc){
		$.ajax({
			url:'{$urlGetInvoiceStatus}',
			type: "POST",
			data:{'id_token':'{$idTokenEncrypted}'},
			dataType: "json",
			success:function(data){
				if (data.error){
					console.log('error');
					return false;
				}else{
					$( "#invoiceValue" ).val( data.status );
					if (perc >90 && perc<99.99){
						$( ".timer-row__progress-bar" ).removeClass( "bg-success" );
						$( ".timer-row__progress-bar" ).addClass( "expiring-soon" );
						$( ".timer-row" ).css("background","#cedc21");
						$( "#timer-row__message_waiting" ).html( "Fattura in scadenza a breve..." );
					}
					switch (data.status){
						case 'expired':
							$('.return-button').show();
							$( ".bp-view" ).removeClass( "active" );
							$( "#expired" ).addClass( "active" );
							$( ".timer-row__spinner" ).hide();
							$( ".order-details" ).hide();
							$( ".currency-selection" ).hide();
							$( ".single-item-order" ).hide();
							$( ".payment-tabs" ).hide();

							$( ".timer-row" ).css( "background","red" );
							$('.timer-row__progress-bar').attr('aria-valuenow', 0).css('width', 0+"%");
							$( "#timer-row__message_waiting" ).text( "Fattura scaduta" );
							//return false;
							break;

						case 'paidPartial':
						$('.return-button').show();
							$( ".bp-view" ).removeClass( "active" );
							$( "#paidPartial" ).addClass( "active" );
							$( ".payment-tabs" ).hide();

							$( ".timer-row" ).css( "background","#899813" );
							$( "#timer-row__message_waiting" ).text( "Fattura pagata parzialmente..." );
							break;

						case 'paidOver':
						$('.return-button').show();
							$( ".bp-view" ).removeClass( "active" );
							$( "#paidOver" ).addClass( "active" );
							$( ".timer-row__spinner" ).hide();
							$( ".order-details" ).show();
							$( ".currency-selection" ).hide();
							$( ".payment-tabs" ).hide();
							$( ".timer-row" ).hide( );

							//$( "#timer-row__message_waiting" ).text( "Fattura pagata oltre il dovuto!" );
							break;

						case 'paid':
            case 'complete':
							$('.return-button').show();
							$( ".bp-view" ).removeClass( "active" );
							$( "#paid" ).addClass( "active" );
							$( ".timer-row__spinner" ).hide();
							$( ".order-details" ).show();
							$( ".currency-selection" ).hide();
							$( ".payment-tabs" ).hide();

							$( ".timer-row" ).hide( );
							break;

					}
				}
			},
			error: function(j){
				console.log('error');
			}
		});
	}



	$(function(){
		$( ".line-items" ).hide();

		var dd = {$dd}
		var mm = {$mm}-1; // i mesi in java partono da 0 !!!
		var yyyy = {$yyyy}
		var hh = {$hh}
		var minutes = {$minutes}
		var ss = {$ss}

		var	ts = new Date(yyyy,mm,dd,hh,minutes,ss);

		if((new Date()) > ts){
			checkPayment(100);//fattura scaduta
		}
		$('#countdown').countdown({
			timestamp	: ts,
			callback	: function(days, hours, minutes, seconds){
				perc = calcolaPerc(seconds,minutes);
				if (perc < 100){
					//$('.return-button').hide();
					checkPayment(perc);
					var message = "";
					if (minutes.toString().length == 1)	message += "0";

					message += minutes + ":";
					my_seconds = seconds.toFixed(0);
					if (my_seconds == 60) my_seconds = 0;
					if (my_seconds.toString().length == 1)	message += "0";

					message += seconds.toFixed(0);
					$('.timer-row__time-left').html(message);
					$('.timer-row__progress-bar').attr('aria-valuenow', perc).css('width', perc+"%");
				}
			}
		});
	});

	$("#copy-tab").click(function(){
		$( "#scan" ).removeClass( "active" );
		$( "#copy" ).addClass( "active" );
		$( "#tabsSlider" ).addClass( "slide-copy" );
	});
	$("#scan-tab").click(function(){
		$( "#copy" ).removeClass( "active" );
		$( "#tabsSlider" ).removeClass( "slide-copy" );
		$( "#scan" ).addClass( "active" );
	});

	$("._copyInput").click(function(){
		var temp = $("<input>");
	    $("body").append(temp);
	    temp.val($('.checkoutTextbox').val()).select();
	    document.execCommand("copy");
	    temp.remove();
		$('#smallModal').modal('show');
	});

	$(".fa-angle-double-down").click(function(){
		$( ".fa-angle-double-down" ).hide();
		$( ".fa-angle-double-up" ).show();
		$( ".line-items" ).show(500);
	});
	$(".fa-angle-double-up").click(function(){
		$( ".fa-angle-double-up" ).hide();
		$( ".fa-angle-double-down" ).show();
		$( ".line-items" ).hide(500);
	});


JS;
Yii::app()->clientScript->registerScript('myScript', $myScript);
?>
