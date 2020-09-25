<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.WebApp');
Yii::import('libs.NaPacks.SaveModels');
Yii::import('libs.NaPacks.Save');
Yii::import('libs.NaPacks.Push');
Yii::import('libs.NaPacks.Notifi');
Yii::import('libs.ethereum.eth');
Yii::import('libs.Utils.Utils');
Yii::import('libs.webRequest.webRequest');

class ReceiveCommand extends CConsoleCommand
{
	public $logfilehandle = null;

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete', // we only allow deletion via POST request
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
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('index'),
				'users'=>array('*'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}



	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return PosInvoices the loaded model
	 * @throws CHttpException
	 */
	public function loadInvoice($id)
	{
		$model=PosInvoices::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');

		return $model;
	}



	// Imposta il LOG file
	private function setLogFile($file){
		$this->logfilehandle = $file;
	}
	// Legge il log file
	private function getLogFile(){
		return $this->logfilehandle;
	}

	//scrive a video e nel file log le informazioni richieste
	private function log($text){
		$save = new Save;
		$save->WriteLog('pos','commands','receive', $text);
		echo "\r\n" .date('Y/m/d h:i:s a - ', time()) .$text;
	}

	public function actionIndex($id){
		set_time_limit(0); //imposto il time limit unlimited
		$seconds = 1;
		$events = true;

		$this->log("Start Check invoice #: $id");

		//carico l'invoice
		$invoice = $this->loadInvoice(crypt::Decrypt($id));
		$this->log("Invoice $id loaded. Status is $invoice->status");

		$expiring_seconds = $invoice->expiration_timestamp +1 - time();
		// $expiring_seconds = $invoice->expiration_timestamp ; //TEST
		$transactionValue = $invoice->token_ricevuti;

		while(true){
			$ipnflag = false;
			if ($invoice->status == 'new' || $invoice->status == 'expired'){ //se il valore è new proseguo
				// cerco le transazioni su bolt_tokens in pending
				$criteria=new CDbCriteria;

				$criteria->compare('type','token');
				//$criteria->compare('status','new',true);
				$criteria->compare('item_desc','wallet');
				$criteria->compare('to_address',$invoice->to_address);
				$criteria->addCondition("invoice_timestamp > " .$invoice->invoice_timestamp);
				$criteria->addCondition("blocknumber > " .$invoice->blocknumber);
				$criteria->compare('token_price',$invoice->token_price,true); // false -> valore identico

				$transactions = Tokens::model()->findAll($criteria);
				//echo '<pre>'.print_r($transactions,true).'</pre>';
				//exit;

				if (!empty($transactions))
				{
					$this->log("Transazioni trovate.");
					foreach ($transactions as $transaction)
					{
						$this->log("Transazione n. $transaction->id_token");
						if ($transaction->status <> 'new'){
							$this->log("Transazione n. $transaction->id_token COMPLETATA.");
							$this->log('<pre>'.print_r($transaction->attributes,true).'</pre>');
							$invoice->status = $transaction->status;
							$invoice->token_ricevuti = $transaction->token_price;
							$invoice->from_address = $transaction->from_address;
							$invoice->blocknumber = $transaction->blocknumber;
							$invoice->txhash = $transaction->txhash;

							// carico il memo di tokens e lo aggiorno con quello del pos
							$memoToken = TokensMemo::model()->findByAttributes(['id_token'=>$transaction->id_token]);
							if (null === $memoToken)
								$memoToken = new TokensMemo;

							$this->log("TokensMemo: ($transaction->id_token)".'<pre>'.print_r(crypt::Decrypt($memoToken->memo),true).'</pre>');
							$invoiceMemo = PosInvoicesMemo::model()->findByAttributes(['id_token'=>$invoice->id_token]);
							if (null === $invoiceMemo)
								$invoiceMemo = new PosInvoicesMemo;
							$this->log("PosInvoicesMemo: ($invoice->id_token)".'<pre>'.print_r(crypt::Decrypt($invoiceMemo->memo),true).'</pre>');
							$memoToken->memo = $invoiceMemo->memo;
							$memoToken->id_token = $transaction->id_token;
							$memoToken->save();

							$ipnflag = true;
							break; //foreach
						}else{
							$this->log("Transazione n. $transaction->id_token ancora in new...");
						}
					}//foreach loop
				}
				if ($ipnflag === false && $expiring_seconds < 0){//invoice expired
					$invoice->status = 'expired';
					$ipnflag = true;
				}
			}
			if ($expiring_seconds < 0){//invoice expired
				$ipnflag = true;
			}
			if ($ipnflag){ //send ipn in case flag is true: può venire
				#fwrite($this->getLogFile(), date('Y/m/d h:i:s a', time()) . " : <pre>".print_r($tokens->attributes,true)."</pre>\n");
				//echo '<pre>'.print_r($invoice->attributes,true).'</pre>';
				if ($invoice->save()){
					$this->log("Invoice n. $invoice->id_token SALVATA.");
					$this->log('<pre>'.print_r($invoice->attributes,true).'</pre>');
					//$this->log("End : invoice #: $id, Status: $invoice->status, Received: $invoice->token_ricevuti");
					$this->sendIpn($invoice->attributes);
				}else{
					$this->log("Error : Cannot save invoice #. $id, Status: $invoice->status.");
				}

				break;
			}

			//conto alla rovescia fino alla scadenza dell'invoice
			$this->log("Invoice: $id, Status: ".$invoice->status.", Seconds: ".$expiring_seconds."\n");
			//echo chr(32).$expiring_seconds;
			// $this->log("remaining seconds... $expiring_seconds");
			$expiring_seconds --;
			sleep(1);
		}
	}

	private function sendIpn($ipn){
		$tokens = (object) $ipn;

		if (true === empty($tokens)) {
			$this->log("Error. Could not decode the JSON payload from Token Server.");
			throw new \Exception('Could not decode the JSON payload from Token Server.');
		}else{
			#fwrite($myfile, $date . " : 2. Json ok.\n");
		}

		//QUINDI INVIO UN MESSAGGIO DI NOTIFICA
		$notification = array(
			'type_notification' => 'invoice',
			'id_user' => $tokens->id_user,
			'id_tocheck' => $tokens->id_token,
			'status' => $tokens->status,
			// 'description' => ' da '. $tokens->item_desc,
			'description' => Notifi::description($tokens->status,'token'),
			'url' => "index.php?r=tokens/view&id=".crypt::Encrypt($tokens->id_token),
			'timestamp' => time(),
			'price' => $tokens->token_price,
			'deleted' => 0,
		);
		$save = new Save;
		Push::Send($save->Notification($notification,true),'dashboard');

		//ADESSO POSSO USCIRE CON UN MESSAGGIO POSITIVO ;^)
		$this->log("IPN received for Invoice #: ".crypt::Encrypt($tokens->id_token).", Status=" .$tokens->status.", Price=". $tokens->token_price.", Received: ".$tokens->token_ricevuti);
	}
}
?>
