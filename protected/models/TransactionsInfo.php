<?php

/**
 * This is the model class for table "np_transactions_info".
 *
 * The followings are the available columns in table 'np_transactions_info':
 * @property integer $id_transaction_info
 * @property integer $id_transaction
 * @property string $cryptoCode
 * @property string $paymentType
 * @property double $rate
 * @property double $paid
 * @property double $price
 * @property double $due
 * @property string $address
 * @property integer $txCount
 * @property string $txId
 * @property integer $received
 * @property double $value
 * @property string $destination
 */
class TransactionsInfo extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'np_transactions_info';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('id_transaction, cryptoCode, paymentType, rate, paid, price, due, address, txCount', 'required'),
			array('id_transaction, txCount, received', 'numerical', 'integerOnly'=>true),
			array('rate, paid, price, due, value', 'numerical'),
			array('cryptoCode', 'length', 'max'=>10),
			array('paymentType', 'length', 'max'=>50),
			array('address, txId, destination', 'length', 'max'=>500),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id_transaction_info, id_transaction, cryptoCode, paymentType, rate, paid, price, due, address, txCount, txId, received, value, destination', 'safe', 'on'=>'search'),
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
			'id_transaction_info' => 'Id Transaction Info',
			'id_transaction' => 'Id Transaction',
			'cryptoCode' => 'Asset',
			'paymentType' => 'Tipo di Pagamento',
			'rate' => 'Tasso',
			'paid' => 'Pagato',
			'price' => 'Prezzo',
			'due' => 'Dovuto',
			'address' => 'Indirizzo',
			'txCount' => 'Tx Count',
			'txId' => 'Transazione',
			'received' => 'Data di Ricezione',
			'value' => 'Valore',
			'destination' => 'Indirizzo di destinazione',
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

		$criteria->compare('id_transaction_info',$this->id_transaction_info);
		$criteria->compare('id_transaction',$this->id_transaction);
		$criteria->compare('cryptoCode',$this->cryptoCode,true);
		$criteria->compare('paymentType',$this->paymentType,true);
		$criteria->compare('rate',$this->rate);
		$criteria->compare('paid',$this->paid);
		$criteria->compare('price',$this->price);
		$criteria->compare('due',$this->due);
		$criteria->compare('address',$this->address,true);
		$criteria->compare('txCount',$this->txCount);
		$criteria->compare('txId',$this->txId,true);
		$criteria->compare('received',$this->received);
		$criteria->compare('value',$this->value);
		$criteria->compare('destination',$this->destination,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return TransactionsInfo the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
