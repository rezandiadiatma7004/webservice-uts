<?php
/*******************************************************
 * SIPDASH - Front End Reader (Single File)
 * Menampilkan 10 tabel via dropdown (tanpa batasan row)
 * + Export CSV
 *******************************************************/

/* ====== KONFIGURASI DATABASE ====== */
$DB_DRIVER = 'mysql';
$DB_HOST   = 'sql313.infinityfree.com';
$DB_PORT   = '3306';
$DB_NAME   = 'if0_40291120_sipdash';
$DB_USER   = 'if0_40291120';
$DB_PASS   = 'Semarangkota12';
$CHARSET   = 'utf8mb4';

/* ====== DAFTAR TABEL (WHITELIST) ======
   Pilih 10 tabel utama agar sesuai permintaan
*/
$ALLOWED_TABLES = [
  'roles',
  'opd',
  'users',
  'data_catalog',
  'request_status',
  'requests',
  'documents',
  'approvals',
  'notifications',
  'activity_logs',
];

/* ====== HELPER: KONEKSI PDO ====== */
function pdo_connect($DB_DRIVER, $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS, $CHARSET) {
    $dsn = "{$DB_DRIVER}:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$CHARSET}";
    $opt = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ];
    return new PDO($dsn, $DB_USER, $DB_PASS, $opt);
}

/* ====== HELPER: AMANKAN NAMA TABEL ====== */
function pick_table($allowed, $input) {
    if (!$input) return $allowed[0]; // default tabel pertama
    return in_array($input, $allowed, true) ? $input : $allowed[0];
}

/* ====== HELPER: AMBIL KOLOM DINAMIS ====== */
function get_columns(PDO $pdo, $table, $dbName) {
    // Pakai INFORMATION_SCHEMA agar presisi & urut
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([':db' => $dbName, ':tbl' => $table]);
    return array_map(fn($r) => $r['COLUMN_NAME'], $stmt->fetchAll());
}

/* ====== HELPER: AMBIL SEMUA BARIS ====== */
function fetch_all(PDO $pdo, $table) {
    // Nama tabel tidak boleh di-binding -> pakai whitelist di luar
    $sql = "SELECT * FROM `{$table}`";
    return $pdo->query($sql); // PDOStatement (buffered)
}

/* ====== HELPER: EXPORT CSV ====== */
function export_csv(PDO $pdo, $table, $dbName) {
    $cols = get_columns($pdo, $table, $dbName);
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$table}.csv");
    $out = fopen('php://output', 'w');
    // header
    fputcsv($out, $cols);
    // data
    $stmt = fetch_all($pdo, $table);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Pastikan urutan kolom konsisten
        $line = [];
        foreach ($cols as $c) {
            $val = $row[$c] ?? '';
            if (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
            $line[] = $val;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

/* ====== MAIN ====== */
try {
    $pdo = pdo_connect($DB_DRIVER, $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS, $CHARSET);
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2>Koneksi database gagal.</h2>";
    echo "<pre>".htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')."</pre>";
    exit;
}

$selected = pick_table($ALLOWED_TABLES, $_GET['table'] ?? null);

// Export CSV jika diminta
if (isset($_GET['export']) && $_GET['export'] === '1') {
    export_csv($pdo, $selected, $DB_NAME);
}

// Ambil metadata kolom & data
$columns = get_columns($pdo, $selected, $DB_NAME);
$stmt    = fetch_all($pdo, $selected);
$rows    = $stmt->fetchAll(); // buffered; tanpa limit (hati-hati jika jutaan baris)
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>SIPDASH - Viewer</title>
  <style>
    :root {
      --bg: #0f172a;
      --card: #111827;
      --muted: #94a3b8;
      --text: #e5e7eb;
      --accent: #22c55e;
      --accent-2: #06b6d4;
      --border: #1f2937;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 24px;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
      background: linear-gradient(180deg, #0b1220, #0f172a);
      color: var(--text);
    }
    .container { max-width: 1200px; margin: 0 auto; }
    .card {
      background: radial-gradient(1200px 200px at 10% -10%, rgba(34,197,94,0.12), transparent),
                  radial-gradient(1000px 160px at 90% -10%, rgba(6,182,212,0.12), transparent),
                  var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,.35);
    }
    h1 {
      font-size: 24px; margin: 0 0 12px 0;
      letter-spacing: .3px;
    }
    .subtitle { color: var(--muted); margin-bottom: 16px; }
    .controls {
      display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
      margin-bottom: 16px;
    }
    select, button, a.button {
      background: #0b1220;
      color: var(--text);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 14px;
      outline: none;
      transition: transform .06s ease, border-color .15s ease;
    }
    select:hover, button:hover, a.button:hover { border-color: var(--accent); }
    button:active, a.button:active { transform: scale(0.98); }
    a.button { text-decoration: none; display: inline-block; }
    .pill {
      display: inline-flex; align-items: center; gap: 8px;
      border: 1px dashed var(--border);
      padding: 8px 10px; border-radius: 9999px; color: var(--muted);
      font-size: 12px;
    }

    .table-wrap {
      width: 100%;
      overflow: auto;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: #0b1220;
    }
    table { border-collapse: separate; border-spacing: 0; width: 100%; }
    thead th {
      position: sticky; top: 0; z-index: 1;
      background: #0e1627;
      color: #cbd5e1;
      text-align: left; font-weight: 600; font-size: 13px;
      border-bottom: 1px solid var(--border);
      padding: 10px 12px;
      white-space: nowrap;
    }
    tbody td {
      border-bottom: 1px solid #0f213a;
      padding: 10px 12px; font-size: 13px; vertical-align: top;
    }
    tbody tr:nth-child(even) td { background: rgba(255,255,255,0.01); }
    code.json {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
      font-size: 12px; color: #a7f3d0;
      white-space: pre-wrap; word-break: break-word;
    }
    .footer {
      display: flex; justify-content: space-between; align-items: center; gap: 12px;
      color: var(--muted); font-size: 12px; margin-top: 12px;
    }
    .kbd { font-family: ui-monospace, monospace; padding: 0 6px; background: #0e1627; border: 1px solid var(--border); border-radius: 6px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1>SIPDASH — Sistem Informasi Pelayanan Data & Arsip Semarang Hebat</h1>
      <div class="subtitle">Rezandi Adiatma | 24.01.53.7004</div>

      <form class="controls" method="get">
        <label for="table" class="pill">Pilih tabel</label>
        <select name="table" id="table" onchange="this.form.submit()">
          <?php foreach ($ALLOWED_TABLES as $tbl): ?>
            <option value="<?= htmlspecialchars($tbl, ENT_QUOTES, 'UTF-8'); ?>"
              <?= $selected === $tbl ? 'selected' : '' ?>>
              <?= htmlspecialchars($tbl, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <a class="button" href="?table=<?= urlencode($selected) ?>&export=1" title="Export CSV">Export CSV</a>
        <button type="submit">Tampilkan</button>
        <span class="pill">Tabel aktif: <strong><?= htmlspecialchars($selected, ENT_QUOTES, 'UTF-8'); ?></strong></span>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
          <tr>
            <?php foreach ($columns as $c): ?>
              <th><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></th>
            <?php endforeach; ?>
          </tr>
          </thead>
          <tbody>
          <?php if (count($rows) === 0): ?>
            <tr><td colspan="<?= count($columns) ?>">(Tidak ada data)</td></tr>
          <?php else:
              foreach ($rows as $r):
                  echo "<tr>";
                  foreach ($columns as $c) {
                      $val = $r[$c] ?? '';
                      // Format JSON/long text agar terbaca
                      if (is_null($val)) {
                          echo "<td><em style='color:#94a3b8'>(NULL)</em></td>";
                      } else if (is_string($val) && strlen($val) > 0 && ($val[0] === '{' || $val[0] === '[')) {
                          // kemungkinan JSON
                          $pretty = $val;
                          // coba decode untuk prettify
                          $decoded = json_decode($val, true);
                          if (json_last_error() === JSON_ERROR_NONE) {
                              $pretty = json_encode($decoded, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
                          }
                          echo "<td><code class='json'>".htmlspecialchars($pretty, ENT_QUOTES, 'UTF-8')."</code></td>";
                      } else {
                          echo "<td>".htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8')."</td>";
                      }
                  }
                  echo "</tr>";
              endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>

      <div class="footer">
        <div>
          <span class="kbd">ESC</span> untuk batal export •
          <span class="kbd">CTRL/CMD + F</span> cari di tabel (native browser)
        </div>
        <div>DB: <?= htmlspecialchars($DB_NAME, ENT_QUOTES, 'UTF-8'); ?> @ <?= htmlspecialchars($DB_HOST, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
    </div>
  </div>
</body>
</html>
