<?php
/* @var $this TokensController */
/* @var $dataProvider CActiveDataProvider */
$visible =  WebApp::isMobileDevice();


$criteria=new CDbCriteria;
if (Yii::app()->user->objUser['privilegi'] == 20){
    $criteria->compare('deleted',0,false);
}
$url = Yii::app()->createUrl('tokens/index');

$myList = <<<JS
    lista = {
		cambia: function(val){
            console.log(val);
		 	var url = '{$url}' + "&typelist="+val;
		 	window.location.href = url;
		}
	}


JS;
Yii::app()->clientScript->registerScript('myList', $myList);


$activeButton = [
    0 => '',    // completati
    1 => '',    // pagati
    2 => '',    // in corso
    3 => '',    // tutti
];

//$activeButton[0] = 'active';

if (!isset($_GET['typelist']))
    $activeButton[0] = 'active';
else
    $activeButton[$_GET['typelist']] = 'active';

?>
<div class='section__content section__content--p30'>
	<div class='container-fluid'>
		<div class="row">
			<div class="col-lg-12">
                <div class="au-card au-card--no-shadow au-card--no-pad ">
					<div class="card-header ">
						<i class="fas fa-star"></i>
						<span class="card-title">Transazioni token</span>

					</div>
					<div class="card-body">
                        <span>
                            <button title='Pagamenti che hanno 6 o più conferme' type='button' class='btn-0 btn btn-outline-info btn-sm <?php echo $activeButton[0]; ?>' onclick='lista.cambia(0);'>Completati</button>
                            <!-- <button title='Pagamenti che hanno da 0 a 5 conferme' type='button' class='btn-1 btn btn-outline-info <?php echo $activeButton[1]; ?>' onclick='lista.cambia(1);'>Pagati</button> -->
                            <button title='Pagamenti in attesa di pagamento' type='button' class='btn-2 btn btn-outline-info btn-sm <?php echo $activeButton[2]; ?>' onclick='lista.cambia(2);'>In corso...</button>
                            <button title='Tutti i pagamenti' type='button' class='btn-3 btn btn-outline-info btn-sm <?php echo $activeButton[3]; ?>' onclick='lista.cambia(3);'>Tutti</button>
                        </span>
        				<div class="table-responsive table--no-card">

        				<?php
                            Yii::import('zii.widgets.grid.CGridView');
                            class SpecialGridView extends CGridView {
                                public $from_address;
                            }
                            $this->widget('SpecialGridView', array(
                              'id' => 'tokens-grid',
                              //'hideHeader' => true,
                              // 'htmlOptions' => array('class' => 'table table-borderless  table-data4 table-wallet text-light'),
                              //'htmlOptions' => array('class' => 'table table-borderless table-data2 table-earning '),
                             // 'htmlOptions' => array('class' => 'table grid-view text-dark  table-wallet'),
                             // 'htmlOptions' => array('class' => 'table table-wallet'),
                                'dataProvider'=>$modelc->search(),
                                'from_address'   => $from_address,          // your special parameter
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
                                  'type'=>'raw',
                                  'name'=>'',
                                  'value'=>'WebApp::typeTransaction($data->type)',
                                  'htmlOptions'=>array('style'=>'width:1px;'),

                                    ),

                                array(
                                  'name'=>'invoice_timestamp',
                                  'type'=>'raw',
                                  //'value' => 'CHtml::link(CHtml::encode(date("d/m/Y H:i:s",$data->invoice_timestamp)), Yii::app()->createUrl("wallet/details")."&id=".CHtml::encode(crypt::Encrypt($data->id_token)))',
                                  'value' => 'CHtml::link(WebApp::dateLN($data->invoice_timestamp,$data->id_token), Yii::app()->createUrl("tokens/view",["id"=>crypt::Encrypt($data->id_token)]) )',
                                  //'value' => 'CHtml::link(CHtml::encode(date("Y-m-d H:i:s",$data->invoice_timestamp)), Yii::app()->createUrl("tokens/view")."&id=".CHtml::encode(crypt::Encrypt($data->id_token)))',
                                  //'value' => 'crypt::Encrypt($data->id_token)<br>date("d/m/Y H:i:s",$data->invoice_timestamp)',


                                    ),
                                array(
                                  'type'=>'raw',
                                  'name'=>'status',
                                  'value'=>'CHtml::link(WebApp::walletStatus($data->status), Yii::app()->createUrl("tokens/view")."&id=".CHtml::encode(crypt::Encrypt($data->id_token)))',
                                  'cssClassExpression' => '( $data->status == "sent" ) ? "denied" : (( $data->status == "complete" ) ? "process" : "desc incorso")',
                                    ),
                                array(
                                  'type'=>'raw',
                                        'name'=>'token_price',
                                  'value'=>'WebApp::typePrice($data->token_price,(($data->from_address == $this->grid->from_address) ? "sent" : "received"))',
                                  'htmlOptions'=>array('style'=>'text-align:center;'),
                                    ),

                                [
                                  'type'=>'raw',
                                  'name'=>'Indirizzo',
                                  'value'=>'CHtml::link($data->from_address, Yii::app()->createUrl("tokens/view")."&id=".CHtml::encode(crypt::Encrypt($data->id_token)))',
                                  'visible'=>!$visible,
                                ],


                              )
                            ));
                    		?>
        				</div>
                    </div>
                    <div class="card-footer">

                    </div>
                </div>
			</div>
		</div>

	<?php echo Logo::footer('#333'); ?>
	</div>
</div>
