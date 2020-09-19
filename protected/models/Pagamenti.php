<?php

/**
 * This is the model class for table "st_pagamenti".
 *
 * The followings are the available columns in table 'st_pagamenti':
 * @property integer $id_pagamento
 * @property integer $id_user
 * @property integer $id_quota
 * @property double $importo
 * @property integer $id_tipo_pagamento
 * @property string $data_registrazione
 * @property string $data_inizio
 * @property string $data_scadenza
 */
class Pagamenti extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'st_pagamenti';
	}

	public function scopes() {
	    return array(
			//utilizzato in controller e useridentity
	        'OrderByIDDesc' => array('order' => 'id_pagamento DESC '),
			//'OrderByDueDateDesc' => array('order' => 'data_scadenza DESC '),
			'Paid'=>array(
			   'condition'=>'status="paid"',
		   ),
	    );
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('id_user, id_quota, importo, id_tipo_pagamento', 'required'),
			array('id_user, id_quota, id_tipo_pagamento, anno, progressivo', 'numerical', 'integerOnly'=>true),
			array('importo', 'numerical'),
			array('id_invoice_bps, paypal_txn_id', 'length', 'max'=>60),
			array('status', 'length', 'max'=>250),
			array('data_registrazione, data_inizio, data_scadenza', 'safe'),
			array('data_registrazione, data_inizio, data_scadenza', 'type', 'type' => 'date', 'message' => '{attribute}: non è nel formato corretto! (gg/mm/aaaa)', 'dateFormat' => 'dd/MM/yyyy'),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id_pagamento, id_user, id_quota, importo, id_tipo_pagamento, data_registrazione, data_inizio, data_scadenza, id_invoice_bps, paypal_txn_id, status', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id_pagamento' => '#',
			'id_user' => 'Nome',
			'id_quota' => 'Id Quota',
			'importo' => 'Importo',
			'id_tipo_pagamento' => 'Tipo di Pagamento',
			'data_registrazione' => 'Data Registrazione',
			'data_inizio' => 'Data Inizio',
			'data_scadenza' => 'Data Scadenza',
			'id_invoice_bps' => 'Codice Transazione',
			'paypal_txn_id' => 'Transazione Paypal',
			'status' => 'Stato',
			'anno' => 'Anno',
			'progressivo' => 'Progressivo',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
	public function search()
	{
		// @todo Please modify the following code to remove attributes that should not be searched.

		$criteria=new CDbCriteria;
		if (Yii::app()->user->objUser['privilegi'] < 20){
		 	$criteria->compare('id_user',Yii::app()->user->objUser['id_user'],false);
		}else{
			if (isset($_GET['id'])){
				$criteria->compare('id_user',crypt::Decrypt($_GET['id']),false);
			}
		}

		//$criteria->compare('id_pagamento',$this->id_pagamento);
		//$criteria->compare('id_user',$this->id_user);
		$criteria->compare('id_quota',$this->id_quota);
		$criteria->compare('importo',$this->importo);
		$criteria->compare('id_tipo_pagamento',$this->id_tipo_pagamento);
		$criteria->compare('anno',$this->anno);
		$criteria->compare('progressivo',$this->progressivo);

		$criteria->compare('data_registrazione',$this->data_registrazione,true);
		$criteria->compare('data_inizio',$this->data_inizio,true);
		//$criteria->compare('data_scadenza',$this->data_scadenza,true);
		if ($this->data_scadenza !== null){
			$criteria->compare('data_scadenza',WebApp::data_eng($this->data_scadenza),true);
		}

		$criteria->compare('id_invoice_bps',$this->id_invoice_bps,true);
		$criteria->compare('paypal_txn_id',$this->paypal_txn_id,true);
		$criteria->compare('status',$this->status,true);


		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
				'sort'=>array(
		    		'defaultOrder'=>array(
		      	// 		'progressivo'=>true,
						// 'anno'=>true,
							'id_pagamento'=>true,
		    		)
		  		),
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Pagamenti the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
