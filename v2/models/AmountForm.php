<?php
namespace app\models;

class AmountForm extends \yii\base\Model
{
    public const MAX_CENTS = 100000;
    public $amount = '';

    public function rules()
    {
        return [
            ['amount', 'filter', 'filter' => static fn($value) => is_string($value) ? trim($value) : ''],
            ['amount', 'required', 'message' => \Yii::t('app', 'Enter an amount.')],
            ['amount', 'match', 'pattern' => '/^(?:0|[1-9][0-9]{0,4})(?:[.,][0-9]{1,2})?$/D', 'message' => \Yii::t('app', 'Use an amount with at most two decimals.')],
            ['amount', function ($attribute) {
                if ($this->hasErrors($attribute)) return;
                if ($this->cents() < 1) $this->addError($attribute, \Yii::t('app', 'Amount greater than zero required.'));
                // SIP-021 fase 3: stesso massimo per richiesta del backend Napay
                elseif ($this->cents() > self::MAX_CENTS) $this->addError($attribute, \Yii::t('app', 'Maximum amount per payment: 1000.00 TTS.'));
            }],
        ];
    }

    public function cents(): int
    {
        // Integer arithmetic only: this preview never computes prices in floating point.
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $this->amount), 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
