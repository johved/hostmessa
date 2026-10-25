<?php
declare(strict_types=1);
require __DIR__ . '/../../src/komite.php';

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
      <form method="post" action="<?= komite_url() ?>">
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


if (!$user && isset($_GET['glemt'])) {
    page_header('Glemt passord · Høstmessa', 'komite');
    ?>
    <section class="login">
      <h2>Glemt passord</h2>
      <p class="sub">Skriv inn e-postadressen du logger inn med, så sender vi deg en lenke for å lage nytt passord.</p>
      <form method="post" action="<?= komite_url() ?>">
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
      <p class="sub">Bare messekomiteen kan legge ut og endre innhold på nettsiden.</p>
      <form method="post" action="<?= komite_url() ?>">
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

$counts = [
    'varer' => (int) db()->query('SELECT COUNT(*) FROM items')->fetchColumn(),
    'galleri' => (int) db()->query('SELECT COUNT(*) FROM gallery')->fetchColumn(),
    'kategorier' => count(categories()),
    'brukere' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];
page_header('For komiteen · Høstmessa', 'komite');
komite_nav($user, '');
?>
<section>
  <h2>Hei, <?= h($user['name']) ?></h2>
  <p class="sub">Du er logget inn som <?= h(mb_strtolower(ROLES[$user['role']][0] ?? $user['role'])) ?>. <?= h(ROLES[$user['role']][1] ?? '') ?></p>
  <div class="komite-tiles">
    <?php foreach (KOMITE_PAGES as $slug => [$label, $adminOnly, $about]): ?>
      <?php if (!$adminOnly || is_admin($user)): ?>
        <a class="komite-tile" href="<?= komite_url($slug) ?>">
          <strong><?= h($label) ?><?php if (isset($counts[$slug])): ?> <span class="count"><?= $counts[$slug] ?></span><?php endif ?></strong>
          <span><?= h($about) ?></span>
        </a>
      <?php endif ?>
    <?php endforeach ?>
  </div>
</section>
<?php
page_footer();
