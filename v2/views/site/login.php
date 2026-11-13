<?php use yii\helpers\Html; ?>
<section class="intro"><p class="eyebrow">ACCESSO OPERATORE</p><h1>Accedi al POS</h1><p class="subtitle">Accedi con le credenziali Napay</p></section>
<section class="terminal" aria-label="Login operatore">
<?= Html::beginForm(['/site/login'], 'post', ['id' => 'operator-login', 'autocomplete' => 'on']) ?>
<?= Html::errorSummary($model, ['class' => 'error', 'role' => 'alert']) ?>
<label for="operator-email">Email</label>
<?= Html::activeTextInput($model, 'email', ['id' => 'operator-email', 'type' => 'email', 'autocomplete' => 'username', 'maxlength' => 254]) ?>
<label for="operator-password">Password</label>
<?= Html::passwordInput('OperatorLoginForm[password]', '', ['id' => 'operator-password', 'autocomplete' => 'current-password', 'maxlength' => 1024]) ?>
<button type="submit" class="primary">Accedi</button>
<p class="boundary">Credenziali verificate dal backend. Nessuna password salvata nel POS.</p>
<?= Html::endForm() ?>
</section>
