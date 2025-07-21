<?php
declare(strict_types=1);

namespace platform;

/**
 * Class AIChatPageComponentConfig
 * Uses configuration from AIChat plugin
 */
class AIChatPageComponentConfig
{
    private static array $config = [];
    private static array $updated = [];

    /**
     * Load the plugin configuration from AIChat
     * @return void
     * @throws AIChatPageComponentException
     */
    public static function load(): void
    {
        global $DIC;
        
        if (!isset($DIC) || !$DIC->database()) {
            throw new AIChatPageComponentException("ILIAS DIC not available or database not initialized");
        }
        
        $db = $DIC->database();
        
        try {
            // Check if xaic_config table exists
            $tables = $db->listTables();
            if (!in_array('xaic_config', $tables)) {
                throw new AIChatPageComponentException("AIChat configuration table 'xaic_config' not found. Please ensure AIChat plugin is properly installed.");
            }
            
            $result = $db->query("SELECT * FROM xaic_config");
            
            while ($row = $db->fetchAssoc($result)) {
                if (isset($row['value']) && $row['value'] !== '') {
                    $json_decoded = json_decode($row['value'], true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row['value'] = $json_decoded;
                    }
                }

                self::$config[$row['name']] = $row['value'];
            }
            
            error_log('AIChatPageComponent: Loaded ' . count(self::$config) . ' configuration items from AIChat');
        } catch (\Exception $e) {
            throw new AIChatPageComponentException("Failed to load AIChat configuration: " . $e->getMessage());
        }
    }

    /**
     * Gets the plugin configuration value for a given key from AIChat
     * @param string $key
     * @return mixed|string
     * @throws AIChatPageComponentException
     */
    public static function get(string $key)
    {
        if (empty(self::$config)) {
            self::load();
        }
        
        return self::$config[$key] ?? self::getFromDB($key);
    }

    /**
     * Gets the plugin configuration value for a given key from the database
     * @param string $key
     * @return mixed|string
     * @throws AIChatPageComponentException
     */
    public static function getFromDB(string $key)
    {
        global $DIC;
        
        if (!isset($DIC) || !$DIC->database()) {
            throw new AIChatPageComponentException("ILIAS DIC not available or database not initialized");
        }
        
        $db = $DIC->database();
        
        try {
            $result = $db->query("SELECT * FROM xaic_config WHERE name = " . $db->quote($key, 'text'));
            
            if ($row = $db->fetchAssoc($result)) {
                if (isset($row['value']) && $row['value'] !== '') {
                    $json_decoded = json_decode($row['value'], true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row['value'] = $json_decoded;
                    }
                }

                self::$config[$key] = $row['value'];
                return $row['value'];
            }
            
            return "";
        } catch (\Exception $e) {
            throw new AIChatPageComponentException("Failed to get configuration from database: " . $e->getMessage());
        }
    }

    /**
     * Gets all the plugin configuration values
     * @return array
     */
    public static function getAll(): array
    {
        if (empty(self::$config)) {
            self::load();
        }
        
        return self::$config;
    }
}