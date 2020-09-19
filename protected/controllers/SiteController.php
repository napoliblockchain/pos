<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.Logo');
Yii::import('libs.NaPacks.SaveModels');
Yii::import('libs.NaPacks.Save');

class SiteController extends Controller
{
	public function init()
	{
		// if (isset(Yii::app()->user->objUser) && Yii::app()->user->objUser['facade'] != 'dashboard'){
		// 	Yii::app()->user->logout();
		// 	$this->redirect('index.php?r=site/index');
		// 	//$this->redirect(Yii::app()->homeUrl);
		// }
	}

	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
			// captcha action renders the CAPTCHA image displayed on the contact page
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xFFFFFF,
			),
			// page action renders "static" pages stored under 'protected/views/site/pages'
			// They can be accessed via: index.php?r=site/page&view=FileName
			'page'=>array(
				'class'=>'CViewAction',
			),
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
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array(
					'index',
					'error',
					'login',
					'logout',
					'contactForm', // bug form

				),
				'users'=>array('*'),
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				//'actions'=>array('index'),
				//'users'=>array('@'),
			),
		);
	}


	/**
	 * This is the default 'index' action that is invoked
	 * when an action is not explicitly requested by users.
	 */
	public function actionIndex()
 	{
 		$this->redirect(array('site/login'));
 	}

	/**
	 * Displays the contact page
	 */
	public function actionContactForm()
	{
		$this->layout='//layouts/column_login'; //NON ATTIVA IL BACKEND

		$model=new ContactForm;
		if(isset($_POST['ContactForm']))
		{
			$model->attributes=$_POST['ContactForm'];
			$model->reCaptcha=$_POST['reCaptcha'];
			if($model->validate())
			{
				if($_FILES['ContactForm']['error']['attach']==0){
					if($_FILES['ContactForm']['size']['attach'] < 3000000){ //< 3Mb

						$path = Yii::app()->basePath . '/../uploads/' . $_FILES['ContactForm']['name']['attach'];
						if (gethostname() == 'blockchain1'){
							$host = 'https://bolt-tts.tk';
						}elseif (gethostname()=='CGF6135T' || gethostname()=='NUNZIA'){ // SERVE PER LE PROVE IN UFFICIO
							$host = 'https://'.$_SERVER['HTTP_HOST'].'/bolt';
						}else{
							$host = 'https://bolt.napoliblockchain.it';
						}
						$wwwpath = $host.'/uploads/' . $_FILES['ContactForm']['name']['attach'];
						$model->attach = $wwwpath;

						move_uploaded_file($_FILES['ContactForm']['tmp_name']['attach'], $path);
					}else{
						$model->attach = '';
					}
		        }else{
					$model->attach = '';
				}
				$content = array(
					'name' => $model->name,
					'subject' => $model->subject,
					'email' => $model->email,
					'body' => $model->body,
					'attach' => $model->attach,
				);
				NMail::SendMail('contact','000abc',Yii::app()->params['adminEmail'],$content);

				Yii::app()->user->setFlash('contact',Yii::t('lang','Thank you for contacting us. We will respond to you as soon as possible.'));
				$this->refresh();
			}
		}
		$this->render('contact',array('model'=>$model));
	}



	/**
	 * This is the action to handle external exceptions.
	 */
	public function actionError()
	{
		if($error=Yii::app()->errorHandler->error)
		{
			if(Yii::app()->request->isAjaxRequest)
				echo $error['message'];
			else
				$this->render('error', $error);
		}
	}

	/**
	* Displays the login page
	*/
	public function actionLogin()
	{
		$this->layout='//layouts/column_login';
		$model=new LoginPosForm;
		if(isset($_POST['ajax']) && $_POST['ajax']==='login-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
		// collect user input data
		if(isset($_POST['LoginPosForm']))
		{
			$model->attributes=$_POST['LoginPosForm'];
			$model->reCaptcha=$_POST['reCaptcha'];
			// validate user input and redirect to the previous page if valid
			if($model->validate() && $model->login()){
				$this->redirect(array('keypad/index'));// per correggere errore con pwa che non fa il redirect dopo il login
			}
				//$this->redirect(Yii::app()->user->returnUrl);
		}
		#echo Yii::app()->user->objUser['facade'];
		#exit;

		if (Yii::app()->user->isGuest){
			if (isset($_GET['sin']))
				$model->username = $_GET['sin'];

			$this->render('login',array('model'=>$model)); // display the login form if not connected or validated user
		}else {
			$this->redirect(array('keypad/index'));
		}
	}

	/**
	* Logs out the current user and redirect to homepage.
	*/
	public function actionLogout()
	{
		Yii::app()->user->logout();
		$this->redirect(array('site/login'));
	}


}
