<?php
/* il returnID serve per fare il check dell'invoice se è stato o meno pagata*/
$redirectUrl = Yii::app()->createUrl('keypad/index',array('id'=>crypt::Encrypt($model->id_token),'action'=>'token'));

define('ITALY_TIMEZONE', 'Europe/Rome');
define('TIME_OFFSET', date_default_timezone_get());

function getTimeOffset() {
    $dateTimeServer = date_create("now", timezone_open(TIME_OFFSET));
    $dateTimeClient = date_create("now", timezone_open(ITALY_TIMEZONE));
    return $dateTimeClient->getOffset() - $dateTimeServer->getOffset();
}

$offset =getTimeOffset();
#echo "offset: ". $offset;
$urlGetInvoiceStatus = Yii::app()->createUrl('qrcode/getInvoiceStatus');
$idTokenEncrypted = crypt::Encrypt($model->id_token);
$totalseconds = $model->expiration_timestamp - $model->invoice_timestamp;
#echo "<br>".$totalseconds;
// Don't know where the server is or how its clock is set, so default to UTC
#echo "<br>".date_default_timezone_get();
$expiration = $model->expiration_timestamp + $offset;

$dd = date("d",$expiration);
$mm = date("m",$expiration);
$yyyy = date("Y",$expiration);
$hh = date("H",$expiration);
$minutes = date("i",$expiration);
$ss = date("s",$expiration);

$customLogo = $storeSettings->CustomLogo;
$customLogo = str_replace("pos.","napay.",$customLogo);
$customLogo = str_replace("localhost","localhost/napay",$customLogo);

//
// echo $customLogo;
// exit;


include ('js_qrcode.php');
?>
<!-- CUSTOM QRCODE CSS -->
<?php
    if (isset($storeSettings->CustomCSS) &&  $storeSettings->CustomCSS <> '' && $storeSettings->CustomCSS <> '0'){
        $customCss = $storeSettings->CustomCSS;
        $customCss = str_replace("pos.","napay.",$customCss);
        $customCss = str_replace("localhost","localhost/napay",$customCss);
        // echo $customCss;
        // exit;
    ?>
        <head>
            <link href="<?php echo $customCss; ?>" rel="stylesheet" media="all">
        </head>
    <?php
    }
?>

<div class='section__content section__content--p30'>
	<div class='container-fluid'>
		<!-- <div class="row"> -->
		<center>
		<div class="col-lg-6">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-body">
						<div class="top-header">
							<div class="header">
								<div class="header__icon">
									<img src="<?php echo $customLogo; ?>" class="header__icon__img" width="250">
								</div>
							</div>
							<div class="timer-row">
								<div class="timer-row__progress-bar progress-bar bg-success" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
								<div class="timer-row__spinner"><bp-spinner><svg xml:space="preserve" version="1.1" viewBox="0 0 50 50" x="0px" xmlns="http://www.w3.org/2000/svg" y="0px"><path d="M11.1,29.6c-0.5-1.5-0.8-3-0.8-4.6c0-8.1,6.6-14.7,14.7-14.7S39.7,16.9,39.7,25c0,1.6-0.3,3.2-0.8,4.6l6.1,2c0.7-2.1,1.1-4.3,1.1-6.6c0-11.7-9.5-21.2-21.2-21.2S3.8,13.3,3.8,25c0,2.3,0.4,4.5,1.1,6.6L11.1,29.6z"></path></svg></bp-spinner></div>
								<div class="timer-row__message">
									<span id='timer-row__message_waiting'>In attesa del pagamento...</span>
								</div>
								<div class="timer-row__time-left"></div>
							</div>
						</div>
						<div class="order-details">
							<div class="currency-selection">
								<div class="single-item-order__left">
									<div style="font-weight: 600;">Paga con</div>
								</div>
								<div class="single-item-order__right">
									<div class="payment__currencies"><img src="<?php echo Yii::app()->request->baseUrl . Yii::app()->params['TokenImage']; ?>"> <span><?php echo Yii::app()->params['TokenNameComplete']; ?></span></div>
								</div>
							</div>
							<div class="single-item-order buyerTotalLine">
								<div class="single-item-order__left">
									<div class="single-item-order__left__name"><?php echo $merchants->denomination; ?></div>
									<div class="single-item-order__left__description"><?php echo $pos->denomination; ?></div>
								</div>
								<div class="single-item-order__right">
									<div class="single-item-order__right__btc-price"><span><?php echo $model->token_price; ?> <?php echo Yii::app()->params['TokenCod']; ?></span></div>
									<div class="single-item-order__right__ex-rate">1 <?php echo Yii::app()->params['TokenCod']; ?> = 1 € (EUR)</div>
								</div>
									<span class="fa fa-angle-double-down"></span>
									<span class="fa fa-angle-double-up"></span>
							</div>
							<line-items>
								<div class="line-items">
									<div class="line-items__item">
										<div class="line-items__item__label">Importo</div>
										<div class="line-items__item__value"><?php echo $model->fiat_price; ?> <?php echo Yii::app()->params['TokenCod']; ?></div>
									</div>
									<div class="line-items__item line-items_fiatvalue">
										<div class="line-items__item__label">&nbsp;</div>
										<div class="line-items__item__value single-item-order__right__ex-rate"><?php echo $model->fiat_price; ?> € (EUR)</div>
									</div>
									<div class="line-items__item">
										<div class="line-items__item__label"></div>
										<div class="line-items__item__value"></div>
									</div>
									<div class="line-items__item line-items__item--total">
										<div class="line-items__item__label">Dovuto</div>
										<div class="line-items__item__value"><?php echo $model->token_price; ?> <?php echo Yii::app()->params['TokenCod']; ?></div>
									</div>
								</div>
							</line-items>
							<div class="payment-tabs">
								<div id="scan-tab" class="payment-tabs__tab active"><span>Scansiona</span></div>
								<div id="copy-tab" class="payment-tabs__tab"><span>Copia</span></div>
								<div id="tabsSlider" class="payment-tabs__slider"></div>
							</div>
						</div>
						<div class="payment-box">
							<div id="scan" class="bp-view payment scan active">
								<div class="payment__scan">
									<?php
									$this->widget('application.extensions.qrcode.QRCodeGenerator',array(
										'data' => $model->to_address.'?amount='.$model->token_price,
										'filename' => $merchants->id_merchant . '.png',
										'filePath' => Yii::app()->basePath . '/qrcodes/',
										'subfolderVar' => false,
										'displayImage'=>true, // default to true, if set to false display a URL path
										'errorCorrectionLevel'=>'H', // available parameter is L,M,Q,H
										'matrixPointSize'=>6, // 1 to 10 only
									));
									?>
								</div>
							</div>
							<div id="copy" class="bp-view payment manual-flow">
								<div class="manual__step-two__instructions">
									<span i18n="">Per completare il pagamento, inviare <b><?php echo $model->token_price; ?> <?php echo Yii::app()->params['TokenCod']; ?></b> all'indirizzo riportato di seguito.</span>
								</div>

								<nav class="copyBox">
									<div class="copySectionBox bottomBorder"><label>Importo</label>
										<div class="copyAmountText copy-cursor _copySpan"><span><?php echo $model->token_price; ?></span> <?php echo Yii::app()->params['TokenCod']; ?></div>
									</div>
									<div class="separatorGem"></div>
									<div class="copySectionBox"><label>Indirizzo</label>
										<div class="inputWithIcon _copyInput">
											<input type="hidden" readonly="readonly" class="checkoutTextbox" value="<?php echo $model->to_address; ?>" size="60"/>
											<?php echo $model->to_address; ?>
										</div>
									</div>
								</nav>
							</div>
							<div id="paid" class="bp-view">
								<div class="status-block">
									<div class="success-block">
										<div class="status-icon__wrapper">
											<div class="inner-wrapper">
												<div class="status-icon__wrapper__icon">
													<img src="<?php echo Yii::app()->request->baseUrl; ?>/css/images/checkmark.svg">
												</div>
												<div class="status-icon__wrapper__outline"></div>
											</div>
										</div>
										<div class="success-message">La fattura è stata pagata</div>
									</div>
								</div>
							</div>
							<div id="paidPartial" class="bp-view partial">
								<div class="status-block">
									<div class="success-block">
										<div class="status-icon__wrapper">
											<div class="inner-wrapper">
												<div class="status-icon__wrapper__icon">
													<img src="<?php echo Yii::app()->request->baseUrl; ?>/css/images/checkmark.svg">
												</div>
												<div class="status-icon__wrapper__outline_partial"></div>
											</div>
										</div>
										<div class="success-message">La fattura è stata pagata solo in parte</div>
										<div class="expired__text">Hai 1 ora di tempo per inviare la differenza, oppure richiedere il rimborso di quanto versato.</div>
									</div>
								</div>
							</div>
							<div id="paidOver" class="bp-view ">
								<div class="status-block">
									<div class="success-block">
										<div class="status-icon__wrapper">
											<div class="inner-wrapper">
												<div class="status-icon__wrapper__icon">
													<img src="<?php echo Yii::app()->request->baseUrl; ?>/css/images/checkmark.svg">
												</div>
												<div class="status-icon__wrapper__outline_over"></div>
											</div>
										</div>
										<div class="success-message">La fattura è stata pagata oltre il dovuto.</div>
										<div class="expired__text">Puoi chiedere al commerciante il rimborso della parte eccedente.</div>
									</div>
								</div>
							</div>
							<div id="archived" class="bp-view expired">
									<div class="expired-icon">
										<img src="<?php echo Yii::app()->request->baseUrl; ?>/css/images/archived.svg">
									</div>
									<div class="archived__message">
										<div class="archived__message__header">
											<span>Questa fattura è stata pagata</span>
										</div>
									</div>
							</div>
							<div id="expired" class="bp-view expired">
								<div class="expired__body">
									<div class="expired__header">Cosa è successo?</div>
									<div i18n="" class="expired__text">Questa fattura è scaduta. Se hai già provato ad inviare un pagamento, forse non è stato accettato dalla rete e non abbiamo ancora ricevuto i tuoi fondi.</div>
									<div class="expired__text">Se la transazione non viene accettata dalla rete, i fondi saranno nuovamente spendibili nel tuo portafoglio.</div>
									<div class="expired__text expired__text__smaller">
										<span class="expired__text__bullet">Numero della Fattura</span>: <?php echo crypt::Encrypt($model->id_token); ?><br>
									</div>
								</div>
							</div>
							<div class="modal fade" id="smallModal" tabindex="-1" role="dialog" aria-labelledby="smallModalLabel" aria-hidden="true" style="display: none;">
								<div class="modal-dialog modal-sm" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h5 class="modal-title" id="smallModalLabel">Copiato</h5>
											<button type="button" class="close" data-dismiss="modal" aria-label="Close">
												<span aria-hidden="true">×</span>
											</button>
										</div>
										<div class="modal-body">
											<p><?php echo $model->to_address; ?></p>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="return-button btn-primary" style="display:none;">
                            <a href="<?php echo $redirectUrl; ?>">
							<?php echo CHtml::Button('Ritorna a '.$pos->denomination, array(
								'class' => 'btn au-btn--blue ',
								//'href' => '#',
								//'onclick' => 'history.back();return false;',
								));
							?>
                            </a>
							<?php echo "<input type='hidden' id='invoiceValue' value='' />"?>
						</div>
						<?php echo Logo::footer('#333'); ?>
					</div>
				</div>
			</div>
		</div>
	</center>
	</div>
</div>
