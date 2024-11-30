<?php
namespace Cloudflared;

class ServiceManager {
    private $config;
    private $serviceScript;
    private $logger;

    public function __construct() {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance();
        $this->serviceScript = '/usr/local/emhttp/plugins/cloudflared/scripts/restart.sh';

        $logDir = dirname($this->logger->getLogPath());
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
            chown($logDir, 'nobody');
            chgrp($logDir, 'users');
        }
    }

    public function isRunning() {
        $result = $this->execute($this->serviceScript . ' status');
        return trim($result['output']) === 'running';
    }

    public function start() {
        if ($this->isRunning()) {
            $this->logger->log('Attempted to start already running service', 'WARN');
            return ['success' => false, 'message' => 'Service is already running'];
        }

        $this->logger->log('Starting service...', 'INFO');
        $result = $this->execute($this->serviceScript . ' start');

        if ($result['success']) {
            $this->logger->log('Service started successfully', 'INFO');
        } else {
            $this->logger->log('Failed to start service: ' . $result['output'], 'ERROR');
        }

        return $result;
    }

    public function stop() {
        if (!$this->isRunning()) {
            $this->logger->log('Attempted to stop already stopped service', 'WARN');
            return ['success' => false, 'message' => 'Service is not running'];
        }

        $this->logger->log('Stopping service...', 'INFO');
        $result = $this->execute($this->serviceScript . ' stop');

        if ($result['success']) {
            $this->logger->log('Service stopped successfully', 'INFO');
        } else {
            $this->logger->log('Failed to stop service: ' . $result['output'], 'ERROR');
        }

        return $result;
    }

    public function restart() {
        $this->logger->log('Restarting service...', 'INFO');
        $result = $this->execute($this->serviceScript . ' restart');

        if ($result['success']) {
            $this->logger->log('Service restarted successfully', 'INFO');
        } else {
            $this->logger->log('Failed to restart service: ' . $result['output'], 'ERROR');
        }

        return $result;
    }

    public function applyConfig($newConfig) {
        $this->logger->log('Applying new configuration...', 'INFO');

        $wasRunning = $this->isRunning();
        $wasEnabled = $this->config->get('SERVICE') === 'enabled';
        $willBeEnabled = $newConfig['SERVICE'] === 'enabled';

        // Save new configuration
        foreach ($newConfig as $key => $value) {
            $this->config->set($key, $value);
        }

        if (!$this->config->save()) {
            $this->logger->log('Failed to save configuration', 'ERROR');
            return ['success' => false, 'message' => 'Failed to save configuration'];
        }

        if (isset($newConfig['SERVICE'])) {
            if (!$wasEnabled && $willBeEnabled) {
                $this->logger->log('Service newly enabled, starting...', 'INFO');
                return $this->start();
            } elseif ($wasEnabled && !$willBeEnabled) {
                $this->logger->log('Service disabled, stopping...', 'INFO');
                return $this->stop();
            } elseif ($willBeEnabled && $wasEnabled) {
                $this->logger->log('Restarting service to apply changes...', 'INFO');
                return $this->restart();
            }
        }

        return ['success' => true, 'message' => 'Configuration updated'];
    }

    public function getTunnelInfo() {
        if ($this->isRunning()) {
            return shell_exec("cloudflared tunnel info 2>/dev/null");
        }
        return "";
    }

    private function execute($command) {
        exec($command . ' 2>&1', $output, $returnCode);
        $output = implode("\n", $output);

        return [
            'success' => $returnCode === 0,
            'output' => $output,
            'code' => $returnCode
        ];
    }
}
?>
