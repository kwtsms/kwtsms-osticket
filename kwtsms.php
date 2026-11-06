<?php
/**
 * kwtSMS plugin for osTicket: wires the settings to the SMS login code backend and the ticket signals.
 */

require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.2fa.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class.kwtsms.php';
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class KwtSmsPlugin extends Plugin {
    var $config_class = 'KwtSmsPluginConfig';

    function bootstrap() {
        $config = $this->getConfig();
        $sender = new KwtSmsSender($config->get('username'), $config->get('password'),
            $config->get('sender'), $config->get('test_mode'));
        if (!$sender->isConfigured()) {
            return;
        }

        KwtSms2FABackend::$sender = $sender;
        TwoFactorAuthenticationBackend::register('KwtSms2FABackend');

        Signal::connect('ticket.created', function ($ticket) use ($config, $sender) {
            KwtSmsPlugin::onTicketCreated($ticket, $config, $sender);
        });
    }

    /** Customer confirmation and agent alert for a new ticket. A failed SMS is logged, never thrown. */
    static function onTicketCreated($ticket, $config, KwtSmsSender $sender) {
        global $ost;

        $vars = array(
            'ticket_number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'name' => (string) $ticket->getName(),
        );

        $jobs = array();
        if ($config->get('customer_new_ticket') && ($phone = trim((string) $ticket->getPhoneNumber())) !== '') {
            $jobs[] = array('customer', $phone,
                $config->get('customer_message') ?: KwtSmsPluginConfig::DEFAULT_CUSTOMER_MESSAGE);
        }
        if ($config->get('agent_new_ticket') && trim((string) $config->get('agent_numbers')) !== '') {
            $jobs[] = array('agent', $config->get('agent_numbers'),
                $config->get('agent_message') ?: KwtSmsPluginConfig::DEFAULT_AGENT_MESSAGE);
        }

        foreach ($jobs as $job) {
            list($who, $numbers, $template) = $job;
            $result = $sender->send($numbers, KwtSmsSender::render($template, $vars));
            if ((!isset($result['result']) || $result['result'] !== 'OK') && $ost) {
                $ost->logWarning('kwtSMS', sprintf('New ticket SMS (%s) for ticket #%s failed: %s %s', $who,
                    $vars['ticket_number'], $result['code'] ?? '', $result['description'] ?? ''), false);
            }
        }
    }
}
