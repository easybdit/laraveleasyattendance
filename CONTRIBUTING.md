# Contributing

Thanks for considering a contribution to LaravelEasyAttendance.

## Setup

```bash
git clone https://github.com/easybdit/laraveleasyattendance.git
cd laraveleasyattendance
composer install
```

## Running tests

```bash
composer test
```

Runs against sqlite in-memory by default (Orchestra Testbench) — no service container needed locally. CI runs the same suite against MySQL 8; to match that locally, override via env vars (no file to edit):

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=your_test_db DB_USERNAME=... DB_PASSWORD=... composer test
```

Please add or update tests for any behavior change — see `tests/Feature` for the existing coverage and `tests/TestCase.php` for how the test app is bootstrapped (feature flags, subject model, migrations).

## Pull requests

- Keep a PR focused on one change; unrelated fixes make it harder to review.
- Update `README.md`/`CHANGELOG.md` when the change is user-facing (a new config key, event, route, or behavior).
- `composer test` must pass. CI (GitHub Actions) runs it again on PHP 8.2/8.3/8.4 against MySQL.
- If you're touching a `date`-cast column comparison, use `whereDate()` rather than `where()` — see the note in the README's Testing section for why an exact-string comparison silently only works on MySQL.

## Reporting issues

Please include: the package version, Laravel version, PHP version, and — for anything attendance/device-sync related — whether you're on pull or push (ADMS) mode.
