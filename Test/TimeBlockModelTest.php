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
}
