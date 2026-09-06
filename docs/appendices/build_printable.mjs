import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
const require = createRequire('C:/Users/JKsars/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/package.json');
const { chromium } = require('playwright');
const out = path.dirname(fileURLToPath(import.meta.url));
const data = JSON.parse(fs.readFileSync(path.join(out,'appendix_n_matrix.json'),'utf8'));
const esc = s=>s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
const header = `<colgroup>${[.95,2.65,.55,.80,.85,.85,.60].map(w=>`<col style="width:${w}in">`).join('')}</colgroup><thead><tr><th>Programmer</th><th>Modules</th><th>System</th><th>Case<br>Manager</th><th>Agency<br>Focal Person</th><th>Adminis-<br>trator</th><th>OFW</th></tr></thead>`;
let html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Appendix N List of Modules</title><style>
@page{size:letter;margin:0}*{box-sizing:border-box}html,body{margin:0;color:#000;background:#fff;font-family:"Times New Roman",serif;font-size:10pt}.page{width:8.5in;height:11in;padding:.55in .625in;break-after:page;overflow:visible}.page:last-child{break-after:auto}h1{font-size:12pt;text-align:center;margin:0 0 12pt;font-weight:bold;line-height:1.15}.legend{font-size:9pt;line-height:1.2;margin:0 0 9pt}table{border-collapse:collapse;width:7.25in;table-layout:fixed}th,td{border:.5pt solid #000;padding:2pt 3pt;line-height:1.12;vertical-align:middle}th{text-align:center;font-size:9pt}th:first-child,th:nth-child(2){text-align:left;font-size:10pt}td:nth-child(n+3){text-align:center}td.actor{text-align:center;font-size:10pt}td.programmer{vertical-align:top;font-size:9pt}tbody.module{break-inside:avoid}.module-title{font-weight:bold}.points td{height:17pt;font-size:10pt}.notes h2{font-size:11pt;margin:0 0 12pt}.note{font-size:10pt;line-height:1.2;margin:0 0 8pt}.total{font-weight:bold} @media screen{.page{margin:auto;border-bottom:1px solid #000}} @media print{.page{border:none;margin:0}}
</style></head><body><main></main><script>const data=${JSON.stringify(data).replaceAll('<','\\u003c')};const header=${JSON.stringify(header)};
const esc = s=>s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
const main=document.querySelector('main');let page,table;
function newPage(notes=false){page=document.createElement('section');page.className='page'+(notes?' notes':'');page.innerHTML='<h1>Appendix N<br>List of Modules</h1>';main.append(page);if(!notes){if(main.children.length===1)page.innerHTML+='<p class="legend">Legend: * = implemented access; blank = no listed access. System * = automated processing.<br>! = access discrepancy (notes 8 and 10). Bracketed numbers refer to the access notes.</p>';table=document.createElement('table');table.innerHTML=header;page.append(table);}else page.innerHTML+='<h2>Access Notes</h2>';}
function overflow(el){return el.getBoundingClientRect().bottom>page.getBoundingClientRect().top+10.45*96;}
newPage();
for(const m of data.modules){const b=document.createElement('tbody');b.className='module';b.innerHTML='<tr><td class="programmer" rowspan="'+(m.rows.length+1)+'">[Programmer]</td><td class="module-title">'+esc(m.name)+'</td><td></td><td></td><td></td><td></td><td></td></tr>';
m.rows.forEach(([text,roles],n)=>{b.innerHTML+='<tr><td>'+ (n+1)+'. &nbsp;'+esc(text)+'</td>'+Array.from({length:5},(_,i)=>'<td class="actor">'+(roles.includes(String(i))?'*':((i===4&&roles.includes('!'))||(i===2&&roles.includes('?'))?'!':''))+'</td>').join('')+'</tr>';});
b.innerHTML+='<tr class="points"><td colspan="2">No. of Points</td><td colspan="5"></td></tr>';table.append(b);if(overflow(b)){b.remove();newPage();table.append(b);}}
const total=document.createElement('tbody');total.innerHTML='<tr class="total"><td colspan="2">Total Number of Modules</td><td colspan="5" style="text-align:center">'+data.modules.length+'</td></tr>';table.append(total);if(overflow(total)){total.remove();newPage();table.append(total);}
newPage(true);data.notes.forEach((note,i)=>{const p=document.createElement('p');p.className='note';p.textContent=(i+1)+'. '+note;page.append(p);if(overflow(p)){p.remove();newPage(true);page.append(p);}});
</script></body></html>`;
const htmlPath=path.join(out,'Appendix_N_List_of_Modules.html');
fs.writeFileSync(htmlPath,html);
const browser = await chromium.launch({headless:true,executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
const page=await browser.newPage({viewport:{width:1000,height:1100},deviceScaleFactor:1.5});
await page.goto('file:///'+htmlPath.replaceAll('\\','/'));
await page.evaluate(()=>document.fonts.ready);
const stats=await page.evaluate(()=>({pages:document.querySelectorAll('.page').length, modules:document.querySelectorAll('tbody.module').length, points:[...document.querySelectorAll('.points')].every(r=>r.cells[1].textContent===''),overflow:[...document.querySelectorAll('.page')].some(p=>p.scrollHeight>p.clientHeight+1)}));
await page.evaluate(()=>document.querySelector('script').remove());
fs.writeFileSync(htmlPath,await page.content());
await page.pdf({path:path.join(out,'Appendix_N_List_of_Modules.pdf'),preferCSSPageSize:true,printBackground:true});
const qa=path.join(process.env.TEMP,'appendix-n-qa');fs.mkdirSync(qa,{recursive:true});
const pages=page.locator('.page');for(let i=0;i<await pages.count();i++) await pages.nth(i).screenshot({path:path.join(qa,`page-${i+1}.png`)});
await browser.close();console.log(JSON.stringify(stats));
