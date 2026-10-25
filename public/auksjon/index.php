<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/layout.php';

$items = db()->query('SELECT lot, title, description, category, min_price, giver, image FROM items ORDER BY lot')->fetchAll();
$usedCategories = array_values(array_filter(CATEGORIES, fn ($c) => in_array($c, array_column($items, 'category'), true)));

page_header('Auksjon · Høstmessa', 'auksjon');
?>
<section class="page-hero">
  <div>
    <h1>Auksjonskatalog</h1>
    <p class="lede">Se hva som kommer under hammeren på messa. Har du noe å gi til auksjonen? Ta kontakt med <?= h(messe()['kontakt']) ?>, så legger de varen inn i katalogen.</p>
  </div>
  <?= facts_html(['auksjon_tid', 'varefrist']) ?>
</section>
<section>
  <?php if (!$items): ?>
    <p class="empty">Katalogen fylles opp etter hvert som komiteen legger inn varer. Kom gjerne tilbake senere.</p>
  <?php else: ?>
    <div class="tools">
      <input class="search" id="search" type="search" placeholder="Søk etter varer, f.eks. «genser» eller «hytte»" aria-label="Søk i katalogen">
      <div class="chips" id="chips">
        <button class="chip" type="button" aria-pressed="true" data-cat="">Alle</button>
        <?php foreach ($usedCategories as $cat): ?>
          <button class="chip" type="button" aria-pressed="false" data-cat="<?= h($cat) ?>"><?= h($cat) ?></button>
        <?php endforeach ?>
      </div>
    </div>
    <div class="grid" id="grid">
      <?php foreach ($items as $item): ?>
        <button class="card" type="button"
          data-lot="<?= h(lot_no((int) $item['lot'])) ?>"
          data-title="<?= h($item['title']) ?>"
          data-desc="<?= h($item['description']) ?>"
          data-cat="<?= h($item['category']) ?>"
          data-price="<?= h(kr($item['min_price'] === null ? null : (int) $item['min_price'])) ?>"
          data-giver="<?= h($item['giver']) ?>">
          <div class="photo"><?= photo_html($item) ?><span class="lot"><?= h(lot_no((int) $item['lot'])) ?></span></div>
          <div class="card-body">
            <span class="cat"><?= h($item['category']) ?></span>
            <h3><?= h($item['title']) ?></h3>
            <div class="meta"><span>Minstepris</span><strong><?= h(kr($item['min_price'] === null ? null : (int) $item['min_price'])) ?></strong></div>
          </div>
        </button>
      <?php endforeach ?>
    </div>
    <p class="empty" id="empty" hidden>Ingen varer passer søket ditt.</p>
  <?php endif ?>
</section>

<dialog id="dlg" aria-labelledby="dlg-title">
  <div style="position:relative">
    <button class="close" id="dlg-close" type="button" aria-label="Lukk">×</button>
    <div class="dlg">
      <div class="photo" id="dlg-photo"></div>
      <div class="dlg-body">
        <div class="cat" id="dlg-cat"></div>
        <h2 id="dlg-title"></h2>
        <p id="dlg-desc" style="white-space:pre-line"></p>
        <dl class="kv">
          <dt>Varenummer</dt><dd id="dlg-lot"></dd>
          <dt>Minstepris</dt><dd id="dlg-pris"></dd>
          <dt>Gitt av</dt><dd id="dlg-giver"></dd>
        </dl>
        <?php if (messe()['auksjon_tid'] !== ''): ?>
          <p class="small-note">Budene gis i salen under auksjonen, som starter <?= h(messe()['auksjon_tid']) ?>.</p>
        <?php endif ?>
      </div>
    </div>
  </div>
</dialog>
<script src="/assets/katalog.js" defer></script>
<?php
page_footer();
