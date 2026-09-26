// CDP-driven test: seeds the auth token via a pre-document script, loads a real SPA route,
// waits for data, and prints the rendered page text.
// Usage: node __cdp_test.mjs <token> <route>
import { spawn } from 'node:child_process';
import http from 'node:http';

const token = process.argv[2] || '';
const route = process.argv[3] || '/borrowing-status';
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PORT = 9333;

const chrome = spawn(CHROME, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  `--remote-debugging-port=${PORT}`, '--user-data-dir=C:/tmc_cdp_prof',
  'about:blank',
], { stdio: ['ignore', 'ignore', 'pipe'] });
chrome.stderr.on('data', d => { const s = String(d); if (/error|fail/i.test(s)) console.error('CHROME:', s.slice(0, 200)); });

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
async function getJson(url) {
  for (let i = 0; i < 50; i++) {
    try { return JSON.parse(await new Promise((res, rej) => {
      http.get(url, (r) => { let d = ''; r.on('data', c => d += c); r.on('end', () => res(d)); }).on('error', rej);
    })); } catch { await sleep(200); }
  }
  throw new Error('CDP not reachable');
}

const targets = await getJson(`http://127.0.0.1:9333/json/list`);
console.log('TARGETS:', targets.map(t => `${t.type}:${t.url}`).join(' ; '));
const page = targets.find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(r => ws.onopen = r);

let id = 0; const pending = new Map();
ws.onmessage = (ev) => {
  const m = JSON.parse(ev.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
};
const send = (method, params = {}) => new Promise((res) => {
  const i = ++id; pending.set(i, res);
  ws.send(JSON.stringify({ id: i, method, params }));
});

await send('Page.enable');
await send('Runtime.enable');

// Pre-document script: grab ?t= from the URL and seed localStorage before the SPA boots.
await send('Page.addScriptToEvaluateOnNewDocument', {
  source: `(() => {
    const t = new URLSearchParams(location.search).get('t');
    if (t) { localStorage.setItem('tmc_token', t); history.replaceState(null, '', location.pathname); }
  })();`,
});

const nav = await send('Page.navigate', { url: `http://localhost:5199${route}?t=${encodeURIComponent(token)}` });
console.log('NAV:', JSON.stringify(nav.result ?? nav));
await sleep(4000);

const dom = await send('Runtime.evaluate', { expression: 'document.body.innerText', returnByValue: true });
const text = dom.result?.result?.value || '';
console.log('PATH:', (await send('Runtime.evaluate', { expression: 'location.pathname', returnByValue: true })).result?.result?.value);
console.log('PAGE_TEXT_START');
console.log(text.slice(0, 3000));
console.log('PAGE_TEXT_END');

chrome.kill();
process.exit(0);
