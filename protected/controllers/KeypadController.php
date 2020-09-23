<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.Logo');
Yii::import('libs.NaPacks.SaveModels');
Yii::import('libs.NaPacks.Save');
Yii::import('libs.NaPacks.WebApp');

Yii::import('libs.bitstamp-real-time-price.BitstampRTP');

class KeypadController extends Controller
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
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	// public $layout='//layouts/column1';
	//column_login

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
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array(
					'index',
					'printInvoice',
				),
				'users'=>array('@'),
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
	 * @return Pos the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=Pos::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}


	/**
	 * show desktop
	 */
	public function actionIndex()
	{
		$id = null;
		$action = null;
		#echo "<pre>".print_r($_GET,true)."</pre>";
		#exit;
		#se l'invoice esiste può anche essere scaduta, quindi controllare se è stata pagata!!!
		$invoiceIsPaid = false;
		if (isset($_GET['id']) && isset($_GET['action'])){
			$id = $_GET['id'];
			$action = $_GET['action'];
			if ($action == 'btcpay')
	            $model = 'Transactions';
	        else
	            $model = 'PosInvoices';

	        $invoice = $model::model()->findByPk(crypt::Decrypt($id));
			if ($invoice->status == 'complete' || $invoice->status == 'paid')
				$invoiceIsPaid = true;
		}
		//$invoiceIsPaid = true; //TEST
		#exit;

		// verifico se è in scadenza
		$warningmessage = null;



	
		if (isset($_GET['tag'])){
			 $shop = Shops::model()->findByPk(crypt::Decrypt($_GET['tag']));
				$this->render('keypad-desktop',array(
					'invoiceIsPaid'=>$invoiceIsPaid,
					'invoiceId' => $id,
					'invoiceAction'=> $action,
					'warningmessage'=>$warningmessage, // messaggio di scadenza
					'bps_shopid' => $shop->bps_shopid,
				));

		}else{

		 	// $this->layout='//layouts/column1';
		 	$this->render('keypad-mobile',array(
				'invoiceIsPaid'=>$invoiceIsPaid,
				'invoiceId' => $id,
				'invoiceAction'=> $action,
				'warningmessage'=>$warningmessage, // messaggio di scadenza
			));
		}
	}
	/**
	 * Genero il div con l'avviso di creare un wallet Token
	 */
	public function writeMessage($what,$days=null){
		$http_host = $_SERVER['HTTP_HOST'];

		if ($what == 'deadline'){
			return '
			<div class="col m-t-25">
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
					<b>ATTENZIONE!</b>
					<br>Restano alla scadenza '.abs($days).' giorni, dopodiché non potrai più usare l\'applicazione.
				</div>
			</div>
			';
		}

	}

	public function actionPrintInvoice($id,$action,$type=null){
		// carico l'estensione
		require_once Yii::app()->basePath . '/extensions/MYPDF.php';

		if ($action == 'btcpay')
            $model = 'Transactions';
        else
            $model = 'PosInvoices';


        $invoice = $model::model()->findByPk(crypt::Decrypt($id));
        $pos = Pos::model()->findByPk(Yii::app()->user->objUser['id_pos']);
        $store = Stores::model()->findByPk($pos->id_store);

        #echo '<pre>'.print_r($invoice->attributes,true).'</pre>';
        #exit;

        if ($action == 'btcpay'){
            $model_id = $invoice->id_transaction;
            $model_price = $invoice->btc_price;
            $model_fiat = $invoice->price;
            $coin = 'Bitcoin';
            $coinCod = 'BTC';
        } else{
            $model_id = $invoice->id_token;
            $model_price = $invoice->token_price;
            $model_fiat = $invoice->fiat_price;
            $coin = Yii::app()->params['TokenName'];
            $coinCod = Yii::app()->params['TokenCod'];
        }

		// create new PDF document
		$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A7', true, 'UTF-8', false);

		// set document information
		$pdf->SetCreator(Yii::app()->params['adminName']);
		$pdf->SetAuthor(Yii::app()->params['shortName']);
		$pdf->SetTitle('Scontrino n. '.Crypt::Decrypt($id));
		$pdf->SetSubject('Scontrino');
		//$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

		// set default header data
		//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
		//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, myPDF_HEADER_STRING);

		// set header and footer fonts
		//$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		//$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		// set margins
		$pdf->SetMargins(2, 2, 2, 0);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

		// set some language-dependent strings (optional)
		if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
			require_once(dirname(__FILE__).'/lang/eng.php');
			$pdf->setLanguageArray($l);
		}
		// ---------------------------------------------------------
		// set font
		$pdf->SetFont('times', '', 10);
		// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

		// // add a page
		$pdf->AddPage();
        $html = '
		<style>
			.head {
				font-size:16px;
			}
			.body{
				font-size:14px;
			}
			.footer{
				font-size:11px;
			}
		</style>

		<table class="head" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr><th colspan="2" align="center">'.$store->denomination.'</th></tr>
            <tr><th colspan="2" align="center">'.$store->address.'</th></tr>
            <tr><th colspan="2" align="center">'.$store->cap .' - '.$store->city.'</th></tr>
            <tr><th colspan="2" align="center">'.'P.IVA '. $store->vat.'</th></tr>
		</table>
		<table class="body" border="0" cellpadding="0" cellspacing="0" width="100%">
			<tr><th colspan="2" align="center"><small>***************</small></th></tr>
            <tr>
                <td colspan="2" align="left">Transazione: '.crypt::Encrypt($model_id).'</td>
            </tr>

			<tr><th colspan="2" align="center"><small></small></th></tr>
            <tr>
                <td align="right">Importo:</td>
                <td align="right"><strong>'.$model_price.'&nbsp;('.$coinCod.')</strong></td>
            </tr>
            <tr>
                <td align="right">Tasso:</td>
                <td align="right"><strong>'.$invoice->rate.'</strong>&nbsp;€</td>
            </tr>
            <tr>
                <td align="right">Valuta:</td>
                <td align="right"><strong>'.$model_fiat.'</strong>&nbsp;€</td>
            </tr>
            <tr><th colspan="2" align="center"><small>***************</small></th></tr>
		</table>
		<table class="footer" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr><th colspan="2" align="left">'.date("d-m-Y H:i",$invoice->invoice_timestamp).'</th></tr>
            <tr><th colspan="2" align="left">SCONTRINO NON FISCALE</th></tr>
            <tr><th colspan="2" align="left"><small>AI SENSI DELL\'ART. 1 COMMA 429 Legge 311/2004</small></th></tr>
            <!-- <tr><th colspan="2" align="center">&nbsp;</th></tr>
            <tr><th colspan="2" align="center">.</th></tr>-->
        </table>
        ';

        // // output the HTML content
		$pdf->writeHTML($html, true, false, true, false, '');
		 // reset pointer to the last page
		$pdf->lastPage();
		//Close and output PDF document
		ob_end_clean();
         $pdf_file_link    = 'invoice-'.$model_id.'.pdf';
         $pdf_file_name    =  Yii::app()->basePath . '/../uploads/' . $pdf_file_link;
		#$pdf_file_link    = 'invoice-'.Utility::decryptURL($invoiceId).'.pdf';
		#$pdf_file_name    =  'https://'.$_SERVER['HTTP_HOST'].Yii::app()->baseUrl . '/uploads/' . $pdf_file_link;

		// echo $pdf_file_link;
		// exit;

		if (isset($type)){
			//$pdf->Output($pdf_file_name, 'F');
			//NON E' PRONTO ANCORA
				//NaPay::WebClientPrint($pdf_file_name);
				//$this->redirect(array('webpos/keypad','id'=>$id, 'action'=>$action,'type'=>$type));

			$pdf->Output($pdf_file_name, 'I');
		}else{
			$pdf->Output($pdf_file_name, 'F');
			echo CJSON::encode(array("invoice"=>$pdf_file_name));
		}

    }

}
