<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/komite.php';

$user = komite_page('konto');
?>
<section class="login">
  <h2>Mitt passord</h2>
  <p class="sub">Du er logget inn som <?= h($user['name']) ?> (<?= h($user['email']) ?>).</p>
  <form method="post" action="<?= komite_url('konto') ?>">
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
