# Security Policy

## Supported Versions

Only the latest major version receives security fixes. Older majors are not backported.

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| 1.x     | :x:                |
| < 1.0   | :x:                |

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Instead, report privately via one of:

- [GitHub Security Advisories](https://github.com/easybdit/laraveleasyattendance/security/advisories/new) (preferred)
- Email: **muradbd.info@gmail.com**

Include as much detail as you can:

- Affected version(s) and Laravel version
- Steps to reproduce, or a minimal test case
- Impact (what an attacker could do)
- Any suggested fix, if you have one

You should get an initial response within **72 hours**. If the report is confirmed, we'll work with you on a fix and coordinated disclosure timeline, and credit you in the release notes unless you'd rather stay anonymous.

## Scope Notes

- **ADMS/Push device routes** (`/attendance/iclock/*` by default) are intentionally unauthenticated — a biometric device presents nothing but its serial number over the ZKTeco push protocol, so there is no credential to check. Mitigations available in config (`config/attendance.php` → `device_sync`): `adms_verify_ip` (reject a push whose source IP doesn't match the device's registered IP), `adms_throttle` (rate limiting, on by default), and `adms_max_lines_per_push` (caps a single push batch). Deploying these routes behind a network you control (VPN, firewall allow-list to known device IPs) is recommended defense in depth.
- CSV export escapes formula-injection characters (`=`, `+`, `-`, `@`) by default — see `Http\Controllers\Concerns\ExportsCsv`.
- Report any bypass of the above, or any other issue (SQL injection, mass-assignment, auth bypass, IDOR on attendance/employee records, etc.) through the channels above.
