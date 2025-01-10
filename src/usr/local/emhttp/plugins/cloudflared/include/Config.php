<?php
namespace Cloudflared;

class Config {
    private static $instance = null;
    private $config = [];
    private $configFile;

    private function __construct() {
        $this->configFile = "/boot/config/plugins/cloudflared/config/cloudflared.cfg";
        $this->loadDefaults();
        $this->loadConfig();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadDefaults() {
        $this->config = [
            'SERVICE' => 'disabled',
            'TUNNEL_TOKEN' => '',
            'TUNNEL_RETRIES' => '5',
            'TUNNEL_REGION' => '',
            'TUNNEL_TRANSPORT_PROTOCOL' => 'auto',
            'TUNNEL_EDGE_IP_VERSION' => 'auto',
            'TUNNEL_GRACE_PERIOD' => '30s',
            'TUNNEL_METRICS' => '0.0.0.0:46495',
            'TUNNEL_LOGLEVEL' => 'info'
        ];
    }

    private function loadConfig() {
        if (file_exists($this->configFile)) {
            $loadedConfig = parse_ini_file($this->configFile);
            if ($loadedConfig) {
                $this->config = array_merge($this->config, $loadedConfig);
            }
        }
    }

    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function set($key, $value) {
        $this->config[$key] = $value;
    }

    public function save() {
        $content = '';
        foreach ($this->config as $key => $value) {
            $content .= "{$key}=\"{$value}\"\n";
        }
        return file_put_contents($this->configFile, $content);
    }

    public function getAll() {
        return $this->config;
    }

    public function getSettingsFields() {
        return [
            [
                'name' => 'TUNNEL_TOKEN',
                'label' => 'Tunnel Token',
                'type' => 'password',
                'help' => 'Required for tunnel authentication. Get this from your Cloudflare Zero Trust dashboard.',
                'placeholder' => 'Your Cloudflare tunnel token'
            ],
            [
                'name' => 'TUNNEL_RETRIES',
                'label' => 'Retry Count',
                'type' => 'number',
                'help' => 'Number of connection retry attempts.',
                'min' => '1',
                'max' => '10'
            ],
            [
                'name' => 'TUNNEL_REGION',
                'label' => 'Region',
                'type' => 'select',
                'options' => ['us' => 'United States', '' => 'Global'],
                'help' => 'Region to connect to. Only US is supported at this time. Omit to use the global network.',
                'default' => 'us'
            ],
            [
                'name' => 'TUNNEL_TRANSPORT_PROTOCOL',
                'label' => 'Transport Protocol',
                'type' => 'select',
                'options' => ['auto' => 'Auto', 'http2' => 'HTTP/2', 'quic' => 'QUIC']
            ],
            [
                'name' => 'TUNNEL_EDGE_IP_VERSION',
                'label' => 'Edge IP Version',
                'type' => 'select',
                'options' => ['auto' => 'Auto', '4' => 'IPv4', '6' => 'IPv6']
            ],
            [
                'name' => 'TUNNEL_GRACE_PERIOD',
                'label' => 'Grace Period',
                'type' => 'text',
                'help' => 'Time to wait before retrying a connection.'
            ],
            [
                'name' => 'TUNNEL_METRICS',
                'label' => 'Metrics',
                'type' => 'text',
                'help' => 'Metrics endpoint for the tunnel.',
                'placeholder' => '0.0.0.0:46495'
            ],
            [
                'name' => 'TUNNEL_LOGLEVEL',
                'label' => 'Log Level',
                'type' => 'select',
                'options' => ['info' => 'Info', 'warn' => 'Warn', 'error' => 'Error', 'debug' => 'Debug', 'fatal' => 'Fatal']
            ]
        ];
    }
}
?>
