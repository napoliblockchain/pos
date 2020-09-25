<div class="form">
<?php
$form=$this->beginWidget('CActiveForm', array(
	'id'=>'details-form',
	'enableAjaxValidation'=>false,
));


// il block explorer
$explorer = false;
$settings=Settings::load();
if (isset($settings->poa_blockexplorer) && $settings->poa_blockexplorer != ''){
	$explorer = $settings->poa_blockexplorer;
}

$http_host = $_SERVER['HTTP_HOST'];
$InvoiceAddress = 'https://pos.' . Utils::get_domain($http_host) . '/index.php';
?>
<div class='section__content section__content--p30'>
	<div class='container-fluid '>
		<div class="row" id="details">
			<div class="col-lg-12">
				<div class="au-card au-card--no-shadow au-card--no-pad bg-overlay--semit ransparent text-dark ">
					<div class="card-header">
						<i class="fas fa-star"></i>
						<span class="card-title"><?php echo Yii::t('lang','Transaction details');?></span>
					</div>
					<div class="card-body">
						<div class="table-responsive table--no-card m-b-30">
							<?php $this->widget('zii.widgets.CDetailView', array(
								//'htmlOptions' => array('class' => 'table table-borderless table-striped'),
								'htmlOptions' => array('class' => 'table table-bor derless  table-earning t ext-light'),
								//'htmlOptions' => array('class' => 'table table-data4 f05'),
								'data'=>$model,
								'attributes'=>array(
									// array(
									// 	'label'=>'Codice transazione',
									// 	'value'=>crypt::Encrypt($model->id_token),
									// ),
									['name'=>Yii::t('model','id'),
										'type'=>'raw',
										//'value'=>crypt::Encrypt($model->id_token),
										'value'=>CHtml::link(CHtml::encode(crypt::Encrypt($model->id_token)), Yii::app()->createUrl('qrcode/index',['id'=>crypt::Encrypt($model->id_token),'id_pos'=>crypt::Encrypt($model->id_pos)]), array("target"=>"_parent")),
										],
									array(
										'type'=>'raw',
										'name'=>Yii::t('model','status'),
										//'value'=>WebApp::walletStatus($model->status),
										'value' => ( $model->status == "new" || $model->status == 'expired' ) ?
										(
											CHtml::ajaxLink(
											    WebApp::walletStatus($model->status),          // the link body (it will NOT be HTML-encoded.)
											    array('tokens/check'."&id=".CHtml::encode(crypt::Encrypt($model->id_token))), // the URL for the AJAX request. If empty, it is assumed to be the current URL.
											    array(
											        'update'=>'.btn-outline-dark',
											        'beforeSend' => 'function() {
											           $(".btn-outline-dark").text(Yii.t("js","Checking..."));
											        }',
											        'complete' => 'function() {
													  	location.reload(true);
											        }',
											    )
											)
										) : WebApp::walletStatus($model->status),
									),
									array(
										'label'=>Yii::t('model','Date'),
										'value'=>date("Y-m-d H:i:s",$model->invoice_timestamp),
									),
									array(
										'label'=>Yii::t('model','Price'),
										//'value'=>$model->token_price,
											'type'=>'raw',
										'value'=>'<h5 class="text-success">+'.$model->token_price.'</h5>',

									),
									array(
										'label'=>Yii::t('model','Received'),
										'type'=>'raw',
										'value'=>'<h5 class="text-success">+'.$model->token_ricevuti.'</h5>',

									),
									array(
										'label'=>Yii::t('model','From'),
										'type'=>'raw',
										'value' => ($explorer == false ? $model->from_address :
											CHtml::link(
												CHtml::encode($model->from_address),
												$explorer . WebApp::isEthAddress($model->from_address),
												array("target"=>"_blank")
											)
										)
									),
									array(
										'label'=>Yii::t('model','To'),
										'type'=>'raw',
										'value' => ($explorer == false ? $model->to_address :
											CHtml::link(
												CHtml::encode($model->to_address),
												$explorer . WebApp::isEthAddress($model->to_address),
												array("target"=>"_blank")
											)
										)
									),
									array(
										'label'=>Yii::t('model','Tx Hash'),
										'type'=>'raw',
										'value' => ($explorer == false ? $model->txhash :
											CHtml::link(
												CHtml::encode($model->txhash),
												$explorer . WebApp::isEthAddress($model->txhash),
												array("target"=>"_blank")
											)
										)
									),
									array(
										//'type'=>'raw',
										'label'=>Yii::t('model','Message'),
										'value'=>(null !== PosInvoicesMemo::model()->findByAttributes(['id_token'=>$model->id_token]) ? crypt::Decrypt(PosInvoicesMemo::model()->findByAttributes(['id_token'=>$model->id_token])->memo) : '') ,
									),

								),
							)); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
			<?php echo Logo::footer('#333'); ?>
	</div>
</div>

<?php $this->endWidget(); ?>
</div><!-- form -->
