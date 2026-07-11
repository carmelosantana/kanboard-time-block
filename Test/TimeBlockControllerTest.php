<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\TimeBlock\Controller\TimeBlockController;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskCreationModel;
use KanboardTests\units\Base;

class TimeBlockControllerTest extends Base
{
    // ── Pure parse logic (no HTTP) ────────────────────────────────────────────

    public function testParseTimesValid(): void
    {
        $parsed = TimeBlockController::parseTimes('2026-07-14', '14:00', '16:00');
        $this->assertNotNull($parsed);
        $this->assertSame(strtotime('2026-07-14 14:00'), $parsed['start']);
        $this->assertSame(strtotime('2026-07-14 16:00'), $parsed['end']);
        $this->assertGreaterThan($parsed['start'], $parsed['end']);
    }

    public function testParseTimesRejectsEmptyFields(): void
    {
        $this->assertNull(TimeBlockController::parseTimes('', '14:00', '16:00'));
        $this->assertNull(TimeBlockController::parseTimes('2026-07-14', '', '16:00'));
        $this->assertNull(TimeBlockController::parseTimes('2026-07-14', '14:00', ''));
    }

    public function testParseTimesRejectsEndNotAfterStart(): void
    {
        $this->assertNull(TimeBlockController::parseTimes('2026-07-14', '16:00', '14:00'));
        $this->assertNull(TimeBlockController::parseTimes('2026-07-14', '14:00', '14:00'));
    }

    // ── ACL gate ──────────────────────────────────────────────────────────────

    private function stubNonAdminUser(int $userId): void
    {
        $this->container['userSession'] = $this
            ->getMockBuilder(\Kanboard\Core\User\UserSession::class)
            ->setConstructorArgs(array($this->container))
            ->onlyMethods(array('isAdmin', 'getId'))
            ->getMock();
        $this->container['userSession']->method('isAdmin')->willReturn(false);
        $this->container['userSession']->method('getId')->willReturn($userId);
    }

    private function stubTaskIdRequest(int $taskId): void
    {
        $this->container['request'] = $this
            ->getMockBuilder(\Kanboard\Core\Http\Request::class)
            ->setConstructorArgs(array($this->container))
            ->onlyMethods(array('getIntegerParam'))
            ->getMock();
        $this->container['request']
            ->method('getIntegerParam')
            ->willReturnCallback(function (string $param) use ($taskId) {
                return $param === 'task_id' ? $taskId : 0;
            });
    }

    public function testSaveForbiddenForUserWithoutAccess(): void
    {
        // Stub the non-admin session before anything touches the container's
        // real userSession service, which freezes it against later overrides.
        $this->stubNonAdminUser(2);

        // Task lives in a project owned by user 1; user 2 has no role on it.
        $p   = new ProjectModel($this->container);
        $tc  = new TaskCreationModel($this->container);
        $pid = (int) $p->create(array('name' => 'Owned'), 1);
        $tid = (int) $tc->create(array('project_id' => $pid, 'title' => 'T'));

        $this->stubTaskIdRequest($tid);

        $controller = new TimeBlockController($this->container);

        $this->expectException(AccessForbiddenException::class);
        $controller->save();
    }
}
