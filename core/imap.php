<?php
// Minimal IMAP client.
// This file was lovingly written by Claude.

namespace imap;

const NOTE_UTI = 'com.apple.mail-note';
const MAILBOX = 'Notes';

class Client {
  private $sock;
  private $tag = 0;

  private function __construct($sock) {
    $this->sock = $sock;
  }

  static function open($hostname, $port, $ssl_mode) {
    if(!in_array($ssl_mode, ENUM_SSL_MODE)) fail("IMAP: unknown ssl_mode '$ssl_mode'.");

    $remote = ($ssl_mode == 'ssl' ? "tls://" : "tcp://") . $hostname . ":" . $port;
    $sock = @stream_socket_client($remote, $errno, $error, 30);
    if(!$sock) fail("IMAP: connecting to $hostname:$port failed: " . ($error ?: "network error"));
    stream_set_timeout($sock, 60);

    $client = new self($sock);
    [$greeting] = $client->read_response();
    if(!preg_match('/^\* (OK|PREAUTH)/i', $greeting))
      fail("IMAP: unexpected greeting from $hostname.");

    if($ssl_mode == 'tls') {
      $client->command("STARTTLS");
      if(!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
        fail("IMAP: STARTTLS negotiation with $hostname failed.");
    }

    return $client;
  }

  function login($username, $password) {
    foreach([$username, $password] as $value) {
      if(preg_match('/[\r\n\0]/', $value ?? ""))
        fail("IMAP: credentials contain control characters.");
    }

    $this->command("LOGIN " . self::quote($username) . " " . self::quote($password),
      error: "authentication failed");
  }

  function capability() {
    $capabilities = [];
    $this->command("CAPABILITY", function($tokens) use (&$capabilities) {
      if(strcasecmp($tokens[0] ?? "", "CAPABILITY") == 0)
        $capabilities = array_map('strtoupper', array_slice($tokens, 1));
    });
    return $capabilities;
  }

  // Returns the advertised notes mailbox: a mailbox flagged \XNotes when
  // present, the literal 'Notes' mailbox otherwise, null when neither exists.
  function find_notes_mailbox() {
    $mailboxes = [];
    $this->command('LIST "" "*"', function($tokens) use (&$mailboxes) {
      if(strcasecmp($tokens[0] ?? "", "LIST") != 0) return;
      $flags = is_array($tokens[1] ?? null) ? $tokens[1] : [];
      $name = $tokens[3] ?? null;
      if($name !== null) $mailboxes[] = ['flags' => array_map('strtoupper', $flags), 'name' => $name];
    });

    foreach($mailboxes as $mailbox)
      if(in_array('\XNOTES', $mailbox['flags'])) return $mailbox['name'];
    foreach($mailboxes as $mailbox)
      if($mailbox['name'] === MAILBOX) return $mailbox['name'];

    return null;
  }

  function create($mailbox) {
    $this->command("CREATE " . self::quote($mailbox));
  }

  function subscribe($mailbox) {
    $this->command("SUBSCRIBE " . self::quote($mailbox));
  }

  // Selects the mailbox and returns its message count.
  function select($mailbox) {
    $exists = 0;
    $this->command("SELECT " . self::quote($mailbox), function($tokens) use (&$exists) {
      if(is_string($tokens[1] ?? null) && strcasecmp($tokens[1], "EXISTS") == 0)
        $exists = (int)$tokens[0];
    });
    return $exists;
  }

  // Fetches every message in the selected mailbox as
  // ['uid' => int, 'message' => raw RFC 5322 bytes].
  function fetch_all($exists) {
    if($exists < 1) return [];

    $messages = [];
    $this->command("FETCH 1:* (UID BODY.PEEK[])", function($tokens) use (&$messages) {
      if(!is_string($tokens[1] ?? null) || strcasecmp($tokens[1], "FETCH") != 0) return;
      $items = is_array($tokens[2] ?? null) ? $tokens[2] : [];

      $uid = null;
      $body = null;
      for($i = 0; $i < count($items) - 1; $i++) {
        if(!is_string($items[$i])) continue;
        if(strcasecmp($items[$i], "UID") == 0) $uid = (int)$items[$i + 1];
        if(str_starts_with(strtoupper($items[$i]), "BODY[")) $body = $items[$i + 1];
      }

      if($uid !== null && is_string($body)) $messages[] = ['uid' => $uid, 'message' => $body];
    });

    return $messages;
  }

  function append($mailbox, $message) {
    $tag = $this->next_tag();
    $this->write("$tag APPEND " . self::quote($mailbox) . " (\\Seen) {" . strlen($message) . "}\r\n");

    // The server answers with a continuation request before accepting the
    // literal, or rejects the APPEND outright with a tagged NO.
    while(true) {
      [$text, $literals] = $this->read_response();
      if(str_starts_with($text, "+")) break;
      if(str_starts_with($text, "* ")) continue;
      fail("IMAP: append rejected: " . self::response_text($text));
    }

    $this->write($message . "\r\n");
    $this->collect($tag, error: "append failed");
  }

  function expunge($uids) {
    if(!$uids) return;
    if(!in_array("UIDPLUS", $this->capability()))
      fail("IMAP: server does not support targeted expunge (UIDPLUS).");

    $set = join(",", $uids);
    $this->command("UID STORE $set" . ' +FLAGS.SILENT (\Deleted)');
    $this->command("UID EXPUNGE $set");
  }

  function logout() {
    try { $this->command("LOGOUT"); }
    finally { @fclose($this->sock); }
  }

  // Protocol plumbing

  function command($command, $on_untagged = null, $error = "command failed") {
    $tag = $this->next_tag();
    $this->write("$tag $command\r\n");
    return $this->collect($tag, $on_untagged, $error);
  }

  private function next_tag() {
    return "A" . str_pad(++$this->tag, 4, "0", STR_PAD_LEFT);
  }

  private function write($data) {
    for($written = 0; $written < strlen($data);) {
      $bytes = @fwrite($this->sock, substr($data, $written));
      if(!$bytes) fail("IMAP: connection closed while sending.");
      $written += $bytes;
    }
  }

  private function collect($tag, $on_untagged = null, $error = "command failed") {
    $untagged = [];

    while(true) {
      [$text, $literals] = $this->read_response();

      if(str_starts_with($text, "+"))
        fail("IMAP: unexpected continuation request.");

      if(str_starts_with($text, "* ")) {
        $tokens = $this->tokenize(substr($text, 2), $literals);
        if($on_untagged) $on_untagged($tokens);
        else $untagged[] = $tokens;
        continue;
      }

      [$response_tag, $rest] = array_pad(explode(" ", $text, 2), 2, "");
      if($response_tag != $tag)
        fail("IMAP: response for unknown tag '$response_tag'.");

      if(!preg_match('/^OK\b/i', $rest))
        fail("IMAP: $error: " . self::response_text($text));

      return $untagged;
    }
  }

  // Reads one logical response line. Literals ({n} followed by n raw bytes)
  // are collected separately and marked with a NUL byte in the text, which
  // cannot occur in the protocol-level text itself.
  private function read_response() {
    $text = "";
    $literals = [];

    while(true) {
      $line = fgets($this->sock);
      if($line === false) fail("IMAP: connection closed unexpectedly.");
      $line = rtrim($line, "\r\n");

      if(preg_match('/\{(\d+)\}$/', $line, $match)) {
        $literals[] = $this->read_bytes((int)$match[1]);
        $text .= substr($line, 0, -strlen($match[0])) . "\0";
        continue;
      }

      return [$text . $line, $literals];
    }
  }

  private function read_bytes($length) {
    $data = "";
    while(strlen($data) < $length) {
      $chunk = fread($this->sock, $length - strlen($data));
      if($chunk === false || $chunk === "") fail("IMAP: connection closed mid-literal.");
      $data .= $chunk;
    }
    return $data;
  }

  // Parses a response into atoms, quoted strings, literal payloads and
  // nested arrays for parenthesised lists.
  private function tokenize($text, $literals) {
    $pos = 0;
    $index = 0;

    $parse = function() use (&$parse, &$pos, &$index, $text, $literals) {
      $tokens = [];

      while($pos < strlen($text)) {
        $c = $text[$pos];

        if($c == ' ') { $pos++; continue; }
        if($c == ')') { $pos++; break; }
        if($c == '(') { $pos++; $tokens[] = $parse(); continue; }
        if($c == "\0") { $pos++; $tokens[] = $literals[$index++]; continue; }

        if($c == '"') {
          $pos++;
          $value = "";
          while($pos < strlen($text) && $text[$pos] != '"') {
            if($text[$pos] == '\\') $pos++;
            $value .= $text[$pos++] ?? "";
          }
          $pos++;
          $tokens[] = $value;
          continue;
        }

        $value = "";
        while($pos < strlen($text) && !in_array($text[$pos], [' ', '(', ')', "\0"])) {
          $value .= $text[$pos++];
        }
        $tokens[] = $value == "NIL" ? null : $value;
      }

      return $tokens;
    };

    return $parse();
  }

  private static function quote($value) {
    return '"' . addcslashes($value ?? "", "\\\"") . '"';
  }

  private static function response_text($text) {
    // Strip the tag so failure messages read naturally.
    return trim(preg_replace('/^\S+ /', '', $text));
  }
}

// Account-level helpers

function connect($account) {
  $client = Client::open($account['hostname'], (int)$account['port'], $account['ssl_mode']);
  $client->login($account['username'], $account['password']);
  return $client;
}

// The address used in message headers. Usernames usually are the email
// address; bare logins get the server appended to stay a valid address.
function account_address($account) {
  return str_contains($account['username'], "@")
    ? $account['username']
    : $account['username'] . "@" . strtolower($account['hostname']);
}

// Finds the notes mailbox, creating and subscribing it when absent,
// and returns its name without selecting it.
function ensure_notes_mailbox($client) {
  $mailbox = $client->find_notes_mailbox();
  if($mailbox !== null) return $mailbox;

  $client->create(MAILBOX);
  $client->subscribe(MAILBOX);
  return MAILBOX;
}

// Connection test used when saving account settings: connect,
// authenticate and verify/create the notes mailbox.
function verify_account($account) {
  $client = connect($account);
  try { ensure_notes_mailbox($client); }
  finally { $client->logout(); }
}

// MIME: parsing Apple mail-note messages

// Parses a raw message into an Apple note, or returns null for messages
// that are not Apple mail-notes. Malformed notes fail the request; a
// broken note must abort the sync rather than be treated as deleted.
function parse_note($raw) {
  [$headers, $body] = split_message($raw);

  if(strcasecmp(trim($headers['x-uniform-type-identifier'] ?? ""), NOTE_UTI) != 0) return null;

  $uuid = normalize_uuid($headers['x-universally-unique-identifier'] ?? null);
  if($uuid === null) fail("IMAP: note with missing or malformed X-Universally-Unique-Identifier.");

  $modified_at = parse_date($headers['date'] ?? null);
  if($modified_at === null) fail("IMAP: note $uuid has a missing or invalid Date header.");

  $created_at = parse_date($headers['x-mail-created-date'] ?? null) ?? $modified_at;

  return [
    'uuid' => $uuid,
    'title' => cast_str(decode_header_text($headers['subject'] ?? "")),
    'content' => note_content($headers, $body),
    'created_at' => $created_at,   // unix timestamp
    'modified_at' => $modified_at, // unix timestamp
  ];
}

function normalize_uuid($value) {
  $value = trim($value ?? "");
  // Some producers wrap the UUID in angle brackets like a Message-ID.
  $value = trim($value, "<>");
  if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) return null;
  return strtoupper($value);
}

function parse_date($value) {
  if(!is_nonempty_str($value)) return null;
  try { return (new \DateTimeImmutable($value))->getTimestamp(); }
  catch(\Exception) { return null; }
}

function split_message($raw) {
  $raw = str_replace("\r\n", "\n", $raw);
  [$head, $body] = array_pad(explode("\n\n", $raw, 2), 2, "");
  return [parse_headers($head), $body];
}

function parse_headers($head) {
  // Unfold continuation lines before splitting.
  $head = preg_replace('/\n[ \t]+/', ' ', $head);

  $headers = [];
  foreach(explode("\n", $head) as $line) {
    [$name, $value] = array_pad(explode(":", $line, 2), 2, null);
    if($value === null) continue;
    $name = strtolower(trim($name));
    $headers[$name] ??= trim($value);
  }

  return $headers;
}

function decode_header_text($value) {
  $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, "UTF-8");
  return $decoded === false ? $value : $decoded;
}

function parse_content_type($value) {
  $parts = explode(";", $value ?? "");
  $type = strtolower(trim(array_shift($parts)));

  $params = [];
  foreach($parts as $part) {
    [$name, $param] = array_pad(explode("=", $part, 2), 2, null);
    if($param === null) continue;
    $params[strtolower(trim($name))] = trim(trim($param), '"');
  }

  return [$type ?: "text/plain", $params];
}

function decode_transfer_encoding($body, $encoding) {
  $encoding = strtolower(trim($encoding ?? ""));

  if(in_array($encoding, ["", "7bit", "8bit", "binary"])) return $body;
  if($encoding == "quoted-printable") return quoted_printable_decode($body);

  if($encoding == "base64") {
    $decoded = base64_decode($body, true);
    if($decoded === false) fail("IMAP: note part with invalid base64 body.");
    return $decoded;
  }

  fail("IMAP: unsupported Content-Transfer-Encoding '$encoding' in note.");
}

function to_utf8($text, $charset) {
  $charset = strtolower(trim($charset ?? "")) ?: "utf-8";
  if(in_array($charset, ["utf-8", "us-ascii", "ascii"])) return $text;

  $converted = @mb_convert_encoding($text, "UTF-8", $charset);
  if($converted === false) fail("IMAP: unsupported charset '$charset' in note.");
  return $converted;
}

// Reduces a supported note body to Markdown. Unsupported structure
// fails the request instead of silently dropping data.
function note_content($headers, $body) {
  [$type, $params] = parse_content_type($headers['content-type'] ?? "text/plain");

  if($type == "text/plain" || $type == "text/html") {
    $text = to_utf8(
      decode_transfer_encoding($body, $headers['content-transfer-encoding'] ?? null),
      $params['charset'] ?? null
    );
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return cast_str($type == "text/html" ? \notes\html_to_markdown($text) : $text);
  }

  if($type == "multipart/alternative") {
    $boundary = $params['boundary'] ?? fail("IMAP: multipart note without boundary.");
    $candidates = split_multipart($body, $boundary);

    foreach(["text/html", "text/plain"] as $preferred) {
      foreach($candidates as [$part_headers, $part_body]) {
        [$part_type] = parse_content_type($part_headers['content-type'] ?? "text/plain");
        if($part_type == $preferred || ($preferred == "text/html" && str_starts_with($part_type, "multipart/")))
          return note_content($part_headers, $part_body);
      }
    }

    fail("IMAP: multipart/alternative note without a text part.");
  }

  if($type == "multipart/related") {
    $boundary = $params['boundary'] ?? fail("IMAP: multipart note without boundary.");
    $parts = split_multipart($body, $boundary);
    if(!$parts) fail("IMAP: empty multipart/related note.");

    // The HTML root is only usable when every sibling part is ignorable
    // metadata; a real attachment must abort instead of being dropped.
    [$root_headers, $root_body] = array_shift($parts);
    foreach($parts as [$part_headers, $part_body]) {
      [$part_type] = parse_content_type($part_headers['content-type'] ?? "text/plain");
      if(trim($part_body) !== "")
        fail("IMAP: note contains an unsupported attachment part ($part_type).");
    }

    return note_content($root_headers, $root_body);
  }

  fail("IMAP: unsupported note content type '$type'.");
}

function split_multipart($body, $boundary) {
  $parts = [];
  $current = null;

  foreach(explode("\n", $body) as $line) {
    $trimmed = rtrim($line);

    if($trimmed == "--$boundary--") break;
    if($trimmed == "--$boundary") {
      if($current !== null) $parts[] = $current;
      $current = "";
      continue;
    }
    if($current !== null) $current .= $line . "\n";
  }

  if($current !== null) $parts[] = $current;

  return array_map(function($part) {
    [$head, $part_body] = array_pad(explode("\n\n", $part, 2), 2, "");
    return [parse_headers($head), $part_body];
  }, $parts);
}

// MIME: serialising Apple mail-note messages

// Builds the RFC 5322 message for a note. Content is the note's Markdown
// rendered to HTML; dates are unix timestamps.
function build_note_message($account, $uuid, $title, $markdown, $created_at, $modified_at) {
  $html = "<html><head><meta charset=\"utf-8\"></head><body>\n"
    . markdown($markdown ?? "")
    . "\n</body></html>";

  $headers = [
    "MIME-Version: 1.0",
    "X-Uniform-Type-Identifier: " . NOTE_UTI,
    "X-Universally-Unique-Identifier: $uuid",
    "X-Mail-Created-Date: " . rfc_date($created_at),
    "Date: " . rfc_date($modified_at),
    "From: " . format_address($account['name'], account_address($account)),
    "To: <" . account_address($account) . ">",
    "Subject: " . encode_header_text($title ?? ""),
    "Message-ID: <" . generate_uuid() . "@" . strtolower($account['hostname']) . ">",
    "Content-Type: text/html; charset=utf-8",
    "Content-Transfer-Encoding: quoted-printable",
  ];

  return join("\r\n", $headers) . "\r\n\r\n" . quoted_printable_encode($html);
}

function rfc_date($timestamp) {
  return (new \DateTimeImmutable("@$timestamp"))->format(\DateTimeInterface::RFC2822);
}

function encode_header_text($value) {
  if(preg_match('/^[\x20-\x7e]*$/', $value)) return $value;
  return mb_encode_mimeheader($value, "UTF-8", "B", "\r\n");
}

function format_address($name, $email) {
  if(!is_nonempty_str($name)) return "<$email>";

  $display = preg_match('/^[\x20-\x7e]*$/', $name)
    ? '"' . addcslashes($name, "\\\"") . '"'
    : mb_encode_mimeheader($name, "UTF-8", "B", "\r\n");

  return "$display <$email>";
}
