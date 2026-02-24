<?php
/**
 * ProPulse — Mini Web + Zona Privada (Single-file)
 * - Públic: Inici, Botiga (compra fake), Qui som, Contacte
 * - Privat: Registrar/Login, Dashboard (activitats + estadístiques)
 * - BD: SQLite (propulse.sqlite) auto-creada
 *
 * Requisits: PHP 8+ amb PDO_SQLITE habilitat
 */

declare(strict_types=1);
date_default_timezone_set("Europe/Madrid");
session_start();

// ---------------------------
// CONFIG
// ---------------------------
$APP_NAME = "ProPulse";
$SQLITE_PATH = __DIR__ . "/propulse.sqlite";

// ---------------------------
// HELPERS
// ---------------------------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

function flash_set(string $type, string $msg): void {
  $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg];
}
function flash_get(): ?array {
  if (!isset($_SESSION["flash"])) return null;
  $f = $_SESSION["flash"];
  unset($_SESSION["flash"]);
  return $f;
}

function is_logged(): bool { return isset($_SESSION["uid"]); }
function require_login(): void {
  if (!is_logged()) {
    flash_set("warn", "Has d'iniciar sessió per accedir a l'àrea privada.");
    header("Location: ?page=login");
    exit;
  }
}

// CSRF
function csrf_init(): void {
  if (empty($_SESSION["csrf"])) $_SESSION["csrf"] = bin2hex(random_bytes(32));
}
function csrf_token(): string { csrf_init(); return $_SESSION["csrf"]; }
function csrf_check(): void {
  csrf_init();
  $t = $_POST["csrf"] ?? "";
  if (!hash_equals($_SESSION["csrf"], $t)) {
    http_response_code(403);
    die("CSRF token invàlid.");
  }
}

// Cart
function cart_init(): void {
  if (!isset($_SESSION["cart"]) || !is_array($_SESSION["cart"])) $_SESSION["cart"] = [];
}
function cart_add(int $pid, int $qty = 1): void {
  cart_init();
  if (!isset($_SESSION["cart"][$pid])) $_SESSION["cart"][$pid] = 0;
  $_SESSION["cart"][$pid] += max(1, $qty);
}
function cart_remove(int $pid): void {
  cart_init();
  unset($_SESSION["cart"][$pid]);
}
function cart_clear(): void {
  $_SESSION["cart"] = [];
}
function cart_count_items(): int {
  cart_init();
  $c = 0;
  foreach ($_SESSION["cart"] as $q) $c += (int)$q;
  return $c;
}

// ---------------------------
// DB
// ---------------------------
function pdo_sqlite(string $path): PDO {
  $pdo = new PDO("sqlite:" . $path, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $pdo->exec("PRAGMA foreign_keys = ON;");
  return $pdo;
}

function migrate(PDO $pdo): void {
  // Users
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT NOT NULL UNIQUE,
      pass_hash TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
  ");

  // Activities (zona privada)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS activities (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      act_date TEXT NOT NULL,
      bpm INTEGER NOT NULL,
      speed REAL NOT NULL,
      minutes INTEGER NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );
  ");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activities_user_date ON activities(user_id, act_date);");

  // Contact messages (públic)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS contact_messages (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT NOT NULL,
      subject TEXT NOT NULL,
      message TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
  ");

  // Orders (botiga fake)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_code TEXT NOT NULL UNIQUE,
      buyer_name TEXT NOT NULL,
      buyer_email TEXT NOT NULL,
      total REAL NOT NULL,
      items_json TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
  ");
}

$db_ok = true;
$db_err = null;
$pdo = null;

try {
  $pdo = pdo_sqlite($SQLITE_PATH);
  migrate($pdo);
} catch (Throwable $e) {
  $db_ok = false;
  $db_err = $e->getMessage();
}

// ---------------------------
// CATALOG (botiga fake)
// ---------------------------
$catalog = [
  1 => [
    "name" => "ProPulse Band",
    "tag" => "Sensor cardíac",
    "price" => 29.90,
    "desc" => "Polsera fictícia per monitoritzar pulsacions i sincronitzar-les amb el panell.",
    "badge" => "TOP",
  ],
  2 => [
    "name" => "Pla Starter (1 mes)",
    "tag" => "Subscripció",
    "price" => 4.99,
    "desc" => "Accés a estadístiques bàsiques i historial (compra simulada).",
    "badge" => "NEW",
  ],
  3 => [
    "name" => "Pla Pro (12 mesos)",
    "tag" => "Subscripció",
    "price" => 39.00,
    "desc" => "Accés complet a analítiques avançades (demo).",
    "badge" => "SAVE",
  ],
  4 => [
    "name" => "Pack Running",
    "tag" => "Add-on",
    "price" => 9.50,
    "desc" => "Plantilles d’entrenament i consells de ritme (contingut fictici).",
    "badge" => "HOT",
  ],
];

// ---------------------------
// ROUTING
// ---------------------------
$page = $_GET["page"] ?? "home";
cart_init();

// ---------------------------
// ACTIONS (POST)
// ---------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_check();
  $action = $_POST["action"] ?? "";

  if (!$db_ok) {
    flash_set("danger", "No es pot accedir a la BD SQLite. Error: " . ($db_err ?? "desconegut"));
    header("Location: ?page=home");
    exit;
  }

  // --- Register ---
  if ($action === "register") {
    $name  = trim((string)($_POST["name"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $pass  = (string)($_POST["password"] ?? "");

    if ($name === "" || $email === "" || $pass === "") {
      flash_set("danger", "Omple tots els camps.");
      header("Location: ?page=register");
      exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set("danger", "El correu electrònic no és vàlid.");
      header("Location: ?page=register");
      exit;
    }
    if (mb_strlen($pass) < 6) {
      flash_set("danger", "La contrasenya ha de tenir com a mínim 6 caràcters.");
      header("Location: ?page=register");
      exit;
    }

    $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $st->execute([$email]);
    if ($st->fetch()) {
      flash_set("danger", "Aquest correu ja està registrat.");
      header("Location: ?page=register");
      exit;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users(name,email,pass_hash) VALUES (?,?,?)");
    $ins->execute([$name, $email, $hash]);

    flash_set("success", "Compte creat! Ja pots iniciar sessió.");
    header("Location: ?page=login");
    exit;
  }

  // --- Login ---
  if ($action === "login") {
    $email = trim((string)($_POST["email"] ?? ""));
    $pass  = (string)($_POST["password"] ?? "");

    if ($email === "" || $pass === "") {
      flash_set("danger", "Introdueix correu i contrasenya.");
      header("Location: ?page=login");
      exit;
    }

    $st = $pdo->prepare("SELECT id,name,email,pass_hash FROM users WHERE email = ?");
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u || !password_verify($pass, (string)$u["pass_hash"])) {
      flash_set("danger", "Credencials incorrectes.");
      header("Location: ?page=login");
      exit;
    }

    session_regenerate_id(true);
    $_SESSION["uid"] = (int)$u["id"];
    $_SESSION["uname"] = (string)$u["name"];
    $_SESSION["uemail"] = (string)$u["email"];

    flash_set("success", "Benvingut/da, " . (string)$u["name"] . "!");
    header("Location: ?page=dashboard");
    exit;
  }

  // --- Logout ---
  if ($action === "logout") {
    $_SESSION = array_diff_key($_SESSION, array_flip(["cart"])); // conserva el carret si vols
    // Si prefereixes esborrar-ho TOT, comenta la línia anterior i descomenta aquestes 3:
    // $_SESSION = [];
    // session_destroy();
    // session_start();

    // neteja dades d'usuari
    unset($_SESSION["uid"], $_SESSION["uname"], $_SESSION["uemail"]);

    flash_set("success", "Sessió tancada correctament.");
    header("Location: ?page=home");
    exit;
  }

  // --- Add activity (privat) ---
  if ($action === "add_activity") {
    require_login();

    $date    = trim((string)($_POST["act_date"] ?? ""));
    $bpm     = trim((string)($_POST["bpm"] ?? ""));
    $speed   = trim((string)($_POST["speed"] ?? ""));
    $minutes = trim((string)($_POST["minutes"] ?? ""));

    if ($date === "" || $bpm === "" || $speed === "" || $minutes === "") {
      flash_set("danger", "Omple tots els camps de l'activitat.");
      header("Location: ?page=dashboard#new");
      exit;
    }

    $d = DateTime::createFromFormat("Y-m-d", $date);
    if (!$d || $d->format("Y-m-d") !== $date) {
      flash_set("danger", "La data no és vàlida.");
      header("Location: ?page=dashboard#new");
      exit;
    }
    if (!ctype_digit($bpm) || (int)$bpm < 30 || (int)$bpm > 250) {
      flash_set("danger", "Les pulsacions han de ser un enter entre 30 i 250.");
      header("Location: ?page=dashboard#new");
      exit;
    }
    if (!is_numeric($speed) || (float)$speed < 0 || (float)$speed > 99.99) {
      flash_set("danger", "La velocitat ha de ser un valor numèric raonable (0 - 99.99).");
      header("Location: ?page=dashboard#new");
      exit;
    }
    if (!ctype_digit($minutes) || (int)$minutes < 1 || (int)$minutes > 10000) {
      flash_set("danger", "Els minuts han de ser un enter positiu.");
      header("Location: ?page=dashboard#new");
      exit;
    }

    $ins = $pdo->prepare("INSERT INTO activities(user_id,act_date,bpm,speed,minutes) VALUES (?,?,?,?,?)");
    $ins->execute([$_SESSION["uid"], $date, (int)$bpm, (float)$speed, (int)$minutes]);

    flash_set("success", "Activitat desada!");
    header("Location: ?page=dashboard");
    exit;
  }

  // --- Cart actions (públic) ---
  if ($action === "cart_add") {
    $pid = (int)($_POST["pid"] ?? 0);
    $qty = (int)($_POST["qty"] ?? 1);
    if (!isset($catalog[$pid])) {
      flash_set("danger", "Producte no trobat.");
      header("Location: ?page=shop");
      exit;
    }
    cart_add($pid, $qty);
    flash_set("success", "Afegit al carret: " . $catalog[$pid]["name"]);
    header("Location: ?page=shop");
    exit;
  }

  if ($action === "cart_remove") {
    $pid = (int)($_POST["pid"] ?? 0);
    cart_remove($pid);
    flash_set("success", "Producte eliminat del carret.");
    header("Location: ?page=cart");
    exit;
  }

  if ($action === "cart_clear") {
    cart_clear();
    flash_set("success", "Carret buidat.");
    header("Location: ?page=cart");
    exit;
  }

  // --- Checkout (compra fake) ---
  if ($action === "checkout") {
    $name  = trim((string)($_POST["buyer_name"] ?? ""));
    $email = trim((string)($_POST["buyer_email"] ?? ""));

    if ($name === "" || $email === "") {
      flash_set("danger", "Omple el nom i el correu per finalitzar la compra (simulada).");
      header("Location: ?page=checkout");
      exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set("danger", "El correu no és vàlid.");
      header("Location: ?page=checkout");
      exit;
    }
    cart_init();
    if (empty($_SESSION["cart"])) {
      flash_set("warn", "El carret està buit.");
      header("Location: ?page=shop");
      exit;
    }

    $items = [];
    $total = 0.0;
    foreach ($_SESSION["cart"] as $pid => $qty) {
      $pid = (int)$pid;
      $qty = (int)$qty;
      if (!isset($catalog[$pid])) continue;
      $price = (float)$catalog[$pid]["price"];
      $items[] = [
        "id" => $pid,
        "name" => $catalog[$pid]["name"],
        "qty" => $qty,
        "unit" => $price,
        "line" => round($price * $qty, 2),
      ];
      $total += $price * $qty;
    }
    $total = round($total, 2);

    $order_code = "PP-" . strtoupper(bin2hex(random_bytes(3))) . "-" . date("His");
    $ins = $pdo->prepare("INSERT INTO orders(order_code,buyer_name,buyer_email,total,items_json) VALUES (?,?,?,?,?)");
    $ins->execute([$order_code, $name, $email, $total, json_encode($items, JSON_UNESCAPED_UNICODE)]);

    cart_clear();
    flash_set("success", "Compra simulada completada! Codi de comanda: $order_code");
    header("Location: ?page=order&code=" . urlencode($order_code));
    exit;
  }

  // --- Contact form ---
  if ($action === "contact_send") {
    $name = trim((string)($_POST["name"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $subject = trim((string)($_POST["subject"] ?? ""));
    $message = trim((string)($_POST["message"] ?? ""));

    if ($name === "" || $email === "" || $subject === "" || $message === "") {
      flash_set("danger", "Omple tots els camps del contacte.");
      header("Location: ?page=contact");
      exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set("danger", "El correu no és vàlid.");
      header("Location: ?page=contact");
      exit;
    }
    if (mb_strlen($message) < 10) {
      flash_set("danger", "El missatge és massa curt (mínim 10 caràcters).");
      header("Location: ?page=contact");
      exit;
    }

    $ins = $pdo->prepare("INSERT INTO contact_messages(name,email,subject,message) VALUES (?,?,?,?)");
    $ins->execute([$name, $email, $subject, $message]);

    flash_set("success", "Missatge enviat! (Guardat a la BD com a demo)");
    header("Location: ?page=contact");
    exit;
  }

  flash_set("warn", "Acció desconeguda.");
  header("Location: ?page=home");
  exit;
}

// ---------------------------
// DATA (GET) per dashboard
// ---------------------------
$user_activities = [];
$stats = ["count"=>0, "avg_bpm"=>0.0, "avg_speed"=>0.0, "total_minutes"=>0];

if ($db_ok && is_logged()) {
  $st = $pdo->prepare("
    SELECT id, act_date, bpm, speed, minutes
    FROM activities
    WHERE user_id = ?
    ORDER BY act_date DESC, id DESC
  ");
  $st->execute([$_SESSION["uid"]]);
  $user_activities = $st->fetchAll();

  $count = count($user_activities);
  if ($count > 0) {
    $sum_bpm = 0; $sum_speed = 0.0; $sum_min = 0;
    foreach ($user_activities as $a) {
      $sum_bpm += (int)$a["bpm"];
      $sum_speed += (float)$a["speed"];
      $sum_min += (int)$a["minutes"];
    }
    $stats["count"] = $count;
    $stats["avg_bpm"] = round($sum_bpm / $count, 1);
    $stats["avg_speed"] = round($sum_speed / $count, 2);
    $stats["total_minutes"] = $sum_min;
  }
}

// Order view (GET)
$order = null;
if ($db_ok && $page === "order") {
  $code = (string)($_GET["code"] ?? "");
  if ($code !== "") {
    $st = $pdo->prepare("SELECT order_code,buyer_name,buyer_email,total,items_json,created_at FROM orders WHERE order_code = ?");
    $st->execute([$code]);
    $order = $st->fetch();
  }
}

// ---------------------------
// VIEW HELPERS
// ---------------------------
function nav_item(string $label, string $to, bool $active=false, string $pill=null): string {
  $cls = $active ? "nav__link nav__link--active" : "nav__link";
  $p = $pill ? '<span class="navpill">'.h($pill).'</span>' : '';
  return '<a class="'.h($cls).'" href="'.h($to).'"><span>'.h($label).'</span>'.$p.'</a>';
}

$flash = flash_get();
csrf_init();

// totals carret
$cart_total = 0.0;
$cart_lines = [];
foreach ($_SESSION["cart"] as $pid => $qty) {
  $pid = (int)$pid;
  $qty = (int)$qty;
  if (!isset($catalog[$pid])) continue;
  $line = (float)$catalog[$pid]["price"] * $qty;
  $cart_total += $line;
  $cart_lines[] = ["pid"=>$pid, "qty"=>$qty, "line"=>$line];
}
$cart_total = round($cart_total, 2);

?>
<!doctype html>
<html lang="ca">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($APP_NAME) ?> · Mini Web + Projecte</title>
  <style>
    :root{
      --bg:#0b1020;
      --panel: rgba(255,255,255,.06);
      --panel2: rgba(255,255,255,.10);
      --stroke: rgba(255,255,255,.12);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.70);
      --brand:#7c3aed;
      --brand2:#22c55e;
      --danger:#ef4444;
      --warn:#f59e0b;
      --ok:#10b981;
      --shadow: 0 20px 60px rgba(0,0,0,.35);
      --radius:18px;
      --radius2:26px;
      --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      --sans: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: var(--sans);
      color: var(--text);
      background:
        radial-gradient(1200px 700px at 20% 10%, rgba(124,58,237,.35), transparent 55%),
        radial-gradient(1000px 600px at 85% 15%, rgba(34,197,94,.22), transparent 55%),
        radial-gradient(900px 600px at 50% 95%, rgba(59,130,246,.18), transparent 50%),
        linear-gradient(180deg, #070a14, var(--bg));
      overflow-x:hidden;
    }
    a{color:inherit; text-decoration:none}
    .wrap{max-width:1120px; margin:0 auto; padding:22px 18px 70px}

    .topbar{
      display:flex; align-items:center; justify-content:space-between;
      gap:16px; padding:14px 16px;
      background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03));
      border:1px solid var(--stroke);
      border-radius: var(--radius2);
      box-shadow: var(--shadow);
      backdrop-filter: blur(14px);
      position: sticky; top: 14px; z-index: 10;
    }
    .brand{display:flex; align-items:center; gap:12px; min-width:220px;}
    .logo{
      width:40px; height:40px; border-radius:14px;
      background: conic-gradient(from 210deg, var(--brand), #60a5fa, var(--brand2), var(--brand));
      box-shadow: 0 10px 25px rgba(124,58,237,.25);
      position:relative;
    }
    .logo:after{
      content:""; position:absolute; inset:10px; border-radius:10px;
      background: rgba(0,0,0,.25);
      border:1px solid rgba(255,255,255,.12);
    }
    .brand h1{margin:0; font-size:15px; letter-spacing:.2px}
    .brand p{margin:0; font-size:12px; color:var(--muted)}

    .nav{display:flex; gap:10px; flex-wrap:wrap; justify-content:center}
    .nav__link{
      display:inline-flex; align-items:center; gap:10px;
      padding:10px 12px; border-radius:999px;
      border:1px solid transparent;
      color: var(--muted);
      transition:.18s ease;
      font-size:13px;
      background: transparent;
    }
    .nav__link:hover{
      color: var(--text);
      background: rgba(255,255,255,.06);
      border-color: rgba(255,255,255,.08);
      transform: translateY(-1px);
    }
    .nav__link--active{
      color: var(--text);
      background: rgba(124,58,237,.20);
      border-color: rgba(124,58,237,.35);
    }
    .navpill{
      font-size:11px;
      padding:4px 8px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,.12);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.82);
    }

    .userchip{
      display:flex; align-items:center; gap:10px;
      padding:10px 12px; border-radius:999px;
      background: rgba(255,255,255,.06);
      border:1px solid rgba(255,255,255,.10);
      min-width:220px; justify-content:flex-end;
    }
    .avatar{
      width:32px; height:32px; border-radius:999px;
      background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.28), rgba(255,255,255,.06));
      border:1px solid rgba(255,255,255,.12);
    }
    .userchip small{display:block; color:var(--muted); font-size:11px}
    .userchip strong{display:block; font-size:13px}

    .grid{margin-top:18px; display:grid; grid-template-columns: 1.2fr .8fr; gap:16px;}
    @media (max-width:980px){
      .grid{grid-template-columns:1fr}
      .brand{min-width:auto}
      .userchip{min-width:auto}
      .topbar{position:relative; top:auto}
    }

    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.07), rgba(255,255,255,.03));
      border:1px solid var(--stroke);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(12px);
      overflow:hidden;
    }
    .card__hd{
      padding:16px 16px 10px;
      border-bottom:1px solid rgba(255,255,255,.08);
      display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
    }
    .card__hd h2{margin:0; font-size:14px; letter-spacing:.2px}
    .card__hd p{margin:6px 0 0; color:var(--muted); font-size:12px; max-width:70ch}
    .card__bd{padding:16px}

    .hero{
      padding:28px 18px;
      border-radius: var(--radius2);
      border:1px solid rgba(255,255,255,.10);
      background:
        radial-gradient(900px 300px at 10% 0%, rgba(124,58,237,.20), transparent 60%),
        radial-gradient(900px 300px at 90% 0%, rgba(34,197,94,.18), transparent 60%),
        rgba(255,255,255,.04);
      box-shadow: var(--shadow);
    }
    .hero h2{margin:0; font-size:26px; letter-spacing:.2px}
    .hero p{margin:10px 0 0; color:var(--muted); line-height:1.55; max-width:85ch}
    .pillrow{margin-top:14px; display:flex; gap:10px; flex-wrap:wrap}
    .pill{
      padding:8px 10px; border-radius:999px;
      border:1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.05);
      color: var(--muted);
      font-size:12px;
    }
    .btnrow{margin-top:18px; display:flex; gap:10px; flex-wrap:wrap}
    .btn{
      display:inline-flex; align-items:center; justify-content:center; gap:10px;
      padding:11px 14px; border-radius:12px;
      border:1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.06);
      color: var(--text);
      font-weight:600; font-size:13px;
      transition:.18s ease;
      cursor:pointer;
    }
    .btn:hover{transform: translateY(-1px); background: rgba(255,255,255,.09)}
    .btn--brand{border-color: rgba(124,58,237,.35); background: rgba(124,58,237,.20);}
    .btn--brand:hover{background: rgba(124,58,237,.28)}
    .btn--ghost{background: transparent}
    .btn--danger{border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.15);}

    .alert{
      margin-top:16px; padding:12px 14px;
      border-radius:14px;
      border:1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.06);
      display:flex; gap:10px; align-items:flex-start;
      box-shadow: 0 12px 30px rgba(0,0,0,.25);
    }
    .alert strong{font-size:13px}
    .alert p{margin:2px 0 0; color: var(--muted); font-size:12px; line-height:1.45}
    .alert--danger{border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.10)}
    .alert--warn{border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.10)}
    .alert--success{border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.10)}
    .alert__dot{
      width:10px; height:10px; border-radius:999px; margin-top:4px;
      background: rgba(255,255,255,.35);
      box-shadow: 0 0 0 4px rgba(255,255,255,.08);
      flex:0 0 auto;
    }
    .alert--danger .alert__dot{background: rgba(239,68,68,.95); box-shadow: 0 0 0 4px rgba(239,68,68,.16)}
    .alert--warn .alert__dot{background: rgba(245,158,11,.95); box-shadow: 0 0 0 4px rgba(245,158,11,.16)}
    .alert--success .alert__dot{background: rgba(16,185,129,.95); box-shadow: 0 0 0 4px rgba(16,185,129,.16)}

    .form{display:grid; gap:12px}
    .field{display:grid; gap:7px}
    label{font-size:12px; color: var(--muted)}
    input, textarea{
      width:100%;
      padding:12px 12px;
      border-radius:14px;
      border:1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.18);
      color: var(--text);
      outline:none;
      transition:.18s ease;
      font-family: var(--sans);
    }
    textarea{min-height: 120px; resize: vertical;}
    input:focus, textarea:focus{
      border-color: rgba(124,58,237,.55);
      box-shadow: 0 0 0 4px rgba(124,58,237,.18);
    }
    .two{display:grid; grid-template-columns:1fr 1fr; gap:12px}
    @media (max-width:560px){ .two{grid-template-columns:1fr} }

    .kpis{display:grid; grid-template-columns:1fr 1fr; gap:12px}
    .kpi{
      padding:14px; border-radius:16px;
      border:1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.05);
    }
    .kpi small{display:block; color: var(--muted); font-size:11px}
    .kpi strong{display:block; margin-top:6px; font-size:18px; letter-spacing:.2px}

    .cards{display:grid; grid-template-columns: 1fr 1fr; gap: 12px;}
    @media (max-width:720px){ .cards{grid-template-columns:1fr} }
    .product{
      padding: 14px;
      border-radius: 18px;
      border:1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.05);
      position:relative;
      overflow:hidden;
    }
    .product__badge{
      position:absolute; top:12px; right:12px;
      font-size:11px;
      padding:4px 8px;
      border-radius: 999px;
      border:1px solid rgba(255,255,255,.12);
      background: rgba(124,58,237,.18);
    }
    .product h3{margin:0; font-size:15px}
    .product p{margin:8px 0 0; color: var(--muted); font-size:12px; line-height:1.55}
    .price{margin-top: 10px; display:flex; align-items:baseline; gap:10px}
    .price strong{font-size:18px}
    .price span{font-size:12px; color: var(--muted)}
    .hr{height:1px; background: rgba(255,255,255,.10); margin:14px 0}
    table{
      width:100%;
      border-collapse:separate;
      border-spacing:0;
      border-radius:16px;
      border:1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.10);
      overflow:hidden;
    }
    thead th{
      text-align:left; font-size:12px; color: var(--muted);
      padding:12px 12px;
      border-bottom:1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.04);
    }
    tbody td{
      padding:12px 12px;
      border-bottom:1px solid rgba(255,255,255,.07);
      font-size:13px;
    }
    tbody tr:hover td{background: rgba(255,255,255,.03)}
    tbody tr:last-child td{border-bottom:none}

    .footer{margin-top:22px; color: rgba(255,255,255,.55); font-size:12px; text-align:center;}
    .badge{
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 10px; border-radius:999px;
      border:1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.04);
      color: rgba(255,255,255,.78);
      font-size:12px;
    }
    .mono{font-family:var(--mono)}
  </style>
</head>
<body>
  <div class="wrap">
    <header class="topbar">
      <div class="brand">
        <div class="logo" aria-hidden="true"></div>
        <div>
          <h1><?= h($APP_NAME) ?></h1>
          <p>Mini web del projecte + zona privada</p>
        </div>
      </div>

      <nav class="nav" aria-label="Navegació">
        <?= nav_item("Inici", "?page=home", $page==="home"); ?>
        <?= nav_item("Botiga", "?page=shop", $page==="shop"); ?>
        <?= nav_item("Carret", "?page=cart", $page==="cart", (string)cart_count_items()); ?>
        <?= nav_item("Qui som", "?page=about", $page==="about"); ?>
        <?= nav_item("Contacte", "?page=contact", $page==="contact"); ?>
        <?= nav_item("Àrea usuari", is_logged() ? "?page=dashboard" : "?page=login", in_array($page, ["login","register","dashboard"], true)); ?>
      </nav>

      <div class="userchip">
        <div>
          <?php if (is_logged()): ?>
            <small>Connectat com</small>
            <strong><?= h((string)$_SESSION["uname"]) ?></strong>
          <?php else: ?>
            <small>Mode</small>
            <strong>Convidat</strong>
          <?php endif; ?>
        </div>
        <div class="avatar" aria-hidden="true"></div>
      </div>
    </header>

    <?php if ($flash): ?>
      <div class="alert alert--<?= h($flash["type"]) ?>">
        <div class="alert__dot" aria-hidden="true"></div>
        <div>
          <strong><?= h(mb_strtoupper((string)$flash["type"])) ?></strong>
          <p><?= h((string)$flash["msg"]) ?></p>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$db_ok): ?>
      <div class="alert alert--danger">
        <div class="alert__dot" aria-hidden="true"></div>
        <div>
          <strong>ERROR BD SQLITE</strong>
          <p>No es pot crear/obrir <span class="mono"><?= h($SQLITE_PATH) ?></span>. Error: <span class="mono"><?= h((string)$db_err) ?></span></p>
          <p>Solució típica: dona permisos d’escriptura a la carpeta del projecte.</p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($page === "home"): ?>
      <section class="hero">
        <h2>ProPulse: el projecte complet (mini web + funcionalitat)</h2>
        <p>
          Aquesta demo presenta una <b>web pública</b> (botiga fictícia, qui som i contacte)
          i una <b>zona privada</b> on l’usuari pot registrar activitats esportives i veure estadístiques.
          Tot en un sol fitxer, amb base de dades local <b>SQLite</b>.
        </p>
        <div class="pillrow">
          <span class="pill">✅ Botiga fake amb carret + checkout</span>
          <span class="pill">✅ Pàgina “Qui som”</span>
          <span class="pill">✅ Formulari de contacte</span>
          <span class="pill">✅ Zona privada (activitats)</span>
        </div>
        <div class="btnrow">
          <a class="btn btn--brand" href="?page=shop">Anar a la botiga</a>
          <a class="btn" href="?page=about">Veure el projecte</a>
          <a class="btn btn--ghost" href="<?= is_logged() ? "?page=dashboard" : "?page=login" ?>">Àrea usuari</a>
        </div>
      </section>

      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Què estàs demostrant aquí</h2>
              <p>Un web “complet”: navegació pública, formularis, dades persistents i una zona privada.</p>
            </div>
            <span class="badge">Projecte</span>
          </div>
          <div class="card__bd">
            <div class="kpis">
              <div class="kpi"><small>Part pública</small><strong>Botiga + Contacte</strong></div>
              <div class="kpi"><small>Persistència</small><strong>SQLite auto</strong></div>
              <div class="kpi"><small>Part privada</small><strong>Dashboard</strong></div>
              <div class="kpi"><small>Seguretat</small><strong>Sessions + CSRF</strong></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Com provar-ho ràpid</h2>
              <p>Si no tens servidor, el PHP no s’executa (no serveix doble clic).</p>
            </div>
          </div>
          <div class="card__bd">
            <div class="pillrow">
              <span class="pill">Opció: <span class="mono">php -S localhost:8000</span></span>
              <span class="pill">Obre: <span class="mono">http://localhost:8000</span></span>
            </div>
            <div class="hr"></div>
            <p style="margin:0;color:var(--muted);font-size:12px;line-height:1.6">
              Es crearà automàticament el fitxer <span class="mono">propulse.sqlite</span>.
            </p>
          </div>
        </div>
      </div>

    <?php elseif ($page === "shop"): ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Botiga (demo)</h2>
              <p>Catàleg fictici per demostrar “carret” i “checkout”. No hi ha pagament real.</p>
            </div>
            <span class="badge">Compra simulada</span>
          </div>
          <div class="card__bd">
            <div class="cards">
              <?php foreach ($catalog as $pid => $p): ?>
                <div class="product">
                  <div class="product__badge"><?= h($p["badge"]) ?></div>
                  <h3><?= h($p["name"]) ?></h3>
                  <p><span class="badge"><?= h($p["tag"]) ?></span></p>
                  <p><?= h($p["desc"]) ?></p>
                  <div class="price">
                    <strong><?= number_format((float)$p["price"], 2) ?>€</strong>
                    <span>IVA inclòs (demo)</span>
                  </div>
                  <div class="hr"></div>
                  <form method="post" action="" class="form" style="gap:10px">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="cart_add">
                    <input type="hidden" name="pid" value="<?= (int)$pid ?>">
                    <div class="two">
                      <div class="field">
                        <label>Quantitat</label>
                        <input name="qty" inputmode="numeric" value="1">
                      </div>
                      <div class="field" style="align-self:end">
                        <button class="btn btn--brand" type="submit">Afegir al carret</button>
                      </div>
                    </div>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Resum carret</h2>
              <p>Articles: <?= cart_count_items() ?> · Total: <?= number_format($cart_total, 2) ?>€</p>
            </div>
          </div>
          <div class="card__bd">
            <div class="btnrow" style="margin-top:0">
              <a class="btn btn--brand" href="?page=cart">Veure carret</a>
              <a class="btn" href="?page=checkout">Finalitzar compra</a>
            </div>
            <div class="hr"></div>
            <p style="margin:0;color:var(--muted);font-size:12px;line-height:1.6">
              Aquesta part “fa veure” una botiga: serveix per demostrar navegació, carret i un flux de compra.
            </p>
          </div>
        </div>
      </div>

    <?php elseif ($page === "cart"): ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Carret</h2>
              <p>Gestiona els productes afegits abans d’anar al checkout (simulat).</p>
            </div>
            <span class="badge"><?= cart_count_items() ?> items</span>
          </div>
          <div class="card__bd">
            <?php if (empty($_SESSION["cart"])): ?>
              <div class="alert alert--warn" style="margin:0">
                <div class="alert__dot"></div>
                <div>
                  <strong>Carret buit</strong>
                  <p>Afegeix productes des de la botiga.</p>
                </div>
              </div>
              <div class="btnrow">
                <a class="btn btn--brand" href="?page=shop">Anar a Botiga</a>
              </div>
            <?php else: ?>
              <div style="overflow:auto">
                <table>
                  <thead>
                    <tr>
                      <th>Producte</th>
                      <th>Quantitat</th>
                      <th>Preu</th>
                      <th>Total línia</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($_SESSION["cart"] as $pid => $qty): ?>
                      <?php $pid = (int)$pid; $qty = (int)$qty; if (!isset($catalog[$pid])) continue; ?>
                      <?php $unit = (float)$catalog[$pid]["price"]; $line = round($unit*$qty, 2); ?>
                      <tr>
                        <td><?= h($catalog[$pid]["name"]) ?></td>
                        <td><?= (int)$qty ?></td>
                        <td><?= number_format($unit, 2) ?>€</td>
                        <td><?= number_format($line, 2) ?>€</td>
                        <td style="text-align:right">
                          <form method="post" action="" style="margin:0">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="cart_remove">
                            <input type="hidden" name="pid" value="<?= (int)$pid ?>">
                            <button class="btn btn--danger" type="submit">Eliminar</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="hr"></div>

              <div class="kpis">
                <div class="kpi">
                  <small>Total</small>
                  <strong><?= number_format($cart_total, 2) ?>€</strong>
                </div>
                <div class="kpi">
                  <small>Nota</small>
                  <strong style="font-size:14px">Compra simulada</strong>
                </div>
              </div>

              <div class="btnrow">
                <a class="btn" href="?page=shop">Seguir comprant</a>
                <a class="btn btn--brand" href="?page=checkout">Anar a Checkout</a>
                <form method="post" action="" style="margin:0">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="cart_clear">
                  <button class="btn btn--ghost" type="submit">Buidar carret</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Per què això suma al projecte</h2>
              <p>És una “mini web comercial” falsa per donar context i demostrar fluxos web.</p>
            </div>
          </div>
          <div class="card__bd">
            <p style="margin:0;color:var(--muted);font-size:12px;line-height:1.7">
              Amb carret + checkout demostres formularis, sessions, validació i persistència (orders).
              A nivell de presentació queda molt més complet que només login.
            </p>
          </div>
        </div>
      </div>

    <?php elseif ($page === "checkout"): ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Checkout (simulat)</h2>
              <p>Introdueix dades per generar una comanda fictícia (es guarda a SQLite).</p>
            </div>
            <span class="badge">No pagament real</span>
          </div>
          <div class="card__bd">
            <?php if (empty($_SESSION["cart"])): ?>
              <div class="alert alert--warn" style="margin:0">
                <div class="alert__dot"></div>
                <div>
                  <strong>No pots finalitzar</strong>
                  <p>El carret està buit. Afegeix productes primer.</p>
                </div>
              </div>
              <div class="btnrow">
                <a class="btn btn--brand" href="?page=shop">Anar a Botiga</a>
              </div>
            <?php else: ?>
              <form class="form" method="post" action="">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="checkout">

                <div class="two">
                  <div class="field">
                    <label for="buyer_name">Nom i cognoms</label>
                    <input id="buyer_name" name="buyer_name" placeholder="Ex: Marta Puig" required>
                  </div>
                  <div class="field">
                    <label for="buyer_email">Correu</label>
                    <input id="buyer_email" name="buyer_email" type="email" placeholder="marta@email.com" required>
                  </div>
                </div>

                <div class="kpis">
                  <div class="kpi">
                    <small>Total a pagar</small>
                    <strong><?= number_format($cart_total, 2) ?>€</strong>
                  </div>
                  <div class="kpi">
                    <small>Mètode</small>
                    <strong style="font-size:14px">Simulat (demo)</strong>
                  </div>
                </div>

                <button class="btn btn--brand" type="submit">Finalitzar compra</button>
                <a class="btn btn--ghost" href="?page=cart">Tornar al carret</a>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Detall tècnic</h2>
              <p>Quan completes, es crea un registre a la taula <span class="mono">orders</span>.</p>
            </div>
          </div>
          <div class="card__bd">
            <div class="pillrow">
              <span class="pill">Guardat: <span class="mono">order_code</span></span>
              <span class="pill">Items: <span class="mono">JSON</span></span>
              <span class="pill">Total: <span class="mono">REAL</span></span>
            </div>
          </div>
        </div>
      </div>

    <?php elseif ($page === "order"): ?>
      <section class="hero">
        <h2>Confirmació de comanda</h2>
        <?php if (!$order): ?>
          <p>No s’ha trobat la comanda. Torna al checkout.</p>
          <div class="btnrow">
            <a class="btn btn--brand" href="?page=shop">Anar a Botiga</a>
          </div>
        <?php else: ?>
          <p>
            Compra simulada completada. Codi: <span class="mono"><?= h((string)$order["order_code"]) ?></span> ·
            Total: <b><?= number_format((float)$order["total"], 2) ?>€</b>
          </p>
          <div class="pillrow">
            <span class="pill">Nom: <b><?= h((string)$order["buyer_name"]) ?></b></span>
            <span class="pill">Correu: <b><?= h((string)$order["buyer_email"]) ?></b></span>
            <span class="pill">Data: <span class="mono"><?= h((string)$order["created_at"]) ?></span></span>
          </div>
          <div class="btnrow">
            <a class="btn btn--brand" href="?page=shop">Tornar a Botiga</a>
            <a class="btn" href="?page=home">Inici</a>
          </div>

          <?php $items = json_decode((string)$order["items_json"], true) ?: []; ?>
          <div class="grid" style="margin-top:16px">
            <div class="card">
              <div class="card__hd">
                <div>
                  <h2>Detall d’items</h2>
                  <p>Visualització del JSON guardat.</p>
                </div>
              </div>
              <div class="card__bd" style="overflow:auto">
                <table>
                  <thead>
                    <tr>
                      <th>Producte</th>
                      <th>Quantitat</th>
                      <th>Unitat</th>
                      <th>Línia</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $it): ?>
                      <tr>
                        <td><?= h((string)$it["name"]) ?></td>
                        <td><?= (int)$it["qty"] ?></td>
                        <td><?= number_format((float)$it["unit"], 2) ?>€</td>
                        <td><?= number_format((float)$it["line"], 2) ?>€</td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card">
              <div class="card__hd">
                <div>
                  <h2>Nota</h2>
                  <p>Això és una demo; no hi ha pagament real ni enviament.</p>
                </div>
              </div>
              <div class="card__bd">
                <p style="margin:0;color:var(--muted);font-size:12px;line-height:1.7">
                  Serveix per demostrar un flux complet de “compra” i persistència de dades.
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </section>

    <?php elseif ($page === "about"): ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Qui som</h2>
              <p>Presentació del projecte com si fos una web real.</p>
            </div>
            <span class="badge">ProPulse</span>
          </div>
          <div class="card__bd">
            <p style="margin:0;color:var(--muted);font-size:12px;line-height:1.8">
              <b>ProPulse</b> és un projecte que simula una plataforma d’entrenament amb dues parts:
              una <b>web pública</b> (informació i botiga demo) i una <b>zona privada</b> on l’usuari
              pot registrar activitats (pulsacions, velocitat i minuts) i veure estadístiques.
              <br><br>
              L’objectiu és demostrar un sistema web complet: navegació, formularis, validació, sessions i persistència.
            </p>

            <div class="hr"></div>

            <div class="kpis">
              <div class="kpi"><small>Objectiu</small><strong>Gestió d’activitats</strong></div>
              <div class="kpi"><small>Tecnologia</small><strong>PHP + SQLite</strong></div>
              <div class="kpi"><small>Disseny</small><strong>UI moderna</strong></div>
              <div class="kpi"><small>Fluxos</small><strong>Compra + Contacte</strong></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Què pots ensenyar a l’exposició</h2>
              <p>Un guió curt per explicar el projecte.</p>
            </div>
          </div>
          <div class="card__bd">
            <ol style="margin:0; padding-left: 18px; color: var(--muted); font-size:12px; line-height:1.85">
              <li>Part pública: navegació + botiga fake + carret.</li>
              <li>Checkout: genera comanda i la guarda a SQLite.</li>
              <li>Contacte: guarda missatges a la BD (demo).</li>
              <li>Àrea usuari: registre/login amb hash + sessions.</li>
              <li>Dashboard: inserir activitats i veure estadístiques.</li>
            </ol>
            <div class="hr"></div>
            <div class="btnrow" style="margin-top:0">
              <a class="btn btn--brand" href="?page=shop">Veure Botiga</a>
              <a class="btn" href="?page=contact">Contacte</a>
              <a class="btn btn--ghost" href="<?= is_logged() ? "?page=dashboard" : "?page=login" ?>">Zona privada</a>
            </div>
          </div>
        </div>
      </div>

    <?php elseif ($page === "contact"): ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Contacte</h2>
              <p>Formulari per enviar un missatge (es guarda a SQLite com a demostració).</p>
            </div>
            <span class="badge">Formulari</span>
          </div>
          <div class="card__bd">
            <form class="form" method="post" action="">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="contact_send">

              <div class="two">
                <div class="field">
                  <label for="cname">Nom</label>
                  <input id="cname" name="name" placeholder="Ex: Jordi" required>
                </div>
                <div class="field">
                  <label for="cemail">Correu</label>
                  <input id="cemail" name="email" type="email" placeholder="jordi@email.com" required>
                </div>
              </div>

              <div class="field">
                <label for="csub">Assumpte</label>
                <input id="csub" name="subject" placeholder="Ex: Dubte sobre el projecte" required>
              </div>

              <div class="field">
                <label for="cmsg">Missatge</label>
                <textarea id="cmsg" name="message" placeholder="Escriu el teu missatge..." required></textarea>
              </div>

              <button class="btn btn--brand" type="submit">Enviar missatge</button>
              <a class="btn btn--ghost" href="?page=about">Veure Qui som</a>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Informació</h2>
              <p>Dades fictícies per ambientar la web.</p>
            </div>
          </div>
          <div class="card__bd">
            <div class="pillrow">
              <span class="pill">Email: <span class="mono">support@propulse.demo</span></span>
              <span class="pill">Horari: <span class="mono">9:00-18:00</span></span>
              <span class="pill">Ubicació: <span class="mono">Barcelona</span></span>
            </div>
            <div class="hr"></div>
            <p style="margin:0;color:var(--muted);font-size:12px;line-height:1.7">
              Això t’ajuda a “omplir” la web i demostrar seccions típiques d’un projecte real.
            </p>
          </div>
        </div>
      </div>

    <?php elseif ($page === "register"): ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Registrar-se</h2>
              <p>Només per accedir al dashboard (la web pública funciona sense compte).</p>
            </div>
            <span class="badge">Àrea usuari</span>
          </div>
          <div class="card__bd">
            <form class="form" method="post" action="">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="register">

              <div class="two">
                <div class="field">
                  <label for="name">Nom</label>
                  <input id="name" name="name" placeholder="Ex: Aleix" required>
                </div>
                <div class="field">
                  <label for="email">Correu</label>
                  <input id="email" name="email" type="email" placeholder="exemple@email.com" required>
                </div>
              </div>

              <div class="field">
                <label for="password">Contrasenya</label>
                <input id="password" name="password" type="password" placeholder="mínim 6 caràcters" required>
              </div>

              <button class="btn btn--brand" type="submit">Crear compte</button>
              <a class="btn btn--ghost" href="?page=login">Ja tens compte? Login</a>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Per què hi ha login?</h2>
              <p>Per separar part pública i privada (control d'accés).</p>
            </div>
          </div>
          <div class="card__bd">
            <p style="margin:0;color:var(--muted);font-size:12px;line-height:1.7">
              Això et dona punts perquè demostres sessions, autenticació i dades per usuari.
              Però la web no queda centrada aquí: tens botiga, qui som i contacte.
            </p>
          </div>
        </div>
      </div>

    <?php elseif ($page === "login"): ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Iniciar sessió</h2>
              <p>Accedeix al panell per guardar activitats i veure estadístiques.</p>
            </div>
            <span class="badge">Àrea usuari</span>
          </div>
          <div class="card__bd">
            <form class="form" method="post" action="">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="login">

              <div class="field">
                <label for="email">Correu</label>
                <input id="email" name="email" type="email" placeholder="exemple@email.com" required>
              </div>

              <div class="field">
                <label for="password">Contrasenya</label>
                <input id="password" name="password" type="password" placeholder="La teva contrasenya" required>
              </div>

              <button class="btn btn--brand" type="submit">Entrar</button>
              <a class="btn btn--ghost" href="?page=register">Crear compte</a>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Tip ràpid</h2>
              <p>Si veus el codi PHP en pantalla, no estàs obrint amb servidor.</p>
            </div>
          </div>
          <div class="card__bd">
            <div class="pillrow">
              <span class="pill">Terminal: <span class="mono">php -S localhost:8000</span></span>
              <span class="pill">Navegador: <span class="mono">http://localhost:8000</span></span>
            </div>
          </div>
        </div>
      </div>

    <?php elseif ($page === "dashboard"): ?>
      <?php require_login(); ?>
      <div class="grid">
        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Dashboard · Activitats</h2>
              <p>Registra pulsacions, velocitat i minuts. Mira estadístiques i historial.</p>
            </div>
            <form method="post" action="" style="margin:0">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="logout">
              <button class="btn btn--danger" type="submit">Logout</button>
            </form>
          </div>
          <div class="card__bd">
            <div class="kpis">
              <div class="kpi"><small>Activitats</small><strong><?= (int)$stats["count"] ?></strong></div>
              <div class="kpi"><small>Temps total</small><strong><?= (int)$stats["total_minutes"] ?> <span style="font-size:12px;color:var(--muted)">min</span></strong></div>
              <div class="kpi"><small>Mitjana bpm</small><strong><?= h((string)$stats["avg_bpm"]) ?></strong></div>
              <div class="kpi"><small>Mitjana km/h</small><strong><?= h((string)$stats["avg_speed"]) ?></strong></div>
            </div>

            <div class="hr"></div>

            <h3 id="new" style="margin:0 0 10px; font-size:14px">Nova activitat</h3>
            <form class="form" method="post" action="">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_activity">

              <div class="two">
                <div class="field">
                  <label for="act_date">Data</label>
                  <input id="act_date" name="act_date" type="date" value="<?= h(date("Y-m-d")) ?>" required>
                </div>
                <div class="field">
                  <label for="bpm">Pulsacions (bpm)</label>
                  <input id="bpm" name="bpm" inputmode="numeric" placeholder="Ex: 145" required>
                </div>
              </div>

              <div class="two">
                <div class="field">
                  <label for="speed">Velocitat (km/h)</label>
                  <input id="speed" name="speed" inputmode="decimal" placeholder="Ex: 10.5" required>
                </div>
                <div class="field">
                  <label for="minutes">Temps (minuts)</label>
                  <input id="minutes" name="minutes" inputmode="numeric" placeholder="Ex: 45" required>
                </div>
              </div>

              <button class="btn btn--brand" type="submit">Guardar activitat</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card__hd">
            <div>
              <h2>Historial</h2>
              <p>Registres de l’usuari actual.</p>
            </div>
            <span class="badge">Privat</span>
          </div>
          <div class="card__bd">
            <?php if (!$user_activities): ?>
              <div class="alert alert--warn" style="margin:0">
                <div class="alert__dot"></div>
                <div>
                  <strong>Encara no tens activitats</strong>
                  <p>Afegeix la primera activitat per veure estadístiques.</p>
                </div>
              </div>
            <?php else: ?>
              <div style="overflow:auto">
                <table>
                  <thead>
                    <tr>
                      <th>Data</th>
                      <th>BPM</th>
                      <th>Velocitat</th>
                      <th>Minuts</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($user_activities as $a): ?>
                      <tr>
                        <td><?= h((string)$a["act_date"]) ?></td>
                        <td><?= (int)$a["bpm"] ?></td>
                        <td><?= h((string)$a["speed"]) ?></td>
                        <td><?= (int)$a["minutes"] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    <?php else: ?>
      <section class="hero">
        <h2>Pàgina no trobada</h2>
        <p>La ruta <span class="mono"><?= h($page) ?></span> no existeix.</p>
        <div class="btnrow">
          <a class="btn btn--brand" href="?page=home">Tornar a inici</a>
        </div>
      </section>
    <?php endif; ?>

    <div class="footer">
      <span class="badge"><?= h($APP_NAME) ?> · Single-file · PHP + SQLite</span>
      <div style="height:10px"></div>
      <div>© <?= date("Y") ?> · Mini web pública + àrea privada (demo).</div>
    </div>
  </div>
</body>
</html>