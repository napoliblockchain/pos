<div class="form">
<?php
$form=$this->beginWidget('CActiveForm', array(
	'id'=>'notifications-form',
	// Please note: When you enable ajax validation, make sure the corresponding
	// controller action is handling ajax validation correctly.
	// There is a call to performAjaxValidation() commented in generated controller code.
	// See class documentation of CActiveForm for details on this.
	'enableAjaxValidation'=>false,
));


?>
<div class='section__content section__content--p30'>
	<div class='container-fluid container-wallet'>
		<div class="row">
			<div class="col-lg-12">
				<div class="au-card au-card--no-shadow au-card--no-pad m-b-40 bg-overlay--semitransparent">
					<div class="card-header ">
						<i class="zmdi zmdi-comment-text"></i>
						<span class="card-title"><?php echo Yii::t('lang','Messages');?></span>
					</div>
					<div class="card-body">
						<div class="table-responsive table--no-card m-b-30">
							<?php
							$this->widget('ext.selgridview.SelGridView', array(
								'id'=>'notifications-grid',
								'selectableRows' => 2, // valori sono 0,1,2
								//'hideHeader' => true,
								'htmlOptions' => array('class' => 'table table-borderless  table-data4 table-wallet ',
														'style' => 'border: 0px;'
													),
							    'dataProvider'=>$dataProvider,
								'pager'=>array(
									//'header'=>'Go to page:',
									//'cssFile'=>Yii::app()->theme->baseUrl
									'cssFile'=>Yii::app()->request->baseUrl."/css/yiipager.css",
									'prevPageLabel'=>'<',
									'nextPageLabel'=>'>',
									'firstPageLabel'=>'<<',
									'lastPageLabel'=>'>>',
								),
								'columns' => array(
									array(
									   'id'=>'selectedNotifications',
									   'class'=>'CCheckBoxColumn',
									   'htmlOptions'=>array('style'=>'padding:0px 0px 0px 0px; margin:0px 0px 0px 0px;vertical-align:middle;'),
								    ),
									[
										'name'=>Yii::t('lang','Select all'),
										'type'=>'raw',
										'value'=>'WebApp::showMessageRows(
											$data->timestamp,
											$data->id_tocheck,
											$data->status,
											$data->url,
											$data->type_notification,
											$data->description,
											$data->price
										)',
										'htmlOptions'=>array('style'=>'width:100%;'),
									],
									[     'name'=>'',
										]
									// array(
									// 	'name'=>Yii::t('lang','Select all'),
									// 	'type'=>'raw',
									// 	//'value' => 'CHtml::link(CHtml::encode(date("d/m/Y H:i:s",$data->invoice_timestamp)), Yii::app()->createUrl("wallet/details")."&id=".CHtml::encode(crypt::Encrypt($data->id_token)))',
									// 	'value' => 'CHtml::link(WebApp::dateLN($data->timestamp), Yii::app()->createUrl("tokens/view",["id"=>crypt::Encrypt($data->id_tocheck)]) )',
									// 	'htmlOptions'=>array('style'=>'vertical-align:middle;'),
									// ),
									//
									// array(
							        //     'name'=>'',
									//    'type' => 'raw',
									// 	'value'=>'CHtml::link(WebApp::walletStatus($data->status), $data->url)',
									// 	'htmlOptions'=>array('style'=>'vertical-align:middle;'),
									// 	'cssClassExpression' => '($data->type_notification == "help" ||  $data->type_notification == "contact" ? "is-hidden" : ( $data->status == "sent" ) ? "denied" : (( $data->status == "complete" ) ? "process" : "desc incorso"))',
							        // ),
									//
									// array(
							        //     'name'=>'',
									// 	'type' => 'raw',
									// 	'value' => 'CHtml::link(Yii::app()->controller->translateMsg($data->description), $data->url)',
									// 	'htmlOptions'=>array('style'=>'vertical-align:middle;'),
							        // ),
									//
									// array(
							        //     'name'=>'',
									// 	'type' => 'raw',
							        //     'value'=>'($data->type_notification == "help" ||  $data->type_notification == "contact" ? "" : $data->price)',
									// 	'htmlOptions'=>array('style'=>'vertical-align:middle;'),
									// 	'cssClassExpression' => '($data->type_notification == "help" ||  $data->type_notification == "contact" ? "is-hidden" : "")',
							        // ),
								)
							));
							?>

						</div>
						<?php if ($dataProvider->totalItemCount >0) { ?>
						<div class="form-group">
							<?php echo CHtml::submitButton(Yii::t('lang','delete messages'), array('class' => 'btn btn-primary ')); ?>
						</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
		<?php echo Logo::footer('#333'); ?>
	</div>
</div>





<!-- RICHIESTA PIN -->
<div class="modal fade " id="pinRequestModal" tabindex="-1" role="dialog" aria-labelledby="pinRequestModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-sm" role="document">
		<div class="modal-content alert-light  ">
			<div class="modal-header">
				<h5 class="modal-title" id="pinRequestModalLabel">PIN Request</h5>
			</div>
			<div class="modal-body ">
				<center>
					<input type='hidden' id='pin_password' class='form-control' readonly="readonly"/>
                    <input type='hidden' id='pin_password_confirm' class='form-control' readonly="readonly"/>
                </center>
                <div class="pin-confirm-numpad pin-newframe-numpad"></div>
			</div>
			<div class="modal-footer">
				<div class="form-group">
					<button type="button" disabled="disabled" class="btn btn-primary disabled" id="pinRequestButton">Confirm</button>
				</div>
			</div>
		</div>
	</div>
</div>
<?php $this->endWidget(); ?>
</div><!-- form -->
