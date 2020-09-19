<?php

/**
 * This is the model class for table "np_transactions_data".
 *
 * The followings are the available columns in table 'np_transactions_data':
 * @property integer $id_pos_data
 * @property integer $id_transaction
 * @property string $cart
 * @property double $customAmount
 * @property double $discountPercentage
 * @property double $subTotal
 * @property double $discountAmount
 * @property double $tip
 * @property double $total
 */
class TransactionsData extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'np_transactions_data';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('id_transaction, cart, customAmount, discountPercentage, subTotal, discountAmount, tip, total', 'required'),
			array('id_transaction', 'numerical', 'integerOnly'=>true),
			array('customAmount, discountPercentage, subTotal, discountAmount, tip, total', 'numerical'),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id_pos_data, id_transaction, cart, customAmount, discountPercentage, subTotal, discountAmount, tip, total', 'safe', 'on'=>'search'),
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
			'id_pos_data' => 'Id Pos Data',
			'id_transaction' => 'Id Transaction',
			'cart' => 'Cart',
			'customAmount' => 'Custom Amount',
			'discountPercentage' => 'Discount Percentage',
			'subTotal' => 'Sub Total',
			'discountAmount' => 'Discount Amount',
			'tip' => 'Tip',
			'total' => 'Total',
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

		$criteria->compare('id_pos_data',$this->id_pos_data);
		$criteria->compare('id_transaction',$this->id_transaction);
		$criteria->compare('cart',$this->cart,true);
		$criteria->compare('customAmount',$this->customAmount);
		$criteria->compare('discountPercentage',$this->discountPercentage);
		$criteria->compare('subTotal',$this->subTotal);
		$criteria->compare('discountAmount',$this->discountAmount);
		$criteria->compare('tip',$this->tip);
		$criteria->compare('total',$this->total);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return TransactionsData the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
