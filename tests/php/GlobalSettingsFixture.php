<?php

/** Restores test-owned globals, including whether a setting was absent. */
class GlobalSettingsFixture
{
    // Every global the SCGI transport, its two doors and their logging read.
    public const SCGI = ['scgi_host', 'scgi_port', 'rpcTimeOut', 'rpcTransferTimeOut',
        'rpcMaxResponseBytes', 'rpcLogCalls', 'rpcLogFaults', 'log_file', 'profileMask',
        'tempDirectory', 'tempDirectory_init_done'];

    private $settings = [];

    public function __construct($names)
    {
        foreach ($names as $name) {
            $this->settings[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
        }
    }

    public static function scgi()
    {
        return new self(self::SCGI);
    }

    public function restore()
    {
        foreach ($this->settings as $name => $setting) {
            if ($setting[0]) {
                $GLOBALS[$name] = $setting[1];
            } else {
                unset($GLOBALS[$name]);
            }
        }
    }
}
