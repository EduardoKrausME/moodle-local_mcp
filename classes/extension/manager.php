<?php
namespace local_mcp\extension;

defined('MOODLE_INTERNAL') || die;

final class manager {
    /** @return read_provider_interface[] */
    public static function read_providers(): array {
        return self::providers('mcp_read_provider', read_provider_interface::class);
    }

    /** @return write_provider_interface[] */
    public static function write_providers(): array {
        return self::providers('mcp_write_provider', write_provider_interface::class);
    }

    private static function providers(string $callback, string $interface): array {
        $providers = [];
        $callbacks = get_plugins_with_function($callback, 'lib.php');
        foreach ($callbacks as $component => $functions) {
            foreach ($functions as $function) {
                $provider = $function();
                if (!$provider instanceof $interface) {
                    throw new \coding_exception($component . ' returned an invalid MCP provider.');
                }
                $providers[] = $provider;
            }
        }
        return $providers;
    }
}
