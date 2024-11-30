<?php
namespace Cloudflared;

$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/cloudflared/include/Logger.php";

try {
    $logger = Logger::getInstance();
    $logger->clear();
    echo "Logs cleared successfully";
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error clearing logs: " . htmlspecialchars($e->getMessage());
} 