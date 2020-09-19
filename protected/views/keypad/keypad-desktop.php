<?php
$display = 'none;';
if (null !== $warningmessage)
 $display = '';
?>

<div class="warning-message">
	<?php
	if (null !== $warningmessage)
		foreach ($warningmessage as $message)
			echo $message;
	?>
</div>

<style>
#posIframe{
    width: 100% !important;
    height: 768px;
    position: relative;
}
</style>

<iframe id="posIframe" src='https://napay-backend.tk/apps/<?php echo isset($bps_shopid) ? $bps_shopid : ""; ?>/pos' style='max-width: 100%; max-height: 100%; border: 0;'></iframe>

<?php
$myPos = <<<JS

$("#posIframe").on("load", function () {
    // do something once the iframe is loaded
    // $('.js-search').prop('placeholder', 'cerca prodotto');
    // $('.js-search').val('pippo');
    // console.log('[POS] cambia prodotto');
});



JS;
Yii::app()->clientScript->registerScript('myPos', $myPos, CClientScript::POS_END);
?>
