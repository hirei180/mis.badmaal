#!/usr/bin/env python3
"""Install an independent daily backup job for the local macOS account."""
import pathlib, plistlib, shutil, subprocess, os
root=pathlib.Path(__file__).resolve().parents[1]
php=shutil.which('php')
if not php:raise SystemExit('PHP is required')
label='so.badmaal.mis.backup'
plist=pathlib.Path.home()/'Library/LaunchAgents'/f'{label}.plist'
plist.parent.mkdir(parents=True,exist_ok=True)
job={'Label':label,'ProgramArguments':[php,str(root/'scripts/backup.php')],
     'StartCalendarInterval':{'Hour':3,'Minute':10},
     'StandardOutPath':str(root/'storage/logs/backup.log'),'StandardErrorPath':str(root/'storage/logs/backup-error.log')}
plist.write_bytes(plistlib.dumps(job))
subprocess.run(['launchctl','bootout',f'gui/{os.getuid()}/{label}'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
subprocess.run(['launchctl','bootstrap',f'gui/{os.getuid()}',str(plist)],check=True)
print('Daily MIS backup scheduled at 03:10 local time (runs when this Mac is available).')
