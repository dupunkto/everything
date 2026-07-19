<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<link rel="stylesheet" href="https://cdn.dupunkto.org/tools.css">
<link rel="stylesheet" href="https://cdn.dupunkto.org/icons/fontawesome/css/all.css">
<link rel="stylesheet" href="<?= CANONICAL ?>/css/main.css">
<?php if(UI_PANEL_POSITION == 'left'): ?>
  <link rel="stylesheet" href="<?= CANONICAL ?>/css/main__left.css">
<?php endif ?>

<!-- NOTE: Order is significant, zhtml must load before xhtml to restore persisted state before onload requests fire -->

<script src="<?= CANONICAL ?>/vendor/zhtml.min.js"></script>
<script src="<?= CANONICAL ?>/vendor/xhtml.min.js"></script>
<script src="<?= CANONICAL ?>/vendor/nav.min.js"></script>
<script src="<?= CANONICAL ?>/vendor/forms.min.js" type="module"></script>
<script src="<?= CANONICAL ?>/vendor/localtime.min.js"></script>
<script src="<?= CANONICAL ?>/client/shell.js"></script>
<script src="<?= CANONICAL ?>/client/tags.js" type="module"></script>

<link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%20100%20100'%3E%3Ctext%20y='.9em'%20font-size='90'%3E🤍%3C/text%3E%3C/svg%3E">
