<?php
session_start();

const DATA_FILE = __DIR__ . '/experience.json';
const PASSWORD_HASH = '$2y$10$HbKvznzLDVdn16hNdJO48Ohl8BprDV42dQ6Ahz1b18/9bbvH0uz0S';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function read_entries(): array
{
    $entries = json_decode((string) @file_get_contents(DATA_FILE), true);
    return is_array($entries) ? $entries : [];
}

function save_entries(array $entries): void
{
    file_put_contents(
        DATA_FILE,
        json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    );
}

function post_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function redirect_home(): never
{
    header('Location: admin.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    redirect_home();
}

if (empty($_SESSION['admin'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (password_verify(post_value('password'), PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            redirect_home();
        }
        $error = 'Incorrect password.';
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Admin Login</title>
      <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#05070d;color:#eaf2ff;font:15px system-ui}.box{width:min(400px,calc(100% - 40px));padding:30px;background:#101826;border:1px solid #00f0ff55;border-radius:14px}h1{margin:8px 0}label{display:block;margin:20px 0 6px}input,button{width:100%;padding:12px;border-radius:7px;font:inherit}input{box-sizing:border-box;background:#080e18;color:#fff;border:1px solid #00f0ff55}button{margin-top:16px;border:0;background:#7dff5e;color:#061006;font-weight:bold;cursor:pointer}.eyebrow{color:#00f0ff;font:11px monospace;letter-spacing:.14em}.error{color:#ff929d}
      </style>
    </head>
    <body><main class="box">
      <div class="eyebrow">PRIVATE ADMIN</div><h1>Portfolio editor</h1>
      <p>Sign in to manage your experience.</p>
      <?php if ($error): ?><p class="error"><?= esc($error) ?></p><?php endif; ?>
      <form method="post"><label for="password">Password</label><input id="password" name="password" type="password" required autofocus><button type="submit">SIGN IN</button></form>
    </main></body></html>
    <?php
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], post_value('csrf'))) {
        http_response_code(403);
        exit('Invalid request.');
    }

    $entries = read_entries();
    $action = post_value('action');
    $id = post_value('id');

    if ($action === 'delete') {
        $entries = array_filter($entries, static fn (array $entry): bool => ($entry['id'] ?? '') !== $id);
    }

    if ($action === 'save') {
        $entry = [
            'id' => $id !== '' ? $id : bin2hex(random_bytes(6)),
            'date' => post_value('date'),
            'title' => post_value('title'),
            'place' => post_value('place'),
            'description' => post_value('description'),
        ];
        $updated = false;
        foreach ($entries as $index => $existing) {
            if (($existing['id'] ?? '') === $entry['id']) {
                $entries[$index] = $entry;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $entries[] = $entry;
        }
    }

    save_entries($entries);
    redirect_home();
}

$entries = read_entries();
$edit_id = (string) ($_GET['edit'] ?? '');
$edit = null;
foreach ($entries as $entry) {
    if (($entry['id'] ?? '') === $edit_id) {
        $edit = $entry;
        break;
    }
}
$edit ??= ['id' => '', 'date' => '', 'title' => '', 'place' => '', 'description' => ''];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Experience Admin</title>
  <style>
    :root{--bg:#05070d;--panel:#101826;--cyan:#00f0ff;--green:#7dff5e;--muted:#8fa0b8;--line:#00f0ff33}
    *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#101c33,var(--bg) 65%);color:#eaf2ff;font:15px system-ui}.wrap{width:min(1000px,calc(100% - 36px));margin:auto}.top{display:flex;justify-content:space-between;align-items:center;padding:28px 0}.top a{color:var(--green);font:12px monospace;text-decoration:none}.layout{display:grid;grid-template-columns:1fr 1fr;gap:24px}.panel{padding:24px;background:#101826cc;border:1px solid var(--line);border-radius:14px}h1,h2{margin:8px 0 18px}.eyebrow{color:var(--cyan);font:11px monospace;letter-spacing:.14em}label{display:block;margin:14px 0 5px;color:#cfe6f7}input,textarea{width:100%;padding:11px;background:#080e18;color:#fff;border:1px solid var(--line);border-radius:7px;font:inherit}.date-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.check{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px}.check input{width:auto}.hint{margin:8px 0 0;color:var(--muted);font-size:12px}textarea{min-height:100px;resize:vertical}button,.button{display:inline-block;padding:10px 14px;border:0;border-radius:7px;background:var(--green);color:#061006;font-weight:bold;text-decoration:none;cursor:pointer}.actions{display:flex;gap:10px;margin-top:16px}.secondary{background:transparent;color:#cfe6f7;border:1px solid var(--line)}.entry{padding:15px 0;border-bottom:1px solid var(--line)}.entry strong{display:block;font-size:17px}.entry small{color:var(--green);font:11px monospace}.entry p{margin:4px 0;color:var(--muted)}.delete{background:#401923;color:#ffacb5}@media(max-width:760px){.layout{grid-template-columns:1fr}.date-grid{grid-template-columns:1fr}}
  </style>
</head>
<body><div class="wrap">
  <header class="top"><div><div class="eyebrow">PRIVATE PORTFOLIO ADMIN</div><h1>Experience editor</h1></div><a href="?logout=1">LOG OUT</a></header>
  <main class="layout">
    <section class="panel"><div class="eyebrow"><?= $edit['id'] ? 'EDIT ENTRY' : 'ADD ENTRY' ?></div><h2><?= $edit['id'] ? 'Update experience' : 'New experience' ?></h2>
      <form method="post" id="experience-form">
        <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= esc($edit['id']) ?>">
        <div class="date-grid"><div><label for="start-month">Start month</label><input id="start-month" type="month"></div><div><label for="end-month">End month</label><input id="end-month" type="month"></div></div>
        <label class="check"><input id="current-role" type="checkbox"> I currently work or study here</label><label for="date">Date shown on portfolio</label><input id="date" name="date" required value="<?= esc($edit['date']) ?>" placeholder="FEB 2026 - PRESENT"><p class="hint">Pick the months above. This label fills automatically, or type a custom value such as EDUCATION.</p>
        <label for="title">Role or degree</label><input id="title" name="title" required value="<?= esc($edit['title']) ?>" placeholder="Cloud Security Intern">
        <label for="place">Company or institution</label><input id="place" name="place" required value="<?= esc($edit['place']) ?>" placeholder="Company name">
        <label for="description">Description</label><textarea id="description" name="description" required><?= esc($edit['description']) ?></textarea>
        <div class="actions"><button type="submit">SAVE ENTRY</button><?php if ($edit['id']): ?><a class="button secondary" href="admin.php">CANCEL</a><?php endif; ?></div>
      </form>
    </section>
    <section class="panel"><div class="eyebrow">LIVE DATA</div><h2>Current entries</h2>
      <?php foreach ($entries as $entry): ?><article class="entry"><small><?= esc($entry['date'] ?? '') ?></small><strong><?= esc($entry['title'] ?? '') ?></strong><p><?= esc($entry['place'] ?? '') ?></p><div class="actions"><a class="button secondary" href="?edit=<?= rawurlencode($entry['id']) ?>">EDIT</a><form method="post" onsubmit="return confirm('Delete this entry?')"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= esc($entry['id']) ?>"><button class="delete" type="submit">DELETE</button></form></div></article><?php endforeach; ?>
    </section>
  </main>
</div>
<script>
    const form = document.querySelector('#experience-form');
    const dateLabel = document.querySelector('#date');
    const startMonth = document.querySelector('#start-month');
    const endMonth = document.querySelector('#end-month');
    const currentRole = document.querySelector('#current-role');
    const monthLabel = value => value ? new Date(`${value}-01T00:00:00`).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }).toUpperCase() : '';
    const monthNumbers = { JAN: '01', FEB: '02', MAR: '03', APR: '04', MAY: '05', JUN: '06', JUL: '07', AUG: '08', SEP: '09', OCT: '10', NOV: '11', DEC: '12' };
    const existingDate = dateLabel.value.match(/^([A-Z]{3}) (\d{4}) - (PRESENT|([A-Z]{3}) (\d{4}))$/);
    if (existingDate && monthNumbers[existingDate[1]]) {
        startMonth.value = `${existingDate[2]}-${monthNumbers[existingDate[1]]}`;
        if (existingDate[3] === 'PRESENT') {
            currentRole.checked = true;
        } else if (monthNumbers[existingDate[4]]) {
            endMonth.value = `${existingDate[5]}-${monthNumbers[existingDate[4]]}`;
        }
    }
    const updateDateLabel = () => {
        if (!startMonth.value) return;
        dateLabel.value = `${monthLabel(startMonth.value)} - ${currentRole.checked ? 'PRESENT' : monthLabel(endMonth.value)}`;
    };
    const updateEndMonth = () => {
        endMonth.disabled = currentRole.checked;
        endMonth.required = !currentRole.checked;
        updateDateLabel();
    };
    [startMonth, endMonth].forEach(control => control.addEventListener('change', updateDateLabel));
    currentRole.addEventListener('change', updateEndMonth);
    form.addEventListener('submit', event => {
        if (startMonth.value && !currentRole.checked && !endMonth.value) {
            event.preventDefault();
            alert('Choose an end month or select the Present checkbox.');
        }
    });
    updateEndMonth();
</script>
</body></html>