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
}
