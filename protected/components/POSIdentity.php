<?php

/**
 * UserIdentity represents the data needed to identity a user.
 * It contains the authentication method that checks if the provided
 * data can identity the user.
 */
class POSIdentity extends CUserIdentity
{
	const ERROR_USERNAME_NOT_MEMBER = 4;
	const ERROR_USERNAME_NOT_PAYER = 6;

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

			//$NomePOS .= chr(32).$stores->denomination;
			//$NomePOS .= chr(32).$merchants->denomination;

			/*
			*	VERIFICA SE IL SOCIO HA PAGATO LA QUOTA D'ISCRIZIONE
			*/
			$timestamp = time();
			$criteria = new CDbCriteria();
			$criteria->compare('id_user',$merchants->id_user, false);

			$provider = Pagamenti::model()->Paid()->OrderByIDDesc()->findAll($criteria);
			if ($provider === null){
				//$expiration_membership = $timestamp;
				$this->errorCode=self::ERROR_USERNAME_NOT_PAYER;
				$save->WriteLog('pos','useridentity','authenticate','User not payer: '.$this->username);
				return !$this->errorCode;
			}else{
				$provider = (array) $provider;
				if (count($provider) == 0)
					$expiration_membership = 1;
				else
					$expiration_membership = strtotime($provider[0]->data_scadenza);
			}
			// scadenza entro il 31 gennaio per provvedere all'iscrizione (se la data_scadenza
			// è al 31 dicembre)
			// temporaneamente posticipato al 28 febbraio
			$expiration_membership += (31+28) *24*60*60;
			if ($expiration_membership <= $timestamp){
				$this->errorCode=self::ERROR_USERNAME_NOT_MEMBER;
				$save->WriteLog('pos','useridentity','authenticate','User not member: '.$this->username);
				return !$this->errorCode;
			}

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
