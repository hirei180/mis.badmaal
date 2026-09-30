<?php
// $rows contains only framework targets and publication-approved actuals.
$coverage = ['component'=>[], 'state'=>[]];
$codes=[]; $reported=0;
foreach($rows as $record){
    $codes[$record['code']]=true;
    $hasResult=$record['actual_value']!==null;
    $reported+=(int)$hasResult;
    foreach(array_keys($coverage) as $dimension){
        $key=$record[$dimension] ?: 'Unspecified';
        $coverage[$dimension][$key]??=['total'=>0,'reported'=>0];
        $coverage[$dimension][$key]['total']++;
        $coverage[$dimension][$key]['reported']+=(int)$hasResult;
    }
}
$recordCount=count($rows);$percent=$recordCount?round(100*$reported/$recordCount):0;
?>
<div class="grid result-stats">
<?php foreach([[count($codes),'Active indicators','In this selection'],[$recordCount,'Reporting records','Targets and reporting periods'],[$reported,'Approved results','Published actual values'],[$recordCount-$reported,'Awaiting results','No approved actual available']] as [$value,$label,$caption]): ?>
<section class="card stat"><span class="eyebrow"><?=e($label)?></span><strong><?=e($value)?></strong><small><?=e($caption)?></small></section>
<?php endforeach; ?>
</div>
<div class="visual-grid">
<section class="card"><div class="chart-heading"><div><span class="eyebrow">Reporting overview</span><h2>Approved result coverage</h2></div><span class="badge"><?=e($fy)?></span></div>
<div class="donut" style="--portion:<?=$percent?>%;" role="img" aria-label="<?=$reported?> of <?=$recordCount?> reporting records have approved results"><div><strong><?=$percent?>%</strong><span>reported</span></div></div>
<div class="chart-legend"><span><i class="swatch"></i>Approved <b><?=$reported?></b></span><span><i class="swatch pending"></i>Awaiting <b><?=$recordCount-$reported?></b></span></div>
<p class="form-note">Coverage measures reporting completeness, not project achievement.</p></section>
<section class="card"><span class="eyebrow">Project components</span><h2>Reporting by component</h2>
<?php foreach($coverage['component'] as $label=>$group): $width=100*$group['reported']/$group['total']; ?>
<div class="bar-row"><div><span><?=e($label)?></span><strong><?=$group['reported']?> / <?=$group['total']?></strong></div><div class="bar-track"><span style="width:<?=$width?>%"></span></div></div>
<?php endforeach; if(!$rows): ?><p class="empty">No reporting records in this selection.</p><?php endif; ?>
<p class="form-note">Approved results / total reporting records</p></section>
<section class="card"><span class="eyebrow">Geographic coverage</span><h2>Reporting by state</h2>
<?php foreach($coverage['state'] as $label=>$group): $width=100*$group['reported']/$group['total']; ?>
<div class="bar-row"><div><span><?=e($label)?></span><strong><?=$group['reported']?> / <?=$group['total']?></strong></div><div class="bar-track state-track"><span style="width:<?=$width?>%"></span></div></div>
<?php endforeach; if(!$rows): ?><p class="empty">No state reporting records available.</p><?php endif; ?>
<p class="form-note">Approved results / total reporting records</p></section>
</div>
<section class="card"><div class="chart-heading"><div><span class="eyebrow">Results framework</span><h2>Targets &amp; approved actuals</h2></div><div class="chart-legend"><span><i class="swatch target"></i>Target</span><span><i class="swatch"></i>Approved actual</span></div></div>
<p class="form-note">Each record uses its own scale and indicator unit. Bar lengths are not comparable across records or a measure of achievement.</p>
<div class="comparison-list">
<?php foreach($rows as $record): $scale=max(1,abs((float)($record['target_value']??0)),abs((float)($record['actual_value']??0))); ?>
<div class="comparison-row"><div><strong><?=e($record['code'])?></strong><span><?=e($record['name'])?></span><small><?=e($record['state'].' · '.$record['reporting_period'].' · '.$record['unit'])?></small></div><div class="comparison-bars">
<?php foreach(['target_value'=>'Target','actual_value'=>'Approved actual'] as $key=>$label): $value=$record[$key]; ?>
<div class="comparison-line"><span class="comparison-label"><?=e($label)?></span><div class="bar-track <?=$key==='target_value'?'target-track':''?>"><span style="width:<?=100*abs((float)($value??0))/$scale?>%"></span></div><strong><?=e($value??($key==='target_value'?'Not scheduled':'Not reported'))?></strong></div>
<?php endforeach; ?></div></div>
<?php endforeach; if(!$rows): ?><p class="empty">No records match these filters.</p><?php endif; ?>
</div></section>
