<?php
// Query parsing for search bars.
// This file was lovingly written by Claude.

namespace query;

// Split a raw query into [$tags, $terms, $selectors].
//
// - `+tag` resolves to a tag id by slug; tags that don't exist are dropped, so a
//   stray `+typo` never silently zeroes the results.
// - `key:value` becomes a [$key, $value] selector for the app to interpret.
// - anything else is a bare term for fuzzy matching.
//
// Pass $tags to reuse an already-loaded tag list; it defaults to all tags.
function parse($query, $tags = null) {
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

// True when every term fuzzy-matches (substring, case-insensitive) the haystack.
// Terms are AND'd, matching the per-token behaviour of the SQL listings.
function matches_terms($haystack, $terms) {
  foreach($terms as $term)
    if(mb_stripos($haystack, $term) === false) return false;
  return true;
}
