<?php

namespace bookmarks;

function fetch_label($url) {
  $response = \http\get($url);
  if($response['state'] != 'success' || $response['status'] >= 400) return null;

  if(!preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $response['body'], $match)) return null;

  $label = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return cast_string(preg_replace('/\s+/u', ' ', $label));
}
