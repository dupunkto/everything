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
<div class="page-header">
  <h1 class="page-header__title"><strong><?= $show == 'shelves' ? 'Shelves' : 'Wishlist' ?></strong></h1>

  <nav class="view-nav">
    <?php if($show == 'shelves'): ?>
      <button type="button" z-set="#wishlist-filter" value="dream">&larr; Back to wishlist</button>
    <?php elseif($show == 'bought'): ?>
      <button type="button" z-set="#wishlist-filter" value="dream"><i class="fa-regular fa-eye"></i> Hide bought</button>
      <button type="button" z-set="#wishlist-filter" value="shelves"><i class="fa-regular fa-box-archive"></i> Shelves</button>
    <?php else: ?>
      <button type="button" z-set="#wishlist-filter" value="bought"><i class="fa-regular fa-eye-slash"></i> Show bought</button>
      <button type="button" z-set="#wishlist-filter" value="shelves"><i class="fa-regular fa-box-archive"></i> Shelves</button>
    <?php endif ?>
  </nav>
</div>

<ul class="listing">
  <?php foreach($wishes as $wish): ?>
    <li class="listing__item" tabindex="0">
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
          z-key="c"
          <?php if(in_array($wish['status'], ['bought', 'nvm'])) echo "checked" ?>
          <?php if($wish['status'] == "nvm") echo "disabled" ?>
        >

        <button name="status" value="<?= $wish['status'] == 'nvm' ? 'dream' : 'nvm' ?>" z-key="s" hidden></button>
      </form>

      <h4 class="listing__title">
        <span class="humid"><?= $wish['id'] ?></span>
        <a class="listing__link" href="/wishlist/edit?id=<?= $wish['id'] ?>" tabindex="-1" z-key="e o">
          <?= esc_inner($wish['title']) ?>
        </a>
      </h4>
      <a href="/wishlist/delete?id=<?= $wish['id'] ?>" z-key="d" z-confirm="Delete this wish?" hidden></a>
    </li>
  <?php endforeach ?>
</ul>
