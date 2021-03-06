<?php

/**
 * This is the model class for table "np_notifications".
 *
 * The followings are the available columns in table 'np_notifications':
 */
class Notifications_readers extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'np_notifications_readers';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('id_user, id_notification', 'required'),
			//array('id_notification', 'unique'),
			array('id_notification', 'unique', 'criteria'=>array(
					'condition'=>'id_user=:id_user',
					'params'=>array(':id_user'=>$this->id_user),

				)),
			array('id_user, id_notification, alreadyread', 'numerical', 'integerOnly'=>true),

			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id_notification_reader, id_notification, id_user, alreadyread', 'safe', 'on'=>'search'),
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

	public function scopes() {
			return array(
					'orderById' => array('order' => 'id_notifications_reader DESC'),
			);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id_notification_reader' => 'id_notification_reader',
			'id_user' => 'id_user',
			'id_notification' => 'id_notification',
				'alreadyread' => 'alreadyread',

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

		$criteria->compare('id_notification_reader',$this->id_notification_reader);
		$criteria->compare('id_notification',$this->id_notification,true);
		$criteria->compare('id_user',$this->id_user,true);
			$criteria->compare('alreadyread',$this->alreadyread,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Notifications the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
