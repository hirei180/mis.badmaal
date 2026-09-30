<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/web.php';
use App\Core\Database;
use App\Repositories\MisRepository;
use App\Services\MisWorkflowService;
use App\Services\MisInputValidator as Input;
requireAuth();
requireAnyPermission(['mis.view','mis.indicators.enter','mis.contracts.enter','mis.finance.enter','mis.framework.enter','mis.approve','grm.view']);
$pdo=Database::connection();$repo=new MisRepository($pdo);$user=getCurrentUser();$uid=(int)($user['id']??0);
$workflow=new MisWorkflowService($pdo,$uid,$user['permissions']??[]);
$installed=$repo->isInstalled()&&$workflow->installed();
$labels=['indicators'=>'PDO & IR Indicators','contracts'=>'Contract Management','finance'=>'Financial Management','review'=>'My submissions / Review','grm'=>'GRM'];
$modules=[];
foreach(['indicators','contracts','finance'] as $module) if($workflow->canEnter($module)||$workflow->canApprove()||($module==='indicators'&&$workflow->canEnter('framework'))) $modules[]=$module;
$modules[]='review';
if(hasAnyPermission(['grm.view'])) $modules[]='grm';
$active=(string)($_GET['module']??($modules[0]??'review'));
if(!in_array($active,$modules,true)) { http_response_code(403);exit('Module access denied.'); }
$notice=(string)($_SESSION['mis_notice']??'');unset($_SESSION['mis_notice']);$error='';$formValues=[];$formModule=$active;
if($_SERVER['REQUEST_METHOD']==='POST') {
    requireValidCsrf();
    $action=(string)($_POST['action']??'');
    try {
        if(!$installed) throw new RuntimeException('Dashboard data entry is not available yet. Please contact the administrator.');
        if($action==='save_draft') {
            $formModule=(string)($_POST['entry_module']??'');$formValues=$_POST;
            if(!$workflow->canEnter($formModule)) { http_response_code(403);exit('Module access denied.'); }
            $id=$workflow->saveDraft($formModule,$_POST);
            $_SESSION['mis_notice']="Draft #$id saved. Review its values below, then submit it for approval.";
        } elseif($action==='workflow') {
            $workflow->transition((int)($_POST['submission_id']??0),(string)($_POST['decision']??''),trim((string)($_POST['review_note']??'')));
            $_SESSION['mis_notice']='Submission updated successfully.';
        } else { throw new InvalidArgumentException('Unknown action.'); }
        header('Location: data-entry.php?module=review'.($action==='save_draft'?'&status=draft':''),true,303);exit;
    } catch(InvalidArgumentException|RuntimeException $e) {
        $error=$e instanceof PDOException?'Unable to save right now. Please try again.':$e->getMessage();
        if($e instanceof PDOException) error_log('MIS save failed: '.$e->getMessage());
    }
}
$canEnter=$installed&&$workflow->canEnter($active);
$indicators=in_array('indicators',$modules,true)&&$repo->isInstalled()?$repo->indicators():[];
usort($indicators, static fn($a,$b)=>strnatcasecmp($a['code'],$b['code']) ?: strcmp($a['fiscal_year']??'', $b['fiscal_year']??''));
$indicatorOptions=[];$pdo5Meta=null;
foreach($indicators as $i) {

    $indicatorOptions[(int)$i['id']]=$i;
    if($i['code']==='PDO5') $pdo5Meta=$i;
}
$contracts=$active==='contracts'&&$installed?$repo->contracts():[];
$finances=$active==='finance'&&$installed?$repo->finances():[];
$revision=null;
if($installed&&isset($_GET['revise'])) {
    $revision=$workflow->findVisible((int)$_GET['revise']);
    if(!$revision||(int)$revision['created_by']!==$uid||!$workflow->canEnter($revision['module'])||!in_array($revision['status'],['draft','changes_requested','approved'],true)||$active!==($revision['module']==='framework'?'indicators':$revision['module'])) { http_response_code(403);exit('This submission cannot be revised.'); }
    if(!$error) {$formValues=json_decode($revision['payload'],true);$formModule=$revision['module'];}
}
if(!$error&&isset($_GET['source'])&&$canEnter) {
    $records=match($active) {'indicators'=>$indicators,'contracts'=>$contracts,'finance'=>$finances,default=>[]};
    foreach($records as $record) {
        $recordId=$active==='indicators'?($record['period_id']??0):$record['id'];
        if((int)$recordId===(int)$_GET['source']) {$formValues=$record;if($active==='indicators')$formValues['indicator_id']=$record['id'];break;}
    }
}
$filters=[];
foreach(['fiscal_year'=>Input::YEARS,'component'=>Input::COMPONENTS,'indicator_type'=>['PDO','IR'],'state'=>Input::STATES,'reporting_period'=>Input::PERIODS] as $key=>$choices) {
    $value=(string)($_GET['filter_'.$key]??($key==='fiscal_year'?($formValues['fiscal_year']??'FY26'):''));
    $filters[$key]=in_array($value,$choices,true)?$value:'';
}
$filteredIndicators=array_values(array_filter($indicators,static function($row) use($filters) {
    foreach($filters as $key=>$value) if($value!==''&&($row[$key]??'')!==$value)return false;
    return true;
}));
foreach(['fiscal_year','state','reporting_period'] as $key) if(!isset($formValues[$key])&&$filters[$key]!=='')$formValues[$key]=$filters[$key];
$status=(string)($_GET['status']??'');$page=max(1,(int)($_GET['page']??1));
$submissions=$active==='review'&&$installed?$workflow->visibleSubmissions($status,$page):[];
$hasNext=count($submissions)>20;$submissions=array_slice($submissions,0,20);
$escape=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');
function misField(string $name,string $label,string $type='text',array $options=[],mixed $default='',bool $required=false):void {
    global $formValues,$escape;
    $value=$formValues[$name]??$default;
    echo '<label for="mis-'.$escape($name).'">'.$escape($label).($required?' <span aria-hidden="true">*</span>':'');
    $attrs=' id="mis-'.$escape($name).'" name="'.$escape($name).'"'.($required?' required':'');
    if($type==='select') {
        echo '<select'.$attrs.'>';
        foreach($options as $key=>$option) { $optionValue=is_int($key)?$option:$key;echo '<option value="'.$escape($optionValue).'"'.((string)$value===(string)$optionValue?' selected':'').'>'.$escape($option).'</option>'; }
        echo '</select>';
    } elseif($type==='textarea') { echo '<textarea'.$attrs.' rows="3" maxlength="10000">'.$escape($value).'</textarea>'; }
    else {echo '<input'.$attrs.' type="'.$escape($type).'" value="'.$escape($value).'"'.($type==='number'?' min="0" max="99999999999999.99" step="0.01"':'').($type==='text'?' maxlength="500"':'').'>';}
    echo '</label>';
}
function misFormStart(string $module):void {echo '<form method="post" class="mis-entry-form">'.csrfField().'<input type="hidden" name="action" value="save_draft"><input type="hidden" name="entry_module" value="'.$module.'">';}
function misFormEnd():void {echo '<p class="form-note">Saving creates a private draft. Submit it from My submissions / Review when ready.</p><button type="submit">Save draft</button></form>';}
?>
<?php pageStart('Data Entry','data-entry.php');?>
<?php if(!$installed):?><div class="notice is-error" role="alert">Data entry is unavailable until the dashboard approval migration is installed.</div><?php endif;?>
<?php if($notice):?><div class="notice" role="status"><?=$escape($notice)?></div><?php endif;?>
<?php if($error):?><div class="notice is-error" role="alert"><?=$escape($error)?></div><?php endif;?>
<nav class="module-tabs" aria-label="Dashboard data modules"><?php foreach($modules as $module):?><a href="data-entry.php?module=<?=$module?>" <?=$active===$module?'class="active" aria-current="page"':''?>><?=$escape($labels[$module])?></a><?php endforeach;?></nav>
<?php if($revision):?><div class="notice">Creating a new draft from submission #<?=(int)$revision['id']?>. The previous submission remains in the history.</div><?php endif;?>
<?php if($active==='review'): require dirname(__DIR__).'/views/review.php';
elseif($active==='grm'):?>
<section class="grm-bridge"><h2>Grievance case management</h2><p>Review migrated cases, category links, responses and private evidence in the independent MIS.</p><a href="/grievances.php">Open GRM cases →</a></section>
<?php else:?>
<?php if($active==='indicators'):?>
<form method="get" class="framework-filters records-card filters"><input type="hidden" name="module" value="indicators">
<?php foreach(['fiscal_year'=>['Fiscal year',Input::YEARS],'component'=>['Component',Input::COMPONENTS],'indicator_type'=>['Indicator type',['PDO','IR']],'state'=>['State',Input::STATES],'reporting_period'=>['Period',Input::PERIODS]] as $key=>[$label,$choices]):?>
<label><?=$escape($label)?><select name="filter_<?=$key?>"><option value="">All</option><?php foreach($choices as $choice):?><option <?=$filters[$key]===$choice?'selected':''?> value="<?=$escape($choice)?>"><?=$escape($choice)?></option><?php endforeach;?></select></label>
<?php endforeach;?><button type="submit">Apply filters</button><a href="data-entry.php?module=indicators&amp;filter_fiscal_year=">Clear filters</a>
<p>Filters narrow the results framework. Component and type also narrow the entry indicator list.</p></form>
<?php endif;?>
<div class="portal-grid">
<section class="entry-card"><h2><?=$canEnter?'Create a reporting draft':'Reporting records'?></h2>
<?php if(!$canEnter):?><p>You can review submitted records from the review tab.</p>
<?php elseif($active==='indicators'): misFormStart('indicators');
$options=[];foreach($indicatorOptions as $id=>$i)$options[(string)$id]=$i['code'].' — '.$i['name'];
// Indicator IDs must remain option values, even when PHP converts numeric keys to integers.
?><label for="mis-indicator_id">Indicator *</label><select id="mis-indicator_id" name="indicator_id" required><?php foreach($indicatorOptions as $id=>$i):if(($filters['component']!==''&&$i['component']!==$filters['component'])||($filters['indicator_type']!==''&&$i['indicator_type']!==$filters['indicator_type']))continue;?><option value="<?=$id?>" <?=((string)($formValues['indicator_id']??'')===(string)$id)?'selected':''?> data-unit="<?=$escape($i['unit'])?>" data-method="<?=$escape($i['calculation_method'])?>" data-info="<?=$escape($i['component'].' · '.$i['unit'].' · '.$i['frequency'])?>"><?=$escape($i['code'].' — '.$i['name'])?></option><?php endforeach;?></select><p id="indicator-meta" class="form-note"></p>
<div class="two"><?php misField('fiscal_year','Fiscal year','select',Input::YEARS,'FY26',true);misField('reporting_period','Reporting period','select',Input::PERIODS,'Annual',true);?></div>
<?php misField('state','State / administration','select',Input::STATES,'National',true);?>
<div class="two"><?php misField('target_value','Target (optional)','number');misField('actual_value','Actual result','number');?></div>
<p class="form-note" id="unit-help"></p><p class="form-note">Leave target blank to use the framework target. Leave actual blank when no result has been reported. Enter 0 for a measured zero.</p>
<?php misField('reported_at','Reporting date','date');misField('notes','Evidence / source reference and notes','textarea');misFormEnd();
elseif($active==='contracts'): misFormStart('contracts');misField('reference_no','Contract reference','text',[],'',true);misField('title','Contract title','text',[],'',true);
misField('component','Component','select',Input::COMPONENTS,'Component 1',true);misField('state','State / administration','select',Input::STATES,'National',true);misField('contractor','Contractor');
?><div class="two"><?php misField('contract_value','Contract value (USD)','number',[],0,true);misField('paid_amount','Amount paid (USD)','number',[],0,true);?></div>
<?php misField('progress_percent','Delivery progress (%)','number',[],0,true);?><div class="two"><?php misField('start_date','Start date','date');misField('end_date','End date','date');?></div>
<?php misField('status','Delivery status','select',['planned','procurement','active','completed','suspended','cancelled'],'planned',true);misField('notes','Evidence / source reference and notes','textarea');misFormEnd();
elseif($active==='finance'):misFormStart('finance');?><div class="two"><?php misField('fiscal_year','Fiscal year','select',Input::YEARS,'FY26',true);misField('quarter','Reporting period','select',Input::PERIODS,'Q1',true);?></div>
<?php misField('component','Component','select',Input::COMPONENTS,'Component 1',true);misField('budget_amount','Budget (USD)','number',[],0,true);misField('committed_amount','Committed (USD)','number',[],0,true);misField('disbursed_amount','Disbursed (USD)','number',[],0,true);misField('expenditure_amount','Expenditure (USD)','number',[],0,true);misField('notes','Evidence / source reference and notes','textarea');?><p class="form-note">Enter amounts for the selected period. An approved Annual record replaces that year's quarterly amounts in dashboard totals.</p><?php misFormEnd();endif;?>
<?php if($active==='indicators'&&$installed&&$workflow->canEnter('framework')&&$pdo5Meta):
$reportValues=$formValues;if($formModule!=='framework')$formValues=['baseline_value'=>$pdo5Meta['baseline_value'],'baseline_date'=>$pdo5Meta['baseline_date'],'baseline_source'=>$pdo5Meta['data_source']];?>
<details class="baseline-entry" <?=$formModule==='framework'?'open':''?>><summary>Propose a PDO5 baseline change</summary><p class="form-note">Baseline changes affect achievement calculations and require another reviewer's approval.</p><?php misFormStart('framework');misField('baseline_value','Baseline measurement','number',[],'',true);misField('baseline_date','Reference date','date',[],'',true);misField('baseline_source','Authoritative source / reference','text',[],'',true);misFormEnd();$formValues=$reportValues;?></details>
<?php endif;?></section>
<section class="records-card"><h2><?=$active==='indicators'?'Results framework':'Existing reporting records'?></h2><p class="form-note">These include legacy records. Only records approved through the review queue appear publicly. Use Create draft to prepare an update.</p>
<div class="table-scroll"><table>
<?php if($active==='indicators'):?><thead><tr><th>Indicator</th><th>Year / period</th><th>State</th><th>Target</th><th>Actual</th><?php if($canEnter):?><th>Action</th><?php endif;?></tr></thead><tbody>
<?php foreach($filteredIndicators as $i):if(!isset($indicatorOptions[(int)$i['id']]))continue;?><tr><td><strong><?=$escape($i['code'])?></strong><br><?=$escape($i['name'])?></td><td><?=$escape(($i['fiscal_year']??'—').' / '.($i['reporting_period']??'—'))?></td><td><?=$escape($i['state']??'—')?></td><td><?=$escape($i['target_value']??'—')?></td><td><?=$escape($i['actual_value']??'Not reported')?></td><?php if($canEnter):?><td><?php if($i['period_id']):?><a href="data-entry.php?module=indicators&amp;source=<?=(int)$i['period_id']?>">Create draft</a><?php endif;?></td><?php endif;?></tr><?php endforeach;?>
<?php elseif($active==='contracts'):?><thead><tr><th>Reference / title</th><th>State</th><th>Value</th><th>Paid</th><th>Progress</th><?php if($canEnter):?><th>Action</th><?php endif;?></tr></thead><tbody>
<?php foreach($contracts as $c):?><tr><td><strong><?=$escape($c['reference_no'])?></strong><br><?=$escape($c['title'])?></td><td><?=$escape($c['state'])?></td><td>$<?=number_format((float)$c['contract_value'])?></td><td>$<?=number_format((float)$c['paid_amount'])?></td><td><?=$escape($c['progress_percent'])?>%</td><?php if($canEnter):?><td><a href="data-entry.php?module=contracts&amp;source=<?=(int)$c['id']?>">Create draft</a></td><?php endif;?></tr><?php endforeach;?>
<?php else:?><thead><tr><th>Period</th><th>Component</th><th>Budget</th><th>Committed</th><th>Disbursed</th><th>Expenditure</th><?php if($canEnter):?><th>Action</th><?php endif;?></tr></thead><tbody>
<?php foreach($finances as $f):?><tr><td><?=$escape($f['fiscal_year'].' / '.$f['quarter'])?></td><td><?=$escape($f['component'])?></td><?php foreach(['budget_amount','committed_amount','disbursed_amount','expenditure_amount'] as $key):?><td>$<?=number_format((float)$f[$key])?></td><?php endforeach;?><?php if($canEnter):?><td><a href="data-entry.php?module=finance&amp;source=<?=(int)$f['id']?>">Create draft</a></td><?php endif;?></tr><?php endforeach;?>
<?php endif;?></tbody></table></div>
<?php if(!match($active){'indicators'=>$filteredIndicators,'contracts'=>$contracts,default=>$finances}):?><p>No reporting records match these filters.</p><?php endif;?>
</section></div><?php endif;?>
<script src="/assets/entry.js" defer></script><?php pageEnd();?>
