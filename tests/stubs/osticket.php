<?php
/**
 * Minimal stand-ins for the osTicket 1.18 classes the plugin uses, copied in shape from include/class.2fa.php and
 * include/class.plugin.php. Only for tests/run.php.
 */

function __($s) { return $s; }

class Plugin {
    static function translate($domain) {
        return array(function ($x) { return $x; }, function ($x, $y, $n) { return $n != 1 ? $y : $x; });
    }
}
class PluginConfig {}
class FormField { function __construct($o = array()) {} }
class TextboxField extends FormField {}
class PasswordField extends TextboxField {}
class TextareaField extends FormField {}
class BooleanField extends FormField {}
class SectionBreakField extends FormField {}
class Signal { static function connect($n, $f) {} }
class Misc { static function randNumber($n) { return str_pad((string) random_int(0, 999999), $n, '0', STR_PAD_LEFT); } }
class ExpiredOTP extends Exception {}

abstract class TwoFactorAuthenticationBackend {
    protected $timeout = 6;
    protected $maxstrikes = 3;
    static $id;
    function getId() { return static::$id; }
    function getTimeout() { return $this->timeout; }
    function getMaxStrikes() { return $this->maxstrikes; }
    protected function store($otp) {
        $store = &$_SESSION['_2fa'][$this->getId()];
        $store = array('otp' => $otp, 'time' => time(), 'strikes' => 0);
        return $store;
    }
    protected function _validate($otp, $strict = true) {
        $store = &$_SESSION['_2fa'][$this->getId()];
        $store['strikes'] += 1;
        if ($strict && $store['strikes'] > $this->getMaxStrikes()) {
            throw new ExpiredOTP('Too many attempts');
        }
        return !strcmp($store['otp'], $otp);
    }
    protected function onValidate($user) { $user->clear2FA(); }
    static function register($c) {}
}
abstract class Staff2FABackend extends TwoFactorAuthenticationBackend {}

class StubConfig {
    private $v;
    function __construct($v) { $this->v = $v; }
    function get($k) { return isset($this->v[$k]) ? $this->v[$k] : null; }
}
class StubTicket {
    function __construct($n, $s, $name, $phone) { $this->n = $n; $this->s = $s; $this->name = $name; $this->phone = $phone; }
    function getNumber() { return $this->n; }
    function getSubject() { return $this->s; }
    function getName() { return $this->name; }
    function getPhoneNumber() { return $this->phone; }
}
class StubOst {
    public $warnings = array();
    function logWarning($t, $m, $alert = true) { $this->warnings[] = $m; }
}
class StubSiteConfig {
    function __construct($t) { $this->t = $t; }
    function getTitle() { return $this->t; }
}
class StubStaff {
    public $cleared = false;
    function __construct($c) { $this->c = $c; }
    function get2FAConfig($id) { return isset($this->c[$id]) ? $this->c[$id] : array(); }
    function getUserName() { return 'agent1'; }
    function clear2FA() { $this->cleared = true; }
}
class StubForm {
    function __construct($c) { $this->c = $c; }
    function isValid() { return true; }
    function getClean() { return $this->c; }
}
