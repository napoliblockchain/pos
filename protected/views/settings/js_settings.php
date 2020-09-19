<?php
$urlSavesubscription = Yii::app()->createUrl('settings/saveSubscription'); //save subscription for push messages
$urlChangeLanguage = Yii::app()->createUrl('settings/changelanguage');

$settingsWebapp = Settings::load();
$vapidPublicKey = $settingsWebapp->VapidPublic;

$mySettings = <<<JS
    var ajax_loader_url = 'css/images/loading.gif';


    /*
     * This code checks if service workers and push messaging is supported by the current browser and if it is, it registers our sw.js file.
     */
    const applicationServerPublicKey = '{$vapidPublicKey}';
    const pushButton = document.querySelector('.js-push-btn');
    const pushButtonModal = document.querySelector('.js-push-btn-modal');

    let isSubscribed = false;
    let swRegistration = null;

    if ('serviceWorker' in navigator && 'PushManager' in window) {
        console.log('Push is supported');
        navigator.serviceWorker.register('sw.js')
            .then(function(swReg) {
                console.log('Service Worker is registered again');

                swRegistration = swReg;
                initializeUI();
            })
            .catch(function(error) {
                console.error('Service Worker Error', error);
            });
    } else {
        console.warn('Push messaging is not supported');
        pushButtonModal.textContent = Yii.t('js','Push Not Supported');
    }

    /*
     * check if the user is currently subscribed
     */
    function initializeUI() {
        pushButton.addEventListener('click', function() {
            pushButtonModal.disabled = true;
            if (isSubscribed) {
                unsubscribeUser();
            } else {
                subscribeUser();
            }
        });
        // Set the initial subscription value
        swRegistration.pushManager.getSubscription()
            .then(function(subscription) {
                isSubscribed = !(subscription === null);

                updateSubscriptionOnServer(subscription);

            if (isSubscribed) {
              console.log('User IS subscribed.');
            } else {
              console.log('User is NOT subscribed.');
            }

            updateBtn();
        });

    }
    /*
    * change the text if the user is subscribed or not
    */
    function updateBtn() {
        if (Notification.permission === 'denied') {
           pushButtonModal.textContent = Yii.t('js','Notifications are locked');
           pushButtonModal.disabled = true;
           updateSubscriptionOnServer(null);
           return;
         }

         if (isSubscribed) {
           pushButtonModal.textContent = Yii.t('js','Disable');
           $('.js-push-btn-modal').prop('data-target', 'pushDisableModal');
         } else {
           pushButtonModal.textContent = Yii.t('js','Enable');
            $('.js-push-btn-modal').prop('data-target', 'pushEnableModal');
         }

         pushButtonModal.disabled = false;
   }

    /*
     * SUBSCRIBE A USER
     */
    function subscribeUser() {
        const applicationServerKey = urlBase64ToUint8Array(applicationServerPublicKey);
        swRegistration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey
        })
        .then(function(subscription) {
            console.log('User is subscribed.');
            updateSubscriptionOnServer(subscription);
            isSubscribed = true;
            updateBtn();
        })
        .catch(function(err) {
            console.log('Failed to subscribe the user: ', err);
            updateBtn();
        });
    }
    /*
    * UNSUBSCRIBE A USER
    */
    function unsubscribeUser() {
      swRegistration.pushManager.getSubscription()
      .then(function(subscription) {
        if (subscription) {
          return subscription.unsubscribe();
        }
      })
      .catch(function(error) {
        console.log('Error unsubscribing', error);
      })
      .then(function() {
        updateSubscriptionOnServer(null);

        console.log('User is unsubscribed.');
        isSubscribed = false;

        updateBtn();
      });
    }

    /*
     *  Send subscription to application server
    */
    function updateSubscriptionOnServer(subscription) {
        if (subscription) {
            sub = JSON.stringify(subscription);
            console.log('Salvo la sottoscrizione',subscription);
        }else{
            sub = JSON.stringify(null);
            console.log('Elimino la sottoscrizione');
        }

        $.ajax({
            url:'{$urlSavesubscription}',
            type: "POST",
            data: sub,
            dataType: "html",
            success:function(res){
                console.log(res);
            },
            error: function(j){
                console.log('ERRORE Update subscription',j);
            }
        });
    }

    function saveOnDesktop() {
    	if (deferredPrompt) {
    		deferredPrompt.prompt();
    		deferredPrompt.userChoice.then(function(choiceResult) {
    			// console.log('[deferred prompt]',choiceResult.outcome);
    			if (choiceResult.outcome === 'dismissed') {
    	  			console.log('[deferred prompt] User cancelled installation');
    			} else {
    	  			console.log('[deferred prompt] User added to home screen');
    			}
    		});
    		deferredPrompt = null;
    	}
    }


    //EFFETTUA il cambio lingua
    // var language = document.querySelector('#SettingsUserForm_language');
    // language.addEventListener('change', function(){
    //     $.ajax({
    //         url:'{$urlChangeLanguage}',
    //         type: "POST",
    //         data: {lang: $('#SettingsUserForm_language').val() },
    //         dataType: "json",
    //         success:function(data){
    //             if (data.success){
    //                 location.href = location.href;
    //             }
    //             console.log(data);
    //         },
    //         error: function(j){
    //             console.log(j);
    //         }
    //     });
    // });



JS;
Yii::app()->clientScript->registerScript('mySettings', $mySettings, CClientScript::POS_END);
?>
