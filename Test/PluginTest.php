<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\TimeBlock\Plugin;
use KanboardTests\units\Base;

/**
 * Smoke tests for the TimeBlock Plugin.
 *
 * Run from the Kanboard root via the plugin test harness:
 *   ./testing/run-plugin-tests.sh TimeBlock
 */
class PluginTest extends Base
{
    public function testPluginMetadata(): void
    {
        $plugin = new Plugin($this->container);

        $this->assertSame('TimeBlock', $plugin->getPluginName());
        $this->assertSame('1.0.0', $plugin->getPluginVersion());
        $this->assertSame('>=1.2.47', $plugin->getCompatibleVersion());
        $this->assertSame('MIT', $plugin->getPluginLicense());
        $this->assertNotEmpty($plugin->getPluginDescription());
        $this->assertNotEmpty($plugin->getPluginAuthor());
        $this->assertNotEmpty($plugin->getPluginHomepage());
    }

    public function testTemplateAndAssetFilesExist(): void
    {
        $base = dirname(__DIR__);
        $this->assertFileExists($base . '/Template/task/panel.php');
        $this->assertFileExists($base . '/Template/board/badge.php');
        $this->assertFileExists($base . '/Assets/css/timeblock.css');
    }

    public function testHelperFormatsRangeAndBadge(): void
    {
        $helper = new \Kanboard\Plugin\TimeBlock\Helper\TimeBlockHelper($this->container);
        $block  = array('start' => strtotime('2026-07-14 14:00'), 'end' => strtotime('2026-07-14 16:00'));

        $this->assertStringContainsString('14:00', $helper->formatRange($block));
        $this->assertStringContainsString('16:00', $helper->formatRange($block));
        $this->assertStringContainsString('14:00', $helper->formatBadge($block));
    }
}
