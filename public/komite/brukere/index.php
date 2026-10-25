<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/komite.php';

$user = komite_page('brukere');
$users = db()->query('SELECT id, name, email, role FROM users ORDER BY name')->fetchAll();
?>
<section>
  <div class="form-layout">
    <div>
      <h2>Brukere</h2>
      <p class="sub">Alle her kan logge inn på komitésiden.</p>
      <ul class="role-list">
        <?php foreach (ROLES as [$label, $about]): ?>
          <li><strong><?= h($label) ?>:</strong> <?= h($about) ?></li>
        <?php endforeach ?>
      </ul>
      <div class="queue">
        <?php foreach ($users as $u): ?>
          <div class="qitem small user-row">
            <div>
              <h3><?= h($u['name']) ?></h3>
              <p><?= h($u['email']) ?></p>
            </div>
            <div class="qact">
              <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                <form method="post" action="<?= komite_url('brukere') ?>" class="inline role-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="set_role">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <select name="role" aria-label="Brukergruppe for <?= h($u['name']) ?>" onchange="this.form.submit()">
                    <?php foreach (ROLES as $key => [$label]): ?>
                      <option value="<?= $key ?>"<?= $u['role'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach ?>
                  </select>
                  <noscript><button class="btn ghost" type="submit">Lagre</button></noscript>
                </form>
                <?php if (mail_configured()): ?>
                  <form method="post" action="<?= komite_url('brukere') ?>" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="invite_user">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn ghost" type="submit" title="Send e-post med lenke for å lage passord">Send passordlenke</button>
                  </form>
                <?php endif ?>
                <form method="post" action="<?= komite_url('brukere') ?>" class="inline" onsubmit="return confirm('Fjerne <?= h(addslashes($u['name'])) ?> som bruker?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn ghost" type="submit">Fjern</button>
                </form>
              <?php else: ?>
                <span class="small-note"><?= h(ROLES[$u['role']][0] ?? $u['role']) ?> · deg</span>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
    <div>
      <h3 class="list-h">Legg til en bruker</h3>
      <form method="post" action="<?= komite_url('brukere') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_user">
        <label for="u-name">Navn
          <input type="text" id="u-name" name="name" maxlength="100" required>
        </label>
        <label for="u-email">E-post
          <input type="email" id="u-email" name="email" maxlength="190" required>
        </label>
        <label for="u-role">Brukergruppe
          <select id="u-role" name="role">
            <?php foreach (ROLES as $key => [$label]): ?>
              <option value="<?= $key ?>"<?= $key === 'bidragsyter' ? ' selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach ?>
          </select>
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
        <form method="post" action="<?= komite_url('brukere') ?>" class="section">
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
<?php
page_footer();
