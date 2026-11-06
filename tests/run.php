<?php
/**
 * Unit tests with small stand-ins for the osTicket classes the plugin touches. No network: the kwtSMS client is
 * replaced by KwtSmsSender::$transport. Numbers use country code 999 (unassigned), so nobody can receive them.
 *
 * Usage: php tests/run.php
 */

define('INCLUDE_DIR', __DIR__ . '/stubs/');
require __DIR__ . '/stubs/osticket.php';
require __DIR__ . '/../kwtsms.php';

$fails = 0;
$count = 0;
function check($name, $cond) {
    global $fails, $count;
    $count++;
    if (!$cond) {
        $fails++;
        echo "FAIL $name\n";
    }
}

$calls = array();
KwtSmsSender::$transport = function ($numbers, $message, $sender, $test) use (&$calls) {
    $calls[] = compact('numbers', 'message', 'sender', 'test');
    return array('result' => 'OK', 'msg-id' => 'm1');
};

// render
check('render', KwtSmsSender::render('#{ticket_number} {subject} {name} {other}',
    array('ticket_number' => '123456', 'subject' => "<b>Printer</b>\n broken", 'name' => 'Sara'))
    === '#123456 Printer broken Sara {other}');

// sender defaults and guards
$s = new KwtSmsSender('', '', '', false);
check('not configured', $s->send('99912345678', 'x')['code'] === 'NOT_CONFIGURED');
$s = new KwtSmsSender('osticket_username', 'osticket_password', '', true);
check('no number', $s->send(' , ', 'x')['code'] === 'NO_NUMBER');
check('send ok', $s->send('99912345678, 99912345679', 'hello')['result'] === 'OK');
check('numbers split', $calls[0]['numbers'] === array('99912345678', '99912345679'));
check('default sender', $calls[0]['sender'] === 'KWT-SMS');
check('test flag passed', $calls[0]['test'] === true);

// a throwing client is turned into an ERROR answer, never an exception
KwtSmsSender::$transport = function () { throw new RuntimeException('boom'); };
check('exception caught', $s->send('99912345678', 'x')['code'] === 'EXCEPTION');
KwtSmsSender::$transport = function ($numbers, $message, $sender, $test) use (&$calls) {
    $calls[] = compact('numbers', 'message', 'sender', 'test');
    return array('result' => 'OK');
};

// ticket created: customer and agent SMS, each only when switched on
$ticket = new StubTicket('654321', 'VPN down', 'Sara', '+999 1234 5670');
$calls = array();
$cfg = new StubConfig(array('customer_new_ticket' => true, 'agent_new_ticket' => false));
KwtSmsPlugin::onTicketCreated($ticket, $cfg, $s);
check('customer only', count($calls) === 1 && $calls[0]['numbers'] === array('+999 1234 5670'));
check('customer default text', $calls[0]['message'] === 'We received your ticket #654321: VPN down. We will reply soon.');

$calls = array();
$cfg = new StubConfig(array('customer_new_ticket' => false, 'agent_new_ticket' => true,
    'agent_numbers' => '99912345678,99912345679', 'agent_message' => 'T{ticket_number} {name}'));
KwtSmsPlugin::onTicketCreated($ticket, $cfg, $s);
check('agent only', count($calls) === 1 && $calls[0]['message'] === 'T654321 Sara');

$calls = array();
KwtSmsPlugin::onTicketCreated(new StubTicket('1', 's', 'n', ''), new StubConfig(array('customer_new_ticket' => true)), $s);
check('no customer phone, no SMS', count($calls) === 0);

$calls = array();
KwtSmsPlugin::onTicketCreated($ticket, new StubConfig(array()), $s);
check('all off by default', count($calls) === 0);

// failed SMS is logged, not thrown
KwtSmsSender::$transport = function () { return array('result' => 'ERROR', 'code' => 'ERR010', 'description' => 'zero balance'); };
$GLOBALS['ost'] = new StubOst();
KwtSmsPlugin::onTicketCreated($ticket, new StubConfig(array('customer_new_ticket' => true)), $s);
check('failure logged', strpos($GLOBALS['ost']->warnings[0], 'ERR010') !== false);

// 2FA backend: code sent to the saved number, validated once, wrong code refused
$calls = array();
KwtSmsSender::$transport = function ($numbers, $message, $sender, $test) use (&$calls) {
    $calls[] = compact('numbers', 'message', 'sender', 'test');
    return array('result' => 'OK');
};
KwtSms2FABackend::$sender = $s;
$GLOBALS['cfg'] = new StubSiteConfig('Helpdesk');
$_SESSION = array();
$b = new KwtSms2FABackend();
$agent = new StubStaff(array('2fa-kwtsms' => array('config' => array('phone' => '99912345678'))));
check('2fa send', is_string($b->send($agent)) && count($calls) === 1);
preg_match('/code is (\d{6})/', $calls[0]['message'], $m);
check('2fa message', isset($m[1]) && strpos($calls[0]['message'], 'Helpdesk: your login code is') === 0);
check('2fa wrong code', $b->validate(new StubForm(array('token' => '000000' === $m[1] ? '111111' : '000000')), $agent) === false);
check('2fa right code', $b->validate(new StubForm(array('token' => $m[1])), $agent) === true && $agent->cleared);
check('2fa no phone', $b->send(new StubStaff(array())) === false);

KwtSmsSender::$transport = function () { return array('result' => 'ERROR', 'code' => 'ERR003', 'description' => 'auth'); };
check('2fa send failure returns false', $b->send($agent) === false);

echo ($fails ? "FAILED $fails of $count\n" : "OK $count tests\n");
exit($fails ? 1 : 0);
