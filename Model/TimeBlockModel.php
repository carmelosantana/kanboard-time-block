<?php

namespace Kanboard\Plugin\TimeBlock\Model;

use Kanboard\Core\Base;

/**
 * TimeBlock model.
 *
 * Stores one planned start+end datetime per task as task metadata — distinct
 * from date_due (deadline) and date_started (actual start). Timestamps are
 * unix seconds, persisted as strings via core TaskMetadataModel.
 *
 * @package Kanboard\Plugin\TimeBlock\Model
 */
class TimeBlockModel extends Base
{
    const KEY_START = 'timeblock_start';
    const KEY_END   = 'timeblock_end';

    /**
     * Return the task's block as ['start'=>int, 'end'=>int], or null if unset.
     */
    public function get(int $taskId): ?array
    {
        $start = $this->taskMetadataModel->get($taskId, self::KEY_START, '');
        $end   = $this->taskMetadataModel->get($taskId, self::KEY_END, '');

        if ($start === '' || $end === '') {
            return null;
        }

        return array('start' => (int) $start, 'end' => (int) $end);
    }

    /**
     * Persist a block. Rejects (returns false) when end is not strictly after start.
     */
    public function set(int $taskId, int $start, int $end): bool
    {
        if ($end <= $start) {
            return false;
        }

        return $this->taskMetadataModel->save($taskId, array(
            self::KEY_START => (string) $start,
            self::KEY_END   => (string) $end,
        ));
    }

    /**
     * Remove both keys. Returns true when no block remains (idempotent).
     */
    public function clear(int $taskId): bool
    {
        if ($this->taskMetadataModel->exists($taskId, self::KEY_START)) {
            $this->taskMetadataModel->remove($taskId, self::KEY_START);
        }
        if ($this->taskMetadataModel->exists($taskId, self::KEY_END)) {
            $this->taskMetadataModel->remove($taskId, self::KEY_END);
        }

        return $this->get($taskId) === null;
    }
}
