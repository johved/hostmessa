<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/komite.php';

$user = komite_page('galleri');
$gallery = db()->query('SELECT id, image, caption, created_by FROM gallery ORDER BY id')->fetchAll();
?>
<section>
  <div class="admin-head">
    <div>
      <h2>Bildegalleri <span class="count"><?= count($gallery) ?></span></h2>
      <p class="sub">For varer som kommer på auksjonen, men som ikke er beskrevet i katalogen. Velg mange bilder på en gang. De vises under katalogen på auksjonssiden. Bildetekst er valgfritt. Vil du fremheve en av varene, trykk «Gjør til utvalgt vare».</p>
    </div>
    <?php if ($gallery && is_admin($user)): ?>
      <form method="post" action="<?= komite_url('galleri') ?>" onsubmit="return confirm('Fjerne alle <?= count($gallery) ?> bildene fra galleriet? Dette kan ikke angres.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clear_gallery">
        <button class="btn ghost" type="submit">Tøm galleriet</button>
      </form>
    <?php endif ?>
  </div>
  <form method="post" action="<?= komite_url('galleri') ?>" enctype="multipart/form-data" id="gallery-form" class="gallery-upload">
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
        <div class="gallery-admin-item" id="g<?= (int) $g['id'] ?>">
          <img src="/uploads/<?= h(thumb_name($g['image'])) ?>" alt="" loading="lazy">
          <?php if (can_edit($user, $g['created_by'])): ?>
            <form method="post" action="<?= komite_url('galleri') ?>" class="cat-rename">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="caption_gallery">
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <input type="text" name="caption" maxlength="150" value="<?= h($g['caption']) ?>" placeholder="Bildetekst" aria-label="Bildetekst">
              <button class="btn ghost" type="submit">Lagre</button>
            </form>
            <div class="gallery-actions">
              <a class="btn ghost" href="<?= komite_url('varer') ?>?fra_galleri=<?= (int) $g['id'] ?>">Gjør til utvalgt vare</a>
              <form method="post" action="<?= komite_url('galleri') ?>" onsubmit="return confirm('Fjerne bildet fra galleriet?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_gallery">
                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                <button class="btn ghost" type="submit">Fjern</button>
              </form>
            </div>
          <?php elseif ($g['caption'] !== ''): ?>
            <p class="small-note"><?= h($g['caption']) ?></p>
          <?php endif ?>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>
<script src="<?= asset('galleri-opplasting.js') ?>" defer></script>
<?php
page_footer();
