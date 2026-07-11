<?php
/**
 * Task-page TimeBlock panel.
 *
 * Attached to `template:task:show:before-internal-links`; rendered with
 * $task and $project in scope. Shows the current block (if any) plus a
 * Set/Edit form and a Clear form. No inline <script> — CSP-safe.
 */
$tb_block = $this->timeBlock->get($task['id']);
?>
<div class="accordion-section timeblock-panel">
    <div class="accordion-title"><?= t('Time block') ?></div>
    <div class="accordion-content">
        <?php if ($tb_block !== null): ?>
            <p class="tb-current">🗓 <?= $this->text->e($this->timeBlock->formatRange($tb_block)) ?></p>
        <?php else: ?>
            <p class="tb-empty"><?= t('No time block set.') ?></p>
        <?php endif ?>

        <details class="tb-edit">
            <summary><?= $tb_block === null ? t('Set time block') : t('Edit time block') ?></summary>
            <form method="post"
                  class="tb-form"
                  action="<?= $this->url->href('TimeBlockController', 'save', array('plugin' => 'TimeBlock', 'task_id' => $task['id'])) ?>">
                <?= $this->form->csrf() ?>
                <?= $this->form->label(t('Date'), 'date') ?>
                <input type="date" id="date" name="date" required
                       value="<?= $tb_block !== null ? date('Y-m-d', $tb_block['start']) : '' ?>">
                <?= $this->form->label(t('Start time'), 'start_time') ?>
                <input type="time" id="start_time" name="start_time" required
                       value="<?= $tb_block !== null ? date('H:i', $tb_block['start']) : '' ?>">
                <?= $this->form->label(t('End time'), 'end_time') ?>
                <input type="time" id="end_time" name="end_time" required
                       value="<?= $tb_block !== null ? date('H:i', $tb_block['end']) : '' ?>">
                <div class="tb-actions">
                    <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
                </div>
            </form>
        </details>

        <?php if ($tb_block !== null): ?>
            <form method="post"
                  class="tb-clear-form"
                  action="<?= $this->url->href('TimeBlockController', 'clear', array('plugin' => 'TimeBlock', 'task_id' => $task['id'])) ?>">
                <?= $this->form->csrf() ?>
                <button type="submit" class="btn btn-red"><?= t('Clear') ?></button>
            </form>
        <?php endif ?>
    </div>
</div>
