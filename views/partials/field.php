<?php
/** @var string $name @var string $label */
$type = $type ?? 'text';
$err = $errors[$name] ?? '';
?>
<div class="field<?= $err ? ' bad' : '' ?>">
  <label for="f-<?= e($name) ?>"><?= e($label) ?></label>
  <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($old[$name] ?? '') ?>"
    <?= !empty($ph) ? 'placeholder="' . e($ph) . '"' : '' ?> <?= $type === 'tel' ? 'inputmode="tel" autocomplete="tel"' : '' ?>
    <?= !empty($auto) ? 'autocomplete="' . e($auto) . '"' : '' ?> <?= $err ? 'aria-invalid="true"' : '' ?>>
  <?php if ($err): ?><span class="err"><?= e($err) ?></span><?php endif; ?>
</div>
