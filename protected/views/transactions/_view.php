<?php
/* @var $this PosTrxController */
/* @var $model PosTrx */

$settings=Settings::load();
$btcpayInvoiceAddress = $settings->BTCPayServerAddress;

?>
<div class='section__content section__content--p30'>
<div class='container-fluid'>
<div class="row">
	<div class="col-lg-12">
		<h2 class='title-1 m-b-25'>Dettaglio transazione #<?php echo $model->id_transaction; ?></h2>
		<div class="table-responsive table--no-card m-b-40">
			<?php $this->widget('zii.widgets.CDetailView', array(
				'htmlOptions' => array('class' => 'table table-borderless table-striped '),
				'data'=>$model,
				'attributes'=>array(
					//'id_invoice_bps',
					array(
						'type'=>'raw',
						'name'=>'id_invoice_bps',
						'value' => CHtml::link(CHtml::encode($model->id_invoice_bps), $btcpayInvoiceAddress.'/invoice?id='.$model->id_invoice_bps, array("target"=>"_blank")),
					),
					array(
						'type'=>'raw',
						'name'=>'status',
						'value'=>WebApp::walletStatus($model->status),
					),
					array(
						'label'=>'Data',
						'value'=>date("d/m/Y H:i:s",$model->invoice_timestamp),
					),
					array(
						'label'=>'Importo',
						'value'=>'€ '.$model->price,
					),
					array(
						'label'=>'Rate',
						'value'=>$model->rate,
					),
					'btc_price',
					'btc_due',
					'btc_paid',
					array(
						'label'=>'POS Utilizzato',
						'value'=>$model->item_desc,
					),
					array(
						'label'=>'Satoshis/byte',
						'value'=>$model->satoshis_perbyte,
					),

					//'currency',
					array(
						'label'=>'Tassa Miner',
						'value'=>$model->total_fee,
					),
					array(
						'label'=>'Indirizzo',
						'type'=>'raw',
						'value' => CHtml::link(CHtml::encode($model->bitcoin_address), WebApp::isBtcAddress($model->bitcoin_address), array("target"=>"_blank")),
					),

				),
			)); ?>
		</div>
	</div>
</div>
<?php echo Logo::footer('#333'); ?>
</div>
</div>
