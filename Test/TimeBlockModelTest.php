<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\TimeBlock\Model\TimeBlockModel;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskCreationModel;
use KanboardTests\units\Base;

class TimeBlockModelTest extends Base
{
    private function seedTask(): int
    {
        $p  = new ProjectModel($this->container);
        $tc = new TaskCreationModel($this->container);
        $pid = $p->create(array('name' => 'TB'), 1); // user 1 becomes a member
        return (int) $tc->create(array('project_id' => $pid, 'title' => 'Task'));
    }

    public function testGetReturnsNullWhenNoBlock(): void
    {
        $model = new TimeBlockModel($this->container);
        $this->assertNull($model->get($this->seedTask()));
    }

    public function testSetThenGetRoundTrips(): void
    {
        $model = new TimeBlockModel($this->container);
        $taskId = $this->seedTask();

        $this->assertTrue($model->set($taskId, 1000000000, 1000003600));
        $this->assertSame(
            array('start' => 1000000000, 'end' => 1000003600),
            $model->get($taskId)
        );
    }

    public function testSetRejectsEndNotAfterStart(): void
    {
        $model = new TimeBlockModel($this->container);
        $taskId = $this->seedTask();

        $this->assertFalse($model->set($taskId, 1000003600, 1000000000)); // end < start
        $this->assertFalse($model->set($taskId, 1000000000, 1000000000)); // end == start
        $this->assertNull($model->get($taskId));                          // nothing persisted
    }

    public function testClearRemovesBlock(): void
    {
        $model = new TimeBlockModel($this->container);
        $taskId = $this->seedTask();
        $model->set($taskId, 1000000000, 1000003600);

        $this->assertTrue($model->clear($taskId));
        $this->assertNull($model->get($taskId));
        $this->assertTrue($model->clear($taskId)); // idempotent: clearing an empty block still "succeeds"
    }

    // ── blocksForCalendar ─────────────────────────────────────────────────────

    private const R_START = 1000000000; // range start
    private const R_END   = 1000086400; // +1 day

    private function seedTaskInProject(int $ownerUserId, string $projectName = 'Cal'): array
    {
        $p  = new ProjectModel($this->container);
        $tc = new TaskCreationModel($this->container);
        // $addUser=true: ProjectModel::create() sets owner_id but does NOT add a
        // project_has_users row by default, so without this the "owner" would not
        // be an accessible member per ProjectPermissionModel::getActiveProjectIds().
        $pid = (int) $p->create(array('name' => $projectName), $ownerUserId, true);
        $tid = (int) $tc->create(array('project_id' => $pid, 'title' => 'Blocked Task', 'color_id' => 'green'));
        return array('project_id' => $pid, 'task_id' => $tid);
    }

    public function testBlocksForCalendarReturnsNamespacedShapedEvent(): void
    {
        $model = new TimeBlockModel($this->container);
        $seed  = $this->seedTaskInProject(1);
        $start = self::R_START + 3600;
        $end   = $start + 7200;
        $model->set($seed['task_id'], $start, $end);

        $events = $model->blocksForCalendar(1, array(), self::R_START, self::R_END);

        $this->assertCount(1, $events);
        $ev = $events[0];
        $this->assertSame('timeblock-' . $seed['task_id'], $ev['id']);
        $this->assertSame('Blocked Task', $ev['title']);
        $this->assertSame(date('c', $start), $ev['start']);
        $this->assertSame(date('c', $end), $ev['end']);
        $this->assertFalse($ev['allDay']);
        $this->assertArrayHasKey('color', $ev);
        $this->assertArrayHasKey('url', $ev);
        $this->assertArrayHasKey('extendedProps', $ev);
        $this->assertArrayHasKey('badges', $ev['extendedProps']);
    }

    public function testBlocksForCalendarRangeFilters(): void
    {
        $model = new TimeBlockModel($this->container);
        $seed  = $this->seedTaskInProject(1);
        // Block starts AFTER the visible range → excluded.
        $model->set($seed['task_id'], self::R_END + 3600, self::R_END + 7200);

        $this->assertSame(array(), $model->blocksForCalendar(1, array(), self::R_START, self::R_END));
    }

    public function testBlocksForCalendarAccessFilters(): void
    {
        // Create a project owned by user 2 that user 1 is NOT a member of.
        $userModel = new \Kanboard\Model\UserModel($this->container);
        $uid2 = (int) $userModel->create(array('username' => 'u2', 'password' => 'p'));

        $model = new TimeBlockModel($this->container);
        $seed  = $this->seedTaskInProject($uid2, 'PrivateProj');
        $start = self::R_START + 3600;
        $model->set($seed['task_id'], $start, $start + 3600);

        // User 1 cannot access the project → no events.
        $this->assertSame(array(), $model->blocksForCalendar(1, array(), self::R_START, self::R_END));
        // The owning member sees it.
        $this->assertCount(1, $model->blocksForCalendar($uid2, array(), self::R_START, self::R_END));
    }

    public function testBlocksForCalendarHonorsProjectIdsFilter(): void
    {
        $model = new TimeBlockModel($this->container);
        $seed  = $this->seedTaskInProject(1, 'Wanted');
        $other = $this->seedTaskInProject(1, 'Unwanted');
        $start = self::R_START + 3600;
        $model->set($seed['task_id'], $start, $start + 3600);
        $model->set($other['task_id'], $start, $start + 3600);

        $events = $model->blocksForCalendar(1, array('project_ids' => array($seed['project_id'])), self::R_START, self::R_END);
        $this->assertCount(1, $events);
        $this->assertSame('timeblock-' . $seed['task_id'], $events[0]['id']);
    }
}
