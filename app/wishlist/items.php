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
      <a class="listing__link" href="/wishlist/edit?id=<?= $wish['id'] ?>" tabindex="-1" z-key="enter e o">
        <?= esc_inner($wish['title']) ?>
      </a>
    </h4>

    <?php if($wish['total_price'] !== null): ?>
      <span class="listing__price"><?= esc_inner(format_price($wish['total_price'])) ?></span>
    <?php endif ?>
    <button type="button" x-delete="/wishlist/delete?id=<?= $wish['id'] ?>" z-key="d" z-confirm="Delete this wish?" hidden></button>
  </li>
<?php endforeach ?>
<?php if($has_more): ?>
  <?php infinite_scroll("/wishlist/listing?" . http_build_query([
    'show' => $show,
    'i' => join(",", $include),
    'page' => $page + 1,
  ])) ?>
<?php endif ?>
