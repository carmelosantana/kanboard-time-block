<?php

namespace Kanboard\Plugin\TimeBlock\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * TimeBlock controller.
 *
 * save()  — parse a date + start/end time into two unix timestamps and persist
 *           the block. Write-ACL + CSRF gated.
 * clear() — remove the task's block. Write-ACL + CSRF gated.
 *
 * @package Kanboard\Plugin\TimeBlock\Controller
 * @author  Carmelo Santana
 */
class TimeBlockController extends BaseController
{
    public function save(): void
    {
        $task = $this->getTask();

        if (! $this->helper->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id'])) {
            throw new AccessForbiddenException();
        }

        $this->checkCSRFForm();

        $values = $this->request->getValues();
        $parsed = self::parseTimes(
            isset($values['date'])       ? (string) $values['date']       : '',
            isset($values['start_time']) ? (string) $values['start_time'] : '',
            isset($values['end_time'])   ? (string) $values['end_time']   : ''
        );

        $taskId = (int) $task['id'];

        if ($parsed === null) {
            $this->flash->failure(t('Invalid time block: fill every field and make the end time later than the start time.'));
        } elseif ($this->timeBlockModel->set($taskId, $parsed['start'], $parsed['end'])) {
            $this->flash->success(t('Time block saved.'));
        } else {
            $this->flash->failure(t('Unable to save the time block.'));
        }

        $this->redirectToTask($taskId, (int) $task['project_id']);
    }

    public function clear(): void
    {
        $task = $this->getTask();

        if (! $this->helper->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id'])) {
            throw new AccessForbiddenException();
        }

        $this->checkCSRFForm();

        $taskId = (int) $task['id'];
        $this->timeBlockModel->clear($taskId);
        $this->flash->success(t('Time block cleared.'));

        $this->redirectToTask($taskId, (int) $task['project_id']);
    }

    /**
     * Compose a date + start time + end time into two unix timestamps.
     * Returns null when any field is empty or end is not strictly after start.
     *
     * Static + HTTP-free so it is unit-testable without a request.
     */
    public static function parseTimes(string $date, string $start, string $end): ?array
    {
        $date  = trim($date);
        $start = trim($start);
        $end   = trim($end);

        if ($date === '' || $start === '' || $end === '') {
            return null;
        }

        $startTs = strtotime($date . ' ' . $start);
        $endTs   = strtotime($date . ' ' . $end);

        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            return null;
        }

        return array('start' => $startTs, 'end' => $endTs);
    }

    private function redirectToTask(int $taskId, int $projectId): void
    {
        $this->response->redirect($this->helper->url->to('TaskViewController', 'show', array(
            'task_id'    => $taskId,
            'project_id' => $projectId,
        )));
    }
}
