<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use InvalidArgumentException;
use RuntimeException;
final class GrmCaseService
{
 public const STATUSES=['received','pending','under_review','resolved','closed','archived','submitted'];
 public const PRIORITIES=['low','medium','high','urgent'];
 public const FIELDS=['subject'=>255,'description'=>12000,'complainant_name'=>180,'email'=>190,'phone'=>60,'region'=>120,'district'=>120,'preferred_followup'=>60,'response'=>12000,'review_comments'=>12000];
 public static function revision(array $case,array $categories):string {sort($categories);return hash('sha256',json_encode([$case,$categories],JSON_THROW_ON_ERROR));}
 public static function validate(array $input):array {
  $data=[];
  foreach(self::FIELDS as $key=>$max){$v=$input[$key]??'';if(!is_string($v)||mb_strlen($v)>$max)throw new InvalidArgumentException('Invalid '.str_replace('_',' ',$key).'.');$data[$key]=trim($v);}
  if($data['subject']===''||$data['description']==='')throw new InvalidArgumentException('Subject and description are required.');
  if($data['email']!==''&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');
  foreach(['status'=>self::STATUSES,'priority'=>self::PRIORITIES] as $key=>$options){if(!in_array($input[$key]??null,$options,true))throw new InvalidArgumentException('Select a valid '.$key.'.');$data[$key]=$input[$key];}
  $date=$input['incident_date']??'';
  if(!is_string($date)||($date!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!($parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date))||$parsed->format('Y-m-d')!==$date||$date>date('Y-m-d'))))throw new InvalidArgumentException('Enter a valid incident date that is not in the future.');
  $data['incident_date']=$date?:null;
  $people=$input['people_affected']??'';
  if(!is_string($people)||($people!==''&&(!ctype_digit($people)||strlen($people)>10||(float)$people>4294967295)))throw new InvalidArgumentException('People affected must be a non-negative whole number.');
  $data['people_affected']=$people===''?null:(int)$people;
  $raw=$input['categories']??'';if(!is_string($raw)||mb_strlen($raw)>2500)throw new InvalidArgumentException('Enter valid categories.');
  $categories=array_values(array_unique(array_filter(array_map('trim',preg_split('/[\r\n]+/',$raw)))));
  if(count($categories)>20)throw new InvalidArgumentException('Use at most 20 categories.');
  foreach($categories as $c)if(mb_strlen($c)>120)throw new InvalidArgumentException('Each category must be at most 120 characters.');
  return [$data,$categories];
 }
 public static function save(PDO $db,array $actor,int $id,array $input):int {
  if(!in_array('grm.manage',$actor['permissions']??[],true)||!in_array('grm.view',$actor['permissions']??[],true))throw new RuntimeException('GRM editing is not permitted.');
  [$data,$categories]=self::validate($input);
  $db->beginTransaction();
  try{
   $before=null;$oldCategories=[];
   if($id){
    $s=$db->prepare('SELECT * FROM grievances WHERE id=? FOR UPDATE');$s->execute([$id]);$before=$s->fetch();if(!$before)throw new RuntimeException('Case not found.');
    $s=$db->prepare('SELECT category FROM grievance_categories WHERE grievance_id=? ORDER BY category');$s->execute([$id]);$oldCategories=$s->fetchAll(PDO::FETCH_COLUMN);
    if(!is_string($input['revision']??null)||!hash_equals(self::revision($before,$oldCategories),$input['revision']))throw new RuntimeException('This case changed after you opened it. Reload the case before saving to avoid overwriting another update.');
   }
   $now=date('Y-m-d H:i:s');$data['updated_by']=$actor['id'];$data['updated_at']=$now;
   if($data['response']!==($before['response']??'')){$data['response_by']=$actor['id'];$data['response_at']=$now;}
   if($data['status']==='archived'&&($before['status']??'')!=='archived')$data['archived_at']=$now;
   if($id){$sets=implode(',',array_map(fn($key)=>"`$key`=?",array_keys($data)));$db->prepare('UPDATE grievances SET '.$sets.' WHERE id=?')->execute([...array_values($data),$id]);}
   else{
    $data['external_id']='MIS-GRM-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(5)));$data['created_by']=$actor['id'];$data['submitted_at']=$now;
    $columns=implode(',',array_map(fn($key)=>"`$key`",array_keys($data)));$db->prepare('INSERT INTO grievances ('.$columns.') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));$id=(int)$db->lastInsertId();
   }
   $db->prepare('DELETE FROM grievance_categories WHERE grievance_id=?')->execute([$id]);$s=$db->prepare('INSERT INTO grievance_categories(grievance_id,category)VALUES(?,?)');foreach($categories as $category)$s->execute([$id,$category]);
   $s=$db->prepare('SELECT * FROM grievances WHERE id=?');$s->execute([$id]);$after=$s->fetch();
   $changes=array_keys(array_filter($after,fn($value,$key)=>!$before||$value!==$before[$key],ARRAY_FILTER_USE_BOTH));if($categories!==$oldCategories)$changes[]='categories';
   $details=['changed_fields'=>$changes,'before'=>$before,'after'=>$after,'categories_before'=>$oldCategories,'categories_after'=>$categories];
   $db->prepare('INSERT INTO audit_logs(user_id,username_snapshot,action,entity_type,entity_id,details,ip_address)VALUES(?,?,?,?,?,?,?)')->execute([$actor['id'],$actor['username'],$before?'grm_case_updated':'grm_case_created','grievance',$id,json_encode($details,JSON_THROW_ON_ERROR),$_SERVER['REMOTE_ADDR']??null]);
   $db->commit();return $id;
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
