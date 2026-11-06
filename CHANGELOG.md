# Changelog

## [1.0.0] - Unreleased

### Added

- SMS login verification codes for agents (two-factor method "SMS (kwtSMS)").
- New ticket SMS alerts for agents and ticket confirmation SMS for customers, with templates.
- Test mode until "Send real SMS" is turned on (live sending is opt-in, so a setting osTicket drops on a new instance
  falls back to test mode); failed messages logged to the osTicket system log.
- Tested on osTicket 1.18.4 (PHP 8.3): customer confirmation and agent alert sent with test=1.
