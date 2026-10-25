<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

$m = messe();
$count = (int) db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
$highlights = db()->query('SELECT lot, title, category, image FROM items WHERE image IS NOT NULL ORDER BY RAND() LIMIT 4')->fetchAll();

page_header('Høstmessa ' . $m['aar'], 'forside');
?>
<section class="home">
  <div class="home-text">
    <h1>Høstmessa <?= h($m['aar']) ?></h1>
    <div class="prose"><?= format_text(page_body('forside')['body']) ?></div>
  </div>
  <aside class="info-card">
    <h2>Praktisk info</h2>
    <?= facts_html(['dato', 'apningstid', 'sted', 'auksjon_tid', 'varefrist']) ?>
    <a class="btn" href="/auksjon/">Se auksjonskatalogen<?= $count ? ' (' . $count . ' ' . ($count === 1 ? 'vare' : 'varer') . ')' : '' ?></a>
  </aside>
</section>

<?php if ($highlights): ?>
<section class="section">
  <div class="admin-head">
    <h2>Smakebiter fra auksjonen</h2>
    <a href="/auksjon/">Se alle varene</a>
  </div>
  <div class="grid">
    <?php foreach ($highlights as $item): ?>
      <a class="card" href="/auksjon/">
        <div class="photo"><?= photo_html($item) ?><span class="lot"><?= h(lot_no((int) $item['lot'])) ?></span></div>
        <div class="card-body">
          <span class="cat"><?= h($item['category']) ?></span>
          <h3><?= h($item['title']) ?></h3>
        </div>
      </a>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>
<?php
page_footer();
