<h1>Предложения на рассмотрении</h1>

<p class="hint">
    Активные предложения изменений контента от пользователей. 
    Одобрение применяет изменения сразу, отклонение удаляет предложение.
</p>

<?php if (empty($suggestions)): ?>
    <div class="flash-notice">
        Нет активных предложений для рассмотрения.
    </div>
<?php else: ?>
    
    <!-- Фильтры -->
    <div class="form-field-group">
        <strong>Фильтр:</strong>
        <a href="/mod/suggestions" class="<?= empty($filter) ? 'active' : '' ?>">
            Все (<?= $totalCount ?>)
        </a>
        <span class="divider">|</span>
        <a href="/mod/suggestions?type=Story" class="<?= ($filter ?? '') === 'Story' ? 'active' : '' ?>">
            Статьи (<?= $storiesCount ?>)
        </a>
        <span class="divider">|</span>
        <a href="/mod/suggestions?type=Comment" class="<?= ($filter ?? '') === 'Comment' ? 'active' : '' ?>">
            Комментарии (<?= $commentsCount ?>)
        </a>
    </div>
    
    <hr>
    
	<!-- Список предложений -->
	<ol class="stories">
		<?php foreach ($suggestions as $suggestion): ?>
			<?php
			$proposedData = json_decode($suggestion['proposed_data'], true);
			?>
			<li class="story">
				<div class="story_liner">
					
					<!-- Заголовок с типом и ID -->
					<div class="link">
						<strong>
							<?php if ($suggestion['target_type'] === 'Story'): ?>
								📄 Статья #<?= (int)$suggestion['target_id'] ?>
							<?php else: ?>
								💬 Комментарий #<?= (int)$suggestion['target_id'] ?>
							<?php endif; ?>
						</strong>
						<small class="hint">
							Предложил: 
							<a href="/user/<?= e($suggestion['suggester_name']) ?>">
								<?= e($suggestion['suggester_name']) ?>
							</a>
							<?= date('d.m.Y H:i', strtotime($suggestion['created_at'])) ?>
						</small>
					</div>
					
					<!-- Предлагаемые изменения -->
					<div class="story_content">
						<?php if (!empty($proposedData['title'])): ?>
							<div class="form-field-group">
								<strong>Новый заголовок:</strong><br>
								<?= e($proposedData['title']) ?>
							</div>
						<?php endif; ?>
		
						<?php if (!empty($suggestion['tags_details'])): ?>
							<div class="form-field-group">
								<strong>Новые теги:</strong><br>
								<span class="tags">
									<?php foreach ($suggestion['tags_details'] as $tagData): ?>
										<a href="/t/<?= e($tagData['slug']) ?>" class="tag"><?= e($tagData['name']) ?></a>
									<?php endforeach; ?>
								</span>
							</div>
						<?php endif; ?>

						<?php if (!empty($proposedData['text'])): ?>
							<div class="form-field-group">
								<strong>Новый текст:</strong><br>
								<?= e(mb_substr($proposedData['text'], 0, 200)) ?><?= mb_strlen($proposedData['text']) > 200 ? '...' : '' ?>
							</div>
						<?php endif; ?>
					</div>
					
					<!-- Действия модератора -->
					<div class="byline">
						<form action="/mod/suggestions/<?= (int)$suggestion['id'] ?>/approve" method="POST" class="inline-form">
							<?= csrf_field() ?>
							<button type="submit" class="is-link text-success">
								✓ Одобрить
							</button>
						</form>
						
						<span class="divider">|</span>
						
						<form action="/mod/suggestions/<?= (int)$suggestion['id'] ?>/reject" method="POST" class="inline-form" data-confirm="Отклонить это предложение?">
							<?= csrf_field() ?>
							<input type="hidden" name="reason" value="Отклонено модератором">
							<button type="submit" class="is-link red">
								✗ Отклонить
							</button>
						</form>
						
						<span class="divider">|</span>
						
						<?php if ($suggestion['target_type'] === 'Story'): ?>
							<a href="/story/<?= (int)$suggestion['target_id'] ?>">Перейти к статье →</a>
						<?php else: ?>
							<a href="/story/<?= (int)($suggestion['story_id'] ?? 0) ?>#comment-block-<?= (int)$suggestion['target_id'] ?>">
								Перейти к комментарию →
							</a>
						<?php endif; ?>
					</div>
					
				</div>
			</li>
		<?php endforeach; ?>
	</ol>
    
    <!-- Пагинация -->
    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php if ($i === $current_page): ?>
                    <strong><?= $i ?></strong>
                <?php else: ?>
                    <a href="/mod/suggestions?page=<?= $i ?><?= !empty($filter) ? '&type=' . e($filter) : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>