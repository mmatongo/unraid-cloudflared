<?php
namespace Cloudflared;

header('Content-Type: application/json');

$script = '/usr/local/emhttp/plugins/cloudflared/scripts/cf-update.sh';
$settingsFile = '/boot/config/plugins/cloudflared/config/update.cfg';
$dest = '/boot/config/plugins/cloudflared';

function installedVersion() {
    $out = trim((string)shell_exec('/usr/local/sbin/cloudflared --version 2>/dev/null'));
    return preg_match('/version\s+(\S+)/', $out, $m) ? $m[1] : '';
}

function settings($file) {
    $s = ['UPDATE_CHECK' => 'never', 'UPDATE_AUTO' => 'no'];
    if (file_exists($file)) {
        $s = array_merge($s, parse_ini_file($file));
    }
    return $s;
}

function staged($dest) {
    $versions = [];
    foreach (glob("$dest/cloudflared-[0-9]*") as $f) {
        $versions[] = substr(basename($f), strlen('cloudflared-'));
    }
    usort($versions, 'version_compare');
    return $versions;
}

function runScript($script, $args) {
    $cmd = escapeshellarg($script) . ' ' . $args . ' 2>&1';
    exec($cmd, $lines, $rc);
    return [$rc, implode("\n", $lines)];
}

try {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'status':
            echo json_encode(['success' => true, 'installed' => installedVersion(), 'staged' => staged($dest)] + settings($settingsFile));
            break;

        case 'check':
            list($rc, $out) = runScript($script, 'check');
            if ($rc !== 0 || !preg_match('/latest:\s*(\S+)/', $out, $m)) {
                throw new \Exception('Could not reach the release server');
            }
            $latest = $m[1];
            $installed = installedVersion();
            echo json_encode(['success' => true, 'installed' => $installed, 'latest' => $latest,
                'newer' => version_compare($latest, $installed, '>')]);
            break;

        case 'install':
            $version = $_POST['version'] ?? '';
            if (!preg_match('/^[0-9]+(\.[0-9]+)*$/', $version)) {
                throw new \Exception('Invalid version');
            }
            list($rc, $out) = runScript($script, 'stage ' . escapeshellarg($version));
            if ($rc !== 0) {
                throw new \Exception("Download or verification failed:\n$out");
            }
            list($rc, $out2) = runScript($script, 'apply ' . escapeshellarg($version));
            echo json_encode(['success' => $rc === 0, 'message' => $out . "\n" . $out2, 'installed' => installedVersion()]);
            break;

        case 'save':
            $check = $_POST['UPDATE_CHECK'] ?? 'never';
            $auto = $_POST['UPDATE_AUTO'] ?? 'no';
            if (!in_array($check, ['never', 'boot', 'daily', 'weekly'], true) || !in_array($auto, ['no', 'yes'], true)) {
                throw new \Exception('Invalid settings');
            }
            file_put_contents($settingsFile, "UPDATE_CHECK=\"$check\"\nUPDATE_AUTO=\"$auto\"\n");
            runScript($script, 'cron');
            echo json_encode(['success' => true]);
            break;

        default:
            throw new \Exception('Invalid action specified');
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
