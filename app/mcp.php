<?php
// MCP connector for integration with Claude Code.

$connector = \store\get_connector_by_token($params[1])
  or fail("Connector not found.", status: 404);

$_AUTHENTICATED = true;

if($method == 'OPTIONS') {
  header("Allow: POST, OPTIONS");
  stay_on_page();
}

if($method != 'POST') {
  header("Allow: POST, OPTIONS");
  fail("MCP requests must use POST.", status: 405);
}

$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

try {
  $request = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
}
catch(JsonException $error) {
  throw new MCPError("Parse error.", -32700, previous: $error);
}

if(!is_array($request)
  || @$request['jsonrpc'] != '2.0'
  || !is_string(@$request['method'])) {
  throw new MCPError("Invalid request.", -32600);
}

if(!array_key_exists('id', $request)) {
  http_response_code(202); exit;
}

$id = $request['id'];
$rpc_method = $request['method'];
$rpc_params = @$request['params'];
$result = null;

if($rpc_params !== null && !is_array($rpc_params))
  throw new MCPError("Parameters must be an object.", -32602, id: $id);

$rpc_params = $rpc_params ?: [];

switch($rpc_method) {
  case 'initialize':
    $requested = @$rpc_params['protocolVersion'];
    $version = in_array($requested, MCP_PROTOCOL_VERSIONS)
      ? $requested : MCP_PROTOCOL_VERSIONS[0];

    $result = [
      'protocolVersion' => $version,
      'capabilities' => ['tools' => ['listChanged' => false]],
      'serverInfo' => ['name' => 'everything', 'version' => EVERYTHING_VERSION],
      'instructions' => "Read-only access to the Everything applications enabled for this connector.",
    ];

    break;

  case 'notifications/initialized':
    break;

  case 'ping':
    $result = (object)[];
    break;

  case 'tools/list':
    if(@$rpc_params['cursor'])
      throw new MCPError("Unknown tools cursor.", -32602, id: $id);

    $result = ['tools' => array_values(array_map(function($tool) {
      unset($tool['app']);
      return $tool;
    }, \mcp\allowed_tools($connector)))];

    break;

  case 'tools/call':
    $name = @$rpc_params['name'];
    $args = @$rpc_params['arguments'] ?: [];

    if(!is_string($name))
      throw new MCPError("Tool name is required.", -32602, id: $id);

    if(!isset(\mcp\allowed_tools($connector)[$name]))
      throw new ToolError("Tool is not available.", id: $id);

    try {
      switch($name) {
        case 'search_notes':
          [$query, $limit, $offset] = \mcp\page_arguments($args);

          $data = array_map(fn($row) => \mcp\note($row),
            \store\list_notes_paginated($query, $limit, offset: $offset));

          break;

        case 'get_note':
          \mcp\validate_arguments($args, ['id']);

          $item_id = array_require_string($args, 'id');
          $row = \store\get_note($item_id) or throw new ToolError("Note not found.", id: $id);
          $data = \mcp\note($row, true);
          break;

        case 'search_todos':
          [$query, $limit, $offset] = \mcp\page_arguments($args);

          $tasks = \store\list_tasks($query);
          [$tags, $terms] = \core\parse_query($query);

          $tasks = array_values(array_filter($tasks, function($task) use ($tags, $terms) {
            $ids = array_column($task['tags'], 'id');
            return (!$tags || !array_diff($tags, $ids))
              && str_contains_terms("{$task['title']} {$task['content']}", $terms);
          }));

          $data = array_map(fn($row) => \mcp\todo($row), array_slice($tasks, $offset, $limit));
          break;

        case 'get_todo':
          \mcp\validate_arguments($args, ['id']);

          $item_id = array_require_string($args, 'id');
          $row = \store\get_task($item_id) or throw new ToolError("Todo not found.", id: $id);
          $data = \mcp\todo($row, true);
          break;

        case 'search_bookmarks':
          [$query, $limit, $offset] = \mcp\page_arguments($args);

          $data = array_map(fn($row) => \mcp\bookmark($row),
            \store\list_bookmarks_paginated($query, $limit, offset: $offset));
          break;

        case 'get_bookmark':
          \mcp\validate_arguments($args, ['id']);

          $item_id = array_require_string($args, 'id');
          $row = \store\get_bookmark($item_id) or throw new ToolError("Bookmark not found.", id: $id);
          $data = \mcp\bookmark($row, true);
          break;

        case 'list_appointments':
          \mcp\validate_arguments($args, ['from', 'to']);

          $from = cast_dt_iso(array_require_string($args, 'from'))
            or throw new InvalidArgumentException("Argument 'from' must be an ISO 8601 date or datetime.");
          $to = cast_dt_iso(array_require_string($args, 'to'))
            or throw new InvalidArgumentException("Argument 'to' must be an ISO 8601 date or datetime.");

          if($to <= $from)
            throw new ToolError("Argument 'to' must be after 'from'.", id: $id);
          if($to > $from->modify('+366 days'))
            throw new ToolError("Calendar ranges cannot exceed 366 days.", id: $id);

          $events = \calendar\appointments($from, $to);
          \calendar\chronological($events);

          $data = array_map(fn($event) => \mcp\appointment($event), $events);
          break;

        case 'get_appointment':
          \mcp\validate_arguments($args, ['id']);

          $item_id = array_require_string($args, 'id');
          $row = \store\get_appointment_by_humid($item_id)
            or throw new ToolError("Appointment not found.", id: $id);

          $row['starts_at'] = \calendar\wall($row['starts_at']);
          $row['ends_at'] = \calendar\wall($row['ends_at']);

          $data = \mcp\appointment($row, !!$row['recurrence']);
          break;

        default:
          throw new ToolError("Tool is not available.", id: $id);
      }
    }
    catch(InvalidArgumentException $error) {
      throw new ToolError($error->getMessage(), id: $id, previous: $error);
    }

    $result = [
      'content' => [['type' => 'text', 'text' => json_encode($data, $flags)]],
      'structuredContent' => ['data' => $data],
    ];

    break;

  default:
    throw new MCPError("Method not found.", -32601, id: $id);
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], $flags);
