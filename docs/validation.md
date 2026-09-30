# Validation — 29 September 2026

The standalone MIS runs on the same Mac at http://localhost:8093 with document root `public/`.

Passed:

- Imported 22 indicator definitions, 112 period records, all other MIS tables (currently empty), and 135 original audit entries. Source/target record checksums matched. Source database unchanged.
- Created independent roles and new administrator/M&E credentials; six legacy website identities have disabled hashes, inactive status and no login entitlement.
- Runtime MIS DB user cannot read the website database and has no schema-administration privileges.
- Encrypted snapshot restored all 15 tables into an isolated DB with exact row checksums and foreign keys; damaged backup rejected; nonempty restore refused.
- HTTP tests against an isolated restored DB: unauthenticated redirects, legacy-account login rejection, admin/M&E/procurement/GRM role boundaries, protected management pages, CSRF rejection, FY/component/type filtering, integer count validation, draft isolation, submit, self-approval denial, independent approval, approved-public-result visibility, private-evidence exclusion, attribution history, account creation, first-password-change enforcement, deactivation/session revocation, legacy-account activation rejection, settings save and POST-only logout.
- Separate local daily backup LaunchAgent installed.
- Website's `/dashboard` redirects to MIS public results on the updated workspace server (`badmaal.test:8088`). Old admin/M&E data-entry routes redirect to the standalone MIS. Port-80 `badmaal.test` still serves an older administrator-owned Apache instance and was not changed.

Not verified or performed:

- Browser visual review: no connected browser was available. Pages were exercised through HTTP, not screenshots.
- cPanel login, upload, live DNS changes, trusted HTTPS certificate issuance, live database migration, production cron, off-server copies and live cutover: awaiting connected cPanel access.
- Full former public map/charts parity and site-level reporting are outside this first extraction.

## Original dashboard transfer — 30 September 2026

Copied the original website dashboard markup, CSS and chart/filter models into the independent MIS. Copied local Leaflet and the administration/site GeoJSON, and added self-hosted Inter and Font Awesome assets with licenses. Public results use MIS repositories in approved-only mode. The website dashboard was not modified by this transfer. Staff routes, accounts and permissions remain independent.

Passed: PDO, IR and finance model tests; PHP syntax; full isolated recovery/HTTP integration suite with the new payload; 12 map sites; all five dashboard panels; HTTP asset availability; publication after approval; private evidence exclusion; unchanged staff-login security policy; existing website dashboard still serves directly. Browser visual review remains unavailable because no browser connection is present. GRM is explicitly unavailable until its own data module is migrated.

## GRM data migration — 30 September 2026

Source and target row checksums matched for 19 cases and 94 category links; 11 private evidence files were copied and checksum-verified. Fourteen existing GRM audit entries matched the historical archive. No website data was changed. The public GRM payload contains aggregate totals and category/priority counts only; initial snapshot shows 19 cases, 17 received and 2 under review.

Encrypted recovery now includes evidence bytes and checksums as well as all 18 database tables. The integration suite checks administrator/GRM access, M&E/procurement denial, authenticated evidence downloads against all 11 restored hashes, and public payload exclusion of private case fields. Website intake is not redirected or synchronised by this migration.


## GRM production deployment and recovery — 1 October 2026

GRM is deployed at `https://mis.badmaal.so/?module=grm`. The protected register is `/grievances.php`. The selective migration added 19 cases, 94 category links and 11 evidence files; row checksums and all evidence hashes were verified. The existing 22 indicator definitions and 4 approved publications remain present. MIS Administrator and GRM Officer have case/evidence access; the public dashboard exposes aggregate counts only.

The live dashboard subsequently reported a MySQL authentication failure for its dedicated application user. Synchronizing that user's password restored PDO, IR and GRM data loading. Runtime privileges were returned to SELECT, INSERT, UPDATE and DELETE only. PHP errors now go to private storage, and `error_log` is blocked from HTTP access. A fresh encrypted production backup includes the GRM evidence files. Temporary migration snapshots and their key were removed from the server.
