<?php
// Shared UI components.

define('LISTING_PAGE_SIZE', 50);

function listing_page() {
  $page = max(1, (int)(@$_GET['page'] ?: @$_POST['page'] ?: 1));
  return [$page, ($page - 1) * LISTING_PAGE_SIZE];
}

function listing_batch($rows) {
  return [array_slice($rows, 0, LISTING_PAGE_SIZE), count($rows) > LISTING_PAGE_SIZE];
}

function infinite_scroll($url, $tag = 'li', $colspan = null) {
  if($tag == 'tr') {
    ?>
    <tr class="infinite-scroll" z-intersect="preload" x-get="<?= esc_attr($url) ?>" x-on="intersect" x-replace="outerHTML"><td colspan="<?= esc_attr($colspan) ?>">Loading…</td></tr>
    <?php
    return;
  }
  ?>
  <<?= $tag ?> class="infinite-scroll" z-intersect="preload" x-get="<?= esc_attr($url) ?>" x-on="intersect" x-replace="outerHTML">Loading…</<?= $tag ?>>
  <?php
}

// Renders a fragment inline for the initial page load, so sections
// arrive pre-filled instead of fetching themselves after paint. The
// fragment sees $params merged over the page's own query string —
// the same request its x-get would have made. xhtml skips the on-load
// fetch for non-empty elements, so the x-get stays for refreshes.
function fragment($path, $params = []) {
  $saved = $_GET;
  $_GET = $params + $_GET;
  include path_join(__DIR__, "..", "app", "$path.php");
  $_GET = $saved;
}

// Tag picker: the selected tags as removable badges (hidden `tags[]`
// inputs carry the ids) in front of a search input. The suggestion list
// holds every tag; client/tags.js (z-tags) filters it while typing.
function tags_field($selected = []) {
  ?>
  <div class="tags" z-tags>
    <?php foreach($selected as $tag): ?>
      <button type="button" class="tag tags__tag" title="Remove tag" style="--tag-color: <?= esc_attr($tag['color']) ?>"><input
        type="hidden" name="tags[]" value="<?= esc_attr($tag['id']) ?>"><?= esc_inner($tag['label']) ?></button>
    <?php endforeach ?>
    <!-- The wrapper anchors the suggestion list to the input, so it opens
         under the caret rather than at the component's left edge. The input's
         name never reaches a store function; it lets xhtml restore focus
         here when an autosaving editor re-renders the document. -->
    <span class="tags__search">
      <input class="tags__input" type="text" name="tags_search" placeholder="Add tags..." autocomplete="off">
      <ul class="listing tags__suggestions" hidden>
        <?php foreach(\store\list_tags() as $tag): ?>
          <li class="listing__item tags__suggestion" data-id="<?= esc_attr($tag['id']) ?>" data-label="<?= esc_attr($tag['label']) ?>"
            data-slug="<?= esc_attr(tag_slug($tag['label'])) ?>" data-color="<?= esc_attr($tag['color']) ?>" hidden><?= esc_inner($tag['label']) ?></li>
        <?php endforeach ?>
      </ul>
    </span>
    <template><button type="button" class="tag tags__tag" title="Remove tag"><input type="hidden" name="tags[]"></button></template>
  </div>
  <?php
}

function circle($class = "") {
  ?>
  <svg class="circle <?= esc_attr($class) ?>" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
    <path d="M20 16 C45 5 80 8 92 30 C99 50 92 78 68 89 C42 99 12 93 6 64 C1 40 12 18 36 11"/>
    <path class="circle__ghost" d="M24 12 C48 3 82 6 95 29 C102 49 95 80 70 92 C44 102 12 95 4 66 C-2 41 10 15 33 9"/>
  </svg>
  <?php
}
