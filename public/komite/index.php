<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/layout.php';

start_session();
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // PHP forkaster hele skjemaet når det er større enn post_max_size.
    flash('error', 'Bildet er for stort. Maks 15 MB.');
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

    if ($title === '' || $description === '' || $giver === '' || !in_array($category, CATEGORIES, true)) {
        flash('error', 'Fyll inn varenavn, beskrivelse, kategori og hvem som har gitt varen.');
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
        flash('ok', sprintf('«%s» (%s) er oppdatert.', $title, lot_no((int) $existing['lot'])));
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $lot = (int) $pdo->query('SELECT COALESCE(MAX(lot), 0) + 1 FROM items FOR UPDATE')->fetchColumn();
        $pdo->prepare('INSERT INTO items (lot, title, description, category, min_price, giver, image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$lot, $title, $description, $category, $minPrice, $giver, $image, $user['id']]);
        $pdo->commit();
        flash('ok', sprintf('«%s» er lagt ut som %s og vises nå i katalogen.', $title, lot_no($lot)));
    }
    redirect('/komite/');
}

if ($action === 'delete_item') {
    $stmt = db()->prepare('SELECT title, image FROM items WHERE id = ?');
    $stmt->execute([(int) ($_POST['id'] ?? 0)]);
    if ($item = $stmt->fetch()) {
        db()->prepare('DELETE FROM items WHERE id = ?')->execute([(int) $_POST['id']]);
        delete_image($item['image']);
        flash('ok', sprintf('«%s» er fjernet fra katalogen.', $item['title']));
    }
    redirect('/komite/');
}

if ($action === 'add_user') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Skriv inn navn og en gyldig e-postadresse.');
    } elseif (strlen($password) < 10) {
        flash('error', 'Passordet må ha minst 10 tegn.');
    } else {
        try {
            db()->prepare('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)')
                ->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
            flash('ok', sprintf('%s er lagt til. Gi beskjed om passordet, og be dem bytte det etter første innlogging.', $name));
        } catch (PDOException $e) {
            if ($e->errorInfo[1] !== 1062) {
                throw $e;
            }
            flash('error', 'Det finnes allerede et komitémedlem med den e-postadressen.');
        }
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
$f = $edit ?? ['id' => 0, 'title' => '', 'description' => '', 'category' => CATEGORIES[0], 'min_price' => null, 'giver' => '', 'image' => null];

page_header('For komiteen · Høstmessa', 'komite');
?>
<section>
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
    <form method="post" action="/komite/" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_BYTES ?>">
      <label class="drop" for="f-image">
        <?php if ($f['image']): ?><img class="current" src="/uploads/<?= h($f['image']) ?>" alt="Nåværende bilde"><?php endif ?>
        <span><strong><?= $f['image'] ? 'Bytt bilde' : 'Velg bilde' ?></strong><br><span class="hint">JPG, PNG eller WebP, maks 15 MB</span></span>
        <input type="file" id="f-image" name="image" accept="image/jpeg,image/png,image/webp">
      </label>
      <?php if ($f['image']): ?>
        <label class="check" for="f-remove"><input type="checkbox" id="f-remove" name="remove_image" value="1"> Fjern bildet</label>
      <?php endif ?>
      <label for="f-title">Hva er varen?
        <input type="text" id="f-title" name="title" maxlength="150" value="<?= h($f['title']) ?>" placeholder="F.eks. Strikket barnelue" required>
      </label>
      <label for="f-description">Beskrivelse <span class="hint">Størrelse, stand, hvem som har laget den</span>
        <textarea id="f-description" name="description" required><?= h($f['description']) ?></textarea>
      </label>
      <div class="row2">
        <label for="f-category">Kategori
          <select id="f-category" name="category">
            <?php foreach (CATEGORIES as $cat): ?>
              <option<?= $cat === $f['category'] ? ' selected' : '' ?>><?= h($cat) ?></option>
            <?php endforeach ?>
          </select>
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
            <div class="photo"><?= photo_html($item) ?></div>
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
        <label for="u-password">Midlertidig passord <span class="hint">Minst 10 tegn</span>
          <input type="text" id="u-password" name="password" minlength="10" autocomplete="off" required>
        </label>
        <button class="btn green" type="submit">Legg til</button>
      </form>
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
