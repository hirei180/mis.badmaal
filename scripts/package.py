#!/usr/bin/env python3
"""Build a source-only deployment archive. Credentials, DB data and keys never enter it."""
import pathlib, tarfile, datetime, hashlib
root=pathlib.Path(__file__).resolve().parents[1]
out=root/'storage/releases';out.mkdir(parents=True,exist_ok=True)
archive=out/('mis-badmaal-source-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d-%H%M%S')+'.tar.gz')
blocked={'config/local.php','config/operations.php','config/backup.key'}
with tarfile.open(archive,'w:gz') as tar:
    for path in sorted(root.rglob('*')):
        rel=path.relative_to(root)
        if not path.is_file() or any(p in {'.git','__pycache__','storage'} for p in rel.parts) or str(rel) in blocked or path.name=='.DS_Store':continue
        tar.add(path,arcname='mis-badmaal/'+str(rel),recursive=False)
checksum=hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix(archive.suffix+'.sha256').write_text(checksum+'  '+archive.name+'\n')
print(archive)
print('SHA256 '+checksum)
