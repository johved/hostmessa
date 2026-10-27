<?php
declare(strict_types=1);
// Felles for alle sidene under /komite/: innlogging, tilgang, menyen og alt skjemaene sender inn.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/mail.php';

// Undersidene: adresse => [navn i menyen, bare for administratorer, kort forklaring].
const KOMITE_PAGES = [
    'varer' => ['Utvalgte varer', false, 'Varer som fremheves i auksjonskatalogen med bilder og beskrivelse.'],
    'galleri' => ['Bildegalleri', false, 'Bilder av de andre auksjonsvarene. Last opp mange på en gang.'],
    'kategorier' => ['Kategorier', true, 'Kategoriene varene sorteres etter i katalogen.'],
    'messeinfo' => ['Messeinfo', true, 'Dato, sted og tider som vises på forsiden.'],
    'tekster' => ['Sidetekster', true, 'Teksten på forsiden, auksjonssiden, basarsiden og historiesiden.'],
    'brukere' => ['Brukere', true, 'Hvem som kan logge inn, og hva de har lov til.'],
    'konto' => ['Mitt passord', false, 'Bytt ditt eget passord.'],
];

function komite_url(string $side = ''): string
{
    return '/komite/' . ($side === '' ? '' : $side . '/');
}

// Krever innlogging (og administrator der siden krever det), og skriver toppen av siden med menyen.
function komite_page(string $side): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Du må logge inn først.');
        redirect(komite_url());
    }
    [$title, $adminOnly] = KOMITE_PAGES[$side];
    if ($adminOnly && !is_admin($user)) {
        flash('error', 'Bare administratorer har tilgang til ' . mb_strtolower($title) . '.');
        redirect(komite_url());
    }
    page_header($title . ' · For komiteen · Høstmessa', 'komite');
    komite_nav($user, $side);
    return $user;
}

function komite_nav(array $user, string $active): void
{
    ?>
<div class="komite-bar">
  <nav class="subnav" aria-label="Komitésiden">
    <a href="<?= komite_url() ?>"<?= $active === '' ? ' aria-current="page"' : '' ?>>Oversikt</a>
    <?php foreach (KOMITE_PAGES as $slug => [$label, $adminOnly]): ?>
      <?php if (!$adminOnly || is_admin($user)): ?>
        <a href="<?= komite_url($slug) ?>"<?= $active === $slug ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endif ?>
    <?php endforeach ?>
  </nav>
  <form method="post" action="<?= komite_url() ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="logout">
    <button class="btn ghost" type="submit">Logg ut <?= h($user['name']) ?></button>
  </form>
</div>
    <?php
}

function require_admin(array $user, string $back): void
{
    if (!is_admin($user)) {
        flash('error', 'Bare administratorer kan gjøre dette.');
        redirect($back);
    }
}

function find_item(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function find_gallery_image(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM gallery WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

start_session();
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // PHP forkaster hele skjemaet når det er større enn post_max_size.
    flash('error', 'Bildene var for store til å sendes på en gang. Prøv med færre bilder, og legg til resten etterpå.');
    redirect($_SERVER['REQUEST_URI'] ?? komite_url());
}

if ($action !== null) {
    check_csrf();
}

if ($action === 'login') {
    $error = attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($error) {
        flash('error', $error);
    }
    redirect(komite_url());
}

if ($action === 'forgot') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (too_many_attempts($email)) {
        flash('error', sprintf('For mange forsøk. Vent %d minutter og prøv igjen.', LOGIN_WINDOW_MINUTES));
        redirect('/komite/?glemt');
    }
    db()->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)')->execute([client_ip(), $email]);
    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($found = $stmt->fetch()) {
        try {
            send_password_link($found, false);
        } catch (RuntimeException $e) {
            error_log('Glemt passord: ' . $e->getMessage());
            flash('error', 'E-posten kunne ikke sendes akkurat nå. Prøv igjen senere, eller kontakt en i komiteen.');
            redirect('/komite/?glemt');
        }
    }
    // Samme svar uansett, så siden ikke avslører hvilke adresser som finnes.
    flash('ok', 'Hvis adressen tilhører en bruker, har vi sendt en e-post med lenke for å lage nytt passord. Lenken virker i 1 time.');
    redirect(komite_url());
}

if ($action === 'set_password') {
    $token = (string) ($_POST['token'] ?? '');
    $owner = find_password_token($token);
    $new = (string) ($_POST['new_password'] ?? '');
    if (!$owner) {
        flash('error', 'Lenken er utløpt eller allerede brukt. Be om en ny under «Glemt passord».');
        redirect('/komite/?glemt');
    }
    if (strlen($new) < 10) {
        flash('error', 'Passordet må ha minst 10 tegn.');
        redirect('/komite/?passord=' . urlencode($token));
    }
    if ($new !== (string) ($_POST['repeat_password'] ?? '')) {
        flash('error', 'Passordene er ikke like.');
        redirect('/komite/?passord=' . urlencode($token));
    }
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $owner['id']]);
    db()->prepare('DELETE FROM password_tokens WHERE user_id = ?')->execute([$owner['id']]);
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$owner['email']]);
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $owner['id'];
    flash('ok', 'Passordet er lagret, og du er logget inn.');
    redirect(komite_url());
}

$user = current_user();

if ($action !== null && !$user) {
    flash('error', 'Du må logge inn først.');
    redirect(komite_url());
}

if ($action === 'logout') {
    logout();
    flash('ok', 'Du er logget ut.');
    redirect(komite_url());
}

if ($action === 'save_item') {
    $id = (int) ($_POST['id'] ?? 0);
    $fromGallery = $id ? null : find_gallery_image((int) ($_POST['from_gallery'] ?? 0));
    if ($fromGallery && !can_edit($user, $fromGallery['created_by'])) {
        $fromGallery = null;
    }
    $back = komite_url('varer') . ($id ? '?edit=' . $id : ($fromGallery ? '?fra_galleri=' . $fromGallery['id'] : ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $category = (string) ($_POST['category'] ?? '');
    $price = trim((string) ($_POST['min_price'] ?? ''));
    $minPrice = $price === '' ? null : max(0, (int) $price);
    $crop = parse_crop((string) ($_POST['crop'] ?? ''));

    if ($title === '' || $description === '' || ($category !== '' && !in_array($category, categories(), true))) {
        flash('error', 'Fyll inn varenavn og beskrivelse.');
        redirect($back);
    }

    $existing = null;
    if ($id) {
        $existing = find_item($id);
        if (!$existing) {
            flash('error', 'Fant ikke varen. Den kan ha blitt slettet av noen andre.');
            redirect(komite_url('varer'));
        }
        if (!can_edit($user, $existing['created_by'])) {
            flash('error', 'Du kan bare endre varer du har lagt ut selv.');
            redirect(komite_url('varer'));
        }
    }

    $image = $existing['image'] ?? null;
    $file = $_FILES['image'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $newImage = save_uploaded_image($file);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect($back);
        }
        delete_image($image);
        $image = $newImage;
    } elseif (!empty($_POST['remove_image'])) {
        delete_image($image);
        $image = null;
    } elseif ($fromGallery) {
        $image = $fromGallery['image'];
    }
    if ($image === null) {
        $crop = null;
    }

    if ($existing) {
        db()->prepare('UPDATE items SET title = ?, description = ?, category = ?, min_price = ?, image = ?, crop = ? WHERE id = ?')
            ->execute([$title, $description, $category, $minPrice, $image, $crop, $id]);
        $message = sprintf('«%s» (%s) er oppdatert.', $title, lot_no((int) $existing['lot']));
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $lot = (int) $pdo->query('SELECT COALESCE(MAX(lot), 0) + 1 FROM items FOR UPDATE')->fetchColumn();
        $pdo->prepare('INSERT INTO items (lot, title, description, category, min_price, giver, image, crop, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$lot, $title, $description, $category, $minPrice, '', $image, $crop, $user['id']]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        $message = sprintf('«%s» er lagt ut som %s og vises nå i katalogen.', $title, lot_no($lot));
    }
    if ($image !== null) {
        make_card_image($image, $crop);
    }

    // Galleribildet flyttes til varen: som hovedbilde, eller som ekstra bilde hvis et annet hovedbilde ble valgt.
    if ($fromGallery) {
        if ($image !== $fromGallery['image']) {
            db()->prepare('INSERT INTO item_images (item_id, image) VALUES (?, ?)')->execute([$id, $fromGallery['image']]);
        }
        db()->prepare('DELETE FROM gallery WHERE id = ?')->execute([$fromGallery['id']]);
        $message .= ' Bildet er flyttet fra bildegalleriet.';
    }

    // Flere bilder: fjern de som er krysset av, og legg til nye.
    $remove = array_map('intval', (array) ($_POST['remove_extra'] ?? []));
    $extras = db()->prepare('SELECT id, image FROM item_images WHERE item_id = ? ORDER BY id');
    $extras->execute([$id]);
    $kept = 0;
    foreach ($extras->fetchAll() as $extra) {
        if (in_array((int) $extra['id'], $remove, true)) {
            db()->prepare('DELETE FROM item_images WHERE id = ?')->execute([$extra['id']]);
            delete_image($extra['image']);
        } else {
            $kept++;
        }
    }
    $insert = db()->prepare('INSERT INTO item_images (item_id, image) VALUES (?, ?)');
    foreach (uploaded_files('extra_images') as $file) {
        if ($kept >= MAX_EXTRA_IMAGES) {
            flash('error', sprintf('En vare kan ha maks %d ekstra bilder. Resten ble ikke lagret.', MAX_EXTRA_IMAGES));
            break;
        }
        try {
            $name = save_uploaded_image($file);
            make_thumbnail($name);
            $insert->execute([$id, $name]);
            $kept++;
        } catch (RuntimeException $e) {
            flash('error', $file['name'] . ': ' . $e->getMessage());
        }
    }

    // Uten hovedbilde blir første ekstra bilde hovedbilde.
    if ($image === null) {
        $first = db()->prepare('SELECT id, image FROM item_images WHERE item_id = ? ORDER BY id LIMIT 1');
        $first->execute([$id]);
        if ($promote = $first->fetch()) {
            db()->prepare('UPDATE items SET image = ?, crop = NULL WHERE id = ?')->execute([$promote['image'], $id]);
            db()->prepare('DELETE FROM item_images WHERE id = ?')->execute([$promote['id']]);
        }
    }

    flash('ok', $message);
    redirect(komite_url('varer'));
}

if ($action === 'delete_item') {
    $item = find_item((int) ($_POST['id'] ?? 0));
    if ($item && !can_edit($user, $item['created_by'])) {
        flash('error', 'Du kan bare fjerne varer du har lagt ut selv.');
    } elseif ($item) {
        $extras = db()->prepare('SELECT image FROM item_images WHERE item_id = ?');
        $extras->execute([$item['id']]);
        $extraImages = $extras->fetchAll(PDO::FETCH_COLUMN);
        db()->prepare('DELETE FROM items WHERE id = ?')->execute([$item['id']]);
        delete_image($item['image']);
        array_map('delete_image', $extraImages);
        flash('ok', sprintf('«%s» er fjernet fra katalogen.', $item['title']));
    }
    redirect(komite_url('varer'));
}

if ($action === 'upload_gallery') {
    $saved = 0;
    $errors = [];
    $stmt = db()->prepare('INSERT INTO gallery (image, created_by) VALUES (?, ?)');
    foreach (uploaded_files('images') as $file) {
        try {
            $name = save_uploaded_image($file);
            make_thumbnail($name);
            $stmt->execute([$name, $user['id']]);
            $saved++;
        } catch (RuntimeException $e) {
            $errors[] = $file['name'] . ': ' . $e->getMessage();
        }
    }
    // Nettleseren laster opp i små porsjoner og vil ha svaret som JSON.
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
        header('Content-Type: application/json');
        echo json_encode(['saved' => $saved, 'errors' => $errors]);
        exit;
    }
    if ($saved) {
        flash('ok', sprintf('%d %s lagt til i galleriet.', $saved, $saved === 1 ? 'bilde er' : 'bilder er'));
    }
    foreach ($errors as $error) {
        flash('error', $error);
    }
    if (!$saved && !$errors) {
        flash('error', 'Velg ett eller flere bilder først.');
    }
    redirect(komite_url('galleri'));
}

if ($action === 'gallery_done') {
    $saved = (int) ($_POST['saved'] ?? 0);
    flash('ok', sprintf('%d %s lagt til i galleriet.', $saved, $saved === 1 ? 'bilde er' : 'bilder er'));
    foreach (array_slice((array) ($_POST['errors'] ?? []), 0, 20) as $error) {
        flash('error', (string) $error);
    }
    redirect(komite_url('galleri'));
}

if ($action === 'caption_gallery' || $action === 'delete_gallery') {
    $g = find_gallery_image((int) ($_POST['id'] ?? 0));
    if ($g && !can_edit($user, $g['created_by'])) {
        flash('error', 'Du kan bare endre bilder du har lastet opp selv.');
    } elseif ($g && $action === 'caption_gallery') {
        db()->prepare('UPDATE gallery SET caption = ? WHERE id = ?')
            ->execute([mb_substr(trim((string) ($_POST['caption'] ?? '')), 0, 150), $g['id']]);
        flash('ok', 'Bildeteksten er lagret.');
    } elseif ($g) {
        db()->prepare('DELETE FROM gallery WHERE id = ?')->execute([$g['id']]);
        delete_image($g['image']);
        flash('ok', 'Bildet er fjernet fra galleriet.');
    }
    redirect(komite_url('galleri') . ($g ? '#g' . $g['id'] : ''));
}

if ($action === 'clear_gallery') {
    require_admin($user, komite_url('galleri'));
    $images = db()->query('SELECT image FROM gallery')->fetchAll(PDO::FETCH_COLUMN);
    db()->exec('DELETE FROM gallery');
    array_map('delete_image', $images);
    flash('ok', sprintf('Galleriet er tømt (%d bilder fjernet).', count($images)));
    redirect(komite_url('galleri'));
}

if ($action === 'add_category') {
    require_admin($user, komite_url());
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 50);
    if ($name === '') {
        flash('error', 'Skriv inn et navn på kategorien.');
    } elseif (in_array(mb_strtolower($name), array_map('mb_strtolower', categories()), true)) {
        flash('error', sprintf('Kategorien «%s» finnes allerede.', $name));
    } else {
        db()->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
        flash('ok', sprintf('Kategorien «%s» er lagt til.', $name));
    }
    redirect(komite_url('kategorier'));
}

if ($action === 'rename_category') {
    require_admin($user, komite_url());
    $old = (string) ($_POST['old'] ?? '');
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 50);
    $others = array_filter(categories(), fn ($c) => $c !== $old);
    if ($name === '') {
        flash('error', 'Kategorien må ha et navn.');
    } elseif (in_array(mb_strtolower($name), array_map('mb_strtolower', $others), true)) {
        flash('error', sprintf('Kategorien «%s» finnes allerede.', $name));
    } elseif ($name !== $old) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE categories SET name = ? WHERE name = ?')->execute([$name, $old]);
        $pdo->prepare('UPDATE items SET category = ? WHERE category = ?')->execute([$name, $old]);
        $pdo->commit();
        flash('ok', sprintf('«%s» heter nå «%s».', $old, $name));
    }
    redirect(komite_url('kategorier'));
}

if ($action === 'delete_category') {
    require_admin($user, komite_url());
    $name = (string) ($_POST['name'] ?? '');
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE items SET category = \'\' WHERE category = ?')->execute([$name]);
    $pdo->prepare('DELETE FROM categories WHERE name = ?')->execute([$name]);
    $pdo->commit();
    flash('ok', sprintf('Kategorien «%s» er slettet. Varer i den er nå uten kategori.', $name));
    redirect(komite_url('kategorier'));
}

if ($action === 'save_settings') {
    require_admin($user, komite_url());
    $stmt = db()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach (array_keys(SETTINGS) as $key) {
        $stmt->execute([$key, mb_substr(trim((string) ($_POST[$key] ?? '')), 0, 200)]);
    }
    flash('ok', 'Messeinfoen er oppdatert.');
    redirect(komite_url('messeinfo'));
}

if ($action === 'save_page') {
    require_admin($user, komite_url());
    $slug = (string) ($_POST['slug'] ?? '');
    if (isset(PAGES[$slug])) {
        db()->prepare('INSERT INTO pages (slug, body) VALUES (?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body)')
            ->execute([$slug, trim((string) ($_POST['body'] ?? ''))]);
        flash('ok', sprintf('%s er oppdatert.', PAGES[$slug]['title']));
    }
    redirect(komite_url('tekster') . '#side-' . $slug);
}

if ($action === 'upload_page_image') {
    require_admin($user, komite_url());
    $slug = (string) ($_POST['slug'] ?? '');
    $caption = trim(str_replace([']', '[', "\n", "\r"], ' ', mb_substr((string) ($_POST['caption'] ?? ''), 0, 150)));
    $file = $_FILES['image'] ?? null;
    try {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Velg et bilde først.');
        }
        $markdown = '![' . $caption . '](/uploads/' . save_uploaded_image($file) . ')';
        $error = null;
    } catch (RuntimeException $e) {
        $markdown = null;
        $error = $e->getMessage();
    }
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
        header('Content-Type: application/json');
        echo json_encode(['markdown' => $markdown, 'error' => $error]);
        exit;
    }
    // Uten JavaScript: vis linjen som skal limes inn i teksten.
    flash($error ? 'error' : 'ok', $error ?? 'Bildet er lastet opp. Lim inn denne linjen i teksten der bildet skal stå: ' . $markdown);
    redirect(komite_url('tekster') . '#side-' . rawurlencode($slug));
}

if ($action === 'add_user') {
    require_admin($user, komite_url());
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = isset(ROLES[$_POST['role'] ?? '']) ? $_POST['role'] : 'bidragsyter';
    $invite = $password === '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Skriv inn navn og en gyldig e-postadresse.');
    } elseif ($invite && !mail_configured()) {
        flash('error', 'E-post er ikke satt opp ennå, så du må skrive inn et midlertidig passord.');
    } elseif (!$invite && strlen($password) < 10) {
        flash('error', 'Passordet må ha minst 10 tegn.');
    } else {
        try {
            // Ved invitasjon får brukeren et tilfeldig passord ingen kjenner, og lager sitt eget via lenken.
            db()->prepare('INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, ?)')
                ->execute([$email, $name, password_hash($invite ? bin2hex(random_bytes(32)) : $password, PASSWORD_DEFAULT), $role]);
            if ($invite) {
                try {
                    send_password_link(['id' => (int) db()->lastInsertId(), 'name' => $name, 'email' => $email], true);
                    flash('ok', sprintf('%s er lagt til og har fått en e-post med lenke for å lage passord.', $name));
                } catch (RuntimeException $e) {
                    flash('error', sprintf('%s er lagt til, men e-posten kunne ikke sendes: %s', $name, $e->getMessage()));
                }
            } else {
                flash('ok', sprintf('%s er lagt til. Gi beskjed om passordet, og be dem bytte det etter første innlogging.', $name));
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] !== 1062) {
                throw $e;
            }
            flash('error', 'Det finnes allerede en bruker med den e-postadressen.');
        }
    }
    redirect(komite_url('brukere'));
}

if ($action === 'set_role') {
    require_admin($user, komite_url());
    $id = (int) ($_POST['id'] ?? 0);
    $role = (string) ($_POST['role'] ?? '');
    if ($id === (int) $user['id']) {
        flash('error', 'Du kan ikke endre din egen brukergruppe. Be en annen administrator om det.');
    } elseif (isset(ROLES[$role])) {
        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
        flash('ok', 'Brukergruppen er endret.');
    }
    redirect(komite_url('brukere'));
}

if ($action === 'invite_user') {
    require_admin($user, komite_url());
    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $stmt->execute([(int) ($_POST['id'] ?? 0)]);
    if ($member = $stmt->fetch()) {
        try {
            send_password_link($member, true);
            flash('ok', sprintf('E-post med lenke for å lage passord er sendt til %s.', $member['email']));
        } catch (RuntimeException $e) {
            flash('error', 'E-posten kunne ikke sendes: ' . $e->getMessage());
        }
    }
    redirect(komite_url('brukere'));
}

if ($action === 'test_mail') {
    require_admin($user, komite_url());
    try {
        send_mail($user['email'], 'Test fra Høstmessa', "Hei {$user['name']}!\n\nDette er en test. E-post fra nettsiden virker.\n\nHilsen nettsiden til Høstmessa");
        flash('ok', sprintf('Test-e-post er sendt til %s. Sjekk innboksen (og søppelposten).', $user['email']));
    } catch (RuntimeException $e) {
        flash('error', 'E-posten kunne ikke sendes: ' . $e->getMessage());
    }
    redirect(komite_url('brukere'));
}

if ($action === 'delete_user') {
    require_admin($user, komite_url());
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $user['id']) {
        flash('error', 'Du kan ikke fjerne deg selv.');
    } else {
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        flash('ok', 'Brukeren er fjernet.');
    }
    redirect(komite_url('brukere'));
}

if ($action === 'change_password') {
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $stmt->fetchColumn())) {
        flash('error', 'Nåværende passord er feil.');
    } elseif (strlen($new) < 10) {
        flash('error', 'Det nye passordet må ha minst 10 tegn.');
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        flash('ok', 'Passordet er byttet.');
    }
    redirect(komite_url('konto'));
}

if ($action !== null) {
    redirect(komite_url());
}
