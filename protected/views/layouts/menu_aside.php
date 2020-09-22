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

				</ul>

			<?php } ?>
		</nav>
	</div>
</aside>
