<?php
declare(strict_types=1);

const PLACEHOLDER_SVG = '<svg class="placeholder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="1"/><path d="M2 8h20M12 8v13M12 8c-2-4-6-5-6-2 0 2 6 2 6 2zm0 0c2-4 6-5 6-2 0 2-6 2-6 2z"/></svg>';

function photo_html(array $item): string
{
    if (!empty($item['image'])) {
        return '<img src="/uploads/' . h($item['image']) . '" alt="" loading="lazy">';
    }
    return PLACEHOLDER_SVG;
}

function page_header(string $title, string $active): void
{
    $m = messe();
    ?>
<!doctype html>
<html lang="nb">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Young+Serif&family=Figtree:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="top">
  <div class="wrap">
    <div>
      <div class="org">KFUK-KFUM Lindesnes</div>
      <h1>Høstmessa <?= h($m['aar']) ?><br>Auksjonskatalog</h1>
      <p class="lede">Se hva som kommer under hammeren på messa. Har du noe å gi til auksjonen? Ta kontakt med <?= h($m['kontakt']) ?>, så legger de varen inn i katalogen.</p>
    </div>
    <dl class="facts">
      <?php foreach (['Dato' => $m['dato'], 'Sted' => $m['sted'], 'Auksjon' => $m['tid'], 'Frist for varer' => $m['frist']] as $label => $value): ?>
        <?php if ($value !== ''): ?><dt><?= h($label) ?></dt><dd><?= h($value) ?></dd><?php endif ?>
      <?php endforeach ?>
    </dl>
  </div>
</header>
<nav class="tabs" aria-label="Seksjoner">
  <div class="wrap">
    <a class="tab" href="/"<?= $active === 'katalog' ? ' aria-current="page"' : '' ?>>Katalog</a>
    <a class="tab" href="/komite/"<?= $active === 'komite' ? ' aria-current="page"' : '' ?>>For komiteen</a>
  </div>
</nav>
<main>
  <div class="wrap">
<?php foreach (take_flashes() as [$type, $message]): ?>
    <div class="flash <?= h($type) ?>" role="status"><?= h($message) ?></div>
<?php endforeach ?>
<?php
}

function page_footer(): void
{
    ?>
  </div>
</main>
<footer><div class="wrap">Høstmessa er KFUK-KFUM Lindesnes sin store dugnad hver høst. Spørsmål om auksjonen: kontakt <?= h(messe()['kontakt']) ?>.</div></footer>
</body>
</html>
<?php
}
