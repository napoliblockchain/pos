<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.Logo');

Yii::import('libs.bitstamp-real-time-price.BitstampRTP');

class SettingsController extends Controller
{
	public function init()
	{
		if (isset(Yii::app()->user->objUser) && Yii::app()->user->objUser['facade'] != 'pos'){
			Yii::app()->user->logout();
			$this->redirect(Yii::app()->homeUrl);
		}
	}
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			//'postOnly + delete', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array(
					'index',
					'changelanguage', //cambia la lingua live
					'saveSubscription', //salva lo sottoscrizinoe dell'user per le notifiche push
				),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * Saves the Subscription for push messages.
	 * @param POST VAPID KEYS
	 * this function NOT REQUIRE user to login
	 */
	public function actionSaveSubscription()
	{
		ini_set("allow_url_fopen", true);
		//
 		$raw_post_data = file_get_contents('php://input');
 		if (false === $raw_post_data) {
 			throw new \Exception('Could not read from the php://input stream or invalid Subscription object received.');
 		}
 		$raw = json_decode($raw_post_data);
		$browser = $_SERVER['HTTP_USER_AGENT'];

		$Criteria=new CDbCriteria();
		$Criteria->compare('id_user',Yii::app()->user->objUser['id_user'], false);
		$Criteria->compare('browser',$browser, false);

		$vapidProvider=new CActiveDataProvider('PushSubscriptions', array(
			'criteria'=>$Criteria,
		));

		if ($vapidProvider->totalItemCount == 0 && $raw != null ){
			//save
			$vapid = new PushSubscriptions;
			$vapid->id_user = Yii::app()->user->objUser['id_user'];
			$vapid->browser = $browser;
			$vapid->endpoint = $raw->endpoint;
			$vapid->auth = $raw->keys->auth;
			$vapid->p256dh = $raw->keys->p256dh;
			$vapid->type = 'wallet';

			if (!$vapid->save()){
				echo 'Cannot save subscription on server!';
				exit;//
			}
			echo 'Subscription saved on server!';
		}else{
			//delete
			$iterator = new CDataProviderIterator($vapidProvider);
			foreach($iterator as $data) {
				echo '<pre>'.print_r($data->id_subscription,true).'</pre>';
				#exit;
				$vapid=PushSubscriptions::model()->findByPk($data->id_subscription)->delete();

				// if($vapid!==null)
				// 	$vapid->delete();
			}
			echo 'Subscriptions deleted on server!';
		}
	}

	public function actionChangelanguage()
	{
		$sourceLanguages = [
			'it' => 'it_it',
			'en' => 'en_us',
		];
    	setcookie('lang', $_POST['lang']);
		setcookie('langSource', $sourceLanguages[$_POST['lang']]);
		echo CJSON::encode(['success'=>true],true);
	}

	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionIndex($id)
	{
		//$settings = Settings::loadUser(crypt::Decrypt($id));
		$social = Socialusers::model()->findByAttributes(['id_user'=>crypt::Decrypt($id)]);
		if (null === $social){
			$social = new Socialusers;
		}

		$userForm = new SettingsUserForm;
		$settings = Settings::loadUser(crypt::Decrypt($id));
		$userForm->attributes = (array)$settings;

		//carico il wallet selezionato nei settings
		if (!(isset($settings->id_wallet))){
			$wallet_address = '0x0000000000000000000000000000000000000000';
		}else{
			$wallet = Wallets::model()->findByPk($settings->id_wallet);
			$wallet_address = $wallet->wallet_address;
		}

		$this->render('index',array(
			'user'=>$this->loadUserModel(crypt::Decrypt($id)),
			'userForm'=>$userForm,
			'social'=>$social,
			'wallet_address'=>$wallet_address, // indirizzo del wallet dell'utente
		));

	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return Users the loaded model
	 * @throws CHttpException
	 */
	public function loadUserModel($id)
	{
		$model=Users::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}











}
