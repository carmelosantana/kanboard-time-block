<?php

namespace Kanboard\Plugin\TimeBlock\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

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

    /**
     * calendarEventSources provider body (spec §1).
     *
     * Returns FullCalendar event objects for every task the user may access
     * whose timeblock_start falls in [$rangeStart, $rangeEnd), honoring the
     * calendar $filters where meaningful. Access control is THIS source's job —
     * CalendarPlugin does not re-check.
     *
     * @param array $filters ['project_ids'=>int[], 'assignee_id'=>int, 'category_id'=>int, 'column_id'=>int, 'hide_completed'=>bool]
     */
    public function blocksForCalendar(int $userId, array $filters, int $rangeStart, int $rangeEnd): array
    {
        $projectIds = $this->accessibleProjectIds($userId);

        if (! empty($filters['project_ids'])) {
            $requested  = array_map('intval', (array) $filters['project_ids']);
            $projectIds = array_values(array_intersect($projectIds, $requested));
        }

        if (empty($projectIds)) {
            return array();
        }

        // Start-metadata rows in range, joined to their task for title/color/project.
        // Unix timestamps are 10 digits through year 2286, so a same-length string
        // compare on the stored value is order-preserving; range endpoints are cast
        // to string to match the stored representation.
        $rows = $this->db->table('task_has_metadata')
            ->columns(
                'task_has_metadata.task_id',
                'task_has_metadata.value AS start_ts',
                TaskModel::TABLE . '.title',
                TaskModel::TABLE . '.project_id',
                TaskModel::TABLE . '.color_id',
                TaskModel::TABLE . '.owner_id',
                TaskModel::TABLE . '.category_id',
                TaskModel::TABLE . '.column_id',
                TaskModel::TABLE . '.is_active'
            )
            ->join(TaskModel::TABLE, 'id', 'task_id', 'task_has_metadata')
            ->eq('task_has_metadata.name', self::KEY_START)
            ->in(TaskModel::TABLE . '.project_id', $projectIds)
            ->gte('task_has_metadata.value', (string) $rangeStart)
            ->lt('task_has_metadata.value', (string) $rangeEnd)
            ->findAll();

        if (empty($rows)) {
            return array();
        }

        // Batch-fetch the matching end values.
        $taskIds = array();
        foreach ($rows as $r) {
            $taskIds[] = (int) $r['task_id'];
        }
        $ends = array();
        foreach ($this->db->table('task_has_metadata')
                     ->columns('task_id', 'value')
                     ->eq('name', self::KEY_END)
                     ->in('task_id', $taskIds)
                     ->findAll() as $er) {
            $ends[(int) $er['task_id']] = (int) $er['value'];
        }

        $events = array();
        foreach ($rows as $r) {
            $taskId = (int) $r['task_id'];

            if (! isset($ends[$taskId])) {
                continue; // start without end ⇒ not a complete block
            }

            if (! empty($filters['assignee_id']) && (int) $r['owner_id'] !== (int) $filters['assignee_id']) {
                continue;
            }
            if (! empty($filters['category_id']) && (int) $r['category_id'] !== (int) $filters['category_id']) {
                continue;
            }
            if (! empty($filters['column_id']) && (int) $r['column_id'] !== (int) $filters['column_id']) {
                continue;
            }
            if (! empty($filters['hide_completed']) && (int) $r['is_active'] !== TaskModel::STATUS_OPEN) {
                continue;
            }

            $start = (int) $r['start_ts'];
            $end   = $ends[$taskId];

            $events[] = array(
                'id'     => 'timeblock-' . $taskId,
                'title'  => $r['title'],
                'start'  => date('c', $start),
                'end'    => date('c', $end),
                'allDay' => false,
                'color'  => $this->colorModel->getBorderColor($r['color_id']),
                'url'    => $this->helper->url->to('TaskViewController', 'show', array(
                    'task_id'    => $taskId,
                    'project_id' => (int) $r['project_id'],
                )),
                'extendedProps' => array(
                    'project' => (string) $r['project_id'],
                    'badges'  => array(),
                ),
            );
        }

        return $events;
    }

    /**
     * Project ids the user may access — mirrors CalendarQueryModel's
     * access filter (role-based active memberships).
     */
    protected function accessibleProjectIds(int $userId): array
    {
        return array_map('intval', $this->projectPermissionModel->getActiveProjectIds($userId));
    }
}
