<?php
/**
 * kwtSMS plugin settings (Admin Panel > Manage > Plugins > kwtSMS > Instances).
 */

require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.forms.php';

class KwtSmsPluginConfig extends PluginConfig {

    const DEFAULT_CUSTOMER_MESSAGE = 'We received your ticket #{ticket_number}: {subject}. We will reply soon.';
    const DEFAULT_AGENT_MESSAGE = 'New ticket #{ticket_number} from {name}: {subject}';

    function translate() {
        return Plugin::translate('kwtsms');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'account' => new SectionBreakField(array(
                'label' => $__('kwtSMS account'),
                'hint' => $__('Use the API username and password of your kwtSMS account, not your mobile number.'),
            )),
            'username' => new TextboxField(array(
                'label' => $__('API username'),
                'required' => true,
                'configuration' => array('size' => 40, 'length' => 100),
            )),
            'password' => new PasswordField(array(
                'label' => $__('API password'),
                'required' => false,
                'validator' => 'noop',
                'hint' => $__('Leave empty to keep the saved password.'),
                'configuration' => array('size' => 40, 'length' => 100, 'key' => 'kwtsms'),
            )),
            'sender' => new TextboxField(array(
                'label' => $__('Sender ID'),
                'default' => 'KWT-SMS',
                'configuration' => array('size' => 15, 'length' => 11),
            )),
            // 2026-11-07 00:54 +03: "Test mode" (default on) replaced by "Send real SMS" (default off). osTicket 1.18.4 stores
            // every checkbox as off when an instance is first added (seen in the Docker QA: test mode posted ticked, saved 0),
            // so a default-on test mode silently became live sending. Now a dropped checkbox means test mode, the safe side.
            // Help text: test messages hold credits until deleted from the Sending Queue (was "no credits"). Task osticket-new.
            'live_mode' => new BooleanField(array(
                'label' => $__('Send real SMS'),
                'default' => false,
                'configuration' => array(
                    'desc' => $__('Off = test mode: messages are queued on kwtSMS but not delivered, and their credits are held until you delete them from the Sending Queue in your kwtSMS account. Turn on when you are ready to send.'),
                ),
            )),
            'customer' => new SectionBreakField(array(
                'label' => $__('Customer SMS'),
            )),
            'customer_new_ticket' => new BooleanField(array(
                'label' => $__('New ticket'),
                'default' => false,
                'configuration' => array(
                    'desc' => $__('Send an SMS to the customer when a ticket is created, if the customer has a phone number.'),
                ),
            )),
            'customer_message' => new TextareaField(array(
                'label' => $__('Message'),
                'default' => self::DEFAULT_CUSTOMER_MESSAGE,
                'hint' => $__('Placeholders: {ticket_number}, {subject}, {name}.'),
                'configuration' => array('rows' => 3, 'cols' => 60, 'html' => false),
            )),
            'agents' => new SectionBreakField(array(
                'label' => $__('Agent alerts'),
            )),
            'agent_new_ticket' => new BooleanField(array(
                'label' => $__('New ticket'),
                'default' => false,
                'configuration' => array(
                    'desc' => $__('Send an SMS to the numbers below when a ticket is created.'),
                ),
            )),
            'agent_numbers' => new TextboxField(array(
                'label' => $__('Alert numbers'),
                'hint' => $__('International format, separated by commas, for example 965XXXXXXXX.'),
                'configuration' => array('size' => 60, 'length' => 500),
            )),
            'agent_message' => new TextareaField(array(
                'label' => $__('Message'),
                'default' => self::DEFAULT_AGENT_MESSAGE,
                'hint' => $__('Placeholders: {ticket_number}, {subject}, {name}.'),
                'configuration' => array('rows' => 3, 'cols' => 60, 'html' => false),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        list($__, $_N) = self::translate();
        if (!empty($config['agent_new_ticket']) && trim((string) $config['agent_numbers']) === '') {
            $errors['err'] = $__('Add at least one alert number, or turn off agent alerts.');
            return false;
        }
        return true;
    }
}
