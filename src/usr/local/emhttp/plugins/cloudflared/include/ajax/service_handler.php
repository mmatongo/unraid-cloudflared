<?php
namespace Cloudflared;

header('Content-Type: application/json');

$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/cloudflared/include/ServiceManager.php";
require_once "$docroot/plugins/cloudflared/include/Config.php";
require_once "$docroot/plugins/cloudflared/include/Logger.php";

try {
    $action = $_POST['action'] ?? '';

    $serviceManager = new ServiceManager();

    switch ($action) {
        case 'apply':
            if (!isset($_POST['config']) || !is_array($_POST['config'])) {
                throw new \Exception('Invalid configuration data');
            }

            $result = $serviceManager->applyConfig($_POST['config']);
            echo json_encode($result);
            break;

        case 'start':
            $result = $serviceManager->start();
            echo json_encode($result);
            break;

        case 'stop':
            $result = $serviceManager->stop();
            echo json_encode($result);
            break;

        case 'restart':
            $result = $serviceManager->restart();
            echo json_encode($result);
            break;

        case 'status':
            $result = [
                'success' => true,
                'running' => $serviceManager->isRunning(),
            ];
            echo json_encode($result);
            break;

        default:
            throw new \Exception('Invalid action specified');
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
