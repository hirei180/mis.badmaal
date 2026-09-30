<?php
require dirname(__DIR__).'/app/web.php';requireAuth();$u=getCurrentUser();requireAnyPermission(['mis.view']);
$reviewer=hasAnyPermission(['mis.approve']);$sql='SELECT status,COUNT(*) total FROM mis_submissions';$args=[];
if(!$reviewer){$sql.=' WHERE created_by=?';$args[]=$u['id'];}
$s=db()->prepare($sql.' GROUP BY status');$s->execute($args);$counts=$s->fetchAll(PDO::FETCH_KEY_PAIR);
pageStart('Dashboard','dashboard.php');
?><section class="card hero"><h1>Welcome, <?=e($u['full_name'])?></h1><p>Project reporting, evidence and approvals in one independent workspace.</p><a class="button" href="/data-entry.php">Open Data Entry</a></section>
<div class="grid"><?php foreach(['draft'=>'Drafts','submitted'=>'Awaiting approval','approved'=>'Approved','changes_requested'=>'Returned for changes'] as $status=>$label):?><a class="card stat" href="/data-entry.php?module=review&amp;status=<?=$status?>"><strong><?=$counts[$status]??0?></strong><?=e($label)?></a><?php endforeach;?></div>
<?php $submissionTotal=array_sum($counts); ?>
<div class="two">
<section class="card"><span class="eyebrow">Submission pipeline</span><h2>Reporting workflow</h2>
<?php foreach(['draft'=>'Drafts','submitted'=>'Awaiting approval','approved'=>'Approved','changes_requested'=>'Returned for changes'] as $status=>$label): $value=(int)($counts[$status]??0); ?>
<div class="bar-row"><div><a href="/data-entry.php?module=review&amp;status=<?=$status?>"><?=e($label)?></a><strong><?=$value?></strong></div><div class="bar-track workflow-<?=$status?>"><span style="width:<?=$submissionTotal?100*$value/$submissionTotal:0?>%"></span></div></div>
<?php endforeach; ?>
<p class="form-note"><?=$reviewer?'All reporting submissions':'Your reporting submissions'?> · <?=$submissionTotal?> total</p>
<?php if(!$submissionTotal): ?><p class="muted">Your reporting pipeline will appear as submissions are created.</p><?php endif; ?>
</section>
<section class="card dashboard-callout"><span class="eyebrow">From evidence to impact</span><h2>A clear view of project delivery</h2><p>Explore targets, published results and reporting coverage across components and states.</p><a class="button" href="/index.php">Explore visual results →</a><p class="form-note">Use fiscal year, indicator type and component filters to focus your view.</p></section>
</div>
<div class="two"><section class="card"><h2>Results framework</h2><p>Browse PDO and IR indicators by fiscal year, component, state and reporting period.</p><a href="/data-entry.php?module=indicators">Browse indicators →</a></section><section class="card"><h2>Public reporting</h2><p>Only approved results are published. Drafts and evidence stay inside the MIS.</p><a href="/index.php" target="_blank" rel="noopener">View public dashboard →</a></section></div>
<section class="card"><h2>Workflow</h2><p>Save a draft, check its evidence, and submit for review. A different M&E user or MIS administrator can approve or return it. Your own submissions cannot be self-approved.</p><p class="muted"><?=$reviewer?'The counts above cover all reporting submissions.':'The counts above cover your submissions.'?></p></section>
<?php
$fy=in_array($_GET['fy']??'FY26',App\Services\MisInputValidator::YEARS,true)?($_GET['fy']??'FY26'):'FY26';
$s=db()->prepare("SELECT i.code,i.name,i.unit,i.component,p.reporting_period,p.state,p.target_value,CASE WHEN EXISTS(SELECT 1 FROM mis_publications pub WHERE pub.module='indicators' AND pub.record_id=p.id AND pub.revision>0) THEN p.actual_value ELSE NULL END actual_value FROM mis_indicators i JOIN mis_indicator_periods p ON p.indicator_id=i.id WHERE i.is_active=1 AND p.fiscal_year=?");
$s->execute([$fy]);$rows=$s->fetchAll();
usort($rows,static fn($a,$b)=>strnatcasecmp($a['code'],$b['code'])?:strcmp($a['state'],$b['state']));
?>
<form class="filters card" method="get"><div><span class="eyebrow">Project performance</span><h2>Results at a glance</h2></div><label>Fiscal year<select name="fy"><?php foreach(App\Services\MisInputValidator::YEARS as $year): ?><option <?=$year===$fy?'selected':''?>><?=e($year)?></option><?php endforeach; ?></select></label><button>Update overview</button></form>
<?php require MIS_ROOT.'/views/result-visuals.php'; pageEnd();?>
