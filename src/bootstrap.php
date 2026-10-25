<?php
declare(strict_types=1);

const CATEGORIES = [
    'Håndarbeid',
    'Mat og bakst',
    'Antikk og interiør',
    'Kunst',
    'Opplevelser',
    'Barn og leker',
    'Diverse',
];

const MAX_IMAGE_BYTES = 15 * 1024 * 1024;
const MAX_IMAGE_SIDE = 1600;
const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_PER_EMAIL = 5;
const LOGIN_MAX_PER_IP = 20;

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function upload_dir(): string
{
    return env('UPLOAD_DIR', dirname(__DIR__) . '/public/uploads');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', 'db'), env('DB_NAME', 'hostmessa'));
        $pdo = new PDO($dsn, env('DB_USER', 'hostmessa'), env('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        ensure_schema($pdo);
        ensure_first_user($pdo);
    }
    return $pdo;
}

// Tabeller som er lagt til etter første versjon. db/init.sql dekker nye installasjoner,
// dette oppgraderer databaser som allerede finnes.
function ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        name  VARCHAR(50) PRIMARY KEY,
        value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
        slug       VARCHAR(30) PRIMARY KEY,
        body       MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Oppretter første komitémedlem fra .env så lenge brukertabellen er tom.
function ensure_first_user(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        return;
    }
    $email = strtolower(env('ADMIN_EMAIL'));
    $password = env('ADMIN_PASSWORD');
    if ($email === '' || $password === '') {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$email, env('ADMIN_NAME', 'Administrator'), password_hash($password, PASSWORD_DEFAULT)]);
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function kr(?int $n): string
{
    return $n ? number_format($n, 0, ',', ' ') . ' kr' : 'Ingen';
}

function lot_no(int $n): string
{
    return 'Nr. ' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('hostmessa');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Skjemaet var utløpt. Gå tilbake, last siden på nytt og prøv igjen.');
    }
}

function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    start_session();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        start_session();
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, email, name FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function too_many_attempts(string $email): bool
{
    $since = sprintf('NOW() - INTERVAL %d MINUTE', LOGIN_WINDOW_MINUTES);
    $byEmail = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > $since");
    $byEmail->execute([$email]);
    $byIp = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > $since");
    $byIp->execute([client_ip()]);
    return $byEmail->fetchColumn() >= LOGIN_MAX_PER_EMAIL || $byIp->fetchColumn() >= LOGIN_MAX_PER_IP;
}

// Returnerer en feilmelding, eller null når innloggingen lyktes.
function attempt_login(string $email, string $password): ?string
{
    $email = strtolower(trim($email));
    if (too_many_attempts($email)) {
        return sprintf('For mange forsøk. Vent %d minutter og prøv igjen.', LOGIN_WINDOW_MINUTES);
    }
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        db()->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)')->execute([client_ip(), $email]);
        return 'Feil e-post eller passord.';
    }
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return null;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_regenerate_id(true);
}

// Lagrer et opplastet bilde som JPEG, skalert ned og uten metadata. Returnerer filnavnet.
function save_uploaded_image(array $file): string
{
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > MAX_IMAGE_BYTES) {
        throw new RuntimeException('Bildet er for stort. Maks 15 MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Bildet kunne ikke lastes opp. Prøv igjen.');
    }
    $info = @getimagesize($file['tmp_name']);
    $src = match ($info[2] ?? null) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default => false,
    };
    if (!$src) {
        throw new RuntimeException('Filen er ikke et bilde vi kan lese. Bruk JPG, PNG eller WebP.');
    }

    if (($info[2] ?? null) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $orientation = @exif_read_data($file['tmp_name'])['Orientation'] ?? 1;
        $src = match ($orientation) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => $src,
        };
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, MAX_IMAGE_SIDE / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $name = bin2hex(random_bytes(16)) . '.jpg';
    if (!is_dir(upload_dir())) {
        mkdir(upload_dir(), 0775, true);
    }
    if (!imagejpeg($dst, upload_dir() . '/' . $name, 82)) {
        throw new RuntimeException('Bildet kunne ikke lagres på serveren.');
    }
    return $name;
}

function delete_image(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.jpg$/', $name)) {
        @unlink(upload_dir() . '/' . $name);
    }
}

// Messeinfo som komiteen kan endre. Feltene i .env brukes til de er lagret fra komitésiden.
const SETTINGS = [
    'aar' => ['Årstall', 'MESSE_AAR'],
    'dato' => ['Dato', 'MESSE_DATO'],
    'apningstid' => ['Åpningstid', 'MESSE_TID'],
    'sted' => ['Sted', 'MESSE_STED'],
    'auksjon_tid' => ['Auksjonen starter', 'AUKSJON_TID'],
    'varefrist' => ['Frist for å gi varer', 'VAREFRIST'],
    'kontakt' => ['Kontakt', 'KONTAKT'],
];

function messe(): array
{
    static $values = null;
    if ($values === null) {
        $saved = db()->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $values = [];
        foreach (SETTINGS as $key => [, $envKey]) {
            $values[$key] = $saved[$key] ?? env($envKey);
        }
        $values['aar'] = $values['aar'] ?: date('Y');
        $values['kontakt'] = $values['kontakt'] ?: 'messekomiteen';
    }
    return $values;
}

const PAGES = [
    'forside' => [
        'title' => 'Forside',
        'default' => "Velkommen til Høstmessa, Lindesnes KFUK-KFUM sin store dugnad hver høst.\n\nDenne teksten er et utgangspunkt. Komiteen kan skrive om program, kafé, aktiviteter og annet under «For komiteen».",
    ],
    'historie' => [
        'title' => 'Messens historie',
        'default' => "Her kommer historien om Høstmessa. Komiteen kan skrive den under «For komiteen».",
    ],
];

function page_body(string $slug): array
{
    $stmt = db()->prepare('SELECT body, updated_at FROM pages WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: ['body' => PAGES[$slug]['default'], 'updated_at' => null];
}

// Enkel og trygg formatering: tom linje gir nytt avsnitt, «## » gir overskrift,
// «- » gir punktliste, **fet** og [lenketekst](https://...).
function format_text(string $text): string
{
    $inline = function (string $s): string {
        $s = h($s);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        return preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', '<a href="$2">$1</a>', $s);
    };
    $html = '';
    foreach (preg_split('/\R\s*\R/u', trim(str_replace("\r", '', $text))) as $block) {
        $lines = explode("\n", trim($block));
        if (str_starts_with($lines[0], '## ')) {
            $html .= '<h2>' . $inline(substr(array_shift($lines), 3)) . '</h2>';
            if (!$lines) {
                continue;
            }
        }
        if (array_filter($lines, fn ($l) => str_starts_with($l, '- ')) === $lines) {
            $html .= '<ul>' . implode('', array_map(fn ($l) => '<li>' . $inline(substr($l, 2)) . '</li>', $lines)) . '</ul>';
        } else {
            $html .= '<p>' . implode('<br>', array_map($inline, $lines)) . '</p>';
        }
    }
    return $html;
}
