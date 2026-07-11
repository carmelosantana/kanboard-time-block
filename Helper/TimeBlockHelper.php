<?php

namespace Kanboard\Plugin\TimeBlock\Helper;

use Kanboard\Core\Base;

/**
 * Template-facing helper for TimeBlock.
 *
 * Exposes the current block and human-readable formatting to board/task
 * templates via $this->timeBlock->... (registered in Plugin::initialize()).
 *
 * @package Kanboard\Plugin\TimeBlock\Helper
 */
class TimeBlockHelper extends Base
{
    /** Current block for a task, or null. */
    public function get(int $taskId): ?array
    {
        return $this->timeBlockModel->get($taskId);
    }

    /** Full range label, e.g. "Tue 14 Jul, 14:00–16:00" (or spanning days). */
    public function formatRange(array $block): string
    {
        $sameDay = date('Y-m-d', $block['start']) === date('Y-m-d', $block['end']);
        $start   = date('D j M, H:i', $block['start']);
        $end     = $sameDay ? date('H:i', $block['end']) : date('D j M, H:i', $block['end']);

        return $start . '–' . $end;
    }

    /** Compact badge label, e.g. "Tue 14:00". */
    public function formatBadge(array $block): string
    {
        return date('D H:i', $block['start']);
    }
}
