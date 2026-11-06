# kwtSMS for osTicket

SMS for [osTicket](https://osticket.com) through [kwtSMS](https://www.kwtsms.com), the SMS gateway for Kuwait and the
Gulf.

## Features

- **SMS login codes for agents:** a new two-factor method, "SMS (kwtSMS)", next to osTicket's e-mail and app codes.
- **New ticket alerts for agents:** an SMS to your on-call numbers when a ticket is created.
- **Ticket confirmations for customers:** an SMS with the ticket number when a customer with a phone number opens a
  ticket. Off by default.
- Message templates with `{ticket_number}`, `{subject}` and `{name}`. English and Arabic.
- Test mode: messages are queued on kwtSMS and never delivered, no credits used.
- A failed SMS never stops a ticket or a login; it is written to the osTicket system log.

## Requirements

- osTicket 1.18 (PHP 8.1 or later with curl and json).
- A kwtSMS account with API access. [Sign up free](https://www.kwtsms.com/signup/).

## Installation

1. Download `kwtsms-osticket-<version>.zip` from the [latest release](https://github.com/kwtsms/kwtsms-osticket/releases/latest).
2. Unzip it into `include/plugins/` of your osTicket, so you have `include/plugins/kwtsms/plugin.php`.
3. In the Admin Panel go to **Manage > Plugins > Add New Plugin**, install **kwtSMS**, and enable it.

## Setup

1. Open **kwtSMS**, then **Instances > Add New Instance**, and enable the instance.
2. Enter the API username and password of your kwtSMS account (not your mobile number) and your Sender ID
   (default `KWT-SMS`). Save.
3. Choose what to send: customer confirmations, agent alerts (with the alert numbers), or both.
4. SMS login codes: each agent opens **Profile > Authentication**, chooses **SMS (kwtSMS)**, enters a mobile number
   and confirms it with the code. To make two-factor login compulsory, use **Admin Panel > Settings > Agents**.

Phone numbers go in international format, for example `965XXXXXXXX`.

## Arabic

Arabic messages are sent as Unicode: one SMS page holds 70 Arabic characters, against 160 English ones. Keep Arabic
templates short to save credits.

## Troubleshooting

Failed messages are listed in **Admin Panel > Dashboard > System Logs** as warnings titled `kwtSMS`, with the kwtSMS
error code: `ERR003` wrong API login, `ERR010` zero balance, `ERR008` banned Sender ID, `ERR024` the server IP is not
in your kwtSMS API IP whitelist.

## Development

```sh
php tests/run.php     # unit tests with osTicket stand-ins, no network
sh build.sh           # release zip in dist/
```

## Support

Help: https://www.kwtsms.com/support.html | FAQ: https://www.kwtsms.com/faq/ | support@kwtsms.com

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT, (c) kwtSMS
