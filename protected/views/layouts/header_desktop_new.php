
<div class="qrcode-hidden" style="width:200px;">

  <div id='poa-pulse'>
    <button class="pulse-button" ></button>
  </div>

    <div class="section__content section__content--p30">
        <div class="container-fluid">
            <div class="header-wrap">
			         <?php if (!Yii::app()->user->isGuest) { ?>
                  <form class="form-header" action="" method="POST">
                 <?php echo BitstampRTP::RTP(); ?>

                  </form>

			           <?php } ?>
            </div>
        </div>
    </div>
    <div class="header-button">
      <div class="noti-wrap">
        <?php  include ('header_notify.php'); ?>
      </div>
    </div>

</div>
