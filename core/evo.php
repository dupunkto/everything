<?php
// Interface with DirectAdmin Evo for managing shared hosting servers.

namespace evo;

function list_mailboxes($domains) {
  return api_iterate($domains, 'mailboxes', '/CMD_API_EMAIL_POP', function($domain, $data) {
    if (!isset($data['emails'])) return [];

    $mailboxes = [];
    foreach ($data['emails'] as $user) {
      if (!isset($user['account']) || $user['account'] == APP['username']) continue;

      $mailboxes[] = [
        'username' => $user['account'],
        'domain' => $domain,
        'quota' => $user['usage']['quota'] ?? 'unlimited',
        'usage' => $user['usage']['usage'] ?? 'unknown',
      ];
    }
    return $mailboxes;
  });
}

function list_forwarders($domains) {
  $result = api_iterate($domains, 'forwarders', '/CMD_API_EMAIL_FORWARDERS', function($domain, $data) {
    if (!is_array($data)) return [];

    $forwarders = [];
    foreach ($data as $source => $targets) {
      $forwarders[] = [
        'source' => $source,
        'domain' => $domain,
        'targets' => is_array($targets) ? $targets : [$targets]
      ];
    }
    return $forwarders;
  });

  if ($result['state'] == 'success') usort($result['body'],
    fn($a, $b) => strcasecmp($a['targets'][0] ?? '', $b['targets'][0] ?? ''));
  
  return $result;
}

function list_domains() {
  return cached('domains', fn() => api_get('/CMD_API_SHOW_DOMAINS'));
}

function create_mailbox($username, $password, $quota, $domain) {
  return api_post('/CMD_API_EMAIL_POP', [
    'action' => 'create',
    'user' => $username,
    'passwd' => $password,
    'passwd2' => $password,
    'quota' => $quota,
    'domain' => $domain
  ]);
}

function delete_mailbox($username, $domain) {
  return api_post('/CMD_API_EMAIL_POP', [
    'action' => 'delete',
    'select0' => $username,
    'domain' => $domain
  ]);
}

function create_forwarder($source, $target, $domain) {
  return api_post('/CMD_API_EMAIL_FORWARDERS', [
    'action' => 'create',
    'user' => $source,
    'email' => $target,
    'domain' => $domain,
    'create' => 'Create'
  ]);
}

function delete_forwarder($source, $domain) {
  return api_post('/CMD_API_EMAIL_FORWARDERS', [
    'action' => 'delete',
    'select0' => $source,
    'domain' => $domain
  ]);
}

function get_webmail_url($username, $domain) {
  if (!@APP['webmail']) return null;
  return "https://" . APP['webmail'] . "/?_user=" . urlencode("{$username}@{$domain}");
}

// Helpers & HTTP layer

function api_iterate($domains, $cache_key, $endpoint, $transform) {
  $items = [];
  $uncached = [];

  foreach ($domains as $domain) {
    $cached = cache_get("{$cache_key}_{$domain}");
    if ($cached !== null) $items = array_merge($items, $cached);
    else $uncached[] = $domain;
  }

  if (empty($uncached)) return ['state' => 'success', 'status' => 200, 'body' => $items];

  $result = api_parallel($uncached, $endpoint);
  if ($result['state'] != 'success') return $result;

  foreach ($result['body'] as $domain => $data) {
    $transformed = $transform($domain, $data);
    cache_set("{$cache_key}_{$domain}", $transformed);
    $items = array_merge($items, $transformed);
  }

  return ['state' => 'success', 'status' => 200, 'body' => $items];
}

function api_get($endpoint, $params = []) {
  $params['json'] ??= 'yes';
  $url = build_url($endpoint) . '?' . http_build_query($params);
  $result = \http\get($url, ['Authorization' => 'Basic ' . APP['credential']]);

  dbg($result);

  return normalize_response($result);
}

function api_post($endpoint, $payload = []) {
  $payload['json'] ??= 'yes';
  $url = build_url($endpoint);

  $result = \http\post($url, http_build_query($payload), [
    'Authorization' => 'Basic ' . APP['credential'],
    'Content-Type' => 'application/x-www-form-urlencoded'
  ]);

  // Invalidate caches for the given domain.
  if ($result['state'] == 'success' && $result['status'] <= 299 && isset($payload['domain'])) {
    if (str_contains($endpoint, 'EMAIL_POP')) cache_delete("mailboxes_{$payload['domain']}");
    if (str_contains($endpoint, 'EMAIL_FORWARDERS')) cache_delete("forwarders_{$payload['domain']}");
  }

  return normalize_response($result);
}

function api_parallel($domains, $endpoint, $params = []) {
  $multi = curl_multi_init();
  $handles = [];

  foreach ($domains as $domain) {
    $query = array_merge($params, ['domain' => $domain, 'json' => 'yes']);
    $url = build_url($endpoint) . '?' . http_build_query($query);
    $headers = ['Authorization' => 'Basic ' . APP['credential']];

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => zip(": ", $headers),
      CURLOPT_SSL_VERIFYPEER => false,
    ]);

    curl_multi_add_handle($multi, $ch);
    $handles[$domain] = $ch;
  }

  do {
    curl_multi_exec($multi, $running);
    curl_multi_select($multi);
  } while ($running > 0);

  $responses = [];
  foreach ($handles as $domain => $ch) {
    $body = curl_multi_getcontent($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_multi_remove_handle($multi, $ch);
    curl_close($ch);

    if ($status > 299) {
      curl_multi_close($multi);
      return ['state' => 'error', 'status' => $status, 'body' => $body];
    }

    $responses[$domain] = json_decode($body, true) ?? [];
  }

  curl_multi_close($multi);
  return ['state' => 'success', 'status' => 200, 'body' => $responses];
}

function build_url($endpoint) {
  return "https://" . APP['host'] . ":" . APP['port'] . $endpoint;
}

function normalize_response($result) {
  if ($result['state'] != 'success') return $result;
  $result['body'] = json_decode($result['body'], true);
  return $result;
}

// Cache layer

function cached($key, $fetcher) {
  $value = cache_get($key);
  if ($value !== null) return $value;

  $result = $fetcher();
  if ($result['state'] == 'success' && $result['status'] <= 299) {
    cache_set($key, $result);
    return $result;
  }

  return $result;
}

function cache_get($key) {
  $cache = "/tmp/__evo_{$key}__.json";
  $ttl = 86400; // Cache for a day.
  if (!file_exists($cache) || (time() - filemtime($cache)) >= $ttl) {
    error_log("CACHE MISS: $cache"); return null;
  }
  return json_decode(file_get_contents($cache), true);
}

function cache_set($key, $value) {
  file_put_contents("/tmp/__evo_{$key}__.json", json_encode($value));
}

function cache_delete($key) {
  $cache = "/tmp/__evo_{$key}__.json";
  if (file_exists($cache)) unlink($cache);
}