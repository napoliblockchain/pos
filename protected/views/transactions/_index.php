<?php
/* @var $this PosTrxController */
/* @var $dataProvider CActiveDataProvider */


?>
<style>
.items {
	background-color: #fff;
}

</style>
<div class='section__content section__content--p30'>
	<div class='container-fluid'>
		<div class="row">
			<div class="col-lg-12">
				<h2 class='title-1 m-b-25'>Lista transazioni</h2>
				<div class="table-responsive table--no-card m-b-40">

				<?php
				$this->widget('zii.widgets.grid.CGridView', array(
					//'htmlOptions' => array('class' => 'table table-borderless table-striped table-data2'),
					'htmlOptions' => array('class' => 'table table-data4'),
				    'dataProvider'=>$dataProvider,
					'columns' => array(

						array(
							'type'=>'raw',
 						   'name'=>'invoice_timestamp',
 						   'value' => 'CHtml::link(CHtml::encode(date("d/m/Y H:i:s",$data->invoice_timestamp)), Yii::app()->createUrl("transactions/view")."&id=".CHtml::encode(Utility::encryptURL($data->id_transaction)))',
				        ),

						array(
				            'name'=>'status',
				            //'value'=>'$data->status',
							'type' => 'raw',
							//'value'=>'WebApp::walletStatus($data->status)',
							'value'=>'CHtml::link(WebApp::walletStatus($data->status), Yii::app()->createUrl("transactions/view")."&id=".CHtml::encode(Utility::encryptURL($data->id_transaction)))',
							'cssClassExpression' => '( $data->status == "complete" ) ? "process" : (( $data->status == "expired" ) ? "denied" : "desc incorso")',
				        ),
						array(
				            'name'=>'price',
							'type' => 'raw',
				            'value'=>'"€ ". $data->price',
				        ),


						array(
				            'name'=>'bitcoin_address',
							'type' => 'raw',
				            'value' => 'CHtml::encode(substr($data->bitcoin_address,0,7))."&hellip;"',
							//'value' => 'CHtml::link(CHtml::encode($data->bitcoin_address), WebApp::isBtcAddress($data->bitcoin_address), array("target"=>"_blank"))',

				    ),

					)
				));
				?>
				</div>
			</div>
		</div>

		<?php echo Logo::footer('#333'); ?>
	</div>
</div>
