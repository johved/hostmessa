<?php
declare(strict_types=1);
require __DIR__ . '/../../../src/komite.php';

$user = komite_page('messeinfo');
?>
<section>
  <h2>Messeinfo</h2>
  <p class="sub">Vises på forsiden og øverst på alle sider. Tomme felt skjules.</p>
  <form method="post" action="<?= komite_url('messeinfo') ?>" class="login">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">
    <?php foreach (SETTINGS as $key => [$label]): ?>
      <label for="s-<?= $key ?>"><?= h($label) ?>
        <input type="text" id="s-<?= $key ?>" name="<?= $key ?>" maxlength="200" value="<?= h(messe()[$key]) ?>">
      </label>
    <?php endforeach ?>
    <button class="btn green" type="submit">Lagre messeinfo</button>
  </form>
</section>
<?php
page_footer();
