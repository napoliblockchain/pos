<?php
// per conosxcere il server Blockchain devo recuperarlo dallo user che ha creato la tx
// pertanto:
// $settings=Settings::load();
// $btcpayInvoiceAddress = $settings->blockchainAddress;

$merchants = Merchants::model()->findByPk($model->id_merchant);
$btcpayInvoiceAddress = Settings::loadUser($merchants->id_user)->blockchainAddress;

?>
<div class='section__content section__content--p30'>
<div class='container-fluid'>
<div class="row">
	<div class="col-lg-12">
		<div class="au-card au-card--no-shadow au-card--no-pad bg-overlay--semitransparent">
			<div class="card-header ">
				<i class="fab fa-btc"></i>
				<span class="card-title">Dettaglio transazione <b><?php echo CHtml::link(CHtml::encode($model->id_invoice_bps), $btcpayInvoiceAddress.'/invoice?id='.$model->id_invoice_bps, array("target"=>"_blank")); ?></b></span>
			</div>
			<div class="card-body">
				<div class="table-responsive table--no-card m-b-40">
					<?php $this->widget('zii.widgets.CDetailView', array(
						//'htmlOptions' => array('class' => 'table table-borderless table-striped '),
						'data'=>$model,
						'attributes'=>array(
							//'id_invoice_bps',
							// array(
							// 	'type'=>'raw',
							// 	'name'=>'id_invoice_bps',
							// 	'value' => CHtml::link(CHtml::encode($model->id_invoice_bps), $btcpayInvoiceAddress.'/invoice?id='.$model->id_invoice_bps, array("target"=>"_blank")),
							// ),
							array(
								'type'=>'raw',
								'name'=>'status',
								//'value'=>WebApp::walletStatus($model->status),
								'value' => ( $model->status <> "complete" ) ?
									(
										CHtml::ajaxLink(
										    WebApp::walletStatus($model->status),          // the link body (it will NOT be HTML-encoded.)
										    array('backend/checkSingleInvoice'."&id=".CHtml::encode(crypt::Encrypt($model->id_transaction))), // the URL for the AJAX request. If empty, it is assumed to be the current URL.
										    array(
										        'update'=>'.btn-outline-dark',
										        'beforeSend' => 'function() {
										           		$(".btn").text("Checking...");
														$(".btn").addClass("alert-warning text-light");
										        	}',

										        'complete' => 'function(data) {
													$(".btn").removeClass("btn-secondary");
													//console.log(data);
													var obj = JSON.parse(data.responseText)
													console.log(obj);
													$(".btn").text(obj.message);
													var time = 1;

													if (obj.success == 1){
														function waitUntil(time){
															time -= 1;
															$(".btn").text(obj.message+ " (- "+time+")");

															if (time >0)
																setTimeout(function(){ waitUntil(time) }, 1000);
															else
																location.reload();
														}
														setTimeout(function(){ waitUntil(time) }, 1000);
													}else{
														$(".btn").addClass("btn-warning");
														$(".btn").text("Transazione non trovata. Se vuoi cancellarla clicca sul pulsante \'ELIMINA\' sottostante");
														$("#btn-delete-transaction").text("ELIMINA");
														$(".delete-transaction").show();
													}
										        }',
										    )
										)
									) : WebApp::walletStatus($model->status),
							),
							array(
								'label'=>'Data',
								'value'=>date("d/m/Y H:i:s",$model->invoice_timestamp),
							),
							array(
								'label'=>'Importo',
								'type'=>'raw',
								'value'=>'<h5>€ '.$model->price.'</h5>',
							),
							array(
								'label'=>'POS Utilizzato',
								'value'=>($model->id_pos >0) ? Pos::model()->findByPk($model->id_pos)->denomination : Shops::model()->findByPk($model->item_code)->denomination,
							),
							[
								'label' => '<strong>Sommario</strong>',
								'type'=>'raw',
								'value' => WebApp::showCryptoInfo($model->id_transaction),
							],
							[
								'label' => '<strong>Vendita Pos</strong>',
								'type'=>'raw',
								'value' => WebApp::showPosData($model->id_transaction),
								'visible' => WebApp::issetPosData($model->id_transaction),
							],

						),
					)); ?>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="row delete-transaction" style="display:none;">
	<div class="col-md-12">
		<div class="overview-wrap">
			<h2 class="title-1">
				<?php $deleteTransactionURL = Yii::app()->createUrl('transactions/delete',['id'=>crypt::Encrypt($model->id_transaction)]); ?>

				<form>
				<a href="<?php echo $deleteTransactionURL;?>">
					<button type="button" class="btn btn-danger" id="btn-delete-transaction"></button>
				</a>

				</form>

			</h2>
		</div>
	</div>
</div>
<?php echo Logo::footer('#333'); ?>
</div>
</div>
