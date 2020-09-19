<?php
require_once Yii::app()->params['libsPath'] . '/ethereum/web3/vendor/autoload.php';

use Web3\Web3;
use Web3\Contract;

class InvoicesController extends Controller
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
			// captcha action renders the CAPTCHA image displayed on the contact page
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xFFFFFF,
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
					'Btcpayserver',
					'Bitpay',
					'Coingate',
					'token'
				),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * CREA UNA invoice su PosInvoices con stato new che verrà mostrata
	 * per il successivo pagamento tramite qr-code
	 * @param _POST['amount'] the amount to be send
	 * @param _POST['wallet_address'] the wallet_address will receive
	 * @param _POST['id_pos'] the pos creating invoice
	 * @exec  il comando in background di ricerca di una transazione
	 * @return URL con la pagina per visualizzare il qrcode
	 */
	public function actionToken()
	{
		// TEST SCRIPT
		// $cmd = Yii::app()->basePath.DIRECTORY_SEPARATOR.'yiic receive --id='.crypt::Encrypt(658);
		// Utils::execInBackground($cmd);
		// //finalmente ritorno all'app e restituisco l'url con il qr-code della transazione da pagare!!!
		// $send_json = array(
		// 	'success'=> 1,
		// 	'url' => Yii::app()->createUrl('qrcode/index',array(
		// 		'id'=>crypt::Encrypt(658),
		// 		'id_pos'=>crypt::Encrypt(20), // serve per recuperare le info del POS, visto che ormai il wallet è scollegato dal merchant id
		// 	)),
		// );
		// echo CJSON::encode($send_json);
		// exit;

		if (true === isset($_POST['amount']) && trim($_POST['amount']) <> 0) {
 			$amount = (float)trim($_POST['amount']);
 		} else {
 			echo CJSON::encode(array("error"=>"Amount invalid!"));
 			exit;
 		}

		if (true === isset($_POST['wallet_address'])) {
 			$toAccount = trim($_POST['wallet_address']);
 		} else {
 			echo CJSON::encode(array("error"=>"Wallet address invalid!"));
 			exit;
 		}

		if (true === isset($_POST['id_pos']) && trim($_POST['id_pos']) <> 0) {
 			$idPos = trim($_POST['id_pos']);
 		} else {
 			echo CJSON::encode(array("error"=>"Id POS invalid!"));
 			exit;
 		}

		$pos=Pos::model()->findByPk($idPos);
		if($pos===null){
			echo CJSON::encode(array("error"=>"The requested ID Pos does not exist!"));
 			exit;
		}

		$wallets = Wallets::model()->findByAttributes(array('wallet_address'=>$toAccount));
		if($wallets===null){
			echo CJSON::encode(array("error"=>"The requested Wallet address does not exist!"));
 			exit;
		}

		//Carico i parametri
		// $settings=Settings::load();  //$settings=SettingsNapos::model()->findByPk(1); // la PK è 1 per i settings dell'applicazione Napos
		// if ($settings === null){//} || empty($settings->poa_port)){
		// 	echo CJSON::encode(array("error"=>'Errore: I parametri di configurazione Token non sono stati trovati'));
		// 	exit;
		// }

		// se la connessione al nodo poa è attiva proseguo
		//if( webRequest::url_test( $settings->poa_url ) ) {
			// $web3 = new Web3($settings->poa_url);
			// $web3 = new Web3(WebApp::getPoaNode());
		$poaNode = WebApp::getPoaNode();
		if (!$poaNode){
			$save = new Save;
			$save->WriteLog('pos','invoices','token',"All Nodes are down.");
			$send_json =  array(
				'success'=> 0,
				"error"=>'Error: All Nodes are down.',
			);
		}else{
			$web3 = new Web3($poaNode);	

			// blocco in cui presumibilmente avviene la transazione
			$block = null;
			$web3->eth->getBlockByNumber('latest',false, function ($err, $response) use (&$block){
				if ($err !== null) {
					//throw new CHttpException(404,'Errore: '.$err->getMessage());
					echo CJSON::encode(array("error"=>'404 Error: '.$err->getMessage()));
					exit;
				}
				$block = $response;
			});

			//salva la transazione ERC20
	 		$timestamp = time();
	 		$invoice_timestamp = $timestamp;

	 		//calcolo expiration time
			$storeSettings = Settings::loadStore($pos->id_store);

	 		$totalseconds = $storeSettings->invoice_expiration * 60; //invoice_expiration è in minuti, * 60 lo trasforma in secondi
	 		$expiration_timestamp = $timestamp + $totalseconds; //DEFAULT = 15 MINUTES

			// TODO: al momento il token è peggato 1/1 sull'euro
			$rate = eth::getFiatRate('token');
			$fromAccount = '';

			$attributes = array(
	 			'id_user' => Yii::app()->user->objUser['id_user'],
	 			'status'	=> 'new',
				'type'	=> 'token',
	 			'token_price'	=> $amount,
	 			'token_ricevuti'	=> 0,
	 			'fiat_price'		=> $rate * $amount,
	 			'currency'	=> 'EUR',
	 			'id_pos' => $idPos,
	 			'invoice_timestamp' => $invoice_timestamp,
	 			'expiration_timestamp' => $expiration_timestamp,
	 			'rate' => $rate,
	 			'from_address' => $fromAccount,
				'to_address' => $toAccount,
				'blocknumber' => hexdec($block->number), // numero del blocco in base 10
				'txhash'	=> '0x0',
	 		);

			// echo '<pre>'.print_r($attributes,true).'</pre>';
			// exit;

			//salvo la transazione in db. Restituisce object
			$save = new Save;
			$invoice = $save->PosInvoices($attributes);

			// echo '<pre>'.print_r($tokens,true).'</pre>';
			// exit;

			$memo = 'Pagamento a '.$pos->denomination;

			// salvo l'eventuale messaggio inserito
			if (!empty($memo)){
				$save = new Save;
				$message = $save->PosMemo([
					'id_token'=>$invoice->id_token,
					'memo'=>crypt::Encrypt($memo)
				]);
			}

	 		//salva la notifica
	 		$notification = array(
	 			'type_notification' => 'invoice',
	 			'id_user' => $invoice->id_user,
	 			'id_tocheck' => $invoice->id_token,
	 			'status' => 'new',
				'description' => 'You generated a new token invoice.',
				// 'url' => Yii::app()->createUrl("tokens/view",['id'=>crypt::Encrypt($invoice->id_token)]),
				'url' => 'index.php?r=tokens/view&id='.crypt::Encrypt($invoice->id_token),
	 			'timestamp' => $timestamp,
	 			'price' => $rate * $amount,
	 			'deleted' => 0,
	 		);
			$save = new Save;
			Push::Send($save->Notification($notification,true),'dashboard');

			//eseguo lo script che si occuperà in background di verificare lo stato dell'invoice appena creata...
			$cmd = Yii::app()->basePath.DIRECTORY_SEPARATOR.'yiic receive --id='.crypt::Encrypt($invoice->id_token);
			Utils::execInBackground($cmd);

			//finalmente ritorno all'app e restituisco l'url con il qr-code della transazione da pagare!!!
			$send_json = array(
				'success'=> 1,
				'url' => Yii::app()->createUrl('qrcode/index',array(
					'id'=>crypt::Encrypt($invoice->id_token),
					'id_pos'=>crypt::Encrypt($idPos), // serve per recuperare le info del POS, visto che ormai il wallet è scollegato dal merchant id
				)),
			);
		}
		// }else{
		// 	$send_json =  array(
		// 		'success'=> 0,
		// 		"error"=>'Errore: La connessione alla POA sembra non funzionare.',
		// 	);
		// }
     	echo CJSON::encode($send_json);
	}



	/**
	 * action BTCPAY SERVER Transaction
	 * @param _POST['amount'] the amount to be send
	 * @param _POST['id_pos'] the pos creating invoice
	 * @return URL con la pagina per visualizzare il qrcode
	 */
	public function actionBtcpayserver()
	{
		$save = new Save;

		if (true === isset($_POST['amount']) && trim($_POST['amount']) != 0) {
 			$amount = trim($_POST['amount']);
 		} else {
 			echo CJSON::encode(array("error"=>"Amount invalid!"));
 			exit;
 		}
			//echo $url;
		//exit;

		$pos = new Pos;
		$pos=Pos::model()->findByPk($_POST['id_pos']);
		if($pos===null){
			echo CJSON::encode(array("error"=>"The requested ID Pos does not exist!"));
 			exit;
		}
		$pairings = Pairings::model()->findByAttributes(array('id_pos'=>$pos->id_pos));

		// carico l'estensione
		require_once Yii::app()->params['libsPath'] . '/BTCPay/BTCPay.php';

		// Effettuo il login senza dati
		$BTCPay = new BTCPay(null,null);
		// imposto l'url che è associato a ciascun merchant
		$merchants = Merchants::model()->findByPk($pos->id_merchant);
		$BTCPay->setBTCPayUrl(Settings::loadUser($merchants->id_user)->blockchainAddress);

		// Carico l'URL del Server BTC direttamente dalla CLASSE
		$BPSUrl = $BTCPay->getBTCPayUrl();

		/**
		*	AUTOLOADER GATEWAYS
		*/
		$btcpayserver = Yii::app()->params['libsPath'] . '/gateways/btcpayserver/Btcpay/Autoloader.php';
		if (true === file_exists($btcpayserver) &&  true === is_readable($btcpayserver)){
				require_once $btcpayserver;
				\Btcpay\Autoloader::register();
		} else {
		    throw new Exception('BtcPay Server Library could not be loaded');
				exit;
		}

		/**
		 * To load up keys that you have previously saved, you need to use the same
		 * storage engine. You also need to tell it the location of the key you want
		 * to load.
		 */

		$folder = str_replace("htdocs\pos","htdocs\\napay",Yii::app()->basePath . '/privatekeys/');
		$folder = str_replace("var/www/pos","var/www/napay",$folder);


		//verifico esistenza coppia chiavi public/private
		if (!file_exists($folder.$pairings->sin.".pub") ){
	        $save->WriteLog('pos','invoice','Btcpayserver','The requested Public Key does not exist on this Server.',true);
		}

		//verifico esistenza coppia chiavi public/private
		if (!file_exists($folder.$pairings->sin.".pri") ){
			$save->WriteLog('pos','invoice','Btcpayserver','The requested Private Key does not exist on this Server.',true);
		}

		//CREO UNA NUOVA TRANSAZIONE SU DB PER PERMETTERNE IL RECUPERO PER LA STAMPA ricevuta
		$timestamp = time();
		$attributes = array(
			'id_pos'	=> $_POST['id_pos'],
			'id_merchant' => $pos->id_merchant,
			'status'	=> 'new', //è sicuramente new
			'btc_price'	=> 0,
			'price'		=> $_POST['amount'],
			'currency'	=> 'EUR',
			'item_desc' => '',
			'item_code' => '',
			'id_invoice_bps' => '',
			'invoice_timestamp' => $timestamp,
			'expiration_timestamp' => $timestamp,
			'current_tempo' => $timestamp,
			'btc_paid' => 0,
			'rate' => 0,
			'bitcoin_address' => '',
			'token' => '',
			'btc_due' => 0,
			'satoshis_perbyte' => 0,
			'total_fee' => 0,
		);
		$transaction = $save->Transaction($attributes,'new'); //viene restituito un oggetto non un array

		$storageEngine = new \Btcpay\Storage\EncryptedFilesystemStorage('mc156MdhshuUYTF5365');
		$privateKey    = $storageEngine->load($folder.$pairings->sin.'.pri');
		$publicKey     = $storageEngine->load($folder.$pairings->sin.'.pub');

		$client        = new \Btcpay\Client\Client();
		$adapter       = new \Btcpay\Client\Adapter\CurlAdapter();

		$client->setPrivateKey($privateKey);
		$client->setPublicKey($publicKey);
		$client->setUri($BPSUrl);
		$client->setAdapter($adapter);
		// ---------------------------

		/**
		 * The last object that must be injected is the token object.
		 */
		$token = new \Btcpay\Token();
		$token->setToken(crypt::Decrypt($pairings->token)); // UPDATE THIS VALUE
		$token->setFacade('merchant'); // BTCPAYSERVER :::SERGIO CASIZZONE

		/**
		 * Token object is injected into the client
		 */
		$client->setToken($token);

		/**
		 * This is where we will start to create an Invoice(for bitcpay and transaction for Napos) object, make sure to check
		 * the InvoiceInterface for methods that you can use.
		 */
		$invoice = new \Btcpay\Invoice();

		$buyer = new \Btcpay\Buyer();
		// $buyer
		//     ->setEmail($_POST['email']);

		// Add the buyers info to invoice
		$invoice->setBuyer($buyer);
		$invoice->setFullNotifications(true); //serve per l'ipn
		$invoice->setExtendedNotifications(true); //serve per l'ipn

		/**
		 * Item is used to keep track of a few things
		 */
		 //PRODUCT INFORMATIONS
		 //item code
		 //item description

		$item = new \Btcpay\Item();
		$item
		    ->setCode($_POST['id_pos'])
		    ->setDescription(substr($pairings->label,0,20))
		    ->setPrice($_POST['amount']);

		$invoice->setItem($item);

		/**
		 * BitPay supports multiple different currencies. Most shopping cart applications
		 * and applications in general have defined set of currencies that can be used.
		 * Setting this to one of the supported currencies will create an invoice using
		 * the exchange rate for that currency.
		 *
		 * @see https://test.bitpay.com/bitcoin-exchange-rates for supported currencies
		 */
		$invoice->setCurrency(new \Btcpay\Currency('EUR'));

		// Configure the rest of the invoice
		//come order ID salvo l'id della transazione in locale
		$redirectUrl = 'https://'.$_SERVER['HTTP_HOST'].Yii::app()->createUrl('keypad/index',array('id'=>crypt::Encrypt($transaction->id_transaction),'action'=>'btcpay'));
		#echo $redirectUrl;

		// per distinguere test da produzione...
		if (gethostname() == 'blockchain1'){
		  $URLIpn = 'https://napay.napoliblockchain.tk'.Yii::app()->createUrl('ipn/btcpayserver');
		}elseif (gethostname()=='CGF6135T' || gethostname()=='NUNZIA'){ // SERVE PER LE PROVE IN UFFICIO
		  $URLIpn = 'https://'.$_SERVER['HTTP_HOST'].Yii::app()->createUrl('ipn/btcpayserver');
		}else{
		  $URLIpn = 'https://napay.napoliblockchain.it'.Yii::app()->createUrl('ipn/btcpayserver');
		}
		// echo "<pre>".print_r($URLIpn,true)."</pre>";
		// exit;
		$invoice
		    ->setOrderId(crypt::Encrypt($transaction->id_transaction))
			//->setNotificationEmail('')
		    // You will receive IPN's at this URL, should be HTTPS for security purposes!
		    ->setNotificationUrl($URLIpn)
			//->setRedirectUrl($_POST['redirectUrl'])
			->setRedirectUrl($redirectUrl);

		/**
		 * Updates invoice with new information such as the invoice id and the URL where
		 * a customer can view the invoice.
		 */

		// echo "<pre>".print_r($invoice,true)."</pre>";
		// exit;


		try {
		    $client->createInvoice($invoice);
		} catch (\Exception $e) {
			$body = $this->getJsonBody($client->getResponse());
			echo CJSON::encode($body);
		    exit(1); // We do not want to continue if something went wrong
		}

		$body = $this->getJsonBody($client->getResponse());
		$result = $body['data'];

		// aggiorno la transactions_info
		$cryptoInfoRestApi = null;
		$save = new Save;
		$cryptoInfo = $save->CryptoInfo($transaction->id_transaction, $invoice->getCryptoInfo());
		// echo '<pre>'.print_r($cryptoInfo,true).'</pre>';
		// exit;
		if (!empty($cryptoInfo)){
			foreach ($cryptoInfo as $index => $restItem){
				$cryptoInfoRestApi[$index]['cryptoCode'] = $restItem->cryptoCode;
				$cryptoInfoRestApi[$index]['paymentType'] = $restItem->paymentType;
				$cryptoInfoRestApi[$index]['rate'] = $restItem->rate;
				$cryptoInfoRestApi[$index]['paid'] = $restItem->paid;
				$cryptoInfoRestApi[$index]['price'] = $restItem->price;
				$cryptoInfoRestApi[$index]['due'] = $restItem->due;
				$cryptoInfoRestApi[$index]['txId'] = $restItem->txId;
				$cryptoInfoRestApi[$index]['receivedDate'] = $restItem->received;
				$cryptoInfoRestApi[$index]['value'] = $restItem->value;
				$cryptoInfoRestApi[$index]['address'] = $restItem->destination;
			}
		}

		$return = (object) [
			'url' => $result['url'],
			'status' => $result['status'],
			'btcPrice' => $result['btcPrice'],
			'rate' => $result['rate'],
			'id' => $result['id'],
			'invoiceTime' => $result['invoiceTime'],
			'expirationTime' => $result['expirationTime'],
			'cryptoInfo' => $cryptoInfoRestApi,
		];


		//AGGIORNA ADESSO la transazione su DB CON I DATI RICEVUTI DA _BTCSERVER_
		$attributes = array(
			'id_transaction' => $transaction->id_transaction,
			//'id_pos'	=> $_POST['id_pos'],
			//'id_merchant' => $_POST['id_merchant'],
			'status'	=> $invoice->getStatus(),
			'btc_price'	=> $invoice->getBtcPrice(),
			//'price'		=> $_POST['amount'],
			//'currency'	=> 'EUR',
			'item_desc' => $invoice->getItemDesc(),
			'item_code' => $invoice->getItemCode(),
			'id_invoice_bps' => $invoice->getId(),
			'invoice_timestamp' => substr($invoice->getInvoiceTime(),0,-3),
			'expiration_timestamp' => substr($invoice->getExpirationTime(),0,-3),
			'current_tempo' => substr($invoice->getCurrentTime(),0,-3),
			'btc_paid' => $invoice->getBtcPaid(),
			'rate' => $invoice->getRate(),
			'bitcoin_address' => $result['bitcoinAddress'],
			'token' => (string) $invoice->getToken(),
			'btc_due' => $result['btcDue'],
			'satoshis_perbyte' => $result['minerFees']['BTC']['satoshisPerByte'],
			'total_fee' => $result['minerFees']['BTC']['totalFee'],
		);

		$transaction = $save->Transaction($attributes,'update'); //viene restituito un oggetto non un array

		$notification = array(
			'type_notification' => 'invoice',
			'id_user' => Merchants::model()->findByPk($transaction->id_merchant)->id_user,
			'id_tocheck' => $transaction->id_transaction,
			'status' => 'new',
			'description' => 'You generated a new invoice.',
			// La URL non deve comprendere l'hostname in quanto deve essere raggiungibile da più applicazioni
			'url' => 'index.php?r=transactions/view&id='.crypt::Encrypt($transaction->id_transaction),
			'timestamp' => $timestamp,
			'price' => $_POST['amount'],
			'deleted' => 0,
		);

		Push::Send($save->Notification($notification,true),'dashboard');

		//finalmente ritorno all'app e restituisco l'url con il qr-code della transazione da pagare!!!
		$send_json = array(
			'url' => $invoice->getUrl(),
		);
    	echo CJSON::encode($send_json);
	}

	//recupera lo streaming json dal contenuto txt del body
	private function getJsonBody($response)
	{
		$start = strpos($response,'{',0);
        $substr = substr($response,$start);
        return json_decode($substr, true);
	}

	/**
	 * action BITPAY Transaction
	 */
	// public function actionBitpay()
	// {
	// 	if (true === isset($_POST['amount']) && trim($_POST['amount']) != 0) {
 	// 		$amount = trim($_POST['amount']);
 	// 	} else {
 	// 		echo CJSON::encode(array("error"=>"Amount invalid!"));
 	// 		exit;
 	// 	}
	//
	// 	$pos = new Pos;
	// 	$pos=Pos::model()->findByPk($_POST['id_pos']);
	// 	if($pos===null){
	// 		echo CJSON::encode(array("error"=>"The requested ID Pos does not exist!"));
 	// 		exit;
	// 	}
	// 	$pairings = Pairings::model()->findByAttributes(array('id_pos'=>$pos->id_pos));
	//
	// 	/**
	// 	*	AUTOLOADER GATEWAYS
	// 	*/
	// 	$bitpay = Yii::app()->basePath . '/extensions/gateways/bitpay/Bitpay/Autoloader.php';
	// 	if (true === file_exists($bitpay) &&
	// 	    true === is_readable($bitpay))
	// 	{
	// 	    require_once $bitpay;
	// 	    \Bitpay\Autoloader::register();
	// 	} else {
	// 	    throw new Exception('Bitpay Server Library could not be loaded');
	// 			exit;
	// 	}
	// 	//Yii::app()->user->setState("agenzia_entrate", Yii::app()->params['agenziaentrate']);
	//
	// 	/**
	// 	 * To load up keys that you have previously saved, you need to use the same
	// 	 * storage engine. You also need to tell it the location of the key you want
	// 	 * to load.
	// 	 */
	// 	//$folder = Yii::app()->basePath . '/privatekeys/bitpay-';
	// 	//$folder = $_SERVER["DOCUMENT_ROOT"].'/../npay/protected/privatekeys/bitpay-';
	//
	// 	/** NON AGISCO PIù SU PATH WWW, MA SULLE CARTELLE
	// 	  * Visto che paolo ha modificato il nome delle cartelle da npay a napay su .tk come faccio ad accedervi?
	// 	*/
	// 	if (gethostname()=='blockchain1'){
	// 		$folder = $_SERVER["DOCUMENT_ROOT"].'/../napay/protected/privatekeys/bitpay-';
	// 	}elseif (gethostname()=='CGF6135T'){ // SERVE PER LE PROVE IN UFFICIO
    //         $folder = $_SERVER["DOCUMENT_ROOT"].'npay/protected/privatekeys/bitpay-';
	// 	}else{
	// 		$folder = $_SERVER["DOCUMENT_ROOT"].'/../npay/protected/privatekeys/bitpay-';
	// 	}
	//
	//
	// 	$storageEngine = new \Bitpay\Storage\EncryptedFilesystemStorage('mnewhdo3yy4yFDASc156MdhshuUYTF5365');
	// 	$privateKey    = $storageEngine->load($folder.$_POST['id_pos'].'.pri');
	// 	$publicKey     = $storageEngine->load($folder.$_POST['id_pos'].'.pub');
	//
	// 	// Get a BitPay Client to prepare for invoice creation
	// 	$client        = new \Bitpay\Client\Client();
	// 	//$network        = new \Bitpay\Network\Testnet();
	// 	$network        = new \Bitpay\Network\Livenet();
	// 	$adapter       = new \Bitpay\Client\Adapter\CurlAdapter();
	//
	// 	$client->setPrivateKey($privateKey);
	// 	$client->setPublicKey($publicKey);
	// 	$client->setNetwork($network);
	// 	$client->setAdapter($adapter);
	// 	/**
	// 	 * The last object that must be injected is the token object.
	// 	 */
	// 	$token = new \Bitpay\Token();
	// 	$token
	// 		->setToken(Utility::decryptURL($pairings->token));
	// 	/**
	// 	 * Token object is injected into the client
	// 	 */
	// 	$client->setToken($token);
	// 	/**
	// 	 * This is where we will start to create an Invoice(for bitcpay and transaction for Napos) object, make sure to check
	// 	 * the InvoiceInterface for methods that you can use.
	// 	 */
	// 	$invoice = new \Bitpay\Invoice();
	//
	// 	//woocomerce
	// 	//$invoice->setOrderId((string)$order_number);
	// 	//$invoice->setCurrency('EUR');
	// 	$invoice->setFullNotifications(true);
	// 	$invoice->setExtendedNotifications(true);
	//
	//
	// 	// Add the buyers info to invoice
	// 	$buyer = new \Bitpay\Buyer();
	// 	$buyer->setEmail($_POST['email']);
	// 	$invoice->setBuyer($buyer);
	//
	// 	/**
	// 	 * Item is used to keep track of a few things
	// 	 */
	// 	 //PRODUCT INFORMATIONS
	// 	 //item code
	// 	 //item description
	//
	// 	$item = new \Bitpay\Item();
	// 	$item
	// 	    ->setCode($_POST['id_pos'])
	// 	    ->setDescription(substr($pairings->label,0,20))
	// 	    ->setPrice($_POST['amount']);
	//
	// 	$invoice->setItem($item);
	//
	// 	/**
	// 	 * BitPay supports multiple different currencies. Most shopping cart applications
	// 	 * and applications in general have defined set of currencies that can be used.
	// 	 * Setting this to one of the supported currencies will create an invoice using
	// 	 * the exchange rate for that currency.
	// 	 *
	// 	 * @see https://test.bitpay.com/bitcoin-exchange-rates for supported currencies
	// 	 */
	// 	$invoice->setCurrency(new \Bitpay\Currency('EUR'));
	//
	// 	// Configure the rest of the invoice
	// 	//non c'è nessun order id in quanto non viene generato prima un carrello ed un ordine, ma passa subito
	// 	//all'acquisto!
	// 	$invoice->setOrderId($pos->denomination);
	// 	// You will receive IPN's at this URL, should be HTTPS for security purposes!
	// 	$invoice->setRedirectUrl($_POST['redirectUrl']);
	// 	$invoice->setNotificationUrl($_POST['ipnUrl']);
	// 	//$invoice->setTransactionSpeed($this->transaction_speed);
	//
	// 	/**
	// 	 * Updates invoice with new information such as the invoice id and the URL where
	// 	 * a customer can view the invoice.
	// 	 */
	// 	try {
	// 	    $client->createInvoice($invoice);
	// 	} catch (\Exception $e) {
	// 	    $request  = $client->getRequest();
	// 	    $response = $client->getResponse();
	// 	    	//echo (string) $request.PHP_EOL.PHP_EOL.PHP_EOL;
	// 	    	//echo (string) $response.PHP_EOL.PHP_EOL;
	// 		//$send_json = array ('error'=>(string) $response);
	// 		// echo "<pre>".print_r($send_json,true)."</pre>";
	// 		// exit;
	//
	// 		$string = (string) $response;
	//         $start = strpos($string,'{',0);
	//         $substr = substr($string,$start);
	// 		$send_json = array ('error'=>$substr);
	// 		echo CJSON::encode($send_json);
	// 		exit;
	// 	   // exit(1); // We do not want to continue if something went wrong
	// 	}
	// 	//TRASFORMA OBJECT $client IN ARRAY
	// 	$response = $client->getResponse();
    //     $start = strpos($response,'{',0);
    //     $substr = substr($response,$start);
    //     $body = json_decode($substr, true);
	// 	$array = $body['data'];
	// 	//
	// 	#echo "<pre>".print_r($array,true)."</pre>";
	// 	#exit;
	//
	// 	$btc_due = $array['paymentTotals']['BTC']/100000000;
	// 	$satoshis_perbyte = $array['minerFees']['BTC']['satoshisPerByte'];
	// 	$total_fee = $array['minerFees']['BTC']['totalFee']/100000000;
	// 	$bitcoin_address = $array['url']; //$array['bitcoinAddress'];
	//
	// 	$invoice_timestamp = substr($array['invoiceTime'],0,-3);
	// 	$expiration_timestamp = substr($array['expirationTime'],0,-3);
	// 	$current_tempo = substr($array['currentTime'],0,-3);
	//
	// 	#echo "<pre>".print_r($array,true)."</pre>";
	// 	#exit;
	//
	// 	//salva la transazione BitPay
	// 	$timestamp = time();
	// 	$attributes = array(
	// 		'id_pos'	=> $_POST['id_pos'],
	// 		'id_merchant' => $_POST['id_merchant'],
	// 		'status'	=> $invoice->getStatus(),
	// 		'btc_price'	=> $array['paymentSubtotals']['BTC']/100000000,
	// 		'price'		=> $_POST['amount'],
	// 		'currency'	=> 'EUR',
	// 		'item_desc' => $invoice->getItemDesc(),
	// 		'item_code' => $array['orderId'],
	// 		'id_invoice_bps' => $array['id'],
	// 		'invoice_timestamp' => $invoice_timestamp,
	// 		'expiration_timestamp' => $expiration_timestamp,
	// 		'current_tempo' => $current_tempo,
	// 		'btc_paid' => $array['amountPaid'],
	// 		'rate' => $array['exchangeRates']['BTC']['EUR'],
	// 		'bitcoin_address' => $bitcoin_address,
	// 		'token' => (string) $invoice->getToken(),
	// 		'btc_due' => $btc_due,
	// 		'satoshis_perbyte' => $satoshis_perbyte,
	// 		'total_fee' => $total_fee,
	// 	);
	// 	$transaction = $this->save_transaction($attributes);
	// 	//salva la notifica BitPay
	// 	$notification = array(
	// 		'type_notification' => 'new',
	// 		'id_merchant' => $transaction['id_merchant'],
	// 		'id_tocheck' => $transaction['id_transaction'],
	// 		'status' => $transaction['status'],
	// 		'description' => ' da '. $pairings->label,
	// 		'url' => "index.php?r=transactions/view&id=".Utility::encryptURL($transaction['id_transaction']),
	// 		'timestamp' => $timestamp,
	// 		'price' => $_POST['amount'],
	// 		'deleted' => 0,
	// 	);
	// 	NaPay::save_notification($notification);
	//
	// 	//finalmente ritorno all'app e restituisco l'url con il qr-code della transazione da pagare!!!
	// 	$send_json = array(
	// 		'url' => $invoice->getUrl(),
	// 	);
    // 	echo CJSON::encode($send_json);
	// }

	/**
	 * action BITPAY Transaction
	 */
	// public function actionCoingateInvoice()
	// {
	// 	if (true === isset($_POST['amount']) && trim($_POST['amount']) != 0) {
 	// 		$amount = trim($_POST['amount']);
 	// 	} else {
 	// 		echo CJSON::encode(array("error"=>"Amount invalid!"));
 	// 		exit;
 	// 	}
	//
	// 	//$pos = new Pos;
	// 	$pos=Pos::model()->findByPk($_POST['id_pos']);
	// 	if($pos===null){
	// 		echo CJSON::encode(array("error"=>"The requested ID Pos does not exist!"));
 	// 		exit;
	// 	}
	// 	//$merchants = new Merchants;
	// 	$merchants=Merchants::model()->findByPk($pos->id_merchant);
	// 	if($merchants===null){
	// 		echo CJSON::encode(array("error"=>"The requested Merchant does not exist!"));
 	// 		exit;
	// 	}
	// 	$pairings = Pairings::model()->findByAttributes(array('id_pos'=>$pos->id_pos));
	// 	if($pairings===null){
	// 		echo CJSON::encode(array("error"=>"The requested Token does not exist!"));
 	// 		exit;
	// 	}
	//
	// 	//init CoinGate
	// 	NaPay::init_coingate(Utility::decryptURL($pairings->token));
	//
	// 	// init array order
	// 	$array = array(
	// 		'order_id'          => $pos->id_pos,
	// 		'price_amount'      => number_format($_POST['amount'], 8, '.', ''),
	// 		'price_currency'    => 'EUR',//get_woocommerce_currency(),
	// 		'receive_currency'  => 'BTC', //$this->receive_currency,
	// 		'cancel_url'        => $_POST['redirectUrl'],//$order->get_cancel_order_url(),
	// 		'callback_url'      => $_POST['ipnUrl'],
	// 		'success_url'       => $_POST['redirectUrl'],
	// 		'title'             => ' ' . $merchants->denomination,
	// 		'description'       => ' ' . $pos->denomination,
	// 		'token'             => Utility::decryptURL($pairings->token)
	// 	);
	// 	$invoice = \CoinGate\Merchant\Order::create($array);
	// 	if ($invoice && $invoice->payment_url) {
	// 		$send_json = array(
	// 			'url' => $invoice->payment_url,
	// 		);
	// 	} else {
	// 		echo CJSON::encode(array("error"=>"Failed!"));
 	// 		exit;
	// 	}
	//
	// 	#echo "<pre>".print_r($invoice,true)."</pre>";
	// 	#exit;
	// 	//salva la transazione CoinGate
	// 	$timestamp = time();
	// 	$attributes = array(
	// 		'id_pos'	=> $_POST['id_pos'],
	// 		'id_merchant' => $_POST['id_merchant'],
	// 		'status'	=> $invoice->status,
	// 		'btc_price'	=> 0,
	// 		'price'		=> $_POST['amount'],
	// 		'currency'	=> $invoice->receive_currency,
	// 		'item_desc' => $pos->denomination,
	// 		'item_code' => $_POST['id_pos'],
	// 		'id_invoice_bps' => $invoice->id,
	// 		'invoice_timestamp' => $timestamp,
	// 		'expiration_timestamp' => $timestamp + (60 * 15), //15 minuti di scadenza
	// 		'current_tempo' => $timestamp,
	// 		'btc_paid' => 0,
	// 		'rate' => $_POST['rate'],
	// 		'bitcoin_address' => $invoice->payment_url,
	// 		'token' => Utility::encryptURL($invoice->token),
	// 		'btc_due' => 0,
	// 		'satoshis_perbyte' => 0,
	// 		'total_fee' => 0
	// 	);
	// 	$transaction = $this->save_transaction($attributes);
	// 	#echo '<pre>'.print_r($transaction,true).'</pre>';
	// 	#exit;
	// 	//salva la notifica CoinGate
	// 	$notification = array(
	// 		'type_notification' => 'new',
	// 		'id_merchant' => $transaction['id_merchant'],
	// 		'id_tocheck' => $transaction['id_transaction'],
	// 		'status' => $transaction['status'],
	// 		'description' => ' da '. $pairings->label,
	// 		'url' => "index.php?r=transactions/view&id=".Utility::encryptURL($transaction['id_transaction']),
	// 		'timestamp' => $timestamp,
	// 		'price' => $_POST['amount'],
	// 		'deleted' => 0,
	// 	);
	// 	NaPay::save_notification($notification);
	//
	// 	//finalmente ritorno all'app e restituisco l'url con il qr-code della transazione da pagare!!!
    // 	echo CJSON::encode($send_json);
	// }

}
