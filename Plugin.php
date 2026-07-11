<?php

namespace Kanboard\Plugin\TimeBlock;

use Kanboard\Core\Plugin\Base;
use Kanboard\Plugin\TimeBlock\Helper\TimeBlockHelper;
use Kanboard\Plugin\TimeBlock\Model\TimeBlockModel;

class Plugin extends Base
{
    public function initialize()
    {
        // Model service — resolved lazily by the controller, helper, and the
        // calendar source closure below.
        $this->container['timeBlockModel'] = function ($c) {
            return new TimeBlockModel($c);
        };

        // Template helper: exposes get()/formatRange()/formatBadge() to templates.
        $this->helper->register('timeBlock', TimeBlockHelper::class);

        // Board card badge: small time-block chip before each card title.
        $this->hook->on('template:board:private:task:before-title', array('template' => 'TimeBlock:board/badge'));

        // Task page panel: set/edit/clear the block.
        $this->hook->on('template:task:show:before-internal-links', array('template' => 'TimeBlock:task/panel'));

        // Inject ~1KB timeblock.css sitewide (same rationale as DependencyPlugin,
        // Plugin.php:41): too small to justify a route allowlist, and the badge
        // renders on board/search pages, not just the task view.
        $this->hook->on('template:layout:css', array('template' => 'plugins/TimeBlock/Assets/css/timeblock.css'));

        // Calendar integration: contribute time-block events to CalendarPlugin's
        // generic `calendarEventSources` extension point (spec §1, CalendarPlugin
        // >= 1.2.0). Append rather than overwrite, since other suite plugins may
        // also register sources. The closure captures $this so `timeBlockModel`
        // resolves lazily, whenever CalendarQueryModel::getEvents() invokes it.
        $this->container['calendarEventSources'] = array_merge(
            isset($this->container['calendarEventSources']) ? $this->container['calendarEventSources'] : array(),
            array(function (int $userId, array $filters, int $rangeStart, int $rangeEnd): array {
                return $this->timeBlockModel->blocksForCalendar($userId, $filters, $rangeStart, $rangeEnd);
            })
        );
    }

    public function getPluginName()        { return 'TimeBlock'; }
    public function getPluginAuthor()      { return 'Carmelo Santana'; }
    public function getPluginVersion()     { return '1.0.0'; }
    public function getPluginDescription() { return 'Give a task one planned start+end time block — shown on the task page and board, and contributed to CalendarPlugin\'s week/day views.'; }
    public function getPluginHomepage()    { return 'https://github.com/carmelosantana/kanboard-time-block'; }
    public function getPluginLicense()     { return 'MIT'; }
    public function getCompatibleVersion() { return '>=1.2.47'; }
}
