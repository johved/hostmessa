<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/layout.php';

$page = page_body('historie');

page_header('Historie · Høstmessa', 'historie');
?>
<article class="prose-page">
  <h1>Messens historie</h1>
  <div class="prose"><?= format_text($page['body']) ?></div>
  <?php if ($page['updated_at']): ?>
    <p class="small-note">Sist oppdatert <?= h(date('j.n.Y', strtotime($page['updated_at']))) ?></p>
  <?php endif ?>
</article>
<?php
page_footer();
