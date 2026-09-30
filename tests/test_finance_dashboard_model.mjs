import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const context={window:{},document:{getElementById(){return null},addEventListener(){}}};
vm.runInNewContext(fs.readFileSync('public/assets/js/custom-dashboard.js','utf8'),context);
const select=context.window.BADMAAL_FINANCE_MODEL.selectRows;
const rows=[
 {fiscal_year:'FY26',component:'Component 1',quarter:'Annual',budget_amount:1000},
 {fiscal_year:'FY26',component:'Component 1',quarter:'Q1',budget_amount:250},
 {fiscal_year:'FY26',component:'Component 1',quarter:'Q2',budget_amount:250},
 {fiscal_year:'FY26',component:'Component 2',quarter:'Q1',budget_amount:300},
 {fiscal_year:'FY26',component:'Component 2',quarter:'Q2',budget_amount:400},
 {fiscal_year:'FY27',component:'Component 1',quarter:'Q1',budget_amount:500},
];
const all=select(rows,{year:'All',component:'All'});
assert.equal(all.length,4);
assert.equal(all.reduce((n,r)=>n+r.budget_amount,0),2200,'Annual amounts must not be counted again through their quarters');
assert.equal(select(rows,{year:'FY26',component:'Component 1'}).length,1);
assert.equal(select(rows,{year:'FY26',component:'Component 2'}).length,2,'Quarterly reports should remain when no annual report exists');
assert.equal(select(rows,{year:'FY25',component:'All'}).length,0);
console.log('Finance reporting aggregation tests passed.');
