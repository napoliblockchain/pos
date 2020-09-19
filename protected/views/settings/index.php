<div class="form">

<?php
$idUserCrypted = crypt::Encrypt($user->id_user);
// $scadenzaPin = [0=>Yii::t('lang','Never'),5=>'5 min',10=>'10 min',15=>'15 min',30=>'30 min',60=>'60 min'];
$language = [''=>'','it'=>Yii::t('lang','Italian'),'en'=>Yii::t('lang','English')];
// $google2faURL = Yii::app()->createUrl('users/2fa').'&id='.$idUserCrypted;
// $google2faRemoveURL = Yii::app()->createUrl('users/2faRemove').'&id='.$idUserCrypted;

include ('js_settings.php');
// include ('js_resetpwd.php');

?>

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'settings-form',
	// Please note: When you enable ajax validation, make sure the corresponding
	// controller action is handling ajax validation correctly.
	// There is a call to performAjaxValidation() commented in generated controller code.
	// See class documentation of CActiveForm for details on this.
	'enableAjaxValidation'=>false,
));
?>

<div class='section__content section__content--p30'>
	<div class='container-fluid'>
		<div class="row">
			<div class="col-lg-12">
		        <div class="au-card au-card--no-shadow bg-overlay--semitransparent">
					<div class="card-header ">
						<i class="fa fa-gear"></i>
						<span class="card-title"><?php echo Yii::t('lang','Settings');?></span>
					</div>
		            <div class="card-body">
						<table class="items " style="width:100%;">
							<tbody>
								<tr class="odd">
									<td><p class="card-title"><?php echo Yii::t('lang','Save application on Homepage');?></p></td>
									<td style="text-align:right;">
										<a href="#" onclick="saveOnDesktop();"><button type="button" class="btn btn-primary btn-sm float-right saveOnDesktop"><?php echo Yii::t('lang','save');?></button></a>
									</td>
								</tr>

								<tr class="even">
									<td><p class="card-title"><?php echo Yii::t('lang','PUSH notifications');?></p></td>
									<td><button disabled type='button' class="js-push-btn-modal btn btn-sm btn-primary float-right"data-toggle="modal" data-target="#pushEnableModal"><?php echo Yii::t('lang','enable');?></button></td>
								</tr>
								<!-- <tr class="odd">
									<td><p class="card-title"><?php echo Yii::t('lang','Select language');?></p></td>
									<td><?php echo $form->dropDownList($userForm,'language',$language,array('class'=>'float-right'));?></td>
								</tr> -->
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	<?php echo Logo::footer('#333'); ?>
    </div>
</div>

<!-- ABILITA PUSH -->
<div class="modal fade " id="pushEnableModal" tabindex="-1" role="dialog" aria-labelledby="pushEnableModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-lg" role="document">
		<div class="modal-content alert-light  ">
			<div class="modal-header">
				<h5 class="modal-title" id="pushEnableModalLabel"><?php echo Yii::t('lang','Push Notifications');?></h5>
			</div>
			<div class="modal-body ">
                <p><b><?php echo Yii::t('lang','Enabling');?>:</b>
                    <br><?php echo Yii::t('lang','By enabling this setting you will receive <b> push </b> notifications when there are new transactions on your wallet. ');?>
					<br> <br><i><?php echo Yii::t('lang','Notifications are enabled for each device. To receive notifications on other devices you need to log in from each one, enable it and make sure you are online.');?>
						</i>
                </p>
                <p>
					<?php echo Yii::t('lang','Be sure to reply <b>Allow </b>when prompted');?>
                </p>
                <p><b><?php echo Yii::t('lang','Disabling');?>:</b>
					<br><?php echo Yii::t('lang','By disabling <b> push </b> notifications, you will no longer receive messages when there are transactions on your wallet.');?> </b> <br> <br>
					<i> <?php echo Yii::t('lang','Disabling push notifications from this device may also eliminate the subscription of any other connected devices.');?> </i>
                </p>
			</div>
			<div class="modal-footer">
                <div class="form-group">
					<button type="button" class="btn btn-secondary" data-dismiss="modal" ><?php echo Yii::t('lang','cancel');?></button>
				</div>
				<div class="form-group">
					<button type="button" class="js-push-btn btn btn-primary" data-dismiss="modal"><?php echo Yii::t('lang','Confirm');?></button>
				</div>
			</div>
		</div>
	</div>
</div>

<?php $this->endWidget(); ?>

</div><!-- form -->
