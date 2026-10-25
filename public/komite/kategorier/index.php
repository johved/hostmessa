<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/komite.php';

$user = komite_page('kategorier');
$categoryCounts = db()->query("SELECT category, COUNT(*) FROM items WHERE category <> '' GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<section>
  <div class="form-layout">
    <div>
      <h2>Kategorier</h2>
      <p class="sub">Kategoriene varene sorteres etter i auksjonskatalogen. Bare kategorier som har varer, vises i katalogen.</p>
      <div class="queue">
        <?php if (!categories()): ?><p class="small-note">Ingen kategorier ennå.</p><?php endif ?>
        <?php foreach (categories() as $cat): ?>
          <div class="qitem small cat-row">
            <form method="post" action="<?= komite_url('kategorier') ?>" class="cat-rename">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename_category">
              <input type="hidden" name="old" value="<?= h($cat) ?>">
              <input type="text" name="name" maxlength="50" value="<?= h($cat) ?>" aria-label="Navn på kategorien <?= h($cat) ?>" required>
              <button class="btn ghost" type="submit">Lagre</button>
            </form>
            <?php $n = (int) ($categoryCounts[$cat] ?? 0); ?>
            <span class="small-note"><?= $n ?> <?= $n === 1 ? 'vare' : 'varer' ?></span>
            <form method="post" action="<?= komite_url('kategorier') ?>" class="inline" onsubmit="return confirm('Slette kategorien «<?= h(addslashes($cat)) ?>»?<?= !empty($categoryCounts[$cat]) ? ' Varene i den blir uten kategori.' : '' ?>')">
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
      <form method="post" action="<?= komite_url('kategorier') ?>">
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
<?php
page_footer();
