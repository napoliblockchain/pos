<?php

/**
 * This is the model class for table "np_shops".
 *
 * The followings are the available columns in table 'np_shops':
 * @property integer $id_shop
 * @property integer $id_merchant
 * @property integer $id_store
 * @property string $denomination
 * @property string $bps_shopid
 * @property integer $deleted
 */
class Shops extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'np_shops';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('id_merchant, id_store, denomination', 'required'),
			array('id_merchant, id_store, deleted', 'numerical', 'integerOnly'=>true),
			array('denomination', 'length', 'max'=>250),
			array('bps_shopid', 'length', 'max'=>50),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id_shop, id_merchant, id_store, denomination, bps_shopid, deleted', 'safe', 'on'=>'search'),
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
			'id_shop' => 'Descrizione',
			'id_merchant' => 'Commerciante',
			'id_store' => 'Negozio',
			'denomination' => 'Descrizione',
			'bps_shopid' => 'ID',
			'deleted' => 'Deleted',
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

		$criteria->compare('id_shop',$this->id_shop);
		$criteria->compare('id_merchant',$this->id_merchant);
		$criteria->compare('id_store',$this->id_store);
		$criteria->compare('denomination',$this->denomination,true);
		$criteria->compare('bps_shopid',$this->bps_shopid,true);
		$criteria->compare('deleted',$this->deleted);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Shops the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
