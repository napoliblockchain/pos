<?php
$login = <<<JS
var accediButton = document.querySelector('#accedi-button');
function openSaveOnDesktop() {
	//createPostArea.style.display = 'block';
	if (deferredPrompt) {
		deferredPrompt.prompt();

		deferredPrompt.userChoice.then(function(choiceResult) {
			console.log(choiceResult.outcome);

			if (choiceResult.outcome === 'dismissed') {
				console.log('User cancelled installation');
			} else {
				console.log('User added to home screen');
			}
		});

		deferredPrompt = null;
	}
}


//LEGGO LE INFORMAZIONI DEL WALLET DA IndexedDB
var start = false;
var sin_address;
readAllData('sin')
	.then(function(data) {
		console.log('Sin',data);
		if (typeof data[0] !== 'undefined') {
			if (null !== data[0].id){
				/*  START 	*/
				sin_address = data[0].id;
				console.log('Sin recuperato: ',sin_address);
				$('#LoginPosForm_username').val(sin_address);
				//start = true;
			}
		}else{
			console.log('sin non trovato!');

		}
	})


accediButton.addEventListener('click', openSaveOnDesktop);

JS;
Yii::app()->clientScript->registerScript('login', $login, CClientScript::POS_END);
?>
