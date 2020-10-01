<header class="header-mobile d-block d-lg-none ">
	<div class="header-mobile__bar qrcode-hidden">
		<div class="container-fluid">
			<div class="header-mobile-inner">
				<?php Logo::header(); ?>
				<button class="hamburger hamburger--slider" type="button">
					<span class="hamburger-box">
						<span class="hamburger-inner"></span>
					</span>
				</button>
			</div>
		</div>
	</div>
	<nav class="navbar-mobile">
		<div class="container-fluid">
			<ul class="navbar-mobile__list list-unstyled">
			 <?php
			if (Yii::app()->user->isGuest)
			{
			?>
						<li>
							<a class="js-arrow" href="<?php echo Yii::app()->createUrl('site/login'); ?>">
							<i class="fas fa-sign-in-alt"></i><?php echo Yii::t('lang','Sign in');?></a>
						</li>
			<?php
			}else{
			?>

			<li class="active">
				<a class="js-arrow" href="<?php echo Yii::app()->createUrl('keypad/index'); ?>">
					Keypad <i class="glyphicon glyphicon-th"></i></a>
			</li>
			<?php
			$pos = Pos::model()->findByPk(Yii::app()->user->objUser['id_pos']);
			//cerco tutti gli shop
			$criteria=new CDbCriteria();
			$criteria->compare('id_store',$pos->id_store,false);
			$criteria->compare('deleted',0,false);
			$shops = Shops::model()->findAll($criteria);

			$bps_shopid = array();
			foreach ($shops as $x => $item)
				$bps_shopid[] = ['denomination'=>$item->denomination,'bps_shopid'=>crypt::Encrypt($item->id_shop)];

			// var_dump($bps_shopid);
			// exit;
			if (!empty($bps_shopid)){
				?>
				<li class="has-sub">
					<a class="js-arrow" href="#">
						Self POS <i class="fa fa-globe"></i></a>
					<ul class="navbar-mobile-sub__list list-unstyled js-sub-list">
						<?php
						foreach ($bps_shopid as $x => $item){
							echo '<li >
								<a href="'.Yii::app()->createUrl('keypad/index',array('tag'=>$item['bps_shopid'])).'">'.substr($item['denomination'],0,13).'... <i class="fa fa-dot-circle-o"></i></a>
							</li>';
						}
						?>
				</ul>
			</li>
		<?php
		}
		?>

			<li>
				<a class="js-arrow" href="<?php echo Yii::app()->createUrl('transactions/index'); ?>">
					Transazioni <i class="fab fa-btc"></i></a>
			</li>
			<li>
				<a href="<?php echo Yii::app()->createUrl('tokens/index'); ?>">
					Token <i class="fas fa-star"></i></a>
			</li>


			<li>
				<a href='<?php echo Yii::app()->createUrl('site/contactForm'); ?>' target="_blank">
					 <?php echo Yii::t('lang','Bug report');?> <i class="fa fa-bug"></i></a>
			</li>
			<li>
				<a href="<?php echo Yii::app()->createUrl('settings/index',array('id'=>crypt::Encrypt(Yii::app()->user->objUser['id_user'])));?>">
					 <?php echo Yii::t('lang','Settings');?> <i class="fa fa-gear"></i></a>
			</li>
			<li>
				<div class="delete-serviceWorker">
						<a href="<?php echo Yii::app()->createUrl('site/logout');?>" >
						<?php echo Yii::t('lang','Logout');?> <i class="fa fa-power-off"></i></a>
				</div>
			</li>


			<?php } ?>
			</ul>
		</div>
	</nav>
</header>
