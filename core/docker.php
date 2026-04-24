<?php
// Interface with Docker CLI.

namespace docker;

function docker(...$args) {
  $binary = escapeshellcmd(APP['binary']);
  $socket = escapeshellarg(APP['socket']);
  $subcmd = implode(' ', array_map('escapeshellarg', $args));
  $cmd = "$binary --host=unix://$socket $subcmd 2>&1";

  exec($cmd, $output, $exit); // Probably not the safest method.

  return ['output' => array_filter($output), 'exit' => $exit];
}

function version_info() {
  $cache = '/tmp/__docker_info__.json';
  $ttl = 3600; // Cache for an hour.

  if (file_exists($cache) && (time() - filemtime($cache)) < $ttl)
    return json_decode(file_get_contents($cache), true);

  $result = docker("info", "--format", "{{json .}}");
  $data = json_decode($result['output'][0], true);
  file_put_contents($cache, json_encode($data));

  return $data;
}

function list_containers() {
  $result = docker("ps", "-a", "--format", "{{json .}}");
  $containers = [];

  foreach ($result['output'] as $line)
    $containers[] = json_decode($line, true);

  return $containers;
}

function usage_stats() {
    $cache = '/tmp/__docker_stats__.json';
    $ttl = 10; // Cache for 10 seconds.

    if (file_exists($cache) && (time() - filemtime($cache)) < $ttl)
      return json_decode(file_get_contents($cache), true);

    $result = docker("stats", "--no-stream", "--format", "{{json .}}");
    $containers = [];
    
    foreach ($result['output'] as $line) {
        $data = json_decode($line, true);

        // Remove '%' and 'MiB', convert to float
        $cpu = isset($data['CPUPerc']) ? (float) rtrim($data['CPUPerc'], '%') : 0;
        $mem = isset($data['MemUsage']) ? explode('/', $data['MemUsage'])[0] : "0MiB";

        preg_match('/([\d.]+) ?([KMG]i?B)/', $mem, $m);
        $mem_value = isset($m[1], $m[2]) ? (float)$m[1] * match($m[2]) {
            'KiB' => 1024,
            'MiB' => 1024*1024,
            'GiB' => 1024*1024*1024,
            default => 1
        } : 0;

        $containers[] = [
            'name' => $data['Name'],
            'cpu' => $cpu,
            'mem' => $mem_value,
        ];
    }

    usort($containers, fn($a,$b) => $b['cpu'] <=> $a['cpu']);
    $top_cpu = array_slice($containers, 0, 3);
    $others_cpu = array_sum(array_column(array_slice($containers, 3), 'cpu'));

    usort($containers, fn($a,$b) => $b['mem'] <=> $a['mem']);
    $top_mem = array_slice($containers, 0, 3);
    $others_mem = array_sum(array_column(array_slice($containers, 3), 'mem'));

    $data = [
      'cpu' => ['top' => $top_cpu, 'others' => $others_cpu],
      'mem' => ['top' => $top_mem, 'others' => $others_mem],
    ];

    file_put_contents($cache, json_encode($data));

    return $data;
}

function start($id) {
  return docker("start", $id);
}

function stop($id) {
  return docker("stop", $id);
}

function restart($id) {
  return docker("restart", $id);
}

function reload($id) {
  return docker("exec", "caddy", "reload", "-c", APP['caddy']['config']);
}
