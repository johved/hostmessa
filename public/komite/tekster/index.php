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
<?php
page_footer();
