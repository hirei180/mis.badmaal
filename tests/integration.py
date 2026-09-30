"""Run against an isolated restored database. Never changes local MIS or website records."""
import json, os, pathlib, re, subprocess, time, urllib.request, urllib.parse, urllib.error, http.cookiejar, tempfile, shutil, hashlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
fixture=json.loads((ROOT/'storage/test-fixture.json').read_text())
base='http://127.0.0.1:8094'
env=os.environ.copy();env.update(MIS_DB_NAME=fixture['database'],MIS_DB_HOST=fixture['db_host'],MIS_DB_USER=fixture['db_user'],MIS_DB_PASS=fixture['db_pass'])
runtime=tempfile.mkdtemp(prefix='mis-http-')
for d in ['sessions','logs']:(pathlib.Path(runtime)/d).mkdir()
env['MIS_STORAGE_DIR']=runtime
if pathlib.Path(fixture.get('evidence_dir','/nonexistent')).is_dir():shutil.copytree(fixture['evidence_dir'],pathlib.Path(runtime)/'grm-evidence')
log=open(ROOT/'storage/logs/test-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8094','-t',str(ROOT/'public')],env=env,stdout=log,stderr=log)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,path,data=None):
    body=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
    try:
        with c.open(base+path,body) as r:return r.status,r.read().decode(),r.url
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url

def dashboard_data(html):
    match=re.search(r'<script type="application/json" id="dashboard-data">(.*?)</script>',html,re.S)
    assert match,'Dashboard payload missing'
    return json.loads(match[1])

def token(html):
    m=re.search(r'name="csrf_token" value="([^"]+)"',html);assert m,'CSRF missing';return m[1]
def signin(username):
    c=client();status,html,_=request(c,'/login.php');assert status==200
    status,html,url=request(c,'/login.php',{'username':username,'password':fixture['password'],'csrf_token':token(html)})
    assert status==200 and '/dashboard.php' in url,(username,status,url,html[:100]);return c
try:
    for _ in range(40):
        try:
            if request(client(),'/login.php')[0]==200:break
        except OSError:time.sleep(.1)
    else:raise AssertionError('Test server did not start')
    anon=client();assert '/login.php' in request(anon,'/dashboard.php')[2]
    # Website identities cannot log in even with the fixture password shared by active MIS accounts.
    _,html,_=request(anon,'/login.php');status,html,url=request(anon,'/login.php',{'username':'npc.super-admin','password':fixture['password'],'csrf_token':token(html)})
    assert 'Invalid MIS' in html
    admin=signin('mis.admin');officer=signin('me.officer');proc=signin('test.procurement');grm=signin('test.grm')
    for page in ['/dashboard.php','/data-entry.php','/data-entry.php?module=review','/users.php','/roles.php','/settings.php','/audit.php','/audit.php?source=legacy']:
        status,html,_=request(admin,page);assert status==200,(page,status,html[:100]);assert 'Warning:' not in html and 'Fatal error' not in html
    for page in ['/users.php','/roles.php','/settings.php','/audit.php']:
        assert request(officer,page)[0]==403,page
    assert request(proc,'/data-entry.php?module=indicators')[0]==403
    assert request(proc,'/data-entry.php?module=contracts')[0]==200
    assert request(grm,'/data-entry.php?module=grm')[0]==200
    assert request(grm,'/data-entry.php?module=indicators')[0]==403
    assert request(grm,'/grievances.php')[0]==200
    assert request(admin,'/grievances.php')[0]==200
    for restricted in [proc]:
        assert request(restricted,'/grievances.php')[0]==403
        assert request(restricted,'/grm-evidence.php?id=1')[0]==403
    assert '/login.php' in request(anon,'/grievances.php')[2]
    code,unknown,_=request(anon,'/storage/grm-evidence/')
    assert code in (403,404) or 'id="dashboard-data"' in unknown # PHP dev server may fall back to public index.
    evidence_files=list(pathlib.Path(fixture['evidence_dir']).iterdir())
    assert len(evidence_files)==11
    for evidence in evidence_files:
        case_id=evidence.name.split('-')[0]
        assert request(grm,'/grievances.php?id='+case_id)[0]==200
        with grm.open(base+'/grm-evidence.php?id='+case_id) as download:
            assert download.status==200 and download.headers['Content-Type']=='application/octet-stream'
            assert 'attachment;' in download.headers['Content-Disposition']
            assert hashlib.sha256(download.read()).hexdigest()==evidence.name.split('-',1)[1]
        with officer.open(base+'/grm-evidence.php?id='+case_id) as download:
            assert download.status==200
    _,html,_=request(officer,'/data-entry.php?filter_fiscal_year=FY27&filter_component=Component+1&filter_indicator_type=IR')
    body=re.search(r'<tbody>(.*?)</tbody>',html,re.S)[1];assert 'FY27' in body and 'FY26' not in body and 'PDO1' not in body
    assert request(officer,'/data-entry.php',{'action':'save_draft'})[0]==403
    _,html,_=request(officer,'/data-entry.php');csrf=token(html)
    indicator=int(re.search(r'<option value="(\d+)"[^>]*data-unit="Number"[^>]*>PDO1 —',html)[1])
    _,before_draft,_=request(anon,'/?fy=FY26&type=PDO')
    published_before=dashboard_data(before_draft)['indicators']
    draft={'csrf_token':csrf,'action':'save_draft','entry_module':'indicators','indicator_id':indicator,'fiscal_year':'FY26','reporting_period':'Annual','state':'National','target_value':'11','actual_value':'8','reported_at':'2026-01-01','notes':'Isolated HTTP test evidence'}
    _,bad,_=request(officer,'/data-entry.php',dict(draft,actual_value='0.14'));assert 'whole actual' in bad
    _,review,_=request(officer,'/data-entry.php',draft);assert 'Draft #' in review
    sid=int(re.search(r'Draft #(\d+) saved',review)[1]);csrf=token(review)
    _,review,_=request(officer,'/data-entry.php?module=review',{'csrf_token':csrf,'action':'workflow','submission_id':sid,'decision':'submit'})
    _,pub,_=request(anon,'/?fy=FY26&type=PDO');payload=dashboard_data(pub)
    # Existing approved results remain visible; new drafts must not change them.
    assert payload['indicators']==published_before
    assert len(payload['map']['sitesData']['features'])==12
    assert payload['grmAvailable'] is True
    assert payload['grm']['total']==19
    assert payload['grm']['resolved']==0 and payload['grm']['pending']==19
    assert sum(payload['grmSegments']['categories'].values())==94
    for private_field in ['complainant_name','email','phone','evidence_path','review_comments']:assert private_field not in payload['grm']
    for module in ['pdo','ir','contracts','finance','grm']:assert 'id="module-'+module+'"' in pub
    _,denied,_=request(officer,'/data-entry.php?module=review',{'csrf_token':csrf,'action':'workflow','submission_id':sid,'decision':'approve'})
    assert 'different authorized reviewer' in denied
    _,html,_=request(admin,'/data-entry.php?module=review');csrfadmin=token(html)
    _,html,_=request(admin,'/data-entry.php?module=review',{'csrf_token':csrfadmin,'action':'workflow','submission_id':sid,'decision':'approve'})
    assert 'Submission updated successfully' in html
    _,pub,_=request(anon,'/?fy=FY26&type=PDO');payload=dashboard_data(pub)
    assert any(r['code']=='PDO1' and r['year']=='FY26' and r['result']==8 for r in payload['indicators'])
    assert 'Isolated HTTP test evidence' not in pub
    assert all('created_by' not in r and 'notes' not in r for r in payload['indicators'])
    # Last-actor attribution and approval activity remain accessible.
    assert 'me.officer' in html and 'mis.admin' in html
    # Account creation, first-login password change and session revocation.
    _,users_html,_=request(admin,'/users.php')
    role_id=int(re.search(r'<option value="(\d+)"[^>]*>M&amp;E Officer</option>',users_html)[1])
    new_user={'csrf_token':token(users_html),'id':0,'full_name':'Isolated Officer','username':'new.officer','email':'new.officer@mis.invalid','role_id':role_id,'status':'active','password':fixture['password']}
    _,users_html,url=request(admin,'/users.php',new_user);assert 'saved=1' in url
    row=re.search(r'<tr><td>new.officer.*?</tr>',users_html,re.S)[0]
    new_id=int(re.search(r'edit=(\d+)',row)[1])
    fresh=client();_,login_html,_=request(fresh,'/login.php')
    _,change_html,url=request(fresh,'/login.php',{'csrf_token':token(login_html),'username':'new.officer','password':fixture['password']})
    assert '/password.php' in url
    _,_,url=request(fresh,'/password.php',{'csrf_token':token(change_html),'current':fixture['password'],'password':fixture['password']+'Z','confirm':fixture['password']+'Z'})
    assert '/dashboard.php' in url
    _,_,url=request(admin,'/users.php',dict(new_user,id=new_id,status='inactive',password=''))
    assert 'saved=1' in url and '/login.php' in request(fresh,'/dashboard.php')[2]
    _,blocked,_=request(admin,'/users.php',dict(new_user,id=1,username='legacy.try'))
    assert 'Historical attribution records cannot become login accounts' in blocked
    # M&E GRM entry, validation, aggregate updates, privacy and concurrent-edit protection.
    assert request(proc,'/grm-entry.php')[0]==403
    assert request(proc,'/grm-entry.php',{'subject':'unauthorized'})[0]==403
    _,entry,_=request(officer,'/grm-entry.php');grm_csrf=token(entry)
    case_data={'csrf_token':grm_csrf,'subject':'Private GRM integration case','description':'Confidential grievance description','status':'received','priority':'high','categories':'Test category','people_affected':'2','incident_date':'2026-01-01','email':'','response':''}
    assert request(officer,'/grm-entry.php',dict(case_data,csrf_token='invalid'))[0]==403
    _,bad,_=request(officer,'/grm-entry.php',dict(case_data,incident_date='2026-02-30'));assert 'valid incident date' in bad
    _,bad,_=request(officer,'/grm-entry.php',dict(case_data,people_affected='1.2'));assert 'whole number' in bad
    _,saved,url=request(officer,'/grm-entry.php',case_data);assert 'saved=1' in url and 'Case saved successfully' in saved
    case_id=int(re.search(r'id=(\d+)',url)[1])
    _,entry,_=request(officer,'/grm-entry.php?id='+str(case_id));revision=re.search(r'name="revision" value="([^"]+)"',entry)[1]
    update=dict(case_data,revision=revision,status='resolved',response='Private resolution')
    _,saved,url=request(officer,'/grm-entry.php?id='+str(case_id),update);assert 'saved=1' in url
    _,stale,_=request(officer,'/grm-entry.php?id='+str(case_id),dict(update,status='closed'));assert 'changed after you opened' in stale
    _,pub,_=request(anon,'/?module=grm');grm_payload=dashboard_data(pub)
    assert grm_payload['grm']['total']==20 and grm_payload['grm']['resolved']==1
    assert 'Private GRM integration case' not in pub and 'Private resolution' not in pub and 'Confidential grievance description' not in pub
    _,history,_=request(admin,'/audit.php');assert 'grm_case_created' in history and 'grm_case_updated' in history
    _,settings_html,_=request(admin,'/settings.php')
    _,_,url=request(admin,'/settings.php',{'csrf_token':token(settings_html),'organisation':'Isolated MIS','support_email':'','session_minutes':45})
    assert 'saved=1' in url
    assert request(admin,'/logout.php')[0]==405
    _,login,url=request(admin,'/logout.php',{'csrf_token':csrfadmin});assert '/login.php' in url
    assert '/login.php' in request(admin,'/users.php')[2]
    print('HTTP checks passed: independent logins, archived-user denial, role boundaries, CSRF, filtering, whole-number validation, draft isolation, self-approval denial, approval/publication, audit and logout.')
finally:
    server.terminate();server.wait(timeout=10);log.close()
    cleanup='''$c=json_decode(file_get_contents($argv[1]),true);$p=new PDO("mysql:host=".$c["db_host"],$c["db_user"],$c["db_pass"]);if(!preg_match("/^mis_test_[a-f0-9]+$/",$c["database"]))exit(1);$p->exec("DROP DATABASE `".$c["database"]."`");'''
    subprocess.run(['php','-r',cleanup,str(ROOT/'storage/test-fixture.json')],check=True)
    (ROOT/'storage/test-fixture.json').unlink()
    shutil.rmtree(runtime)
    if fixture.get('evidence_dir'):shutil.rmtree(fixture['evidence_dir'],ignore_errors=True)
