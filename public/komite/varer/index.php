<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/komite.php';

$user = komite_page('varer');
$items = db()->query('SELECT * FROM items ORDER BY lot DESC')->fetchAll();
$extraImages = extra_images();

$edit = isset($_GET['edit']) ? find_item((int) $_GET['edit']) : null;
if ($edit && !can_edit($user, $edit['created_by'])) {
    $edit = null;
}
$fromGallery = !$edit && isset($_GET['fra_galleri']) ? find_gallery_image((int) $_GET['fra_galleri']) : null;
if ($fromGallery && !can_edit($user, $fromGallery['created_by'])) {
    $fromGallery = null;
}
$f = $edit ?? ['id' => 0, 'title' => $fromGallery['caption'] ?? '', 'description' => '', 'category' => '', 'min_price' => null, 'giver' => '', 'image' => $fromGallery['image'] ?? null, 'crop' => null];
?>
<section>
  <?php if ($edit): ?>
    <h2>Endre <?= h(lot_no((int) $edit['lot'])) ?></h2>
    <p class="sub">Endringene vises i katalogen med en gang du lagrer. <a href="<?= komite_url('varer') ?>">Avbryt</a></p>
  <?php elseif ($fromGallery): ?>
    <h2>Gjør galleribildet til en utvalgt vare</h2>
    <p class="sub">Skriv litt om varen. Når du lagrer, flyttes bildet fra bildegalleriet til katalogen. <a href="<?= komite_url('galleri') ?>">Avbryt</a></p>
  <?php else: ?>
    <h2>Legg ut en utvalgt vare</h2>
    <p class="sub">Velg ut noen av auksjonsvarene som skal fremheves i katalogen, med bilder, beskrivelse og minstepris. De andre auksjonsvarene kan du legge i <a href="<?= komite_url('galleri') ?>">bildegalleriet</a>, som vises under katalogen. Varen får neste ledige varenummer.</p>
  <?php endif ?>

  <div class="form-layout">
    <form method="post" action="<?= komite_url('varer') ?>" enctype="multipart/form-data" data-shrink>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <?php if ($fromGallery): ?><input type="hidden" name="from_gallery" value="<?= (int) $fromGallery['id'] ?>"><?php endif ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_BYTES ?>">
      <input type="hidden" name="crop" id="f-crop" value="<?= h($f['crop'] ?? '') ?>">

      <div class="cropper" id="cropper"<?= $f['image'] ? '' : ' hidden' ?>>
        <div class="crop-head">
          <strong>Hovedbilde</strong>
          <span class="hint">Dra rammen til det som skal vises på kortet i katalogen.</span>
        </div>
        <div class="crop-body">
          <div class="crop-stage" id="crop-stage">
            <img id="crop-img" src="<?= $f['image'] ? '/uploads/' . h($f['image']) : '' ?>" alt="Hovedbildet">
            <div class="crop-frame" id="crop-frame" hidden></div>
          </div>
          <div class="crop-side">
            <span class="hint">Slik blir kortet</span>
            <div class="crop-preview" id="crop-preview"></div>
            <label for="crop-zoom" class="crop-zoom">Størrelse på utsnittet
              <input type="range" id="crop-zoom" min="25" max="100" value="100">
            </label>
            <button type="button" class="btn ghost" id="crop-reset">Hele bildet</button>
          </div>
        </div>
      </div>

      <label class="drop" for="f-image">
        <span><strong id="f-image-label"><?= $f['image'] ? 'Bytt hovedbilde' : 'Velg hovedbilde' ?></strong><br><span class="hint">Vises i katalogen. JPG, PNG eller WebP.</span></span>
        <input type="file" id="f-image" name="image" accept="image/jpeg,image/png,image/webp">
      </label>
      <?php if ($edit && $f['image']): ?>
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
          <?php if (is_admin($user)): ?><span class="hint"><a href="<?= komite_url('kategorier') ?>">Lag eller endre kategorier</a></span><?php endif ?>
        </label>
        <label for="f-price">Minstepris (kr) <span class="hint">Valgfritt</span>
          <input type="number" id="f-price" name="min_price" min="0" step="1" inputmode="numeric" value="<?= h($f['min_price'] === null ? '' : (string) $f['min_price']) ?>">
        </label>
      </div>
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
              <?php if (can_edit($user, $item['created_by'])): ?>
                <a class="btn ghost" href="<?= komite_url('varer') ?>?edit=<?= (int) $item['id'] ?>">Endre</a>
                <form method="post" action="<?= komite_url('varer') ?>" class="inline" onsubmit="return confirm('Fjerne «<?= h(addslashes($item['title'])) ?>» fra katalogen?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                  <button class="btn ghost" type="submit">Fjern</button>
                </form>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>
</section>
<script src="<?= asset('beskjaer.js') ?>" defer></script>
<script src="<?= asset('skaler-bilder.js') ?>" defer></script>
<?php
page_footer();
