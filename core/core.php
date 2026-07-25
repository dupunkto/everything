<?php
// Public Everything API.

namespace core;

function diff($before, ...$values) {
  return array_values(array_filter(array_keys($values),
    fn($field) => @$before[$field] != $values[$field]));
}

function parse_query($query, $tags = null) {
  $tags ??= \store\list_tags();

  $by_slug = [];
  foreach($tags as $tag) $by_slug[tag_slug($tag['label'])] = $tag['id'];

  $tag_ids = [];
  $terms = [];
  $selectors = [];

  foreach(str_explode($query) as $token) {
    if($token[0] === '+') {
      $slug = tag_slug(substr($token, 1));
      if(isset($by_slug[$slug])) $tag_ids[] = $by_slug[$slug];
      continue;
    }

    [$key, $value] = array_pad(explode(":", $token, 2), 2, null);
    if($value !== null) { $selectors[] = [$key, $value]; continue; }

    $terms[] = $token;
  }

  return [array_values(array_unique($tag_ids)), $terms, $selectors];
}

