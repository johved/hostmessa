<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/mail.php';

start_session();
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // PHP forkaster hele skjemaet når det er større enn post_max_size.
    flash('error', 'Bildene var for store til å sendes på en gang. Prøv med færre bilder, og legg til resten etterpå.');
    redirect('/komite/');
}

if ($action !== null) {
    check_csrf();
}

if ($action === 'login') {
    $error = attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($error) {
        flash('error', $error);
    }
    redirect('/komite/');
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
    flash('ok', 'Hvis adressen tilhører et komitémedlem, har vi sendt en e-post med lenke for å lage nytt passord. Lenken virker i 1 time.');
    redirect('/komite/');
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
    redirect('/komite/');
}

if (isset($_GET['passord'])) {
    $token = (string) $_GET['passord'];
    $owner = find_password_token($token);
    page_header('Lag passord · Høstmessa', 'komite');
    if (!$owner) {
        ?>
    <section class="login">
      <h2>Lenken virker ikke</h2>
      <p class="sub">Lenken er utløpt eller allerede brukt. <a href="/komite/?glemt">Be om en ny lenke</a>.</p>
    </section>
        <?php
    } else {
        ?>
    <section class="login">
      <h2>Lag passord</h2>
      <p class="sub">For <?= h($owner['name']) ?> (<?= h($owner['email']) ?>). Passordet må ha minst 10 tegn.</p>
      <form method="post" action="/komite/">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_password">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <input type="text" name="username" value="<?= h($owner['email']) ?>" autocomplete="username" hidden>
        <label for="n-password">Nytt passord
          <input type="password" id="n-password" name="new_password" minlength="10" autocomplete="new-password" required>
        </label>
        <label for="n-repeat">Gjenta passordet
          <input type="password" id="n-repeat" name="repeat_password" minlength="10" autocomplete="new-password" required>
        </label>
        <button class="btn green" type="submit">Lagre passord</button>
      </form>
    </section>
        <?php
    }
    page_footer();
    exit;
}

$user = current_user();

if ($action !== null && !$user) {
    flash('error', 'Du må logge inn først.');
    redirect('/komite/');
}

if ($action === 'logout') {
    logout();
    flash('ok', 'Du er logget ut.');
    redirect('/komite/');
}

if ($action === 'save_item') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $category = (string) ($_POST['category'] ?? '');
    $giver = trim((string) ($_POST['giver'] ?? ''));
    $price = trim((string) ($_POST['min_price'] ?? ''));
    $minPrice = $price === '' ? null : max(0, (int) $price);

    if ($title === '' || $description === '' || $giver === '' || ($category !== '' && !in_array($category, categories(), true))) {
        flash('error', 'Fyll inn varenavn, beskrivelse og hvem som har gitt varen.');
        redirect('/komite/' . ($id ? '?edit=' . $id : ''));
    }

    $existing = null;
    if ($id) {
        $stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch() ?: null;
        if (!$existing) {
            flash('error', 'Fant ikke varen. Den kan ha blitt slettet av noen andre.');
            redirect('/komite/');
        }
    }

    $image = $existing['image'] ?? null;
    $file = $_FILES['image'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $newImage = save_uploaded_image($file);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/komite/' . ($id ? '?edit=' . $id : ''));
        }
        delete_image($image);
        $image = $newImage;
    } elseif (!empty($_POST['remove_image'])) {
        delete_image($image);
        $image = null;
    }

    if ($existing) {
        db()->prepare('UPDATE items SET title = ?, description = ?, category = ?, min_price = ?, giver = ?, image = ? WHERE id = ?')
            ->execute([$title, $description, $category, $minPrice, $giver, $image, $id]);
        $message = sprintf('«%s» (%s) er oppdatert.', $title, lot_no((int) $existing['lot']));
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $lot = (int) $pdo->query('SELECT COALESCE(MAX(lot), 0) + 1 FROM items FOR UPDATE')->fetchColumn();
        $pdo->prepare('INSERT INTO items (lot, title, description, category, min_price, giver, image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$lot, $title, $description, $category, $minPrice, $giver, $image, $user['id']]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        $message = sprintf('«%s» er lagt ut som %s og vises nå i katalogen.', $title, lot_no($lot));
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
            db()->prepare('UPDATE items SET image = ? WHERE id = ?')->execute([$promote['image'], $id]);
            db()->prepare('DELETE FROM item_images WHERE id = ?')->execute([$promote['id']]);
        }
    }

    flash('ok', $message);
    redirect('/komite/');
}

if ($action === 'delete_item') {
    $stmt = db()->prepare('SELECT title, image FROM items WHERE id = ?');
    $stmt->execute([(int) ($_POST['id'] ?? 0)]);
    if ($item = $stmt->fetch()) {
        $extras = db()->prepare('SELECT image FROM item_images WHERE item_id = ?');
        $extras->execute([(int) $_POST['id']]);
        $extraImages = $extras->fetchAll(PDO::FETCH_COLUMN);
        db()->prepare('DELETE FROM items WHERE id = ?')->execute([(int) $_POST['id']]);
        delete_image($item['image']);
        array_map('delete_image', $extraImages);
        flash('ok', sprintf('«%s» er fjernet fra katalogen.', $item['title']));
    }
    redirect('/komite/');
}

if ($action === 'upload_gallery') {
    $saved = 0;
    $errors = [];
    $stmt = db()->prepare('INSERT INTO gallery (image) VALUES (?)');
    foreach (uploaded_files('images') as $file) {
        try {
            $name = save_uploaded_image($file);
            make_thumbnail($name);
            $stmt->execute([$name]);
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
    redirect('/komite/#galleri');
}

if ($action === 'gallery_done') {
    $saved = (int) ($_POST['saved'] ?? 0);
    flash('ok', sprintf('%d %s lagt til i galleriet.', $saved, $saved === 1 ? 'bilde er' : 'bilder er'));
    foreach (array_slice((array) ($_POST['errors'] ?? []), 0, 20) as $error) {
        flash('error', (string) $error);
    }
    redirect('/komite/#galleri');
}

if ($action === 'caption_gallery') {
    db()->prepare('UPDATE gallery SET caption = ? WHERE id = ?')
        ->execute([mb_substr(trim((string) ($_POST['caption'] ?? '')), 0, 150), (int) ($_POST['id'] ?? 0)]);
    flash('ok', 'Bildeteksten er lagret.');
    redirect('/komite/#galleri');
}

if ($action === 'delete_gallery') {
    $stmt = db()->prepare('SELECT image FROM gallery WHERE id = ?');
    $stmt->execute([(int) ($_POST['id'] ?? 0)]);
    if ($image = $stmt->fetchColumn()) {
        db()->prepare('DELETE FROM gallery WHERE id = ?')->execute([(int) $_POST['id']]);
        delete_image($image);
        flash('ok', 'Bildet er fjernet fra galleriet.');
    }
    redirect('/komite/#galleri');
}

if ($action === 'clear_gallery') {
    $images = db()->query('SELECT image FROM gallery')->fetchAll(PDO::FETCH_COLUMN);
    db()->exec('DELETE FROM gallery');
    array_map('delete_image', $images);
    flash('ok', sprintf('Galleriet er tømt (%d bilder fjernet).', count($images)));
    redirect('/komite/#galleri');
}

if ($action === 'add_category') {
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 50);
    if ($name === '') {
        flash('error', 'Skriv inn et navn på kategorien.');
    } elseif (in_array(mb_strtolower($name), array_map('mb_strtolower', categories()), true)) {
        flash('error', sprintf('Kategorien «%s» finnes allerede.', $name));
    } else {
        db()->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
        flash('ok', sprintf('Kategorien «%s» er lagt til.', $name));
    }
    redirect('/komite/#kategorier');
}

if ($action === 'rename_category') {
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
    redirect('/komite/#kategorier');
}

if ($action === 'delete_category') {
    $name = (string) ($_POST['name'] ?? '');
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE items SET category = \'\' WHERE category = ?')->execute([$name]);
    $pdo->prepare('DELETE FROM categories WHERE name = ?')->execute([$name]);
    $pdo->commit();
    flash('ok', sprintf('Kategorien «%s» er slettet. Varer i den er nå uten kategori.', $name));
    redirect('/komite/#kategorier');
}

if ($action === 'save_settings') {
    $stmt = db()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach (array_keys(SETTINGS) as $key) {
        $stmt->execute([$key, mb_substr(trim((string) ($_POST[$key] ?? '')), 0, 200)]);
    }
    flash('ok', 'Messeinfoen er oppdatert.');
    redirect('/komite/#innhold');
}

if ($action === 'save_page') {
    $slug = (string) ($_POST['slug'] ?? '');
    if (isset(PAGES[$slug])) {
        db()->prepare('INSERT INTO pages (slug, body) VALUES (?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body)')
            ->execute([$slug, trim((string) ($_POST['body'] ?? ''))]);
        flash('ok', sprintf('%s er oppdatert.', PAGES[$slug]['title']));
    }
    redirect('/komite/#side-' . $slug);
}

if ($action === 'add_user') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
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
            db()->prepare('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)')
                ->execute([$email, $name, password_hash($invite ? bin2hex(random_bytes(32)) : $password, PASSWORD_DEFAULT)]);
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
            flash('error', 'Det finnes allerede et komitémedlem med den e-postadressen.');
        }
    }
    redirect('/komite/#medlemmer');
}

if ($action === 'invite_user') {
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
    redirect('/komite/#medlemmer');
}

if ($action === 'test_mail') {
    try {
        send_mail($user['email'], 'Test fra Høstmessa', "Hei {$user['name']}!\n\nDette er en test. E-post fra nettsiden virker.\n\nHilsen nettsiden til Høstmessa");
        flash('ok', sprintf('Test-e-post er sendt til %s. Sjekk innboksen (og søppelposten).', $user['email']));
    } catch (RuntimeException $e) {
        flash('error', 'E-posten kunne ikke sendes: ' . $e->getMessage());
    }
    redirect('/komite/#medlemmer');
}

if ($action === 'delete_user') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $user['id']) {
        flash('error', 'Du kan ikke fjerne deg selv.');
    } else {
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        flash('ok', 'Komitémedlemmet er fjernet.');
    }
    redirect('/komite/#medlemmer');
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
    redirect('/komite/#passord');
}

if ($action !== null) {
    redirect('/komite/');
}

if (!$user && isset($_GET['glemt'])) {
    page_header('Glemt passord · Høstmessa', 'komite');
    ?>
    <section class="login">
      <h2>Glemt passord</h2>
      <p class="sub">Skriv inn e-postadressen du logger inn med, så sender vi deg en lenke for å lage nytt passord.</p>
      <form method="post" action="/komite/">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="forgot">
        <label for="f-email">E-post
          <input type="email" id="f-email" name="email" autocomplete="username" required>
        </label>
        <button class="btn green" type="submit">Send lenke</button>
      </form>
      <p class="small-note"><a href="/komite/">Tilbake til innlogging</a></p>
    </section>
    <?php
    page_footer();
    exit;
}

if (!$user) {
    page_header('Logg inn · Høstmessa', 'komite');
    ?>
    <section class="login">
      <h2>Logg inn for messekomiteen</h2>
      <p class="sub">Bare komitémedlemmer kan legge ut og endre varer i katalogen.</p>
      <form method="post" action="/komite/">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label for="l-email">E-post
          <input type="email" id="l-email" name="email" autocomplete="username" required>
        </label>
        <label for="l-password">Passord
          <input type="password" id="l-password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn green" type="submit">Logg inn</button>
      </form>
      <?php if (mail_configured()): ?>
        <p class="small-note"><a href="/komite/?glemt">Glemt passord?</a></p>
      <?php endif ?>
    </section>
    <?php
    page_footer();
    exit;
}

$items = db()->query('SELECT * FROM items ORDER BY lot DESC')->fetchAll();
$users = db()->query('SELECT id, name, email FROM users ORDER BY name')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}
$extraImages = extra_images();
$f = $edit ?? ['id' => 0, 'title' => '', 'description' => '', 'category' => '', 'min_price' => null, 'giver' => '', 'image' => null];

page_header('For komiteen · Høstmessa', 'komite');
?>
<nav class="subnav" aria-label="Komitésiden">
  <a href="#varer">Varer</a>
  <a href="#galleri">Bildegalleri</a>
  <a href="#kategorier">Kategorier</a>
  <a href="#innhold">Messeinfo</a>
  <?php foreach (PAGES as $slug => $pageInfo): ?>
    <a href="#side-<?= $slug ?>"><?= h($pageInfo['title']) ?></a>
  <?php endforeach ?>
  <a href="#medlemmer">Medlemmer</a>
  <a href="#passord">Bytt passord</a>
</nav>
<section id="varer">
  <div class="admin-head">
    <div>
      <?php if ($edit): ?>
        <h2>Endre <?= h(lot_no((int) $edit['lot'])) ?></h2>
        <p class="sub">Endringene vises i katalogen med en gang du lagrer. <a href="/komite/">Avbryt</a></p>
      <?php else: ?>
        <h2>Legg ut en vare</h2>
        <p class="sub">Varen vises i katalogen med en gang og får neste ledige varenummer.</p>
      <?php endif ?>
    </div>
    <form method="post" action="/komite/">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="logout">
      <button class="btn ghost" type="submit">Logg ut <?= h($user['name']) ?></button>
    </form>
  </div>

  <div class="form-layout">
    <form method="post" action="/komite/" enctype="multipart/form-data" data-shrink>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_BYTES ?>">
      <label class="drop" for="f-image">
        <?php if ($f['image']): ?><img class="current" src="/uploads/<?= h($f['image']) ?>" alt="Nåværende bilde"><?php endif ?>
        <span><strong><?= $f['image'] ? 'Bytt hovedbilde' : 'Velg hovedbilde' ?></strong><br><span class="hint">Vises i katalogen. JPG, PNG eller WebP.</span></span>
        <input type="file" id="f-image" name="image" accept="image/jpeg,image/png,image/webp">
      </label>
      <?php if ($f['image']): ?>
        <label class="check" for="f-remove"><input type="checkbox" id="f-remove" name="remove_image" value="1"> Fjern hovedbildet</label>
      <?php endif ?>
      <?php $currentExtras = $extraImages[(int) $f['id']] ?? []; ?>
      <fieldset class="extras">
        <legend>Flere bilder <span class="hint">Valgfritt, maks <?= MAX_EXTRA_IMAGES ?></span></legend>
        <?php if ($currentExtras): ?>
          <div class="extras-list">
            <?php foreach ($currentExtras as $extra): ?>
              <label class="extra-thumb" for="x-<?= (int) $extra['id'] ?>">
                <img src="/uploads/<?= h(thumb_name($extra['image'])) ?>" alt="">
                <span class="check"><input type="checkbox" id="x-<?= (int) $extra['id'] ?>" name="remove_extra[]" value="<?= (int) $extra['id'] ?>"> Fjern</span>
              </label>
            <?php endforeach ?>
          </div>
        <?php endif ?>
        <label for="f-extra" class="file-line">Legg til bilder
          <input type="file" id="f-extra" name="extra_images[]" accept="image/jpeg,image/png,image/webp" multiple>
        </label>
      </fieldset>
      <label for="f-title">Hva er varen?
        <input type="text" id="f-title" name="title" maxlength="150" value="<?= h($f['title']) ?>" placeholder="F.eks. Strikket barnelue" required>
      </label>
      <label for="f-description">Beskrivelse <span class="hint">Størrelse, stand, hvem som har laget den</span>
        <textarea id="f-description" name="description" required><?= h($f['description']) ?></textarea>
      </label>
      <div class="row2">
        <label for="f-category">Kategori
          <select id="f-category" name="category">
            <option value="">Uten kategori</option>
            <?php foreach (categories() as $cat): ?>
              <option<?= $cat === $f['category'] ? ' selected' : '' ?>><?= h($cat) ?></option>
            <?php endforeach ?>
            <?php if ($f['category'] !== '' && !in_array($f['category'], categories(), true)): ?>
              <option selected><?= h($f['category']) ?></option>
            <?php endif ?>
          </select>
          <span class="hint"><a href="#kategorier">Lag eller endre kategorier</a></span>
        </label>
        <label for="f-price">Minstepris (kr) <span class="hint">Valgfritt</span>
          <input type="number" id="f-price" name="min_price" min="0" step="50" inputmode="numeric" value="<?= h($f['min_price'] === null ? '' : (string) $f['min_price']) ?>">
        </label>
      </div>
      <label for="f-giver">Gitt av <span class="hint">Vises i katalogen. Skriv «Anonym giver» om giveren ønsker det.</span>
        <input type="text" id="f-giver" name="giver" maxlength="100" value="<?= h($f['giver']) ?>" required>
      </label>
      <button class="btn" type="submit"><?= $edit ? 'Lagre endringer' : 'Legg ut i katalogen' ?></button>
    </form>

    <div>
      <h3 class="list-h">I katalogen nå <span class="count"><?= count($items) ?></span></h3>
      <div class="queue">
        <?php if (!$items): ?><p class="small-note">Ingen varer ennå.</p><?php endif ?>
        <?php foreach ($items as $item): ?>
          <div class="qitem small">
            <div class="photo"><?= photo_html($item) ?><?php if ($n = count($extraImages[(int) $item['id']] ?? [])): ?><span class="lot">+<?= $n ?></span><?php endif ?></div>
            <div>
              <h3><?= h(lot_no((int) $item['lot'])) ?> · <?= h($item['title']) ?></h3>
              <p><?= h($item['category']) ?> · minstepris <?= h(kr($item['min_price'] === null ? null : (int) $item['min_price'])) ?></p>
            </div>
            <div class="qact">
              <a class="btn ghost" href="/komite/?edit=<?= (int) $item['id'] ?>">Endre</a>
              <form method="post" action="/komite/" class="inline" onsubmit="return confirm('Fjerne «<?= h(addslashes($item['title'])) ?>» fra katalogen?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <button class="btn ghost" type="submit">Fjern</button>
              </form>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>
</section>

<?php $categoryCounts = db()->query("SELECT category, COUNT(*) FROM items WHERE category <> '' GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR); ?>
<?php $gallery = db()->query('SELECT id, image, caption FROM gallery ORDER BY id')->fetchAll(); ?>
<section class="section" id="galleri">
  <div class="admin-head">
    <div>
      <h2>Bildegalleri <span class="count"><?= count($gallery) ?></span></h2>
      <p class="sub">For varer som kommer på auksjonen, men som ikke er beskrevet i katalogen. Velg mange bilder på en gang. De vises under katalogen på auksjonssiden. Bildetekst er valgfritt.</p>
    </div>
    <?php if ($gallery): ?>
      <form method="post" action="/komite/" onsubmit="return confirm('Fjerne alle <?= count($gallery) ?> bildene fra galleriet? Dette kan ikke angres.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clear_gallery">
        <button class="btn ghost" type="submit">Tøm galleriet</button>
      </form>
    <?php endif ?>
  </div>
  <form method="post" action="/komite/" enctype="multipart/form-data" id="gallery-form" class="gallery-upload">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload_gallery">
    <label class="drop" for="g-images">
      <span><strong>Velg bilder</strong><br><span class="hint" id="g-status">Du kan velge mange på en gang. JPG, PNG eller WebP.</span></span>
      <input type="file" id="g-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    </label>
    <button class="btn" type="submit" id="g-submit">Last opp til galleriet</button>
  </form>
  <?php if ($gallery): ?>
    <div class="gallery-admin">
      <?php foreach ($gallery as $g): ?>
        <div class="gallery-admin-item">
          <img src="/uploads/<?= h(thumb_name($g['image'])) ?>" alt="" loading="lazy">
          <form method="post" action="/komite/" class="cat-rename">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="caption_gallery">
            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
            <input type="text" name="caption" maxlength="150" value="<?= h($g['caption']) ?>" placeholder="Bildetekst" aria-label="Bildetekst">
            <button class="btn ghost" type="submit">Lagre</button>
          </form>
          <form method="post" action="/komite/" onsubmit="return confirm('Fjerne bildet fra galleriet?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_gallery">
            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
            <button class="btn ghost" type="submit">Fjern</button>
          </form>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>
<script src="<?= asset('galleri-opplasting.js') ?>" defer></script>
<script src="<?= asset('skaler-bilder.js') ?>" defer></script>

<section class="section" id="kategorier">
  <div class="form-layout">
    <div>
      <h2>Kategorier</h2>
      <p class="sub">Kategoriene varene sorteres etter i auksjonskatalogen. Bare kategorier som har varer, vises i katalogen.</p>
      <div class="queue">
        <?php if (!categories()): ?><p class="small-note">Ingen kategorier ennå.</p><?php endif ?>
        <?php foreach (categories() as $cat): ?>
          <div class="qitem small cat-row">
            <form method="post" action="/komite/" class="cat-rename">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename_category">
              <input type="hidden" name="old" value="<?= h($cat) ?>">
              <input type="text" name="name" maxlength="50" value="<?= h($cat) ?>" aria-label="Navn på kategorien <?= h($cat) ?>" required>
              <button class="btn ghost" type="submit">Lagre</button>
            </form>
            <?php $n = (int) ($categoryCounts[$cat] ?? 0); ?>
            <span class="small-note"><?= $n ?> <?= $n === 1 ? 'vare' : 'varer' ?></span>
            <form method="post" action="/komite/" class="inline" onsubmit="return confirm('Slette kategorien «<?= h(addslashes($cat)) ?>»?<?= !empty($categoryCounts[$cat]) ? ' Varene i den blir uten kategori.' : '' ?>')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_category">
              <input type="hidden" name="name" value="<?= h($cat) ?>">
              <button class="btn ghost" type="submit">Slett</button>
            </form>
          </div>
        <?php endforeach ?>
      </div>
    </div>
    <div>
      <h3 class="list-h">Ny kategori</h3>
      <form method="post" action="/komite/">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <label for="c-name">Navn
          <input type="text" id="c-name" name="name" maxlength="50" placeholder="F.eks. Håndarbeid" required>
        </label>
        <button class="btn green" type="submit">Legg til kategori</button>
      </form>
    </div>
  </div>
</section>

<section class="section" id="innhold">
  <h2>Messeinfo</h2>
  <p class="sub">Vises på forsiden og øverst på alle sider. Tomme felt skjules.</p>
  <form method="post" action="/komite/" class="login">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">
    <?php foreach (SETTINGS as $key => [$label]): ?>
      <label for="s-<?= $key ?>"><?= h($label) ?>
        <input type="text" id="s-<?= $key ?>" name="<?= $key ?>" maxlength="200" value="<?= h(messe()[$key]) ?>">
      </label>
    <?php endforeach ?>
    <button class="btn green" type="submit">Lagre messeinfo</button>
  </form>
</section>

<?php foreach (PAGES as $slug => $pageInfo): ?>
<section class="section" id="side-<?= $slug ?>">
  <div class="form-layout">
    <form method="post" action="/komite/">
      <h2><?= h($pageInfo['title']) ?></h2>
      <p class="sub"><a href="<?= h($pageInfo['url'] ?? ($slug === 'forside' ? '/' : '/' . $slug . '/')) ?>">Se siden</a></p>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_page">
      <input type="hidden" name="slug" value="<?= $slug ?>">
      <label for="page-<?= $slug ?>">Tekst
        <textarea class="tall" id="page-<?= $slug ?>" name="body"><?= h(page_body($slug)['body']) ?></textarea>
      </label>
      <button class="btn green" type="submit">Lagre <?= h(mb_strtolower($pageInfo['title'])) ?></button>
    </form>
    <div class="help">
      <strong>Formatering</strong>
      <p>Tom linje mellom avsnitt.<br>
      <code>## Overskrift</code> på egen linje.<br>
      <code>- punkt</code> for punktliste.<br>
      <code>**fet tekst**</code><br>
      <code>[lenketekst](https://adresse.no)</code></p>
    </div>
  </div>
</section>
<?php endforeach ?>

<section class="section" id="medlemmer">
  <div class="form-layout">
    <div>
      <h2>Komitémedlemmer</h2>
      <p class="sub">Alle her kan logge inn og legge ut varer.</p>
      <div class="queue">
        <?php foreach ($users as $u): ?>
          <div class="qitem small" style="grid-template-columns: minmax(0, 1fr) auto">
            <div>
              <h3><?= h($u['name']) ?></h3>
              <p><?= h($u['email']) ?></p>
            </div>
            <div class="qact">
              <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                <?php if (mail_configured()): ?>
                  <form method="post" action="/komite/" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="invite_user">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn ghost" type="submit" title="Send e-post med lenke for å lage passord">Send passordlenke</button>
                  </form>
                <?php endif ?>
                <form method="post" action="/komite/" class="inline" onsubmit="return confirm('Fjerne <?= h(addslashes($u['name'])) ?> fra komiteen?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn ghost" type="submit">Fjern</button>
                </form>
              <?php else: ?>
                <span class="small-note">Deg</span>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
    <div>
      <h3 class="list-h">Legg til et medlem</h3>
      <form method="post" action="/komite/">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_user">
        <label for="u-name">Navn
          <input type="text" id="u-name" name="name" maxlength="100" required>
        </label>
        <label for="u-email">E-post
          <input type="email" id="u-email" name="email" maxlength="190" required>
        </label>
        <?php if (mail_configured()): ?>
          <label for="u-password">Midlertidig passord <span class="hint">Valgfritt. La stå tomt, så får de en e-post og lager passord selv.</span>
            <input type="text" id="u-password" name="password" minlength="10" autocomplete="off">
          </label>
        <?php else: ?>
          <label for="u-password">Midlertidig passord <span class="hint">Minst 10 tegn</span>
            <input type="text" id="u-password" name="password" minlength="10" autocomplete="off" required>
          </label>
        <?php endif ?>
        <button class="btn green" type="submit">Legg til</button>
      </form>
      <?php if (mail_configured()): ?>
        <form method="post" action="/komite/" class="section">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="test_mail">
          <p class="small-note">Sjekk at e-post fra nettsiden virker:</p>
          <button class="btn ghost" type="submit">Send test-e-post til meg</button>
        </form>
      <?php else: ?>
        <p class="small-note section">E-post er ikke satt opp. Fyll inn SMTP-innstillingene i <code>.env</code> for å kunne sende invitasjoner og «glemt passord».</p>
      <?php endif ?>
    </div>
  </div>
</section>

<section class="section login" id="passord">
  <h2>Bytt passord</h2>
  <form method="post" action="/komite/">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <input type="text" name="username" value="<?= h($user['email']) ?>" autocomplete="username" hidden>
    <label for="p-current">Nåværende passord
      <input type="password" id="p-current" name="current_password" autocomplete="current-password" required>
    </label>
    <label for="p-new">Nytt passord <span class="hint">Minst 10 tegn</span>
      <input type="password" id="p-new" name="new_password" minlength="10" autocomplete="new-password" required>
    </label>
    <button class="btn green" type="submit">Bytt passord</button>
  </form>
</section>
<?php
page_footer();
