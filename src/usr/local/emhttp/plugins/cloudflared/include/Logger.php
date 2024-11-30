<?php
namespace Cloudflared;

class Logger {
    private static $instance = null;
    private $logFile;
    private $maxLogSize = 5242880;

    private function __construct() {
        $this->logFile = '/var/log/cloudflared/cloudflared.log';
        $this->ensureLogDirectory();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
            chown($logDir, 'nobody');
            chgrp($logDir, 'users');
        }

        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
            chown($this->logFile, 'nobody');
            chgrp($this->logFile, 'users');
        }
    }

    private function rotate() {
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $backupFile = $this->logFile . '.' . date('Y-m-d-H-i-s');
            rename($this->logFile, $backupFile);
            chmod($backupFile, 0644);
            chown($backupFile, 'nobody');
            chgrp($backupFile, 'users');
            $this->ensureLogDirectory();

            // Keep only last 5 backup files
            $backups = glob($this->logFile . '.*');
            rsort($backups);
            foreach (array_slice($backups, 5) as $oldBackup) {
                unlink($oldBackup);
            }
        }
    }

    public function log($message, $level = 'INFO') {
        $this->rotate();

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function getLines($count = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = array_reverse(file($this->logFile));
        return array_slice($lines, 0, $count);
    }

    public function clear() {
        file_put_contents($this->logFile, '');
        $this->log('Log cleared', 'INFO');
    }

    public function getLogPath() {
        return $this->logFile;
    }

    public function getFormattedLogs() {
        $output = '';
        $logs = $this->getLines();
        foreach ($logs as $line) {
            $output .= "<div class='log-line'>" . htmlspecialchars($line) . "</div>";
        }
        return $output;
    }
}
?>
