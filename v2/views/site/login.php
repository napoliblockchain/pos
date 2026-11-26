<?php use yii\helpers\Html; ?>
<h1 class="auth-title"><?= Html::encode(Yii::t('app', 'Sign in to the POS')) ?></h1>
<p class="auth-subtitle"><?= Html::encode(Yii::t('app', 'Use your Napay credentials')) ?></p>
<?= Html::beginForm(['/site/login'], 'post', ['id' => 'operator-login', 'autocomplete' => 'on']) ?>
<div class="mb-3"><label class="form-label" for="operator-email"><?= Html::encode(Yii::t('app', 'Email')) ?></label>
<?= Html::activeTextInput($model, 'email', ['id' => 'operator-email', 'class' => 'form-control' . ($model->hasErrors('email') ? ' is-invalid' : ''), 'type' => 'email', 'autocomplete' => 'username', 'maxlength' => 254, 'placeholder' => Yii::t('app', 'name@example.com')]) ?>
<?= Html::error($model, 'email', ['class' => 'invalid-feedback d-block', 'role' => 'alert']) ?></div>
<div class="mb-3"><label class="form-label" for="operator-password"><?= Html::encode(Yii::t('app', 'Password')) ?></label>
<?= Html::passwordInput('OperatorLoginForm[password]', '', ['id' => 'operator-password', 'class' => 'form-control' . ($model->hasErrors('password') ? ' is-invalid' : ''), 'autocomplete' => 'current-password', 'maxlength' => 1024, 'placeholder' => '••••••••']) ?>
<?= Html::error($model, 'password', ['class' => 'invalid-feedback d-block', 'role' => 'alert']) ?></div>
<button type="submit" class="auth-submit"><?= Html::encode(Yii::t('app', 'Sign in')) ?></button>
<?= Html::endForm() ?>
