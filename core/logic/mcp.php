<?php
// MCP connector for integration with Claude Code.

namespace mcp;

define('MCP_APPS', ['notes', 'todo', 'bookmarks', 'calendar']);
define('MCP_PROTOCOL_VERSIONS', ['2025-06-18', '2025-03-26', '2024-11-05']);

function tools() {
  $id = ['type' => 'string', 'minLength' => 1];
  $query = ['type' => 'string', 'description' => "Text, +tag and selector query."];
  $limit = ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25];
  $offset = ['type' => 'integer', 'minimum' => 0, 'default' => 0];

  return [
    'search_notes' => tool('notes', 'search_notes',
      "Search notes. Results contain excerpts; use get_note for complete content.",
      ['query' => $query, 'limit' => $limit, 'offset' => $offset]),
    'get_note' => tool('notes', 'get_note', "Get a complete note by ID.",
      ['id' => $id], ['id']),
    'search_todos' => tool('todo', 'search_todos',
      "Search todos using text, +tag and status selectors such as is:open.",
      ['query' => $query, 'limit' => $limit, 'offset' => $offset]),
    'get_todo' => tool('todo', 'get_todo', "Get a complete todo by ID.",
      ['id' => $id], ['id']),
    'search_bookmarks' => tool('bookmarks', 'search_bookmarks',
      "Search bookmarks. Results contain note excerpts; use get_bookmark for complete content.",
      ['query' => $query, 'limit' => $limit, 'offset' => $offset]),
    'get_bookmark' => tool('bookmarks', 'get_bookmark', "Get a complete bookmark by ID.",
      ['id' => $id], ['id']),
    'list_appointments' => tool('calendar', 'list_appointments',
      "List appointment occurrences in the half-open local-time range [from, to), up to 366 days.", [
        'from' => ['type' => 'string', 'description' => "ISO 8601 date or datetime."],
        'to' => ['type' => 'string', 'description' => "ISO 8601 date or datetime."],
      ], ['from', 'to']),
    'get_appointment' => tool('calendar', 'get_appointment',
      "Get an appointment or recurring-series record by the stable ID returned by list_appointments.",
      ['id' => $id], ['id']),
  ];
}

function tool($app, $name, $description, $properties, $required = []) {
  return [
    'app' => $app,
    'name' => $name,
    'description' => $description,
    'inputSchema' => [
      'type' => 'object',
      'properties' => $properties,
      'required' => $required,
      'additionalProperties' => false,
    ],
    'annotations' => [
      'readOnlyHint' => true,
      'destructiveHint' => false,
      'idempotentHint' => true,
      'openWorldHint' => false,
    ],
  ];
}

function allowed_tools($connector) {
  $apps = array_column(\store\list_connector_apps($connector['id']), 'app');
  return array_filter(tools(), fn($tool) => in_array($tool['app'], $apps));
}

function validate_arguments($args, $allowed) {
  if(!is_array($args)) throw new \InvalidArgumentException("Arguments must be an object.");

  $unknown = array_diff(array_keys($args), $allowed);
  if($unknown) throw new \InvalidArgumentException("Unknown argument '" . reset($unknown) . "'.");
}

function page_arguments($args) {
  validate_arguments($args, ['query', 'limit', 'offset']);

  $query = array_get_string($args, 'query', "");
  $limit = array_get_int($args, 'limit', 25);
  $offset = array_get_int($args, 'offset', 0);

  if($limit < 1 || $limit > 100)
    throw new \InvalidArgumentException("Array key 'limit' is outside its allowed range.");
  if($offset < 0)
    throw new \InvalidArgumentException("Array key 'offset' is outside its allowed range.");

  return [$query, $limit, $offset];
}

function note($note, $complete = false) {
  return [
    'id' => $note['id'],
    'title' => $note['title'],
    ($complete ? 'content' : 'excerpt') => $complete ? $note['content'] : excerpt($note['content']),
    'written_at' => $note['written_at'],
    'tags' => pluck(\store\list_note_tags($note['id']), 'label'),
  ];
}

function todo($task, $complete = false) {
  return [
    'id' => $task['id'],
    'title' => $task['title'],
    ($complete ? 'content' : 'excerpt') => $complete ? $task['content'] : excerpt($task['content']),
    'status' => $task['status'],
    'urgent' => cast_bool($task['urgent']),
    'open_at' => $task['open_at'],
    'due_at' => $task['next'],
    'due_all_day' => cast_bool($task['due_all_day']),
    'expire_at' => $task['expire_at'],
    'recurrence' => $task['recurrence'],
    'updated_at' => $task['updated_date'],
    'tags' => pluck($task['tags'], 'label'),
  ];
}

function bookmark($bookmark, $complete = false) {
  return [
    'id' => $bookmark['id'],
    'label' => $bookmark['label'],
    'url' => $bookmark['url'],
    ($complete ? 'note' : 'note_excerpt') => $complete ? $bookmark['note'] : excerpt($bookmark['note']),
    'saved_at' => $bookmark['saved_at'],
    'tags' => pluck(\store\list_bookmark_tags($bookmark['id']), 'label'),
  ];
}

function appointment($event, $series = false) {
  return [
    'id' => $event['humid'],
    'title' => $event['title'],
    'content' => $event['content'],
    'starts_at' => $event['starts_at'],
    'ends_at' => $event['ends_at'],
    'all_day' => cast_bool($event['all_day']),
    'location' => $event['location'],
    'meeting' => $event['meeting'],
    'going' => cast_bool($event['going']),
    'source' => @$event['calendar_title'] ?: @$event['subscription_title'],
    'recurring' => !!$event['recurrence'],
    'series' => $series,
  ];
}
