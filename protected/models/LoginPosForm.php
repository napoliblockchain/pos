<?php

/**
 * LoginForm class.
 * LoginForm is the data structure for keeping
 * user login form data. It is used by the 'login' action of 'SiteController'.
 */
class LoginPosForm extends CFormModel
{
	public $username;
	public $reCaptcha;

	private $_identity;

	/**
	 * Declares the validation rules.
	 * The rules state that username and password are required,
	 * and password needs to be authenticated.
	 */
	public function rules()
	{
		if (gethostname()=='CGF6135T'){
			return array(
				// username and password are required
				array('username', 'required'),

				// password needs to be authenticated
				array('username', 'authenticatePOS'),

				// secret is required
				array('reCaptcha ', 'required'),

			);

		}else{
			return array(
				// username and password are required
				array('username', 'required'),

				// password needs to be authenticated
				array('username', 'authenticatePOS'),

				// secret is required
				array('reCaptcha ', 'required'),
				array('reCaptcha', 'application.extensions.reCaptcha2.SReCaptchaValidator', 'secret' => Settings::load()->reCaptcha2PrivateKey,'message' => 'The verification code is incorrect.'),
			);

		}

	}

	/**
	 * Declares attribute labels.
	 */
	public function attributeLabels()
	{
		return array(
			'username'=>'ID POS',
			//'ga_cod'=>'Google 2FA',
		);
	}

	/**
	 * Authenticates the password.
	 * This is the 'authenticate' validator as declared in rules().
	 * @param string $attribute the name of the attribute to be validated.
	 * @param array $params additional parameters passed with rule when being executed.
	 */
	public function authenticatePOS($attribute,$params)
	{
		if(!$this->hasErrors())
		{
			$this->_identity=new POSIdentity($this->username,'');
			// if(!$this->_identity->authenticatePOS())
			//  	$this->addError('username','Nessun POS corrispondente.');
			$this->_identity->authenticatePOS();
			$errorCode = $this->_identity->errorCode;

			switch ($errorCode){
				case POSIdentity::ERROR_USERNAME_NOT_MEMBER:
					$this->addError('username',"L'iscrizione è scaduta. Provvedere al pagamento della quota associativa per il rinnovo.");
					break;

				case POSIdentity::ERROR_USERNAME_INVALID:
					$this->addError('username','Nessun POS corrispondente.');
					break;
			}

		}
	}

	/**
	 * Logs in the user using the given username and password in the model.
	 * @return boolean whether login is successful
	 */
	public function login()
	{
		if($this->_identity===null)
		{
			$this->_identity=new POSIdentity($this->username);
			$this->_identity->authenticatePOS();
		}
		if($this->_identity->errorCode===POSIdentity::ERROR_NONE)
		{
			$duration=3600*24*90; // 90 days
			Yii::app()->user->login($this->_identity,$duration);
			return true;
		}
		else
			return false;
	}
}
