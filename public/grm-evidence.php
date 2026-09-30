<?php
require dirname(__DIR__).'/app/web.php';requireAuth();requireAnyPermission(['grm.view']);
$id=(int)($_GET['id']??0);$s=db()->prepare('SELECT f.*,g.evidence_original_name FROM grm_evidence_files f JOIN grievances g ON g.id=f.grievance_id WHERE f.grievance_id=?');$s->execute([$id]);$file=$s->fetch();
if(!$file||!preg_match('/^\d+-[a-f0-9]{64}$/',$file['storage_name'])){http_response_code(404);exit('Evidence not found.');}
$path=MIS_STORAGE.'/grm-evidence/'.$file['storage_name'];
if(!is_file($path)||is_link($path)||hash_file('sha256',$path)!==$file['sha256']){http_response_code(404);exit('Evidence unavailable.');}
audit('grm_evidence_downloaded',[],$id);
$name=basename(str_replace('\\','/',$file['evidence_original_name']?:'evidence-'.$id));
header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="evidence-'.$id.'"; filename*=UTF-8\'\''.rawurlencode($name));header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');session_write_close();readfile($path);
