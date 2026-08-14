<?php

declare(strict_types=1);

/**
 * Универсальный блочный редактор (Editor.js)
 */

$editor = array_merge([
    'name'        => 'description',
    'value'       => '', // Ожидаем здесь JSON-строку
    'placeholder' => 'Начните писать или введите / для выбора блока...',
    'label'       => 'Текст публикации',
    'hint'        => 'Блочный редактор. Поддерживает заголовки, списки, цитаты и код.',
], $editor ?? []);

$uid = substr(md5($editor['name'] . uniqid('', true)), 0, 8);
$containerId = 'editorjs-container-' . $uid;
$hiddenTextareaId = 'editorjs-hidden-' . $uid;
$nonce = csp_nonce();

// Безопасная инициализация данных: пытаемся декодировать JSON, иначе берем пустой блок
$initialData = [
    'time'    => time() * 1000,
    'blocks'  => [['type' => 'paragraph', 'data' => ['text' => '']]],
    'version' => '2.30.7'
];

if (!empty($editor['value']) && is_string($editor['value'])) {
    $decoded = json_decode($editor['value'], true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['blocks'])) {
        $initialData = $decoded;
    }
}

$initialDataJson = json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>

<!-- Подключаем локальные файлы Editor.js -->
<script src="/assets/editor/editorjs.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/header.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/editorjs-list.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/quote.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/inline-code.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/image.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/code.umd.js" nonce="<?= $nonce ?>"></script>

<div class="form-field-group">
    <label><strong><?= e($editor['label']) ?></strong></label>
    <?php if (!empty($editor['hint'])): ?>
        <p class="hint"><?= e($editor['hint']) ?></p>
    <?php endif; ?>

    <!-- Контейнер для визуального редактора -->
    <div id="<?= e($containerId) ?>" class="editorjs-custom-wrapper"></div>

    <!-- Скрытое поле, которое отправит JSON на сервер -->
    <textarea id="<?= e($hiddenTextareaId) ?>" name="<?= e($editor['name']) ?>" class="hidden"><?= e($editor['value']) ?></textarea>
</div>


<script nonce="<?= $nonce ?>">
/**
 * Paywall Block для Editor.js
 * ОПРЕДЕЛЯЕМ КЛАСС ДО ЕГО ИСПОЛЬЗОВАНИЯ!
 */
(function() {
    'use strict';

    class PaywallBlock {
        static get toolbox() {
            return {
                title: 'Замок (paywall)',
                icon: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            };
        }

        static get isReadOnlySupported() {
            return true;
        }

        constructor({ data, api, readOnly }) {
            this.api = api;
            this.readOnly = readOnly;
            this.data = {
                title: (data && data.title) ? data.title : 'Продолжение доступно участникам',
            };
        }

        render() {
            const wrapper = document.createElement('div');
            wrapper.className = 'editorjs-paywall';

            if (!this.readOnly) {
                wrapper.innerHTML = `
                    <div class="editorjs-paywall__marker">
                        <span class="editorjs-paywall__icon">🔒</span>
                        <div class="editorjs-paywall__text">
                            <strong>Начало закрытой части</strong>
                            <p>Всё, что ниже этого маркера, будет видно только авторизованным читателям.</p>
                        </div>
                    </div>
                `;
            } else {
                wrapper.innerHTML = `<div class="paywall-divider"></div>`;
            }

            return wrapper;
        }

        save() {
            return { title: this.data.title };
        }

        static get sanitize() {
            return { title: {} };
        }
    }

    // 🔑 КЛЮЧЕВОЕ: регистрируем класс под ДВУМЯ именами
    window.PaywallBlock = PaywallBlock;
    window.PaywallTool = PaywallBlock;  // ← чтобы PaywallTool был определён
})();
</script>


<script nonce="<?= $nonce ?>">
document.addEventListener('DOMContentLoaded', function() {
    const containerId = '<?= e($containerId) ?>';
    const hiddenTextareaId = '<?= e($hiddenTextareaId) ?>';
    const form = document.getElementById(containerId)?.closest('form');

    const HeaderTool = window.Header;
    const ListTool = window.EditorjsList;
    const QuoteTool = window.Quote;
    const InlineCodeTool = window.InlineCode;
    const ImageTool = window.ImageTool;
    const CodeTool = window.CodeTool;
    const PaywallTool = window.PaywallTool;

    if (!window.EditorJS || !ListTool || !InlineCodeTool) {
        console.error('❌ Не удалось загрузить плагины Editor.js. Проверьте пути и CSP.');
        return;
    }

    if (!ImageTool) {
        console.warn('⚠️ Плагин Image не загружен.');
    }
    
    if (!PaywallTool) {
        console.warn('⚠️ Плагин Paywall не загружен.');
    }
    
    if (!CodeTool) {
        console.warn('⚠️ Плагин Code не загружен.');
    }

    const editorI18n = {
        messages: {
            ui: {
                blockTunes: { toggler: { "Click to tune": "Нажмите для настройки", "or drag to move": "или перетащите" } },
                inlineToolbar: { converter: { "Convert to": "Конвертировать в" } },
                toolbar: { toolbox: { "Add": "Добавить блок" } },
                popover: { "Filter": "Поиск", "Nothing found": "Ничего не найдено" }
            },
            toolNames: {
                "Text": "Текст", "Heading": "Заголовок", "List": "Список",
                "Quote": "Цитата", "Code": "Блок кода", "Link": "Ссылка",
                "Bold": "Жирный", "Italic": "Курсив", "InlineCode": "Встроенный код",
                "Замок (paywall)": "Замок (paywall)",
                "Image": "Изображение",
				"Ordered List": "Нумерованный",    
				"Unordered List": "Маркированный",  
				"Checklist": "Чеклист",  
            },
            tools: {
                heading: { "Heading": "Заголовок" },
                list: { "Ordered": "Нумерованный", "Unordered": "Маркированный" },
                quote: { "Align Left": "По левому краю", "Align Center": "По центру" },
                inlineCode: { "Inline Code": "Встроенный код" },
                link: { "Add a link": "Добавить ссылку", "Enter a link": "Введите URL", "Enter a link text": "Введите текст" },
                image: {
                    "Image": "Изображение",
                    "Select an image": "Выберите изображение",
					 "Select an Image": "Выберите изображение",
                    "Paste an image URL": "Вставьте URL изображения",
                    "Uploading...": "Загрузка...",
                    "Loading...": "Загрузка...",
                    "With border": "С рамкой",
                    "Stretch image": "Растянуть изображение",
                    "With background": "С фоном",
                    "Caption": "Подпись",
                    "Enter a caption": "Введите подпись",
                    "Add Image": "Добавить изображение",
                    "Upload an image": "Загрузить изображение",
                    "Drop an image here": "Перетащите изображение сюда",
                    "URL": "URL"
                },
                code: {
                    "Code": "Код",
                    "Enter your code here...": "Введите код...",
                    "Language": "Язык",
                    "Placeholder": "Напишите код здесь..."
                }
            },
            blockTunes: {
                delete: { "Delete": "Удалить" },
                moveUp: { "Move up": "Переместить вверх" },
                moveDown: { "Move down": "Переместить вниз" }
            },
            errors: { tool: { "name": "Ошибка в инструменте \"%name%\"" } }
        }
    };

    // 🔑 ВАЖНО: строим tools динамически, без undefined значений
    const tools = {
        header: { class: HeaderTool, config: { levels: [2, 3, 4], defaultLevel: 2 } },
        list: { class: ListTool, config: { defaultStyle: 'unordered' } },
        quote: { class: QuoteTool },
        inlineCode: { class: InlineCodeTool, shortcut: 'CMD+SHIFT+C' },
    };

    // Добавляем опциональные инструменты только если они загружены
    if (CodeTool) {
        tools.code = {
            class: CodeTool,
            shortcut: 'CMD+SHIFT+K',
            config: { placeholder: 'Введите код...' }
        };
    }

    if (PaywallTool) {
        tools.paywall = { class: PaywallTool };
    }

    if (ImageTool) {
        tools.image = {
            class: ImageTool,
            config: {
                endpoints: { byFile: '/stories/upload-image' },
                field: 'image',
                types: 'image/jpeg, image/png, image/gif, image/webp',
                additionalRequestHeaders: {
                    'X-XSRF-TOKEN': '<?= csp_nonce() ?>'
                }
            }
        };
    }

	const editor = new EditorJS({
		holder: containerId,
		placeholder: '<?= e($editor['placeholder']) ?>',
		i18n: editorI18n,
		tools: tools,
		data: <?= $initialDataJson ?>,
		onReady: () => {
			console.log('✅ Editor.js инициализирован');
			
			// Проверяем все изображения на битые ссылки
			checkBrokenImages();
			
			// Наблюдаем за изменениями DOM (новые блоки)
			const observer = new MutationObserver(checkBrokenImages);
			observer.observe(document.getElementById(containerId), {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['src']
			});
		},
		onChange: async () => {
			const outputData = await editor.save();
			document.getElementById(hiddenTextareaId).value = JSON.stringify(outputData);
		}
	});

	/**
	 * Проверка битых изображений в редакторе
	 */
	function checkBrokenImages() {
		const container = document.getElementById('<?= e($containerId) ?>');
		if (!container) return;
		
		const images = container.querySelectorAll('.image-tool__image img');
		
		images.forEach(img => {
			// Пропускаем уже обработанные
			if (img.dataset.checked) return;
			
			img.dataset.checked = 'true';
			
			// Обработчик ошибки загрузки
			img.onerror = function() {
				this.classList.add('broken');
				const imageContainer = this.closest('.image-tool__image');
				if (imageContainer) {
					imageContainer.classList.add('has-error');
					
					// Добавляем клик для удаления блока
					imageContainer.addEventListener('click', async function(e) {
						if (confirm('Изображение не найдено. Удалить этот блок?')) {
							const block = this.closest('.ce-block');
							if (block) {
								// Удаляем блок через Editor.js API
								const blockIndex = Array.from(block.parentNode.children).indexOf(block);
								await editor.blocks.delete(blockIndex);
							}
						}
					});
				}
			};
			
			// Если изображение уже битое (naturalWidth === 0)
			if (img.complete && img.naturalWidth === 0) {
				img.onerror();
			}
		});
	}

    if (form) {
        form.addEventListener('submit', async function(e) {
            try {
                const outputData = await editor.save();
                document.getElementById(hiddenTextareaId).value = JSON.stringify(outputData);
            } catch (error) {
                console.error('❌ Ошибка сохранения Editor.js:', error);
                e.preventDefault();
                alert('Ошибка в редакторе. Проверьте консоль (F12).');
            }
        });
        editor.isReady.then(() => {
            editor.save().then(outputData => {
                document.getElementById(hiddenTextareaId).value = JSON.stringify(outputData);
            });
        });
    }
});
</script>