# Deployment on the existing cPanel server

Status: prepared, not executed. cPanel endpoint supplied by the owner: https://cpanel.badmaal.so/. Browser access was unavailable during implementation. Never put a temporary `/cpsess.../` URL into configuration or source code.

## Domain and TLS

1. Sign into cPanel. Open **Domains → Create A New Domain**. Enter `mis.badmaal.so`, disable sharing the main website document root, and choose a separate root ending in `mis-badmaal/public` (for example `/home/CPANELUSER/mis-badmaal/public`). If the host restricts document roots to public_html, ask it to allow this layout or use a separate directory under public_html with the supplied deny rules on private parents. Confirm PHP can read private application files. [Official subdomain instructions](https://support.cpanel.net/hc/en-us/articles/360052780313-How-to-create-a-subdomain).
2. In the authoritative DNS zone, create only the `mis` record. Proposed A value: `192.250.235.217`, the IPv4 address returned for `badmaal.so` on 29 September 2026. **Confirm it matches the hosting account's server IP before saving.** Nameservers at inspection were `ns1.mysecurecloudhost.com` and `ns2.mysecurecloudhost.com`; no A/AAAA/CNAME answer was returned for `mis.badmaal.so`. If cPanel manages the authoritative zone, use its Zone Editor. Do not change the main domain, MX, or nameservers. [Zone Editor documentation](https://docs.cpanel.net/cpanel/domains/zone-editor/).
3. Include `mis.badmaal.so` in the hosting AutoSSL process, request issuance through the controls available in the account or hosting support, and confirm its active certificate covers this hostname. Enable HTTPS redirection only after issuance; leave certificate-validation challenge paths working. No wildcard certificate is required. [SSL/TLS Status documentation](https://docs.cpanel.net/cpanel/security/ssl-tls-status/).

## Application deployment

1. Build a source-only release with `python3 scripts/package.py`. Upload and extract it to the independent MIS directory. Do not upload the development `config/local.php`, initial password file, sessions, backup key or database dump publicly.
2. Select PHP 8.2+ with PDO MySQL, mbstring, OpenSSL and zlib. Check database compatibility with the source before migration. Run `php scripts/prepare-runtime.php` using cPanel Terminal/SSH; this creates the private writable runtime directories and a new backup key.
3. Create a distinct cPanel database and database user. Use separate migration credentials if needed for schema creation. Runtime privileges need SELECT, INSERT, UPDATE and DELETE on the MIS database only, with no website database grants.
4. Create `config/local.php` from the example: production environment, `https://mis.badmaal.so`, and the MIS database credentials. Keep it private/readable only by the application account. The application's public directory must contain no credentials or database exports.
5. Freeze website MIS writes, take a website backup, and import the **live** source into the empty MIS database. Run the importer on the server with `--source-config` pointing to the real website database config and `--target=CPANELUSER_mis`. Use `MIS_IMPORT_TARGET_USER`, `MIS_IMPORT_TARGET_PASS`, and optionally `MIS_IMPORT_TARGET_HOST` / `MIS_IMPORT_TARGET_PORT` for independent target migration credentials. Do not use the local-only `--provision` option on cPanel. Configure secrets through private files/session environment, not shared command history.
6. Check the migration manifest against the live source and verify publication markers, workflow events and actor references. Historical users are disabled attribution identities. Pending legacy drafts cannot be resubmitted by new identities automatically; settle the old queue before cutover or agree an explicit transfer policy. No historical authorship is silently reassigned.
7. Retrieve the two fresh initial credentials from the private file, change both passwords, and record actual staff names/emails through Users. Website credentials must fail on MIS. Remove the initial credential file after secure handoff.
8. Run the deployment checks below, install the independent backup job and arrange an off-server copy of encrypted backups **and a separately protected recovery key**. Only then change the deployed website dashboard redirect to `https://mis.badmaal.so/index.php`. Keep website and MIS releases independent.

## Checks before switching the public link

- HTTPS works with a trusted hostname-valid certificate; HTTP redirects to HTTPS.
- `/config/local.php`, `/storage/initial-credentials.txt`, backup/key paths, and `.git` are not accessible from the MIS domain.
- Staff login forces first password change; website super-admin credentials fail; M&E cannot administer users/roles/settings.
- Data-entry filters work; draft → submit → another user's approval → public actual works using a clearly identified test record that is removed or excluded afterward.
- A real encrypted backup restores into a separate empty database and all table checksums match. Scheduled backup logs and domain access logs are available to the operator.
- Website link redirects to the MIS public results, and Staff Login leads to the MIS login. Never forward website session cookies or implement shared sessions.

The same hosting server can serve both apps. Separate application credentials do not by themselves isolate two apps from a compromised shared Unix/cPanel account. A separate cPanel account under the same server provides a stronger filesystem boundary if the hosting plan supports it.
