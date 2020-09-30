<div class="warning-message">
	<?php
	if (null !== $warningmessage)
		foreach ($warningmessage as $message)
			echo $message;
	?>
</div>
<?php
include ('js_main.php');  // main application

?>
<div class='section__content section__content--p30'>
	<div class='container-fluid'>
		<div class="row row-keypad-mobile">
			<div class="col-lg-7">
				<div class="card bg-dark">
					
					<div class="card-body card-block">
						<div class='easy-get'></div>
					</div>
					<center>
					<?php if ($invoiceIsPaid){ ?>
						<a href="<?php echo Yii::app()->createUrl("keypad/printInvoice",array('id'=>$invoiceId,'action'=>$invoiceAction,'type'=>'mobile'));?>" target="_blank" style="z-index:100;">
							<button class="au-btn au-btn-icon au-btn--blue">
								<i class="fas fa-print"></i>Ricevuta</button>
						</a>
					<?php }	?>
					</center>
				</div>
			</div>
		</div>
		<?php echo Logo::footer('#333'); ?>
	</div>
</div>
