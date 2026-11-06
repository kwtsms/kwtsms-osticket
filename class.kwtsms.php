<?php
/**
 * Sending, templates and the SMS login code backend. Kept free of osTicket globals where possible so it can be
 * unit tested.
 */

class KwtSmsSender {

    const VERSION = '1.0.0';

    /** @var callable|null replaces the kwtSMS client in tests: fn(array $numbers, string $message): array */
    public static $transport = null;

    private $username;
    private $password;
    private $sender;
    private $testMode;

    function __construct($username, $password, $sender, $testMode) {
        $this->username = (string) $username;
        $this->password = (string) $password;
        $this->sender = trim((string) $sender) !== '' ? trim((string) $sender) : 'KWT-SMS';
        $this->testMode = (bool) $testMode;
    }

    function isConfigured() {
        return $this->username !== '' && $this->password !== '';
    }

    /**
     * Send one message to one or more numbers. Returns the kwtSMS answer (result OK or ERROR with code and
     * description); never throws, so a failed SMS never breaks ticket creation or login.
     */
    function send($numbers, $message) {
        $numbers = array_values(array_filter(array_map('trim', is_array($numbers) ? $numbers : explode(',', (string) $numbers))));
        if (!$this->isConfigured()) {
            return array('result' => 'ERROR', 'code' => 'NOT_CONFIGURED', 'description' => 'kwtSMS API username or password is missing');
        }
        if (!$numbers) {
            return array('result' => 'ERROR', 'code' => 'NO_NUMBER', 'description' => 'No phone number');
        }
        try {
            if (self::$transport) {
                return call_user_func(self::$transport, $numbers, $message, $this->sender, $this->testMode);
            }
            $client = new \KwtSMS\KwtSMS($this->username, $this->password, $this->sender, $this->testMode, '',
                'kwtsms-osticket/' . self::VERSION);
            return $client->send($numbers, $message);
        } catch (\Throwable $e) {
            return array('result' => 'ERROR', 'code' => 'EXCEPTION', 'description' => $e->getMessage());
        }
    }

    /** Fill {ticket_number}, {subject} and {name}; unknown placeholders are left as typed. */
    static function render($template, array $vars) {
        $map = array();
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)));
        }
        return strtr((string) $template, $map);
    }
}

/**
 * SMS login verification codes for agents (Profile > Authentication in the agent panel). Same flow as osTicket's
 * built-in e-mail codes: a 6 digit code, valid 6 minutes, 3 attempts.
 */
class KwtSms2FABackend extends Staff2FABackend {
    static $id = '2fa-kwtsms';
    static $name = /* @trans */ 'SMS (kwtSMS)';
    static $desc = /* @trans */ 'Verification codes are sent by SMS';

    /** @var KwtSmsSender */
    static $sender;

    protected function getSetupOptions() {
        return array(
            'phone' => new TextboxField(array(
                'id' => 2, 'label' => __('Mobile number'), 'required' => true, 'default' => '',
                'hint' => __('International format, for example 965XXXXXXXX'),
                'validator' => 'regex',
                'configuration' => array('size' => 20, 'length' => 20, 'regex' => '/^\+?[0-9 ]{7,20}$/',
                    'validator-error' => __('Enter the mobile number in international format')),
            )),
        );
    }

    protected function getInputOptions() {
        return array(
            'token' => new TextboxField(array(
                'id' => 1, 'label' => __('Verification Code'), 'required' => true, 'default' => '',
                'validator' => 'number',
                'hint' => __('Please enter the code you were sent'),
                'configuration' => array(
                    'size' => 10, 'length' => 6,
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'validator-error' => __('Invalid Code format'),
                ),
            )),
        );
    }

    function validate($form, $user) {
        if (!($form->isValid() && ($clean = $form->getClean()) && $clean['token'])) {
            return false;
        }
        if (!$this->_validate($clean['token'])) {
            return false;
        }
        $this->onValidate($user);
        return true;
    }

    function send($user) {
        global $ost, $cfg;

        $info = $user->get2FAConfig($this->getId());
        $phone = isset($info['config']['phone']) ? $info['config']['phone'] : '';
        if (!$phone || !self::$sender) {
            return false;
        }

        $otp = Misc::randNumber(6);
        $this->store($otp);

        $site = $cfg ? $cfg->getTitle() : 'osTicket';
        $result = self::$sender->send($phone, sprintf('%s: your login code is %s. It expires in %d minutes.',
            $site, $otp, $this->getTimeout()));

        if (!isset($result['result']) || $result['result'] !== 'OK') {
            if ($ost) {
                $ost->logWarning('kwtSMS', sprintf('Login code SMS for agent %s failed: %s %s',
                    $user->getUserName(), $result['code'] ?? '', $result['description'] ?? ''), false);
            }
            return false;
        }
        // Not meant to be secure, only to avoid returning the plain code (same as the e-mail backend).
        return md5($otp);
    }
}
