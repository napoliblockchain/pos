<?php
namespace app\components;

use Yii;

/** Local custody only: no store/POS grants or continuous remote validation implied. */
class OperatorSession extends \yii\base\Component
{
    private const KEY = 'pos.operator';

    public function current(): ?array
    {
        $value = Yii::$app->session->get(self::KEY);
        if (!is_array($value) || !is_int($value['expires'] ?? null) || $value['expires'] <= time()
            || !is_string($value['token'] ?? null) || !preg_match('/^napos_[A-Za-z0-9_-]{43}$/D', $value['token'])
            || !is_string($value['name'] ?? null)) {
            Yii::$app->session->remove(self::KEY);
            return null;
        }
        return $value;
    }

    public function establish(array $session): void
    {
        Yii::$app->session->regenerateID(true);
        Yii::$app->session->set(self::KEY, $session);
    }

    public function forget(): void
    {
        $name = Yii::$app->session->name;
        Yii::$app->session->destroy();
        Yii::$app->response->cookies->remove($name);
    }
}
