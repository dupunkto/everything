<?php
// Lightweight structured logger.

namespace logger;

function debug($message, $context = [])  { write('debug', $message, $context); }
function info($message, $context = [])  { write('info', $message, $context); }
function warn($message, $context = [])  { write('warn', $message, $context); }
function error($message, $context = []) { write('error', $message, $context); }

function write($level, $message, $context = []) {
  \store\put_system_log($level, $message, $context);
}
