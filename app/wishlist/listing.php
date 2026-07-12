<?php

  $show = @$_GET['show'] ?? @$_POST['show'] ?? 'dream';
  $include = @$_GET['i'] ?? @$_POST['i'];

  // This array includes IDs of items that have just been clicked.
  // We do not want to have them disappear from under the users cursor,
  // that is a very bad UX. So this 'skips' them from the query that
  // is currently active.
  $include = $include ? explode(",", $include) : [];

  $statuses = match($show) {
    'shelves' => ['nvm'],
    'bought'  => ['dream', 'bought'],
    default   => ['dream'],
  };

  $wishes = \store\list_wishes($statuses, $include);

?>
<?php foreach($wishes as $wish): ?>
  <li class="listing__item" tabindex="0" data-id="<?= $wish['id'] ?>" data-status="<?= esc_attr($wish['status']) ?>">
    <?php if(cast_boolean($wish['urgent'])) circle() ?>
    <form x-post="/wishlist/status" x-target="#wishlist-listing" x-on="change">
      <input type="hidden" name="id" value="<?= $wish['id'] ?>">
      <input type="hidden" name="status" value="dream" />
      <input type="hidden" name="show" value="<?= esc_attr($show) ?>">
      <input type="hidden" name="i" value="<?= esc_attr(join(",", array_unique([...$include, $wish['id']]))) ?>">

      <input
        type="checkbox"
        class="listing__check"
        name="status"
        value="bought"
        <?php if(in_array($wish['status'], ['bought', 'nvm'])) echo "checked" ?>
        <?php if($wish['status'] == "nvm") echo "disabled" ?>
      >
    </form>

    <h4 class="listing__title">
      <span class="humid"><?= $wish['id'] ?></span>
      <a class="listing__link" href="/wishlist/edit?id=<?= $wish['id'] ?>" tabindex="-1">
        <?= esc_inner($wish['title']) ?>
      </a>
    </h4>
  </li>
<?php endforeach ?>
