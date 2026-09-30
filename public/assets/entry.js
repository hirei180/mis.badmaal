document.addEventListener('DOMContentLoaded', () => {
  const indicator = document.getElementById('mis-indicator_id');
  const description = document.getElementById('indicator-meta');
  const update = () => {
    const option=indicator?.selectedOptions[0];
    if(description) description.textContent=option?.dataset.info||'';
    const unit=option?.dataset.unit||'Number';
    ['target_value','actual_value'].forEach(name=>{
      const input=document.getElementById('mis-'+name); if(!input)return;
      input.step=unit==='Number'?'1':'0.01'; input.max=unit==='Percentage'?'100':'99999999999999.99';
      input.placeholder=unit==='Percentage'?'0–100%':unit.includes('USD')?'Amount in USD':'Whole number';
    });
    const help=document.getElementById('unit-help');
    if(help)help.textContent=option?.dataset.method==='reduction_from_baseline'
      ? 'PDO5: target is reduction (%); actual is the comparable measured loss level used with the approved study baseline.'
      : 'Target and actual unit: '+unit+'.';
  };
  indicator?.addEventListener('change', update);
  update();
  const progress = document.getElementById('mis-progress_percent');
  if (progress) progress.max = '100';
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    const note = form.querySelector('[name="review_note"]');
    if (event.submitter?.value === 'return' && note && !note.value.trim()) {
      event.preventDefault(); note.setCustomValidity('Explain which changes are needed.'); note.reportValidity();
    }
  }));
  document.querySelectorAll('[name="review_note"]').forEach(note => note.addEventListener('input', () => note.setCustomValidity('')));
});
