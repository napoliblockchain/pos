<?php
namespace app\models;

class AmountForm extends \yii\base\Model
{
    public $amount = '';

    public function rules()
    {
        return [
            ['amount', 'filter', 'filter' => static fn($value) => is_string($value) ? trim($value) : ''],
            ['amount', 'required', 'message' => 'Inserisci un importo.'],
            ['amount', 'match', 'pattern' => '/^(?:0|[1-9][0-9]{0,4})(?:[.,][0-9]{1,2})?$/D', 'message' => 'Usa un importo in euro con massimo due decimali.'],
            ['amount', function ($attribute) {
                if (!$this->hasErrors($attribute) && $this->cents() < 1) $this->addError($attribute, 'Importo maggiore di zero richiesto.');
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
