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

    public function testInitializeRegistersModelAndCalendarSource(): void
    {
        $plugin = new Plugin($this->container);
        $plugin->initialize();

        $this->assertInstanceOf(
            \Kanboard\Plugin\TimeBlock\Model\TimeBlockModel::class,
            $this->container['timeBlockModel']
        );

        $this->assertArrayHasKey('calendarEventSources', $this->container);
        $this->assertIsArray($this->container['calendarEventSources']);
        $this->assertNotEmpty($this->container['calendarEventSources']);
        $this->assertIsCallable($this->container['calendarEventSources'][0]);
    }

    public function testCalendarSourceAppendsAndDoesNotOverwrite(): void
    {
        // Simulate another plugin having already registered a source.
        $sentinel = function (int $u, array $f, int $s, int $e): array { return array(); };
        $this->container['calendarEventSources'] = array($sentinel);

        $plugin = new Plugin($this->container);
        $plugin->initialize();

        $sources = $this->container['calendarEventSources'];
        $this->assertCount(2, $sources);
        $this->assertSame($sentinel, $sources[0]); // pre-existing source preserved
    }

    public function testCalendarSourceReturnsNamespacedEvent(): void
    {
        // A consumer (like CalendarPlugin) would invoke the registered source.
        $plugin = new Plugin($this->container);
        $plugin->initialize();

        $p  = new \Kanboard\Model\ProjectModel($this->container);
        $tc = new \Kanboard\Model\TaskCreationModel($this->container);
        // $addUser=true: ProjectModel::create() sets owner_id but does NOT add a
        // project_has_users row by default (see TimeBlockModelTest::seedTaskInProject),
        // so without this user 1 would not be an accessible project member per
        // ProjectPermissionModel::getActiveProjectIds() and blocksForCalendar()
        // would correctly return zero events.
        $pid = (int) $p->create(array('name' => 'CalSrc'), 1, true);
        $tid = (int) $tc->create(array('project_id' => $pid, 'title' => 'Blocked'));

        $start = 1000000000 + 3600;
        $this->container['timeBlockModel']->set($tid, $start, $start + 3600);

        $source = $this->container['calendarEventSources'][0];
        $events = call_user_func($source, 1, array(), 1000000000, 1000086400);

        $this->assertCount(1, $events);
        $this->assertSame('timeblock-' . $tid, $events[0]['id']);
    }
}
