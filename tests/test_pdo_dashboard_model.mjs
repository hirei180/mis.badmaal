import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert';
const context={window:{},document:{getElementById(){return null},addEventListener(){}}};
vm.runInNewContext(fs.readFileSync('public/assets/js/custom-dashboard.js','utf8'),context);
const select=context.window.BADMAAL_PDO_MODEL.selectRows;
const targets={
 PDO1:[3,11,17,20,20,20],PDO2:[7,11,15,19,23,26],
 PDO3:[2,8,12,12,12,12],PDO4:[10000,35000,100000,125000,145000,165000],
 PDO5:[null,null,2,null,null,5],
};
const years=['FY25','FY26','FY27','FY28','FY29','FY30'];
const component={PDO1:'Component 2',PDO2:'Component 2',PDO3:'Component 1',PDO4:'Component 1',PDO5:'Component 1'};
const rows=[];
Object.entries(targets).forEach(([code,values])=>values.forEach((target,index)=>rows.push({
 type:'PDO',code,parentCode:null,component:component[code],year:years[index],state:null,
 target,result:null,reportedAt:null,period:null,
})));

for(const year of years){
 const selected=select(rows,{year,component:'All',state:'All'});
 assert.strictEqual(selected.length,5,`${year} must retain five framework indicators`);
 assert.deepStrictEqual(Array.from(selected,x=>x.code),['PDO1','PDO2','PDO3','PDO4','PDO5']);
 const pdo5=selected.find(x=>x.code==='PDO5');
 assert.strictEqual(pdo5.target,targets.PDO5[years.indexOf(year)],`${year} PDO5 target gap changed`);
 assert.strictEqual(selected.filter(x=>x.result!==null&&x.result!=='').length,0,`${year} falsely reported`);
}

rows.push({...rows.find(x=>x.code==='PDO1'&&x.year==='FY25'),state:'National',result:2,reportedAt:'2025-06-30',period:'Annual'});
rows.push({...rows.find(x=>x.code==='PDO1'&&x.year==='FY26'),state:'National',result:9,reportedAt:'2026-06-30',period:'Annual'});
let selected=select(rows,{year:'All',component:'All',state:'All'});
assert.strictEqual(selected.find(x=>x.code==='PDO1').year,'FY26','All years did not select latest reported year');
assert.strictEqual(selected.find(x=>x.code==='PDO1').result,9,'All years altered cumulative result');

selected=select(rows,{year:'FY27',component:'All',state:'Galmudug'});
assert.strictEqual(selected.length,5,'State with no reports must retain framework indicators');
assert.strictEqual(selected.filter(x=>x.result!==null&&x.result!=='').length,0,'State inherited a national report');
assert.strictEqual(select(rows,{year:'FY27',component:'Component 1',state:'All'}).length,3,'Component 1 applicability mismatch');
assert.strictEqual(select(rows,{year:'FY27',component:'Component 2',state:'All'}).length,2,'Component 2 applicability mismatch');

const partial=[...rows,{...rows.find(x=>x.code==='PDO5'&&x.year==='FY26'),state:'National',result:0,reportedAt:'2026-06-30',period:'Annual'}];
selected=select(partial,{year:'FY26',component:'All',state:'All'});
assert.strictEqual(selected.length,5,'Partial reporting removed framework indicators');
assert.strictEqual(selected.filter(x=>x.result!==null&&x.result!=='').length,2,'Partial report count is incorrect');
assert.strictEqual(selected.find(x=>x.code==='PDO5').result,0,'A valid zero result was treated as missing');
assert.strictEqual(selected.find(x=>x.code==='PDO5').target,null,'A missing PDO5 target was converted to zero');

console.log('PDO fiscal-year/filter model tests passed.');
