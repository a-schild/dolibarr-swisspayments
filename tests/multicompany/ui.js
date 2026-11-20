// Browser part of the multi-company test suite (see README.md, started by run.sh).
// Usage: node ui.js <version>   e.g. node ui.js 24  -> http://localhost:8024, containers swp-doli24 / swp-db24
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const v = process.argv[2]; const base = `http://localhost:80${v}`; const shots = process.env.SWP_SHOTS || (require('os').tmpdir() + '/swp-shots'); require('fs').mkdirSync(shots, { recursive: true });
let fails = 0;
const chk = (l, c, extra) => { console.log((c ? 'PASS' : 'FAIL') + '  ' + l + (c || !extra ? '' : '   -> ' + extra)); if (!c) fails++; };
const sql = q => execSync(`docker exec swp-db${v} mariadb -N -udoli -pdoli dolibarr -e "${q}"`).toString().trim();
const QR = ['SPC','0200','1','CH4431999123000889012','S','Robert Schneider AG','Rue du Lac','1268','2501','Biel','CH','','','','','','','','1949.75','CHF','S','Pia-Maria Rutschmann-Schnyder','Grosse Marktgasse','28','9400','Rorschach','CH','QRR','210000000003139471430009017','Order of 15 June 2020','EPD',''].join('\n');

async function login(browser, entity) {
  const ctx = await browser.newContext({ acceptDownloads: true }); const page = await ctx.newPage();
  await page.goto(base + '/index.php');
  await page.fill('input[name=username]', 'admin'); await page.fill('input[name=password]', 'admin123');
  await page.evaluate(e => { const f = document.querySelector('input[name=username]').form; const i = document.createElement('input'); i.type = 'hidden'; i.name = 'entity'; i.value = e; f.appendChild(i); }, String(entity));
  await Promise.all([page.waitForNavigation(), page.evaluate(() => document.querySelector('input[name=username]').form.submit())]);
  return { ctx, page };
}
const msgs = page => page.evaluate(() => { const m = [...document.querySelectorAll('.jnotify-message, div.error, div.warning')].map(e => e.innerText).join(' | '); return ('MSG[' + m + '] ' + document.querySelector('#id-right, body').innerText).replace(/\s+/g, ' '); });

// Submit the "pay" form with an arbitrary accountid; returns final URL
async function pay(page, accountid, num) {
  await page.goto(base + '/custom/swisspayments/dtapayments.php');
  await page.evaluate(([acc, num]) => {
    const s = document.querySelector('select[name=accountid]'); const o = document.createElement('option'); o.value = acc; o.text = 'forced ' + acc; s.appendChild(o); s.value = acc;
    document.querySelector('input[name=num_paiement]').value = num;
    const p = document.querySelector('select[name=paiementid]'); if (p && (!p.value || p.value == '0' || p.selectedIndex < 0)) { for (const op of p.options) if (op.value && op.value != '0') { p.value = op.value; break; } }
  }, [String(accountid), num || ('T' + Date.now())]);
  await Promise.all([page.waitForNavigation(), page.click('input[name=createDTAPay]')]);
  return page.url();
}

(async () => {
  const browser = await chromium.launch();
  console.log(`===== Dolibarr v${v}: ` + sql("select value from llx_const where name='MAIN_VERSION_LAST_INSTALL'"));

  const ids = q => sql(q).split('\n').filter(Boolean);
  const SOC = sql('select min(rowid) from llx_societe where entity=1');           // supplier owned by entity 1, shared with 2
  const open1 = ids('select rowid from llx_bank_account where entity=1 and clos=0'), open2 = ids('select rowid from llx_bank_account where entity=2 and clos=0');
  const closed1 = ids('select rowid from llx_bank_account where entity=1 and clos=1'), closed2 = ids('select rowid from llx_bank_account where entity=2 and clos=1');
  const ent3 = ids('select rowid from llx_bank_account where entity=3');
  console.log(`bank accounts: open E1 ${open1} | open E2 ${open2} | closed ${closed1},${closed2} | entity 3 ${ent3}`);
  // ---------- entity 1: bank account validation ----------
  const inv1 = execSync(`docker exec -u www-data swp-doli${v} php /var/www/html/custom/swisspayments/tests/multicompany/fx.php 1 invoice ${SOC}`).toString().match(/INV (\d+)/)[1];
  const REF = sql(`select ref_supplier from llx_facture_fourn where rowid=${inv1}`);
  let { ctx, page } = await login(browser, 1);
  await page.goto(base + '/custom/swisspayments/dtapayments.php');
  await page.screenshot({ path: `${shots}/v${v}-e1-topay.png`, fullPage: true });
  const opts = await page.$$eval('select[name=accountid] option', os => os.map(o => o.value).filter(x => x && x != '-1'));
  console.log('account options offered in entity 1:', opts.join(','));
  chk('E1 selector offers own open + shared entity-2 open accounts', open1.length > 0 && open2.length > 0 && [...open1, ...open2].every(x => opts.includes(x)));
  chk('E1 selector hides closed and non-shared entity-3 accounts', ent3.length > 0 && ![...closed1, ...closed2, ...ent3].some(x => opts.includes(x)));
  chk('E1 open invoice is listed as payable', await page.$(`input[name=amount_${inv1}]`) !== null);
  const npay = () => sql('select count(*) from llx_paiementfourn') + '/' + sql('select count(*) from llx_swisspayments_payh');
  for (const [acc, why] of [[closed1[0], 'closed account of own entity'], [closed2[0], 'closed account of shared entity'], [ent3[0], 'open account of non-shared entity 3'], [9999, 'non-existent account'], [0, 'no account']]) {
    const before = npay(); const url = await pay(page, acc); const t = await msgs(page);
    chk(`E1 POST accountid=${acc} (${why}) rejected, nothing created`, /dtapayments\.php/.test(url) && /not available for this company/i.test(t) && npay() === before, `url=${url} counts ${before}->${npay()}`);
  }
  await page.screenshot({ path: `${shots}/v${v}-e1-rejected.png`, fullPage: true });
  const NUM = 'BATCH-' + Date.now();
  let url = await pay(page, open2[0], NUM);
  chk(`E1 POST accountid=${open2[0]} (open account SHARED from entity 2) accepted -> dtafile.php`, /dtafile\.php\?payh=\d+/.test(url), url + ' :: ' + (await msgs(page)).slice(0, 300));
  let href = await page.getAttribute('a.butAction[href*="document.php"]', 'href').catch(() => null);
  chk('E1 download link present', !!href, (await msgs(page)).slice(0, 400));
  if (href) { const r = await ctx.request.get(href.startsWith('http') ? href : base + href); const b = await r.text();
    chk('E1 payment file downloads via document.php (pain.001.001.09, contains QRR)', r.status() == 200 && b.includes('pain.001.001.09') && b.includes('210000000003139471430009017'), `status ${r.status()} ${b.slice(0, 200)}`); }
  await ctx.close();

  // ---------- entity 2: QR wizard with a SHARED supplier, duplicate check, pay, download ----------
  ({ ctx, page } = await login(browser, 2));
  const wizard = async billnr => {
    await page.goto(base + '/custom/swisspayments/createinvoice.php');
    await page.fill('#qrcode', QR);
    await Promise.all([page.waitForNavigation(), page.evaluate(() => document.querySelector('#qrcode').form.submit())]);
    const sup = await page.$eval('form[name=myform] input[name=socid]', e => e.value).catch(() => '');
    await page.fill('#billnr', billnr);
    await page.check('input[name=validate]');
    await Promise.all([page.waitForNavigation(), page.evaluate(() => document.querySelector('#billnr').form.submit())]);
    return sup;
  };
  const ninv = () => sql(`select count(*) from llx_facture_fourn where entity=2 and fk_soc=${SOC} and ref_supplier='${REF}'`);
  chk('precondition: reference exists for supplier 1 in entity 1, not in entity 2', sql(`select count(*) from llx_facture_fourn where entity=1 and fk_soc=${SOC} and ref_supplier='${REF}'`) === '1' && ninv() === '0');
  const sup = await wizard(REF);
  await page.screenshot({ path: `${shots}/v${v}-e2-created.png`, fullPage: true });
  chk('E2 scan recognises the shared supplier (owned by entity 1) by IBAN', sup === SOC, 'socid=' + sup);
  chk('E2 same supplier reference as in entity 1 is accepted (dup check is per entity)', ninv() === '1', (await msgs(page)).slice(0, 400));
  await wizard(REF);
  let t = await msgs(page);
  chk('E2 second import of the same reference in the same entity is blocked', ninv() === '1' && t.includes(REF), t.slice(0, 300));
  await page.screenshot({ path: `${shots}/v${v}-e2-duplicate.png`, fullPage: true });
  const inv = sql(`select rowid from llx_facture_fourn where entity=2 and fk_soc=${SOC} and ref_supplier='${REF}'`);
  chk('E2 factf row of new invoice has entity 2 + bill IBAN', sql(`select concat(entity,'/',iban) from llx_swisspayments_factf where fk_factid=${inv}`) === '2/CH4431999123000889012');
  chk('E2 own per-entity swisspayments_soc mapping created for the shared supplier', sql(`select count(*) from llx_swisspayments_soc where fk_societe=${SOC} and entity=2`) === '1' && sql(`select count(*) from llx_swisspayments_soc where fk_societe=${SOC} and entity=1`) === '1');

  await page.goto(base + '/custom/swisspayments/dtapayments.php');
  await page.screenshot({ path: `${shots}/v${v}-e2-topay.png`, fullPage: true });
  chk('E2 to-pay list shows the new invoice as payable, and not entity 1 invoices', await page.$(`input[name=amount_${inv}]`) !== null && await page.$(`input[name=amount_${inv1}]`) === null);
  url = await pay(page, open1[0], NUM);
  chk('E2 pay with an account SHARED from entity 1, using the SAME batch number as the entity-1 batch -> dtafile.php', /dtafile\.php\?payh=\d+/.test(url), url + ' :: ' + (await msgs(page)).slice(0, 300));
  await page.screenshot({ path: `${shots}/v${v}-e2-dtafile.png`, fullPage: true });
  href = await page.getAttribute('a.butAction[href*="document.php"]', 'href').catch(() => null);
  chk('E2 download link carries entity=2', !!href && /entity=2/.test(href), String(href));
  if (href) { const r = await ctx.request.get(href.startsWith('http') ? href : base + href); const b = await r.text();
    chk('E2 payment file downloads via document.php (pain.001.001.09, contains QRR)', r.status() == 200 && b.includes('pain.001.001.09') && b.includes('210000000003139471430009017'), `status ${r.status()} ${b.slice(0, 200)}`);
    const fn = decodeURIComponent(href).match(/dtafiles\/([^&]+)/)[1];
    const where = execSync(`docker exec swp-doli${v} sh -c 'ls /var/www/documents/2/swisspayments/dtafiles/${fn} 2>&1; ls /var/www/documents/swisspayments/dtafiles/${fn} 2>&1 || true'`).toString().trim().split('\n');
    chk('E2 file stored under documents/2/, not in the entity-1 dir', where[0].startsWith('/var/www/documents/2/') && /No such file/.test(where[1] || ''), where.join(' | ')); }
  const ph = url.match(/payh=(\d+)/); 
  if (ph) { await ctx.close(); ({ ctx, page } = await login(browser, 1)); const r = await page.goto(base + '/custom/swisspayments/dtafile.php?payh=' + ph[1]); const tt = await msgs(page);
    chk('E1 cannot open the entity-2 batch (dtafile.php?payh=' + ph[1] + ')', !(await page.$('a.butAction[href*="document.php"]')), tt.slice(0, 200)); }

  // ---------- batch number: unique per entity, readable message on a duplicate ----------
  execSync(`docker exec -u www-data swp-doli${v} php /var/www/html/custom/swisspayments/tests/multicompany/fx.php 1 invoice ${SOC}`);
  const before = npay(); url = await pay(page, open1[0], NUM); t = await msgs(page);
  chk('E1 reusing a batch number of the same entity is rejected with a readable message', /dtapayments\.php/.test(url) && /already been used/i.test(t) && !/Duplicate entry/i.test(t) && npay() === before, t.slice(0, 200));
  url = await pay(page, open1[0], NUM + '-b');
  chk('E1 the same invoice can then be paid with another batch number', /dtafile\.php\?payh=\d+/.test(url), (await msgs(page)).slice(0, 200));
  await ctx.close();

  // ---------- mobile scan pairing is bound to the entity ----------
  const e2 = await login(browser, 2); await e2.page.goto(base + '/custom/swisspayments/createinvoice.php');
  const token = ((await e2.page.content()).match(/[?&]t=([0-9a-f]{32})/) || [])[1];
  const anon = await browser.newContext();
  const ph1 = await anon.request.get(base + '/custom/swisspayments/mobilescan.php?t=' + token);
  const post = await anon.request.post(base + '/custom/swisspayments/mobilescan.php?t=' + token, { form: { payload: 'SPC\nTEST' } });
  chk('scan: public phone page loads with the token, payload accepted', !!token && ph1.status() == 200 && (await post.json()).ok === true);
  const e1 = await login(browser, 1);
  const r1 = await (await e1.ctx.request.get(base + '/custom/swisspayments/scanpoll.php?t=' + token)).json();
  const r2 = await (await e2.ctx.request.get(base + '/custom/swisspayments/scanpoll.php?t=' + token)).json();
  chk('scan: same user polling from entity 1 gets no payload', r1.payload === null, JSON.stringify(r1));
  chk('scan: poll from the pairing entity 2 gets the payload', r2.payload === 'SPC\nTEST', JSON.stringify(r2));
  await browser.close();
  console.log(fails ? `RESULT v${v}: ${fails} FAILED` : `RESULT v${v}: all passed`);
  process.exit(fails ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
