<?php

require_once 'tests/units/Base.php';

use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Plugin\TimeBlock\Plugin;
use KanboardTests\units\Base;

/**
 * Kanboard #7284: the board badge must not read task metadata per task.
 *
 * Builds a board of N tasks, renders TimeBlock's board-card hook for every task the
 * way board/task_private.php does, and counts SQL statements during the render.
 */
class BoardQueryCountTest extends Base
{
    private function seedBoard(int $n): array
    {
        $projectId = (int) (new ProjectModel($this->container))->create(array('name' => 'Board ' . $n), 1, true);
        $creation  = new TaskCreationModel($this->container);

        for ($i = 0; $i < $n; $i++) {
            $taskId = (int) $creation->create(array('project_id' => $projectId, 'title' => 'Task ' . $i));
            if ($i % 2 === 0) {
                $this->container['timeBlockModel']->set($taskId, 1000000000 + $i * 3600, 1000003600 + $i * 3600);
            }
        }

        $tasks = $this->container['taskFinderModel']->getExtendedQuery()
            ->eq('tasks.project_id', $projectId)
            ->findAll();

        return array($projectId, $tasks);
    }

    /** Render the before-title hook for each task; return [queries, html]. */
    private function renderBoard(array $tasks): array
    {
        $statements = $this->container['db']->getStatementHandler();
        $before     = $statements->getNbQueries();
        $html       = '';

        foreach ($tasks as $task) {
            $html .= $this->container['helper']->hook->render('template:board:private:task:before-title', array('task' => $task));
        }

        return array($statements->getNbQueries() - $before, $html);
    }

    public function testBoardRenderQueryCountIsConstantInTaskCount(): void
    {
        (new Plugin($this->container))->initialize();

        list(, $small) = $this->seedBoard(5);
        list(, $large) = $this->seedBoard(40);

        list($smallQueries, $smallHtml) = $this->renderBoard($small);
        list($largeQueries, $largeHtml) = $this->renderBoard($large);

        $this->assertSame(3, substr_count($smallHtml, 'tb-badge'));
        $this->assertSame(20, substr_count($largeHtml, 'tb-badge'));
        $this->assertSame($smallQueries, $largeQueries, "board render made $smallQueries queries for 5 tasks, $largeQueries for 40");
        $this->assertSame(1, $largeQueries, "one metadata query per board");
    }
}
