<div class="form">
<?php
$URLContactForm = Yii::app()->createUrl('site/contactForm');
include ('js_login.php');

$this->pageTitle=Yii::app()->name . ' - Login';
$form=$this->beginWidget('CActiveForm', array(
	'id'=>'login-form',
	'enableClientValidation'=>false,
	'clientOptions'=>array(
		'validateOnSubmit'=>true,
	),
));
$settings = Settings::load();
$reCaptcha2PublicKey = $settings->reCaptcha2PublicKey;
?>

<div class="login-wrap">
	<div class="login-content">
		<div class="login-logo">
			<?php Logo::login(); ?>
		</div>

		<div class="login-form">
				<div class="form-group">
					<div class="input-group">
                        <div class="input-group-addon">
                            <i class="fa fa-shopping-cart fa-2x text-primary"></i>
                        </div>
						<?php echo $form->textField($model,'username',array('placeholder'=>'Codice SIN','class'=>'form-control','style'=>'height:50px;')); ?>
					</div>
					<?php echo $form->error($model,'username',array('class'=>'alert alert-danger')); ?>
				</div>
				<div class="form-group" style="text-align:left;">
					<?php
					$form->widget('application.extensions.reCaptcha2.SReCaptcha', array(
	        				'name' => 'reCaptcha', //is requred
	        				'siteKey' => $reCaptcha2PublicKey,
	        				'model' => $form,
							'lang' => 'it-IT',
						)
					);
					?>
					<?php echo $form->error($model,'reCaptcha',array('class'=>'alert alert-danger')); ?>
				</div>




				<?php echo CHtml::submitButton(Yii::t('lang','sign in'), array('class' => 'au-btn au-btn--block au-btn--blue m-b-20','id'=>'accedi-button')); ?>

				<div class="form-group">
					<div class="input-group">
						<a href="<?php echo $URLContactForm; ?>" target="_blank">
							 <?php echo Yii::t('lang','Did you discover a bug? Please compile this form.');?></a>
					</div>
				</div>

				<div class="form-group">
					<div class="input-group">
						<span><a href="https://www.iubenda.com/privacy-policy/7935688"><?php echo Yii::t('lang','Read our Privacy policy'); ?></a></span>
					</div>
				</div>


			
				<?php echo Logo::footer('#333'); ?>

		</div>
	</div>
</div>
<?php $this->endWidget(); ?>
