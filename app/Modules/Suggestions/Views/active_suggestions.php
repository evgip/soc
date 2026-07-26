<?php
$suggestions = $activeSuggestions ?? [];
$isModerator = \App\Modules\Auth\Services\Auth::isModerator() || \App\Modules\Auth\Services\Auth::isAdmin();
?>

<?php if (!empty($suggestions)): ?>
    <div class="suggestions-container">
        <h4>Активные предложения <span class="suggestions-badge"><?= count($suggestions) ?></span></h4>

        <?php
        // Группируем одинаковые предложения
        $grouped = [];
        foreach ($suggestions as $s) {
            $key = json_encode($s['proposed_data']);
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'proposed_data' => $s['proposed_data'],
                    'users' => [],
                    'created_at' => $s['created_at'],
                    'suggestion_id' => $s['id'] // ID первого предложения в группе
                ];
            }
            $grouped[$key]['users'][] = $s['suggester_name'];
        }
        ?>

        <?php foreach ($grouped as $group): ?>
            <div class="story-row">
                <div class="story-details">
                    <strong><?= count($group['users']) ?> пользовател(ей) предлагают:</strong>
                    <small class="hint"><?= date('d.m.Y H:i', strtotime($group['created_at'])) ?></small>
                    <div class="story-description">
                        <?php if (!empty($group['proposed_data']['title'])): ?>
                            <strong>Заголовок:</strong> "<?= e($group['proposed_data']['title']) ?>"
                        <?php endif; ?>
                        <?php if (!empty($group['proposed_data']['tag_ids'])): ?>
                            <br><strong>Теги:</strong> изменены
                        <?php endif; ?>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= min((count($group['users']) / 3) * 100, 100) ?>%">
                            <?= count($group['users']) ?> из 3
                        </div>
                    </div>

                    <?php if ($isModerator): ?>
                        <!-- Кнопки для модератора -->
                        <div class="moderator-actions">
                            <form action="/suggestions/<?= $group['suggestion_id'] ?>/approve" method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" class="is-link">
                                    ✓ Одобрить
                                </button>
                            </form>
                            <span class="divider">|</span>
                            <form action="/suggestions/<?= $group['suggestion_id'] ?>/reject" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reason" value="Отклонено модератором">
                                <button type="submit" class="is-link red">
                                    ✗ Отклонить
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <small class="hint">
                            <?= count($group['users']) >= 3 ? '✓ Кворум достигнут!' : 'Необходимо для применения' ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>