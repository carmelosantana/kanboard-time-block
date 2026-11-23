<?php
/**
 * Board-card TimeBlock badge.
 *
 * Attached to `template:board:private:task:before-title`; rendered with
 * $task in scope. Reads the whole board's blocks in one query via getForBoard().
 * Emits only text/attributes — CSP-safe.
 */
$tb_block = $this->timeBlock->getForBoard($task);
if ($tb_block !== null): ?>
<span class="tb-badge"
      title="<?= $this->text->e(t('Time block: %s', $this->timeBlock->formatRange($tb_block))) ?>">🗓 <?= $this->text->e($this->timeBlock->formatBadge($tb_block)) ?></span>
<?php endif ?>
