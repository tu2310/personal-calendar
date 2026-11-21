import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const cmd = process.argv[2] || 'dev';

function loadDotEnv() {
  const envFile = path.join(ROOT, '.env');
  const out = {};
  if (!fs.existsSync(envFile)) return out;
  const content = fs.readFileSync(envFile, 'utf8');
  for (const rawLine of content.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#')) continue;
    const eq = line.indexOf('=');
    if (eq === -1) continue;
    const k = line.slice(0, eq).trim();
    let v = line.slice(eq + 1).trim();
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
      v = v.slice(1, -1);
    }
    if (k) out[k] = v;
  }
  return out;
}

const fileEnv = loadDotEnv();
function getCfg(key, fallback) {
  return process.env[key] || fileEnv[key] || fallback;
}

function scanSubdirExe(parentDir, exeSubPath) {
  if (!fs.existsSync(parentDir)) return null;
  const dirs = fs.readdirSync(parentDir).sort().reverse();
  for (const d of dirs) {
    const candidate = path.join(parentDir, d, exeSubPath);
    if (fs.existsSync(candidate)) return candidate;
  }
  return null;
}

function findPhp() {
  const customPhp = getCfg('PHP_PATH', '');
  if (customPhp && fs.existsSync(customPhp)) return customPhp;

  const probe = spawnSync('php', ['-v'], { stdio: 'ignore' });
  if (probe.status === 0) return 'php';

  // Windows: Laragon, XAMPP, WAMP
  const winCandidates = [
    scanSubdirExe('C:\\laragon\\bin\\php', 'php.exe'),
    scanSubdirExe('D:\\laragon\\bin\\php', 'php.exe'),
    'C:\\xampp\\php\\php.exe',
    'D:\\xampp\\php\\php.exe',
    scanSubdirExe('C:\\wamp64\\bin\\php', 'php.exe')
  ];
  for (const c of winCandidates) {
    if (c && fs.existsSync(c)) return c;
  }

  // macOS: Homebrew, MAMP
  const macCandidates = [
    '/opt/homebrew/bin/php',
    '/usr/local/bin/php',
    scanSubdirExe('/Applications/MAMP/bin/php', 'bin/php')
  ];
  for (const c of macCandidates) {
    if (c && fs.existsSync(c)) return c;
  }

  console.error('[npm] Khong tim thay PHP tren may. Hay cai Laragon / XAMPP hoac them php vao PATH (hoac dat PHP_PATH trong .env).');
  process.exit(1);
}

function checkPort(port, host = '127.0.0.1') {
  return new Promise((resolve) => {
    const sock = net.createConnection({ port, host });
    sock.setTimeout(800);
    sock.on('connect', () => { sock.destroy(); resolve(true); });
    sock.on('timeout', () => { sock.destroy(); resolve(false); });
    sock.on('error', () => resolve(false));
  });
}

function collectMysqlCandidates(dbName) {
  const candidates = [];

  for (const drive of ['C:\\laragon\\bin\\mysql', 'D:\\laragon\\bin\\mysql']) {
    if (!fs.existsSync(drive)) continue;
    const dirs = fs.readdirSync(drive).sort().reverse();
    const withExistingDb = [];
    const others = [];
    for (const d of dirs) {
      const binDir = path.join(drive, d, 'bin');
      const mysqld = path.join(binDir, 'mysqld.exe');
      const myIni = path.join(drive, d, 'my.ini');
      if (!fs.existsSync(mysqld)) continue;
      let hasDb = false;
      if (fs.existsSync(myIni)) {
        const iniText = fs.readFileSync(myIni, 'utf8');
        const m = iniText.match(/^\s*datadir\s*=\s*"?([^"\r\n]+)"?/im);
        if (m && m[1]) {
          const dataPath = path.resolve(m[1].trim(), dbName);
          if (fs.existsSync(dataPath)) hasDb = true;
        }
      }
      const item = {
        label: d,
        mysqld,
        cwd: binDir,
        args: fs.existsSync(myIni) ? [`--defaults-file=${myIni}`] : []
      };
      if (hasDb) withExistingDb.push(item);
      else others.push(item);
    }
    candidates.push(...withExistingDb, ...others);
  }

  for (const xamppBase of ['C:\\xampp\\mysql\\bin', 'D:\\xampp\\mysql\\bin']) {
    const mysqld = path.join(xamppBase, 'mysqld.exe');
    const myIni = path.join(xamppBase, 'my.ini');
    if (fs.existsSync(mysqld)) {
      candidates.push({
        label: 'xampp-mysql',
        mysqld,
        cwd: xamppBase,
        args: fs.existsSync(myIni) ? [`--defaults-file=${myIni}`] : []
      });
    }
  }

  if (fs.existsSync('C:\\wamp64\\bin\\mysql')) {
    const dirs = fs.readdirSync('C:\\wamp64\\bin\\mysql').sort().reverse();
    for (const d of dirs) {
      const binDir = path.join('C:\\wamp64\\bin\\mysql', d, 'bin');
      const mysqld = path.join(binDir, 'mysqld.exe');
      const myIni = path.join('C:\\wamp64\\bin\\mysql', d, 'my.ini');
      if (fs.existsSync(mysqld)) {
        candidates.push({
          label: `wamp-${d}`,
          mysqld,
          cwd: binDir,
          args: fs.existsSync(myIni) ? [`--defaults-file=${myIni}`] : []
        });
      }
    }
  }

  return candidates;
}

async function ensureMysql() {
  const dbHost = getCfg('DB_HOST', '127.0.0.1');
  const dbPort = Number(getCfg('DB_PORT', '3306')) || 3306;
  const dbName = getCfg('DB_NAME', 'personal_calendar');

  if (await checkPort(dbPort, dbHost)) return true;

  if (dbHost !== '127.0.0.1' && dbHost !== 'localhost') {
    console.warn(`[warn] Khong ket noi duoc MySQL tu xa tai ${dbHost}:${dbPort}.`);
    return false;
  }

  const candidates = collectMysqlCandidates(dbName);
  for (const c of candidates) {
    console.log(`[mysql] Dang khoi dong ${c.label}...`);
    const child = spawn(c.mysqld, c.args, {
      cwd: c.cwd,
      detached: false,
      stdio: 'ignore',
      windowsHide: true
    });
    child.unref();

    for (let i = 0; i < 25; i++) {
      await new Promise((r) => setTimeout(r, 400));
      if (await checkPort(dbPort, '127.0.0.1')) {
        console.log(`[mysql] MySQL (port ${dbPort}) da san sang.`);
        return true;
      }
    }
  }

  console.warn(`[warn] MySQL (${dbHost}:${dbPort}) chua bat. Hay bat MySQL trong Laragon / XAMPP / Homebrew.`);
  return false;
}

function bootstrapDb(php) {
  const scriptPath = path.join(ROOT, 'scripts', 'bootstrap-db.php');
  const r = spawnSync(php, [scriptPath], { cwd: ROOT, stdio: 'inherit' });
  if (r.status !== 0) {
    process.exit(r.status ?? 1);
  }
}

function walkPhp(dir, list = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === 'node_modules' || entry.name === 'vendor' || entry.name === '.git') continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walkPhp(full, list);
    else if (entry.name.endsWith('.php')) list.push(full);
  }
  return list;
}

if (cmd === 'dev') {
  const php = findPhp();
  const port = getCfg('PORT', '8080');
  const dbHost = getCfg('DB_HOST', '127.0.0.1');
  const dbPort = Number(getCfg('DB_PORT', '3306')) || 3306;

  if (await checkPort(Number(port))) {
    console.warn(`[warn] Cong ${port} dang duoc su dung boi tien trinh khac. Hay tat server cu hoac doi PORT trong .env.`);
    process.exit(1);
  }

  await ensureMysql();
  bootstrapDb(php);

  console.log(`[dev] Personal Calendar dang chay tai http://127.0.0.1:${port}`);
  const env = process.platform === 'win32'
    ? process.env
    : { ...process.env, PHP_CLI_SERVER_WORKERS: '4' };
  const child = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', ROOT], {
    cwd: ROOT,
    stdio: 'inherit',
    env
  });

  const mysqlWatchdog = setInterval(async () => {
    if ((dbHost === '127.0.0.1' || dbHost === 'localhost') && !(await checkPort(dbPort, '127.0.0.1'))) {
      await ensureMysql();
    }
  }, 5000);

  child.on('exit', (code) => {
    clearInterval(mysqlWatchdog);
    process.exit(code ?? 0);
  });
} else if (cmd === 'db:setup') {
  const php = findPhp();
  await ensureMysql();
  bootstrapDb(php);
} else if (cmd === 'lint') {
  const php = findPhp();
  const files = walkPhp(ROOT);
  let failed = 0;
  for (const f of files) {
    const r = spawnSync(php, ['-l', f], { encoding: 'utf8' });
    if (r.status !== 0) {
      failed++;
      console.error(r.stderr || r.stdout);
    }
  }
  if (failed > 0) process.exit(1);
  console.log(`[lint] OK (${files.length} PHP files)`);
} else if (cmd === 'test') {
  const php = findPhp();
  await ensureMysql();
  bootstrapDb(php);
  const child = spawn(php, [path.join(ROOT, 'tests', 'test_phase3_suite.php')], {
    cwd: ROOT,
    stdio: 'inherit'
  });
  child.on('exit', (code) => process.exit(code ?? 0));
} else if (cmd === 'ui') {
  const uiRoot = path.join(ROOT, 'lich-todo-ui');
  const port = Number(getCfg('UI_PORT', '8081'));
  const mime = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.json': 'application/json'
  };
  http.createServer((req, res) => {
    const reqPath = (req.url || '/').split('?')[0];
    const rel = reqPath === '/' ? 'index.html' : reqPath.replace(/^\/+/, '');
    const filePath = path.resolve(uiRoot, rel);
    if (!filePath.startsWith(uiRoot) || !fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
      res.writeHead(404);
      res.end('Not found');
      return;
    }
    const ext = path.extname(filePath).toLowerCase();
    res.writeHead(200, { 'Content-Type': mime[ext] || 'application/octet-stream' });
    fs.createReadStream(filePath).pipe(res);
  }).listen(port, '127.0.0.1', () => {
    console.log(`[ui] lich-todo-ui dang chay tai http://127.0.0.1:${port}`);
  });
} else {
  console.error(`Unknown command: ${cmd}`);
  process.exit(1);
}
