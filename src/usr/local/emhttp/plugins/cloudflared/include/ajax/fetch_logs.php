<?php
namespace Cloudflared;

$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/cloudflared/include/Logger.php";

try {
    $logger = Logger::getInstance();
    echo $logger->getFormattedLogs();
} catch (\Exception $e) {
    http_response_code(500);
    echo "<div class='log-line error'>Error fetching logs: " . htmlspecialchars($e->getMessage()) . "</div>";
}
