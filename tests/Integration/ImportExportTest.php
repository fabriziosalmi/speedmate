<?php

declare(strict_types=1);

namespace SpeedMate\Tests\Integration;

use SpeedMate\Admin\ImportExport;
use SpeedMate\Utils\Settings;
use WP_UnitTestCase;

final class ImportExportTest extends WP_UnitTestCase
{
    private ImportExport $importer;

    public function setUp(): void
    {
        parent::setUp();
        $this->importer = ImportExport::instance();
    }

    public function test_export_includes_all_required_fields(): void
    {
        update_option(SPEEDMATE_OPTION_KEY, [
            'mode' => 'safe',
            'cache_ttl' => 3600,
        ]);
        Settings::refresh();

        $export = $this->importer->export();
        
        $this->assertIsArray($export);
        $this->assertArrayHasKey('version', $export);
        $this->assertArrayHasKey('settings', $export);
        $this->assertArrayHasKey('timestamp', $export);
        $this->assertArrayHasKey('site_url', $export);
    }

    public function test_export_settings_match_current(): void
    {
        $test_settings = [
            'mode' => 'beast',
            'cache_ttl' => 7200,
            'warmer_enabled' => false,
        ];
        
        update_option(SPEEDMATE_OPTION_KEY, $test_settings);
        Settings::refresh();

        $export = $this->importer->export();
        
        $this->assertEquals('beast', $export['settings']['mode']);
        $this->assertEquals(7200, $export['settings']['cache_ttl']);
    }

    public function test_import_validation_requires_fields(): void
    {
        // Missing version
        $invalid_data = [
            'settings' => [],
            'timestamp' => time(),
        ];
        
        $result = $this->importer->import($invalid_data);
        $this->assertFalse($result);

        // Valid data
        $valid_data = [
            'version' => '0.3.0',
            'settings' => ['mode' => 'safe'],
            'timestamp' => time(),
        ];
        
        $result = $this->importer->import($valid_data);
        $this->assertTrue($result);
    }

    public function test_import_updates_settings(): void
    {
        $import_data = [
            'version' => '0.3.0',
            'settings' => [
                'mode' => 'beast',
                'cache_ttl' => 9999,
            ],
            'timestamp' => time(),
        ];
        
        $this->importer->import($import_data);
        Settings::refresh();
        
        $settings = Settings::get();
        $this->assertEquals('beast', $settings['mode']);
        $this->assertEquals(9999, $settings['cache_ttl']);
    }

    public function test_import_rejects_old_versions(): void
    {
        $old_data = [
            'version' => '0.0.1',
            'settings' => ['mode' => 'safe'],
            'timestamp' => time(),
        ];
        
        $result = $this->importer->import($old_data);
        $this->assertFalse($result);
    }
}
