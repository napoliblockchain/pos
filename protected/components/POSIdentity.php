<?php

/**
 * UserIdentity represents the data needed to identity a user.
 * It contains the authentication method that checks if the provided
 * data can identity the user.
 */
class POSIdentity extends CUserIdentity
{
	const ERROR_USERNAME_NOT_MEMBER = 4;

	private $_id;
	/**
	 * Authenticates a user.
	 * The example implementation makes sure if the username and password
	 * are both 'demo'.
	 * In practical applications, this should be changed to authenticate
	 * against some persistent user identity storage (e.g. database).
	 * @return boolean whether authentication succeeds.
	 */
	public function authenticatePOS()
	{
		$save = new Save;

		//Creo la query
		$pairing=Pairings::model()->findByAttributes(array('sin'=>$this->username));
		if($pairing===null){
			$this->errorCode=self::ERROR_USERNAME_INVALID;
			$save->WriteLog('pos','useridentity','authenticate','Incorrect sin: '.$this->username);
		}
		else
		{
			$this->_id=$pairing->id_pos;
			$NomePOS = $pairing->label;
			//$this->_id=$pos->id_maker;
			//$this->setState('title', $record->title);
			$this->errorCode=self::ERROR_NONE;

			// Carico lo user type e la descrizione e l'assegno all'array di stato objUser
			$UsersType = new UsersType;
			$UserDesc=CHtml::listData($UsersType::model()->findAll(),'id_users_type','desc');
			$UserPrivileges=CHtml::listData($UsersType::model()->findAll(),'id_users_type','status');

			//carico i dati del pos
			$pos=Pos::model()->findByAttributes(array('id_pos'=>$this->_id));

			//$NomePOS = $pos->alias;
			$PosIdNegozio = $pos->id_store;
			$merchants=Merchants::model()->findByPk($pos->id_merchant);
			$users=Users::model()->findByPk($merchants->id_user);

			$save->WriteLog('pos','useridentity','authenticate','User '.$this->username. ' logged in.');

			$this->setState('objUser', array(
				'id_user' => $merchants->id_user,
				'sin' => $this->username,
				'id_pos' => $pos->id_pos,
				'id_merchant' => $pos->id_merchant,
				'name' => $NomePOS,
				'surname' => '',
				'ruolo' => $UserDesc[1],
				'email' => $users->email,
				'privilegi' => 10,
				'facade' => 'pos',
			));
		}
		return !$this->errorCode;
	}
}
