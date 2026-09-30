# Independent MIS operations

## Backups

`php scripts/backup.php` creates an AES-256-GCM authenticated, compressed database backup under `storage/backups`. It includes all MIS tables, account hashes, settings, migration records, and historical audit records. The backup key is a 32-byte private file at `config/backup.key`; overrides are `MIS_BACKUP_KEY_FILE` and `MIS_BACKUP_DIR`. Neither the key nor backups are in source releases or version control.

Backups use a consistent database snapshot. Do not run schema updates during backup. This initial app has no uploaded evidence files; evidence is text/reference data in the database. If file attachments are introduced later, add coordinated file backups before enabling them. Source releases and server configuration must also be retained separately for full disaster recovery.

On this Mac, the installed user LaunchAgent `so.badmaal.mis.backup` schedules backup at **03:10 local time**, when the Mac is available. Its definition is in `~/Library/LaunchAgents/so.badmaal.mis.backup.plist`. Output and failures go to private `storage/logs/backup.log` and `backup-error.log`. Reinstall after moving the project with `python3 scripts/schedule-local-backups.py`.

Production needs a cPanel cron job; it is not scheduled by the local LaunchAgent. Configure the hosting PHP binary with an absolute path, for example:

```cron
10 3 * * * /HOST/PHP/BINARY /home/CPANELUSER/mis-badmaal/scripts/backup.php >> /home/CPANELUSER/mis-badmaal/storage/logs/backup.log 2>> /home/CPANELUSER/mis-badmaal/storage/logs/backup-error.log
```

Confirm the actual hosting PHP path and server timezone. Check successful backup timestamps daily. Keep daily/weekly/monthly copies according to the project's agreed retention policy; no automatic backup deletion is enabled yet. Arrange encrypted off-server copies with the hosting operator; this destination is not configured. Store the recovery key separately from those backups. Losing the key makes the encrypted backups unrecoverable.

## Restore

1. Preserve the failed system and keep it out of write service. Prepare an **empty, separate** recovery database and a temporary operator account with schema/data privileges there.
2. Obtain the encrypted backup and its separate key. Deploy a compatible source release, create runtime directories, and set `MIS_RESTORE_USER`, `MIS_RESTORE_PASS`, and optional `MIS_RESTORE_HOST` / `MIS_RESTORE_PORT` privately in the operator session.
3. Run:

```sh
php scripts/restore.php --file=/private/path/backup.backup --database=EMPTY_RECOVERY_DATABASE
```

The restore refuses the configured running database and any nonempty target, authenticates the backup, creates tables in dependency order with foreign-key checks enabled, inserts original rows, and compares every table's checksum. An interrupted restore can leave schema objects in the recovery DB; discard that **recovery** DB and retry into a new empty one. Never point this command at the website database.

4. Check user access, draft/review history and approved public output in isolation. Reconfigure `config/local.php` to use the validated recovered database with a scoped runtime account. Clear private session files to require fresh logins. Switch traffic only after validation. Record the incident and restore result.

Restore was tested on 29 September 2026: 15 tables, matching counts and exact row checksums, intact foreign-key relationships, rejection of damaged ciphertext and refusal to overwrite a populated database. Production recovery time and off-server recovery remain to be measured on the host.

## Releases and rollback

Keep this source in its own Git repository. Build with `python3 scripts/package.py`; verify the SHA-256 sidecar after transfer. Release archives exclude config secrets, keys, data, sessions, logs and credentials. Do not extract a release over private configuration without a backup. Preserve `config/local.php`, `config/backup.key` and `storage/` between updates. Never copy those files from the website.

Before changes: back up the DB, retain the previous source archive, run integration tests against a disposable DB, and apply any future database migrations during a write-maintenance window. Code rollback is safe only if the database schema remains compatible; otherwise use a separately verified recovery database. There is no automatic production deployment or schema updater configured yet.

## Logs and access

Local Apache writes independent `storage/logs/access.log` and `apache-error.log`. Application errors write `application.log`; logins and administrative changes go to `audit_logs`; reporting decisions go to `mis_submission_events`. The UI presents current MIS and imported website audit history separately. No log should contain raw passwords.

In cPanel, enable/retain the MIS subdomain access logs and include them in the host's log rotation. Restrict private directories to the application/operator account. Disable departing staff through Users, which invalidates their existing sessions. Role permission changes also revoke existing sessions. Review admin access and pending approvals routinely; self-approval is denied by the service, not merely hidden in the UI.
