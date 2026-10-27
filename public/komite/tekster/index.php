<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/komite.php';

$user = komite_page('tekster');
?>
<h2>Sidetekster</h2>
<nav class="subnav subnav-small" aria-label="Sidetekstene">
  <?php foreach (PAGES as $slug => $pageInfo): ?>
    <a href="#side-<?= $slug ?>"><?= h($pageInfo['title']) ?></a>
  <?php endforeach ?>
</nav>
<?php foreach (PAGES as $slug => $pageInfo): ?>
<section class="section" id="side-<?= $slug ?>">
  <div class="form-layout">
    <div>
    <form method="post" action="<?= komite_url('tekster') ?>">
      <h3><?= h($pageInfo['title']) ?></h3>
      <p class="sub"><a href="<?= h($pageInfo['url'] ?? ($slug === 'forside' ? '/' : '/' . $slug . '/')) ?>">Se siden</a></p>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_page">
      <input type="hidden" name="slug" value="<?= $slug ?>">
      <label for="page-<?= $slug ?>">Tekst
        <textarea class="tall" id="page-<?= $slug ?>" name="body"><?= h(page_body($slug)['body']) ?></textarea>
      </label>
      <button class="btn green" type="submit">Lagre <?= h(mb_strtolower($pageInfo['title'])) ?></button>
    </form>
    <form method="post" action="<?= komite_url('tekster') ?>" enctype="multipart/form-data" class="page-image" data-target="page-<?= $slug ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_page_image">
      <input type="hidden" name="slug" value="<?= $slug ?>">
      <strong>Sett inn bilde</strong>
      <div class="page-image-fields">
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-label="Bilde til <?= h(mb_strtolower($pageInfo['title'])) ?>" required>
        <input type="text" name="caption" maxlength="150" placeholder="Bildetekst (valgfritt)" aria-label="Bildetekst">
        <button class="btn ghost" type="submit">Sett inn</button>
      </div>
      <span class="hint page-image-status">Bildet settes inn der markøren står i teksten. Husk å lagre teksten etterpå.</span>
    </form>
    </div>
    <div class="help">
      <strong>Formatering</strong>
      <p>Tom linje mellom avsnitt.<br>
      <code>## Overskrift</code> på egen linje.<br>
      <code>- punkt</code> for punktliste.<br>
      <code>**fet tekst**</code><br>
      <code>[lenketekst](https://adresse.no)</code><br>
      <code>![bildetekst](/uploads/…)</code> er et bilde. Bruk «Sett inn bilde», så lages linjen for deg.</p>
    </div>
  </div>
</section>
<?php endforeach ?>
<script src="<?= asset('skaler-bilder.js') ?>" defer></script>
<script src="<?= asset('sidetekst-bilder.js') ?>" defer></script>
<?php
page_footer();
