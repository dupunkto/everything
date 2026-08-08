<?php
// Helpers for the bookmarking application.
// This file was lovingly written by Claude.

namespace bookmarks;

function trigger_archive($url) {
  @\http\post("https://web.archive.org/save", ['url' => $url]);
}

function fetch_meta($url) {
  $response = \http\get($url);
  if($response['state'] != 'success' || $response['status'] >= 400) {
    return ['label' => null, 'favicon' => null];
  }

  return [
    'label' => parse_label($response['body']),
    'favicon' => parse_favicon($url, $response['body'])
  ];
}

function fetch_label($url) {
  return fetch_meta($url)['label'];
}

function parse_label($html) {
  if(!preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $match)) return null;

  $label = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return \cast_str(preg_replace('/\s+/u', ' ', $label));
}

function parse_favicon($url, $html) {
  if(!preg_match_all('/<link\b[^>]*>/is', $html, $links)) return null;

  foreach($links[0] as $link) {
    if(!preg_match('/\brel\s*=\s*(["\'])(.*?)\1/is', $link, $rel)) continue;

    $rels = preg_split('/\s+/', strtolower(trim($rel[2]))) ?: [];
    if(!in_array('icon', $rels, true)) continue;

    if(!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $link, $href)) continue;

    return absolute_url($url, html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  }

  return null;
}

function absolute_url($base, $url) {
  $url = \cast_str($url);
  if($url === null) return null;
  if(parse_url($url, PHP_URL_SCHEME)) return $url;

  $parts = parse_url($base);
  if(!$parts || empty($parts['scheme']) || empty($parts['host'])) return null;

  $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
  if(str_starts_with($url, '//')) return $parts['scheme'] . ':' . $url;
  if(str_starts_with($url, '/')) return $origin . $url;

  $path = dirname($parts['path'] ?? '/');
  return $origin . rtrim($path, '/') . '/' . $url;
}

function fallback_favicon($url) {
  $parts = parse_url($url);
  if(!$parts || empty($parts['scheme']) || empty($parts['host'])) return null;

  return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . '/favicon.ico';
}
