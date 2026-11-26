<?php
namespace app\components;

use Yii;

/** Local custody only: Napay token, POS instance and current opening; grants are always rechecked by Napay. */
class OperatorSession extends \yii\base\Component
{
    private const KEY = 'pos.operator';
    private const OPENING = 'pos.opening';
    private const PAYMENTS = 'pos.payments';
    private const MAX_PAYMENTS = 20;
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function current(): ?array
    {
        $value = Yii::$app->session->get(self::KEY);
        if (!is_array($value) || !is_int($value['expires'] ?? null) || $value['expires'] <= time()
            || !is_string($value['token'] ?? null) || !preg_match('/^napos_[A-Za-z0-9_-]{43}$/D', $value['token'])
            || !is_string($value['name'] ?? null)) {
            Yii::$app->session->remove(self::OPENING);
            Yii::$app->session->remove(self::PAYMENTS);
            Yii::$app->session->remove(self::KEY);
            return null;
        }
        return $value;
    }

    public function establish(array $session): void
    {
        Yii::$app->session->regenerateID(true);
        // Una istanza per sessione browser: le altre schede della stessa sessione la condividono, un altro dispositivo no.
        Yii::$app->session->set(self::KEY, $session + ['instance' => self::uuid()]);
    }

    /** SIP-021 fase 2: istanza POS di questa sessione, inviata a Napay come instance_id. */
    public function instance(): ?string
    {
        $instance = $this->current()['instance'] ?? null;
        return is_string($instance) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $instance) ? $instance : null;
    }

    /** Apertura attiva custodita solo nella sessione server (mai nel browser), con l'etichetta mostrata. */
    public function opening(): ?array
    {
        $value = Yii::$app->session->get(self::OPENING);
        if (!is_array($value) || !NapayLoginClient::validOpening($value['opening'] ?? null) || !is_string($value['label'] ?? null)) {
            Yii::$app->session->remove(self::OPENING);
            return null;
        }
        return $value;
    }

    public function keepOpening(array $opening, string $label): void
    {
        Yii::$app->session->set(self::OPENING, ['opening' => $opening, 'label' => $label]);
    }

    public function dropOpening(): void
    {
        Yii::$app->session->remove(self::OPENING);
        Yii::$app->session->remove(self::PAYMENTS);
    }

    /**
     * SIP-021 fase 3: pagamenti creati da questa sessione POS sull'apertura corrente (ID => istante di creazione).
     * Solo questi sono consultabili dal browser; Napay riverifica comunque sessione e apertura.
     */
    public function rememberPayment(string $paymentId): void
    {
        $payments = $this->payments();
        $payments[$paymentId] = time();
        arsort($payments);
        Yii::$app->session->set(self::PAYMENTS, array_slice($payments, 0, self::MAX_PAYMENTS, true));
    }

    public function ownsPayment(string $paymentId): bool
    {
        return array_key_exists($paymentId, $this->payments());
    }

    /** @return array<string,int> */
    private function payments(): array
    {
        $value = Yii::$app->session->get(self::PAYMENTS);
        if (!is_array($value)) return [];
        return array_filter($value, static fn($time, $id) => is_string($id) && preg_match(self::UUID, $id) === 1 && is_int($time), ARRAY_FILTER_USE_BOTH);
    }

    /** Chiave di idempotenza di un form di apertura (UUID v4). */
    public static function newKey(): string
    {
        return self::uuid();
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public function forget(): void
    {
        $name = Yii::$app->session->name;
        Yii::$app->session->destroy();
        Yii::$app->response->cookies->remove($name);
    }
}
