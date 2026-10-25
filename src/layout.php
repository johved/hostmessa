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
<link rel="stylesheet" href="/assets/style.css?v=<?= @filemtime(dirname(__DIR__) . '/public/assets/style.css') ?>">
<link rel="icon" href="/assets/kfuk-kfum-logo.svg" type="image/svg+xml">
</head>
<body>
<header class="site">
  <div class="wrap">
    <a class="brand" href="/">
      <img class="logo" src="/assets/kfuk-kfum-logo.svg" alt="KFUK-KFUM" width="26" height="40">
      <span class="brand-text"><span class="org">Lindesnes KFUK-KFUM</span>
      <span class="brand-name">Høstmessa</span></span>
    </a>
    <?php if ($m['dato'] !== ''): ?><span class="brand-date"><?= h($m['dato']) ?><?= $m['sted'] !== '' ? ' · ' . h($m['sted']) : '' ?></span><?php endif ?>
  </div>
</header>
<nav class="tabs" aria-label="Meny">
  <div class="wrap">
    <?php foreach (['forside' => ['/', 'Forside'], 'auksjon' => ['/auksjon/', 'Auksjon'], 'historie' => ['/historie/', 'Historie'], 'komite' => ['/komite/', 'For komiteen']] as $key => [$href, $label]): ?>
      <a class="tab<?= $key === 'komite' ? ' tab-end' : '' ?>" href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach ?>
  </div>
</nav>
<main>
  <div class="wrap">
<?php foreach (take_flashes() as [$type, $message]): ?>
    <div class="flash <?= h($type) ?>" role="status"><?= h($message) ?></div>
<?php endforeach ?>
<?php
}

function maps_url(string $place): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($place);
}

// Messeinfo som definisjonsliste. Tomme felt hoppes over.
function facts_html(array $keys): string
{
    $m = messe();
    $html = '';
    foreach ($keys as $key) {
        if ($m[$key] !== '') {
            $value = $key === 'sted' ? '<a href="' . h(maps_url($m[$key])) . '" target="_blank" rel="noopener">' . h($m[$key]) . '</a>' : h($m[$key]);
            $html .= '<dt>' . h(SETTINGS[$key][0]) . '</dt><dd>' . $value . '</dd>';
        }
    }
    return $html === '' ? '' : '<dl class="facts">' . $html . '</dl>';
}

function page_footer(): void
{
    ?>
  </div>
</main>
<footer><div class="wrap">Høstmessa er Lindesnes KFUK-KFUM sin store dugnad hver høst. Spørsmål? Kontakt <?= h(messe()['kontakt']) ?>.</div></footer>
</body>
</html>
<?php
}
