<?php

namespace Kanboard\Plugin\TimeBlock;

use Kanboard\Core\Plugin\Base;

class Plugin extends Base
{
    public function initialize()
    {
        // Wired in Task 6: model service, template helper, hooks, calendarEventSources provider.
    }

    public function getPluginName()        { return 'TimeBlock'; }
    public function getPluginAuthor()      { return 'Carmelo Santana'; }
    public function getPluginVersion()     { return '1.0.0'; }
    public function getPluginDescription() { return 'Give a task one planned start+end time block — shown on the task page and board, and contributed to CalendarPlugin\'s week/day views.'; }
    public function getPluginHomepage()    { return 'https://github.com/carmelosantana/kanboard-time-block'; }
    public function getPluginLicense()     { return 'MIT'; }
    public function getCompatibleVersion() { return '>=1.2.47'; }
}
