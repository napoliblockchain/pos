<aside class="menu-sidebar d-none d-lg-block">
	<div  class="logo">
		<a href="<?php echo Yii::app()->createUrl('site/index'); ?>">
			<?php Logo::header(); ?>
		</a>
	</div>
	<div id="page-vesuvio"></div>
	<div class="menu-sidebar__content js-scrollbar1">
		<nav class="navbar-sidebar">
			<?php
			if (Yii::app()->user->isGuest)
			{
			?>
				<ul class="list-unstyled navbar__list">
					<li class="active">
						<a class="js-arrow" href="<?php echo Yii::app()->createUrl('site/login'); ?>">
							<i class="fas fa-sign-in-alt"></i><?php echo Yii::t('lang','Sign in');?></a>
					</li>
				</ul>
			<?php
			}else{
				?>
				<ul class="list-unstyled navbar__list">
					<li class="active">
						<a class="js-arrow" href="<?php echo Yii::app()->createUrl('keypad/index'); ?>">
							 <i class="glyphicon glyphicon-th"></i>Keypad</a>
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
									<i class="fa fa-globe"></i>Self POS</a>
								<ul class="navbar-mobile-sub__list list-unstyled js-sub-list">
									<?php
									foreach ($bps_shopid as $x => $item){
										echo '<li >
											<a href="'.Yii::app()->createUrl('keypad/index',array('tag'=>$item['bps_shopid'])).'"><i class="fa fa-dot-circle-o"></i>'.$item['denomination'].'</a>
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
							<i class="fab fa-btc"></i>Transazioni</a>
					</li>
					<li>
						<a href="<?php echo Yii::app()->createUrl('tokens/index'); ?>">
							<i class="fas fa-star"></i>Token</a>
					</li>


					<li>
						<a href="<?php echo Yii::app()->createUrl('site/contactForm'); ?>" target="_blank">
							 <i class="fa fa-bug"></i><?php echo Yii::t('lang','Bug report');?></a>
					</li>

					<li>
						<a href="<?php echo Yii::app()->createUrl('settings/index',array('id'=>crypt::Encrypt(Yii::app()->user->objUser['id_user'])));?>">
							 <i class="fa fa-gear"></i><?php echo Yii::t('lang','Settings');?> </a>
					</li>
					<li>
						<div class="delete-serviceWorker">
								<a href="<?php echo Yii::app()->createUrl('site/logout');?>" >
								<i class="fa fa-power-off"></i><?php echo Yii::t('lang','Logout');?> </a>
						</div>
					</li>


				</ul>

			<?php } ?>
		</nav>
	</div>
</aside>
