<?php
namespace app\models;

class OperatorLoginForm extends \yii\base\Model
{
    public $email = '';
    public $password = '';

    public function rules()
    {
        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? trim($value) : ''],
            ['password', 'filter', 'filter' => static fn($value) => is_string($value) ? $value : ''],
            [['email', 'password'], 'required', 'message' => 'Inserisci email e password.'],
            ['email', 'string', 'max' => 254], ['email', 'email'],
            ['password', 'string', 'max' => 1024],
            ['password', function ($attribute) { if (strlen($this->password) > 1024) $this->addError($attribute, 'Password troppo lunga.'); }],
        ];
    }
}
