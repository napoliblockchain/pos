<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Notifi');
Yii::import('libs.NaPacks.WebApp');

class BackendController extends Controller
{
	public function init()
	{
		if (!(isset(Yii::app()->user->objUser))){
			$this->redirect(array('site/logout'));
		}
	}

	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
			// page action renders "static" pages stored under 'protected/views/site/pages'
			// They can be accessed via: index.php?r=site/page&view=FileName
			'page'=>array(
				'class'=>'CViewAction',
			),
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array(
					'notify', //visualizzo le notifiche
					'updateNews', // aggiorno i messaggi cliccati da 0 a 1 (unread -> read)
					'updateAllNews', // aggiorno tutti i messaggi  da 0 a 1 (unread -> read)
					'checkSingleInvoice', // verifica la singola invoice btcpayserver
					'checkBlockchain', // verifica che la blockchain Token sia attiva
				),
				'users'=>array('@'),
			),
		);
	}

	public function actionCheckBlockchain()
	{
		if( WebApp::getPoaNode() === false ) {
			$json = ['success'=>false];
		}else{
			$json = ['success'=>true];
		}
		echo CJSON::encode($json);
	}

	// aggiorna tutte le notifiche in "letta"
	// update all rows
	public function actionUpdateAllNews(){
		$updateAll = Yii::app()->db->createCommand(
    					"UPDATE np_notifications_readers nr
        				SET nr.alreadyread = 1
        				WHERE nr.id_user = " . Yii::app()->user->objUser['id_user'] . ";"
            		)->execute();

		 //
		 // echo "<pre>".print_r($updateAll,true)."</pre>";
		 // exit;
		echo CJSON::encode(['success'=>true],true);
	}


	public function actionUpdateNews(){
		$model = Notifications_readers::model()->findByAttributes([
			'id_user'=> Yii::app()->user->objUser['id_user'],
			'id_notification' => $_POST['id_notification'],
		]);
		if (null !== $model){
			$model->alreadyread = 1;
			$model->update();
		}
		echo CJSON::encode(['success'=>true],true);
	}


	/**
	 * This is the default 'index' action that is invoked
	 * when an action is not explicitly requested by users.
	 */
	public function actionNotify()
	{
		$response['countedRead'] = 0;
		$response['countedUnread'] = 0;
		$response['htmlTitle'] = '';
		$response['htmlContent'] = ''; // ex content
		$response['playSound'] = false;

		$criteria = new CDbCriteria();
		$criteria->compare('id_user',Yii::app()->user->objUser['id_user'],false);
		$newsReaders = Notifications_readers::model()->orderById()->findAll($criteria);

		$arrayCondition = array();
		$news = array();
		foreach ($newsReaders as $key => $item) {
			$notify = Notifications::model()->findByPk($item->id_notification);
			// nel pos non controllo le notifiche token che appartengono
			// al wallet
			if ($notify->type_notification <> 'token'){
				$arrayCondition[] = $item->id_notification;
				($item->alreadyread == 0 ? $response['countedUnread'] ++ : $response['countedRead'] ++);
				$news[] = $item;
			}
		}

		$x=1;
		foreach ($news as $key => $item) {
			// echo "<pre>".print_r($notify,true)."</pre>";
			// exit;
			// Leggo la notifica tramite key
			$notify = Notifications::model()->findByPk($item->id_notification);
			if ($x == 1){
				$response['htmlTitle'] .= '<div class="notifi__title">';
				if ($response['countedUnread']>0){
					$response['htmlTitle'] .= '<p>' . Yii::t('lang','You have {n} unread message.|You have {n} unread messages.',$response['countedUnread']) . '</p>';
				}else{
					$response['htmlTitle'] .= '<p>' . Yii::t('lang','You have read all messages.') . '</p>';
				}
				$response['htmlTitle'] .= '</div>';
			}

			$notifi__icon = Notifi::Icon(
				(strpos($notify->description,'token') !== false ? 'token' : $notify->type_notification )
			);
			$notifi__color = Notifi::Color($notify->status);

			$response['htmlContent'] .= '
			<a href="'.htmlentities($notify->url).'" id="news_'.$notify->id_notification.'">
			<div class="notifi__item">
			<div class="'.$notifi__color.' img-cir img-40">
			<i class="'.$notifi__icon.'"></i>
			</div>
			<div class="content">
			<div onclick="backend.openEnvelope('.$notify->id_notification.');" >';
			if ($item->alreadyread == 0){
				$response['htmlContent'] .= '<p style="font-weight:bold;">';
			}else{
				$response['htmlContent'] .= '<p>';
			}

			$response['htmlContent'] .= WebApp::translateMsg($notify->description);
			$response['htmlContent'] .= '</p>';

			// se il tipo notifica è help o contact ovviamente non mostro il prezzo della transazione
			if ($notify->type_notification <> 'help' && $notify->type_notification <> 'contact'){
				$response['htmlContent'] .= '<p class="text-success">'.$notify->price.'</p>';
				//VERIFICO QUESTE ULTIME 3 TRANSAZIONI PER AGGIORNARE IN REAL-TIME LO STATO (IN CASO CI SI TROVA SULLA PAGINA TRANSACTIONS)
				$response['status'][$notify->id_tocheck] = $notify->status;
			}
			$response['htmlContent'] .= '
			<span class="date text-primary">'.date('d M Y - H:i:s',$notify->timestamp).'</span>
			</div>
			</div>
			</div>
			</a>
			';

			$x++;
			if ($x>3)
				break;
		}
		if ($response['countedRead'] == 0 && $response['countedUnread'] == 0){
			$response['htmlContent'] .= '<div class="notifi__title">';
			$response['htmlContent'] .= '<p>' . Yii::t('lang','You have no messages to read.') . '</p>';
			$response['htmlContent'] .= '</div>';
		}else{
			$response['htmlContent'] .= '
				<div class="notifi__footer">
					<a id="seeAllMessages" onclick="backend.openAllEnvelopes();" href="'.htmlentities(Yii::app()->createUrl('messages/index')).'">'.Yii::t('lang','See all messages').'</a>
				</div>
			';
		}
		echo CJSON::encode($response,true);

	}

	public function actionCheckSingleInvoice($id)
	{
		$response['success'] = 0;

		$item = Transactions::model()->findByPk(crypt::Decrypt($id));

		if ($item->id_invoice_bps == ''){
			$response['message'] = 'id Invoice ('.$item->id_pos.') non trovato!';
		}else{
			//dalla transazione cerco il pos per vedere se esiste la chiave privata
			// se id_pos == -1 allora vuol dire che ho usato il SelfPOS
			if ($item->id_pos == -1){
				$shop = Shops::model()->findByPk($item->item_code);
				if($shop!==null){
					// CERCO UN POS QUALUNQUE PER CARICARE LA PRIVATE E PUBLIC KEY TRAMITE id_store
				    $pos = Pos::model()->findByAttributes(array('id_store'=>$shop->id_store,'deleted'=>0));
				}
			}else{
				$pos=Pos::model()->findByPk($item->id_pos);
			}
			if ($pos !== null){
				$folder = str_replace("www/pos","www/napay",Yii::app()->basePath . '/privatekeys/');
				$pairings = Pairings::model()->findByAttributes(array('id_pos'=>$pos->id_pos));

				// echo '<pre>'.print_r($folder.$pairings->sin,true).'</pre>';
				// exit;

				$privatekeyFile = $folder.$pairings->sin.".pri";
				if (true === file_exists($privatekeyFile)){
					//dalla transazione cerco il merchant
					$merchants=Merchants::model()->findByPk($item->id_merchant);
					if ($merchants !== null){
						//dal merchants cerco il settings
						//$settings=Settings::model()->findByAttributes(array('id_user'=>$merchants->id_user));
						$settings=Settings::loadUser($merchants->id_user);
						//dal settings cerco il gateway
						$gateways=Gateways::model()->findByPk($settings->id_gateway);
						#echo '<br>'.$gateways->action_controller;
						$response['success'] = 1;

						switch (strtolower($gateways->action_controller)){
							case 'coingate':
								$this->checkCoingateInvoice($item);
								break;
							case 'bitpay':
								$this->checkBitpayInvoice($item);
								break;
							case 'btcpayserver':
								$this->checkBtcpayserverInvoice($item);
								break;
						}
					}else{
						$response['message'] = 'id Merchant ('.$item->id_merchant.') non trovato!';
					}
				}else{
					$response['message'] = 'The requested Private Key does not exist on this Server!';
				}
			}else{
				$response['message'] = 'id Pos ('.$item->id_pos.') non trovato!';
			}
		}

		echo CJSON::encode($response,true);
	}

	private function checkBtcpayserverInvoice($item)
	{
		// carico l'estensione
		require_once Yii::app()->params['libsPath'] . '/BTCPay/BTCPay.php';

		// Effettuo il login senza dati
		$BTCPay = new BTCPay(null,null);
		// imposto l'url che è associato a ciascun merchant
		$merchants = Merchants::model()->findByPk($item->id_merchant);
		$BTCPay->setBTCPayUrl(Settings::loadUser($merchants->id_user)->blockchainAddress);

		// Carico l'URL del Server BTC direttamente dalla CLASSE
		$BPSUrl = $BTCPay->getBTCPayUrl();

		/**
		*	AUTOLOADER GATEWAYS
		*/
		// $btcpay = Yii::app()->basePath . '/extensions/gateways/btcpayserver-php-v1/Btcpay/Autoloader.php';
		$btcpay = Yii::app()->params['libsPath'] . '/gateways/btcpayserver-php-v1/Btcpay/Autoloader.php';
		if (true === file_exists($btcpay) &&
				true === is_readable($btcpay))
		{
				require_once $btcpay;
				\Btcpay\Autoloader::register();
		} else {
				throw new Exception('Btcpay Server Library could not be loaded');
		}


		/**
		 * To load up keys that you have previously saved, you need to use the same
		 * storage engine. You also need to tell it the location of the key you want
		 * to load.
		 */
		 $pairing_search = $item->id_pos;

 		if ($item->id_pos == -1){
 			$shop = Shops::model()->findByPk($item->item_code);
 			if($shop!==null){
 				// CERCO UN POS QUALUNQUE PER CARICARE LA PRIVATE E PUBLIC KEY TRAMITE id_store
 				$pos = Pos::model()->findByAttributes(array('id_store'=>$shop->id_store,'deleted'=>0));
 			   if($pos!==null){
 				   $pairing_search = $pos->id_pos;
 			   }
 			}
 		}
 	   $pairings = Pairings::model()->findByAttributes(array('id_pos'=>$pairing_search));
		if($pairings===null){
			throw new \Exception('Error. The requested Pairings does not exist.');
		}

		// if (gethostname()=='blockchain1'){
		// 	$folder = $_SERVER["DOCUMENT_ROOT"].'/../napay/protected/privatekeys/';
		// }elseif (gethostname()=='CGF6135T' || gethostname()=='NUNZIA'){ // SERVE PER LE PROVE IN UFFICIO
        //     $folder = $_SERVER["DOCUMENT_ROOT"].'/napay/protected/privatekeys/';
		// }else{
		// 	$folder = $_SERVER["DOCUMENT_ROOT"].'/../npay/protected/privatekeys/';
		// }

		$folder = str_replace("www/pos","www/napay",Yii::app()->basePath . '/privatekeys/');

		// echo '<pre>'.print_r($folder.$pairings->sin,true).'</pre>';
		// exit;

		//verifico esistenza coppia chiavi public/private
		if (!file_exists($folder.$pairings->sin.".pub") ){
			throw new \Exception('Error. The requested Public key does not exist.');
		}

		//verifico esistenza coppia chiavi public/private
		if (!file_exists($folder.$pairings->sin.".pri") ){
			throw new \Exception('Error. The requested Private key does not exist.');
		}
		$storageEngine = new \Btcpay\Storage\EncryptedFilesystemStorage('mc156MdhshuUYTF5365');

		$publicKey     = $storageEngine->load($folder.$pairings->sin.'.pub');
		$privateKey    = $storageEngine->load($folder.$pairings->sin.'.pri');

		$settings=Settings::load();  //$settings = SettingsNapos::model()->findByPk(1);
		if($settings===null){
			throw new \Exception("Error. The requested Settings does not exist.");
		}

		try {
			// Now fetch the invoice from Btcpay
			// This is needed, since the IPN does not contain any authentication
			$client        = new \Btcpay\Client\Client();
			$adapter       = new \Btcpay\Client\Adapter\CurlAdapter();

			$client->setPrivateKey($privateKey);
			$client->setPublicKey($publicKey);
			$client->setUri($BPSUrl);
			$client->setAdapter($adapter);
			// ---------------------------


			$token = new \Btcpay\Token();
			$token->setToken(crypt::Decrypt($pairings->token)); // UPDATE THIS VALUE
			$token->setFacade('merchant'); //IMPORTANTE PER RECUPERARE LO STATO DELLE FATTURE DA BTCPAYSERVER :::SERGIO CASIZZONE
			$client->setToken($token);


			/**
			* This is where we will fetch the invoice object
			*/
			$invoice = $client->getInvoice($item->id_invoice_bps);
			if ($invoice){
				// $invoice['Id'] 		= $invoice['Obj']->getId();			//per recuperare l'invoice dall'archivio
				// $invoice['Status'] 	= $invoice['Obj']->getStatus(); 	//per impostare il nuovo stato
				// $invoice['btcPaid'] = $invoice['Obj']->getBtcPaid();	//per impostare quanto è stato pagato
				// $invoice['btcPrice']= $invoice['Obj']->getBtcPrice();	//per impostare quanto era il dovuto da pagare
				// // !!! IMPORTANTE !!!
				// $invoice['Price']= $invoice['Obj']->getPrice();	//per impostare quanto era il dovuto da pagare in moneta fiat

				//A QUESTO PUNTO AGGIORNO LA TRANSAZIONE IN ARCHIVIO solo se gli status sono DIVERSI !!!!
				if ($item->status <> $invoice->getStatus()){
					$transactions = Transactions::model()->findByPk($item->id_transaction);
					$transactions->status = $invoice->getStatus();
					$transactions->btc_paid = $invoice->getBtcPaid();
					$transactions->update();

					// aggiorno la transactions_info
					$cryptoInfo = Save::CryptoInfo($transactions->id_transaction, $invoice->getCryptoInfo());

					//QUINDI INVIO UN MESSAGGIO DI NOTIFICA
				    //salva la notifica
				    $notification = array(
					   'type_notification' => 'invoice',
					   'id_user' => Merchants::model()->findByPk($transactions->id_merchant)->id_user,
					   'id_tocheck' => $transactions->id_transaction,
					   'status' => $invoice->getStatus(),
					   'description' => Notifi::description($invoice->getStatus(),'invoice'),
					   // 'url' => Yii::app()->createUrl("transactions/view")."&id=".crypt::Encrypt($transactions->id_transaction),
					   // La URL non deve comprendere l'hostname in quanto deve essere raggiungibile da più applicazioni
		   				'url' => 'index.php?r=transactions/view&id='.crypt::Encrypt($transactions->id_transaction),
					   'timestamp' => time(),
					   'price' => $invoice->getPrice(),
					   'deleted' => 0,
				    );
				    // NaPay::save_notification($notification);
					$save = new Save;
					Push::Send($save->Notification($notification,true),'dashboard');
				}
			}
		} catch (Exception $e) {
			die(get_class($e) . ': ' . $e->getMessage());
		}

	}
	private function checkBitpayInvoice($item)
	{
		/**
		*	AUTOLOADER GATEWAY
		*/
		$bitpay = Yii::app()->basePath . '/extensions/gateways/bitpay/Bitpay/Autoloader.php';
		if (true === file_exists($bitpay) &&
		    true === is_readable($bitpay))
		{
		    require_once $bitpay;
		    \Bitpay\Autoloader::register();
		} else {
		    throw new Exception('Bitpay Server Library could not be loaded');
		}
		//$folder = Yii::app()->basePath . '/privatekeys/bitpay-';
		$folder = str_replace("www/pos","www/napay",Yii::app()->basePath . '/privatekeys/');
		$storageEngine = new \Bitpay\Storage\EncryptedFilesystemStorage('mnewhdo3yy4yFDASc156MdhshuUYTF5365');
		if (file_exists ($folder.$item->id_pos.'.pri')){
			$privateKey    = $storageEngine->load($folder.$item->id_pos.'.pri');
		}
		if (file_exists ($folder.$item->id_pos.'.pub')){
			$publicKey     = $storageEngine->load($folder.$item->id_pos.'.pub');
		}
		try {
			//Yii::app()->user->setState("agenzia_entrate", Yii::app()->params['agenziaentrate']);
			$client        = new \Bitpay\Client\Client();
			//$network        = new \Bitpay\Network\Testnet();
			$network        = new \Bitpay\Network\Livenet();
			$adapter       = new \Bitpay\Client\Adapter\CurlAdapter();
			$client->setNetwork($network);
			$client->setAdapter($adapter);
			$client->setPrivateKey($privateKey);
			$client->setPublicKey($publicKey);
			//
			$token = new \Bitpay\Token(crypt::Decrypt($item->token));
	   		$client->setToken($token);
			$invoice['Obj'] 	= $client->getInvoice($item->id_invoice_bps);
			//echo '<pre>invoice'.print_r($invoice,true).'</pre>';
			if ($invoice){
				$invoice['Id'] 		= $invoice['Obj']->getId();			//per recuperare l'invoice dall'archivio
				$invoice['Status'] 	= $invoice['Obj']->getStatus(); 	//per impostare il nuovo stato
				$invoice['Price']	= $invoice['Obj']->getPrice();	//per impostare quanto era il dovuto da pagare

				//A QUESTO PUNTO AGGIORNO LA TRANSAZIONE IN ARCHIVIO solo se gli status sono DIVERSI !!!!
				$transactions = Transactions::model()->findByPk($item->id_transaction);

				if ($transactions->status != $invoice['Status']){
					$transactions->status = $invoice['Status'];
					$transactions->update();
					//QUINDI INVIO UN MESSAGGIO DI NOTIFICA
				    //salva la notifica CoinGate
				    $notification = array(
					   'type_notification' => 'invoice',
					   //'id_merchant' => $transactions->id_merchant,
					   'id_user' => Merchants::model()->findByPk($transactions->id_merchant)->id_user,
					   'id_tocheck' => $transactions->id_transaction,
					   'status' => $invoice['Status'],
					   'description' => ' da '. $transactions->item_desc,
					   // 'url' => Yii::app()->createUrl("transactions/view")."&id=".crypt::Encrypt($transactions->id_transaction),
					   // La URL non deve comprendere l'hostname in quanto deve essere raggiungibile da più applicazioni
					   'url' => 'index.php?r=transactions/view&id='.crypt::Encrypt($transactions->id_transaction),
					   'timestamp' => time(),
					   'price' => $invoice['Price'],
					   'deleted' => 0,
				    );
				    // NaPay::save_notification($notification);
					$save = new Save;
					Push::Send($save->Notification($notification,true),'dashboard');
				}
			}
		} catch (Exception $e) {
			die(get_class($e) . ': ' . $e->getMessage());
		}

	}
	private function checkCoingateInvoice($item)
	{
		$token = crypt::Decrypt($item->token);
		//init CoinGate
		WebApp::CoingateInitialize($token);

		try {
			$invoice = \CoinGate\Merchant\Order::find($item->id_invoice_bps);
			#echo '<pre>'.print_r($invoice,true).'</pre>';
			#exit;
			if ($invoice){
				//A QUESTO PUNTO AGGIORNO LA TRANSAZIONE IN ARCHIVIO solo se gli status sono DIVERSI !!!!
				$transactions = Transactions::model()->findByPk($item->id_transaction);

				if ($transactions->status != $invoice->status){
					$transactions->status = $invoice->status;
					if (isset($invoice->pay_amount)){
				 		$transactions->btc_paid = $invoice->pay_amount;
						$transactions->btc_due = $invoice->pay_amount;
						$transactions->total_fee = $invoice->pay_amount - $invoice->receive_amount;
					}
		         	$transactions->btc_price = $invoice->receive_amount;
		          	if (isset($invoice->payment_address))
		          		$transactions->bitcoin_address = $invoice->payment_address;
					else
						$transactions->bitcoin_address = $invoice->payment_url;
					$transactions->update();
					//QUINDI INVIO UN MESSAGGIO DI NOTIFICA
				    //salva la notifica CoinGate
				    $notification = array(
					   'type_notification' => 'invoice',
					   //'id_merchant' => $transactions->id_merchant,
					   'id_user' => Merchants::model()->findByPk($transactions->id_merchant)->id_user,
					   'id_tocheck' => $transactions->id_transaction,
					   'status' => $invoice->status,
					   'description' => ' da '. $transactions->item_desc,
					   // 'url' => Yii::app()->createUrl("transactions/view")."&id=".crypt::Encrypt($transactions->id_transaction),
					   // La URL non deve comprendere l'hostname in quanto deve essere raggiungibile da più applicazioni
					   'url' => 'index.php?r=transactions/view&id='.crypt::Encrypt($transactions->id_transaction),
					   'timestamp' => time(),
					   'price' => $invoice->price_amount,
					   'deleted' => 0,
				    );
				    // NaPay::save_notification($notification);
					$save = new Save;
					Push::Send($save->Notification($notification,true),'dashboard');
				}
			}
		} catch (Exception $e) {
			die(get_class($e) . ': ' . $e->getMessage());
		}

	}


}
