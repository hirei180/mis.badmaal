<?php
// Included only by the authenticated dashboard data portal.
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<section class="records-card">
<h2><?= $workflow->canApprove()?'Review dashboard submissions':'My submissions' ?></h2>
<form method="get" class="mis-queue-filter"><input type="hidden" name="module" value="review"><label for="queue-status">Show submissions</label><select id="queue-status" name="status"><?php foreach([''=>'All statuses','submitted'=>'Awaiting review','draft'=>'Drafts','changes_requested'=>'Returned for changes','approved'=>'Approved'] as $value=>$label):?><option value="<?=$escape($value)?>" <?=$status===$value?'selected':''?>><?=$escape($label)?></option><?php endforeach;?></select><button>Filter</button></form>
<p>Drafts are private to their author and reviewers. Approval publishes the record. The author cannot approve their own submission.</p>
<?php if(!$submissions):?><p>No submissions yet.</p><?php endif;?>
<?php foreach($submissions as $submission): $payload=json_decode($submission['payload'],true); ?>
<article class="mis-submission">
<h3>#<?= (int)$submission['id'] ?> · <?= $escape(ucfirst($submission['module'])) ?> <span class="status"><?= $escape(str_replace('_',' ',$submission['status'])) ?></span></h3>
<p>Entered by <?= $escape($submission['author']) ?> · <?= $escape($submission['updated_at']) ?></p>
<details <?= $submission['status']==='submitted'?'open':'' ?>><summary>View submitted values</summary><dl>
<?php foreach($payload as $field=>$value): if($field==='status'&&$submission['module']==='indicators')continue; if($field==='indicator_id'){$indicator=$repo->indicatorById((int)$value);$value=$indicator?$indicator['code'].' — '.$indicator['name']:$value;$field='indicator';} ?><dt><?= $escape(ucfirst(str_replace('_',' ',$field))) ?></dt><dd><?= $escape($value??'Not reported') ?></dd><?php endforeach;?>
</dl></details>
<?php if($submission['review_note']):?><p><strong>Reviewer feedback:</strong> <?= $escape($submission['review_note']) ?></p><?php endif;?>
<?php if((int)$submission['created_by']===$uid&&$workflow->canEnter($submission['module'])): ?>
<?php if($submission['status']==='draft'):?>
<form method="post" action="/data-entry.php?module=review"><?=csrfField()?><input type="hidden" name="action" value="workflow"><input type="hidden" name="submission_id" value="<?=(int)$submission['id']?>"><button name="decision" value="submit">Submit for review</button></form>
<?php endif;?>
<?php if(in_array($submission['status'],['draft','changes_requested','approved'],true)):?>
<a href="/data-entry.php?module=<?= $submission['module']==='framework'?'indicators':$escape($submission['module']) ?>&amp;revise=<?=(int)$submission['id']?>">Create revised draft</a>
<?php endif;?>
<?php endif;?>
<?php if($workflow->canApprove()&&(int)$submission['created_by']!==$uid&&$submission['status']==='submitted'):?>
<form method="post" action="/data-entry.php?module=review"><?=csrfField()?><input type="hidden" name="action" value="workflow"><input type="hidden" name="submission_id" value="<?=(int)$submission['id']?>"><label>Review note<textarea name="review_note" maxlength="10000" rows="2"><?=((int)($_POST['submission_id']??0)===(int)$submission['id'])?$escape($_POST['review_note']??''):''?></textarea></label><button name="decision" value="approve">Approve and publish</button> <button name="decision" value="return">Return for changes</button></form>
<?php endif;?>
<details class="mis-history"><summary>Activity history</summary><ol><?php foreach($workflow->history((int)$submission['id']) as $event):?><li><strong><?=$escape(ucfirst(str_replace('_',' ',$event['action'])))?></strong> · <?=$escape($event['username'])?> · <?=$escape($event['created_at'])?><?php if($event['note']):?><p><?=$escape($event['note'])?></p><?php endif;?></li><?php endforeach;?></ol></details>
</article>
<?php endforeach;?>
<nav class="mis-pagination" aria-label="Submission pages"><?php if($page>1):?><a href="/data-entry.php?module=review&amp;status=<?=urlencode($status)?>&amp;page=<?=$page-1?>">← Previous</a><?php endif;?><span>Page <?=$page?></span><?php if($hasNext):?><a href="/data-entry.php?module=review&amp;status=<?=urlencode($status)?>&amp;page=<?=$page+1?>">Next →</a><?php endif;?></nav>
</section>
