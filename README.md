# BADMAAL MIS

Independent management information system extracted from the BADMAAL website on 29 September 2026. The MIS has no runtime dependency on website files, database connections, user passwords, roles or sessions.

## Live MIS

- Public dashboard: https://mis.badmaal.so/
- Staff login: https://mis.badmaal.so/login.php
- Uses its own `badmaal1_mis` database and database user, separate from the website. The current local MIS data was migrated on 30 September 2026, including the four approved reports. Existing staff credentials are preserved.
- HTTPS and daily encrypted production backups are enabled. See [deployment status](deploy/cpanel.md).

## Open locally

- Staff login: http://localhost:8093/login.php
- Public dashboard: http://localhost:8093/ — original PDO/IR cards, charts, filters, contract/finance tabs, and interactive 12-site map.
- Double-click `Start MIS.command` to start the local server after a restart.
- Initial account names: `mis.admin` and `me.officer`. Temporary passwords are in the private `storage/initial-credentials.txt` file. Each account must change its password at first sign-in. This file must never be uploaded as a public asset or committed.

### Local domain

The Apache virtual host in `deploy/local/mis.badmaal.test.conf` routes `http://mis.badmaal.test` to the MIS server on loopback port 8093. To activate the hostname on this Mac, run once from the project directory:

```sh
sudo bash scripts/enable-local-domain.sh
```

This adds the local hosts entry and gracefully reloads the existing Homebrew Apache. Start the MIS with `Start MIS.command` as usual; it opens the custom hostname once configured. Staff login: `http://mis.badmaal.test/login.php`; public results: `http://mis.badmaal.test/`.

## What is included

- Independent database (`badmaal_mis` locally), DB user with no website DB access, host-only MIS session cookie, password changes, throttled login, CSRF protection, inactivity expiry and session revocation.
- MIS Administrator and M&E Officer roles. Both can enter indicator results and review another user's submissions. Procurement and Finance retain basic entry modules; GRM is a reserved role/module foundation, with no imported grievance cases.
- Users, role permissions, settings and audit history managed inside MIS. Imported historical website identities are permanently excluded from authentication and activation.
- Filtered PDO/IR entry and reporting: FY, component, type, state, period; natural indicator sorting; count/percentage/currency input hints and validation. Approvals publish to the original public dashboard, transferred into this repository on 30 September 2026.
- Encrypted backups, a tested restore into an empty database, a local daily backup job, separate HTTP/application logs, and source-only release packaging.

## Migration status

22 definitions and 112 reporting-period records were copied with original IDs, values and timestamps. The local source had zero contracts, finance records, submissions, publications or submission events. The importer supports all of these tables and preserves their IDs and foreign keys. All 135 website audit entries were copied unchanged into a separately labelled historical archive. Six historical identities retain attribution but no password hashes or login rights from the website. Two fresh MIS accounts were created.

The importer records row counts and SHA-256 checksums in `migration_runs` and a private migration manifest. It refuses to write into a nonempty target. The source database is read-only throughout. Live cPanel data must be migrated from its own current snapshot; do not assume the development database equals production.

## Runtime

PHP 8.2+ with PDO MySQL, mbstring, OpenSSL and zlib; compatible MySQL/MariaDB; Apache with the document root set to **public/**. The local database and recovery were tested with the installed MySQL. Validate the hosting database version/collation before import.

Only `public/` is web-accessible. `app/`, `config/`, `storage/`, backups, keys and scripts remain outside that document root. Production refuses HTTP application requests; provision HTTPS and enable the hosting HTTPS redirect. JavaScript, CSS, Inter fonts, Font Awesome icons and Leaflet are served locally. The interactive map uses OpenStreetMap for background tiles; its project boundaries and site overlays are local.

## Operations and deployment

- [cPanel deployment](deploy/cpanel.md)
- [Operations, backups and recovery](docs/operations.md)
- [Validation results](docs/validation.md)
- [PAD review and unresolved methodology](docs/pad-review-and-mapping.md)

## Verification

```sh
php tests/prepare.php
python3 tests/integration.py
```

The test runner creates a uniquely named database, restores a real encrypted backup into it, verifies checksums, runs HTTP tests against a temporary loopback server, then drops only that test database. Tests require a local database operator able to create/drop test databases (`MIS_TEST_DB_USER`, `MIS_TEST_DB_PASS`, `MIS_TEST_DB_HOST`; local default root). Runtime application credentials cannot create/drop databases. Tests use isolated session and throttle files.

## Current limits

The 12-site catalogue is retained in `config/project-sites.geojson`; site-level entry and aggregation are not yet implemented. Imported baseline/target values and approval history are preserved rather than silently revised. PDO5's measured food-loss baseline remains separate in meaning from its 0% framework reduction baseline; see the PAD review. The staff and public dashboards include reporting coverage charts and target/approved-actual comparisons; the public dashboard also provides fiscal year, type and component filters. The former website's interactive map is not yet included. Production DNS, TLS, deployment and daily server backups are configured. Recurring off-server backup transfer remains to be configured; the initial production backup and recovery key have private local copies. The main website dashboard link has not been redirected.

## Public dashboard checks

```sh
node tests/test_pdo_dashboard_model.mjs
node tests/test_ir_dashboard_model.mjs
node tests/test_finance_dashboard_model.mjs
```

The HTTP integration suite checks draft isolation and approval publication through the public dashboard JSON payload, including the 12-site map, all five module panels and private-evidence exclusion. Executable scripts remain local; public data is embedded as an inert JSON block. Only the public dashboard permits OpenStreetMap tile images in its security policy.
