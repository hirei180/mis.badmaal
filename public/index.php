<?php
declare(strict_types=1);
define('MIS_PUBLIC_DASHBOARD', true);
require dirname(__DIR__).'/app/web.php';
use App\Core\Database;
use App\Repositories\MisRepository;
use App\Services\PdoDashboardService;
use App\Services\IrDashboardService;
$installed=false;$dataAvailable=false;$grmAvailable=false;
$indicators=[];$contracts=[];$finances=[];$grm=['total'=>0,'resolved'=>0,'pending'=>0,'urgent'=>0];
$grmSegments=['priorities'=>[],'categories'=>[]];$lastApproved=null;
try {
    $pdo=Database::connection();$repo=new MisRepository($pdo,true);$installed=$repo->isInstalled();
    if($installed) {
        $indicators=array_merge(IrDashboardService::rows($repo->irIndicators()),PdoDashboardService::rows($repo->pdoIndicators()));
        $contracts=$repo->contracts();$finances=$repo->finances();
        $lastApproved=$pdo->query("SELECT MAX(reviewed_at) FROM mis_submissions WHERE status='approved'")->fetchColumn();
        $dataAvailable=true;
    }
} catch(Throwable $e) { error_log('Public MIS load failed: '.$e->getMessage()); }
// GRM case management has not been migrated. Never read the website database here.
// Only reporting fields belong in the public payload; evidence notes and user IDs stay in Admin.
$contracts=array_map(static fn(array $row):array=>array_intersect_key($row,array_flip(['reference_no','title','component','contractor','state','contract_value','paid_amount','progress_percent','status'])),$contracts);
$finances=array_map(static fn(array $row):array=>array_intersect_key($row,array_flip(['fiscal_year','quarter','component','budget_amount','committed_amount','disbursed_amount','expenditure_amount'])),$finances);
$administrationMap=json_decode((string)@file_get_contents(__DIR__.'/assets/data/maps/project-administrations.geojson'),true);$siteMap=json_decode((string)@file_get_contents(__DIR__.'/assets/data/maps/project-sites.geojson'),true);
$data=['installed'=>$installed,'dataAvailable'=>$dataAvailable,'grmAvailable'=>$grmAvailable,'isSample'=>false,'reportingDate'=>date('d M Y'),'project'=>['budget'=>85.2,'sites'=>12,'beneficiaries'=>165000],'indicators'=>$indicators,'contracts'=>$contracts,'finances'=>$finances,'grm'=>$grm,'grmSegments'=>$grmSegments,'map'=>['administrations'=>'assets/data/maps/project-administrations.geojson','sites'=>'assets/data/maps/project-sites.geojson','administrationsData'=>$administrationMap,'sitesData'=>$siteMap]];
require MIS_ROOT.'/views/public-header.php';
?>
<link rel="stylesheet" href="/assets/css/custom-dashboard.css?v=20260930">
<div class="bd-page" id="badmaal-dashboard"><section class="bd-shell">
 <nav class="bd-module-nav" role="tablist" aria-label="Dashboard modules">
  <button class="bd-module active" data-module="pdo" role="tab" aria-selected="true"><i class="fas fa-bullseye"></i><span>PDO Indicators</span></button>
  <button class="bd-module" data-module="ir" role="tab" aria-selected="false"><i class="fas fa-chart-line"></i><span>IR Indicators</span></button>
  <button class="bd-module" data-module="contracts" role="tab" aria-selected="false"><i class="fas fa-file-contract"></i><span>Contract Management</span></button>
  <button class="bd-module" data-module="finance" role="tab" aria-selected="false"><i class="fas fa-coins"></i><span>Financial Management</span></button>
  <button class="bd-module" data-module="grm" role="tab" aria-selected="false"><i class="fas fa-comments"></i><span>GRM</span></button>
 </nav>
 <div class="bd-toolbar"><div><span class="bd-module-kicker">Results &amp; delivery intelligence</span><h1 id="module-title">PDO Indicators</h1></div><div class="bd-filters"><label>Fiscal year<select id="year-filter"><option value="All">Latest available results</option></select></label><label>Component<select id="component-filter"><option value="All">All components</option></select></label><label>State<select id="state-filter"><option value="All">All states</option></select></label><button id="reset-filters" class="bd-reset"><i class="fas fa-rotate-left"></i> Reset</button></div></div>
 <div class="bd-reporting-row"><span>Last approved update: <strong><?=$lastApproved?htmlspecialchars(date('d M Y',strtotime($lastApproved))):'No approved reports yet'?></strong></span><span class="bd-preview-badge"><?=$dataAvailable?'Approved reporting data':'Reporting data temporarily unavailable'?></span></div>
 <p id="filter-scope" class="bd-filter-scope" aria-live="polite"></p>
 <noscript><p class="bd-empty">Enable JavaScript to view dashboard charts and reporting records.</p></noscript>
 <section class="bd-module-panel active" id="module-pdo"><div class="bd-summary-strip" id="pdo-summary"></div><div class="bd-pdo-dashboard-grid"><div class="bd-pdo-card-strip" id="pdo-cards"></div><article class="bd-chart-card bd-pdo-achievement"><span class="bd-eyebrow">Five PAD indicators</span><h2>Achievement by PDO</h2><div id="pdo-performance-bars" class="bd-bars"></div></article><article class="bd-chart-card bd-pdo-trend"><span class="bd-eyebrow">All years · FY25–FY30</span><h2>Reporting and on-track trend</h2><div id="pdo-trend-chart" class="bd-trend-chart"></div></article><article class="bd-map-card compact bd-pdo-map"><div class="bd-section-heading"><div><span class="bd-eyebrow">12 sites</span><h2>Geographic coverage</h2></div><button type="button" id="map-reset" class="bd-map-reset">All</button></div><div id="dashboard-map" class="bd-leaflet-map" aria-label="Interactive map of BADMAAL project states and sites"></div><p id="map-selection" class="bd-map-selection">Select a state or site.</p></article></div><table hidden><tbody id="pdo-table"></tbody></table></section>
 <section class="bd-module-panel" id="module-ir" hidden><div class="bd-summary-strip" id="ir-summary"></div><div id="ir-cards" class="bd-ir-card-grid"></div><div class="bd-ir-visual-grid"><article class="bd-chart-card"><span class="bd-eyebrow">Comparable performance</span><h2>Achievement by IR indicator</h2><div id="ir-achievement-bars" class="bd-bars"></div></article><article class="bd-chart-card"><span class="bd-eyebrow">All years · FY25–FY30</span><h2>Reporting and delivery trend</h2><div id="ir-trend-chart" class="bd-trend-chart"></div></article></div><article class="bd-table-card bd-ir-register"><div class="bd-section-heading"><div><span class="bd-eyebrow">Authoritative results framework</span><h2>IR indicator register</h2></div><span id="ir-count"></span></div><div class="bd-table-wrap compact"><table><thead><tr><th>Code</th><th>Indicator</th><th>Component</th><th>Target</th><th>Reported</th><th>Achievement</th><th>Status</th></tr></thead><tbody id="ir-table"></tbody></table></div></article></section>
 <section class="bd-module-panel" id="module-contracts" hidden><div class="bd-summary-strip" id="contract-summary"></div><div class="bd-chart-grid"><article class="bd-chart-card"><span class="bd-eyebrow">Portfolio by component</span><h2>Contract value and payments</h2><div id="contract-bars" class="bd-bars"></div></article><article class="bd-chart-card"><span class="bd-eyebrow">Delivery status</span><h2>Active and completed contracts</h2><div id="contract-status" class="bd-status-donut"></div></article></div><article class="bd-table-card"><div class="bd-section-heading"><div><span class="bd-eyebrow">Contract register</span><h2>Implementation portfolio</h2></div></div><div class="bd-table-wrap"><table><thead><tr><th>Reference</th><th>Contract</th><th>Component</th><th>Contractor</th><th>Value</th><th>Paid</th><th>Progress</th><th>Status</th></tr></thead><tbody id="contract-table"></tbody></table></div></article></section>
 <section class="bd-module-panel" id="module-finance" hidden><div class="bd-summary-strip" id="finance-summary"></div><div class="bd-chart-grid"><article class="bd-chart-card"><span class="bd-eyebrow">Component comparison</span><h2>Budget utilization</h2><div id="finance-bars" class="bd-bars"></div></article><article class="bd-chart-card"><span class="bd-eyebrow">Financial controls</span><h2>Budget, commitments and expenditure</h2><div id="finance-ratios"></div></article></div><article class="bd-table-card"><div class="bd-section-heading"><div><span class="bd-eyebrow">Financial register</span><h2>Financial reporting records</h2></div></div><div class="bd-table-wrap"><table><thead><tr><th>FY</th><th>Quarter</th><th>Component</th><th>Budget</th><th>Committed</th><th>Disbursed</th><th>Expenditure</th><th>Utilization</th></tr></thead><tbody id="finance-table"></tbody></table></div></article></section>
 <section class="bd-module-panel" id="module-grm" hidden><div class="bd-summary-strip" id="grm-summary"></div><div class="bd-cockpit bd-cockpit-grm"><article class="bd-chart-card"><span class="bd-eyebrow">Resolution</span><h2>Case status</h2><div id="grm-resolution" class="bd-resolution"></div></article><article class="bd-chart-card"><span class="bd-eyebrow">Case workload</span><h2>Case priorities</h2><div id="grm-channel-bars" class="bd-bars"></div></article><article class="bd-chart-card"><span class="bd-eyebrow">Topics</span><h2>Complaint categories</h2><div id="grm-category-bars" class="bd-bars"></div></article></div></section>
 <footer class="bd-dashboard-footer"><span>BADMAAL Project Monitoring Information System</span><span>Approved project reporting</span></footer>
</section></div>
<script type="application/json" id="dashboard-data"><?=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="/assets/vendor/leaflet/leaflet.js" defer></script><script src="/assets/js/custom-dashboard.js?v=20260930" defer></script>
</body></html>
