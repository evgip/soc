# Wiki Module для w3a

## 📚 Описание

Модуль Wiki предоставляет систему документации для тегов. Позволяет создавать, редактировать и управлять wiki-страницами, привязанными к конкретным тегам.

Архитектура модуля построена по принципу HMVC (Hierarchical Model-View-Controller) и полностью соответствует стилю проекта w3a.

## ✨ Возможности

- ✅ Создание wiki страниц для тегов
- ✅ Markdown разметка с автоматическим рендерингом в HTML
- ✅ Система прав доступа (автор тега, редакторы, модераторы)
- ✅ Версионирование (история изменений через `wiki_revisions`)
- ✅ Транслитерация русских заголовков в URL-friendly slug
- ✅ Ручное редактирование slug
- ✅ Счётчик просмотров
- ✅ Основная страница тега (главная документация)
- ✅ Поиск по wiki в пределах тега
- ✅ Управление правами редакторов
- ✅ Интеграция со страницей тега (автоматическое отображение блока wiki)
- ✅ Система событий (Events) для интеграции с другими модулями
- ✅ Аудит действий через `Audit::log()`
- ✅ Soft delete для wiki страниц

## 📁 Структура модуля

```
app/Modules/Wiki/
├── Components/
│   └── WikiCard.php				# Компонент карточки wiki страницы
├── Controllers/
│   └── WikiController.php          # Контроллер (обработка запросов)
├── Models/
│   ├── WikiPage.php                # Модель wiki страниц
│   ├── WikiRevision.php            # Модель ревизий (история)
│   └── WikiPermission.php          # Модель прав доступа
├── Services/
│   ├── WikiService.php             # Бизнес-логика wiki
│   └── WikiPermissionService.php   # Бизнес-логика прав
├── Views/
│   ├── index.php                   # Список wiki страниц тега
│   ├── show.php                    # Просмотр wiki страницы
│   ├── create.php                  # Форма создания
│   ├── edit.php                    # Форма редактирования
│   ├── _form.php                   # Общая форма (переиспользуемая)
│   ├── search.php                  # Результаты поиска
│   └── permissions.php             # Управление правами
├── Events/                    		# Классы событий
│	├── WikiPageCreated.php			# Событие создания страницы
│	├── WikiPageUpdated.php			# Событие обновления страницы
│	└── WikiPageDeleted.php			# Событие удаления страницы
│
├── ModuleServiceProvider.php       # Регистрация в контейнере
├── routes.php                      # Маршруты модуля
└── README.md                       # Этот файл
```

## 🗄️ Структура базы данных

### Таблица `wiki_pages`

| Поле | Тип | Описание |
|------|-----|----------|
| id | int | Первичный ключ |
| tag_id | int | ID тега (связь с tags) |
| title | varchar(255) | Заголовок страницы |
| slug | varchar(255) | URL-friendly идентификатор (только латиница) |
| content | text | Markdown содержимое |
| rendered_content | mediumtext | Кэшированный HTML |
| author_id | int | ID автора страницы |
| is_primary | tinyint(1) | Флаг основной страницы тега |
| status | enum | draft/published/archived |
| view_count | int | Счётчик просмотров |
| created_at | timestamp | Дата создания |
| updated_at | timestamp | Дата обновления |
| deleted_at | timestamp | Дата удаления (soft delete) |

### Таблица `wiki_revisions`

| Поле | Тип | Описание |
|------|-----|----------|
| id | int | Первичный ключ |
| wiki_page_id | int | ID wiki страницы |
| revision_number | int | Номер ревизии |
| content | text | Содержимое на момент ревизии |
| edit_summary | varchar(500) | Описание изменений |
| user_id | int | ID пользователя, внёсшего изменения |
| created_at | timestamp | Дата создания ревизии |

### Таблица `wiki_permissions`

| Поле | Тип | Описание |
|------|-----|----------|
| id | int | Первичный ключ |
| tag_id | int | ID тега |
| user_id | int | ID пользователя |
| can_edit | tinyint(1) | Может редактировать |
| can_delete | tinyint(1) | Может удалять |
| granted_by | int | ID пользователя, выдавшего права |
| created_at | timestamp | Дата выдачи прав |

## 🛣️ Маршруты

### Публичные маршруты

```
GET  /t/{tag}/wiki                    # Список wiki страниц тега
GET  /t/{tag}/wiki/{slug}             # Просмотр wiki страницы
```

### Защищённые маршруты (требуют авторизации)

```
GET  /t/{tag}/wiki/create             # Форма создания
POST /t/{tag}/wiki/store              # Сохранение новой страницы
GET  /t/{tag}/wiki/{id}/edit          # Форма редактирования
POST /t/{tag}/wiki/{id}/update        # Обновление страницы
POST /t/{tag}/wiki/{id}/delete        # Удаление страницы
GET  /t/{tag}/wiki/search             # Поиск по wiki
GET  /t/{tag}/wiki/permissions        # Управление правами
POST /t/{tag}/wiki/permissions/grant  # Выдать права
POST /t/{tag}/wiki/permissions/revoke # Отозвать права
```

## 🔐 Система прав доступа

### Кто может создавать wiki?

1. **Администраторы** — всегда могут
2. **Модераторы** — всегда могут
3. **Автор тега** — может для своего тега
4. **Назначенные редакторы** — если выданы права `can_edit`

### Кто может редактировать страницу?

1. **Администраторы** — всегда могут
2. **Модераторы** — всегда могут
3. **Автор страницы** — может свою страницу
4. **Автор тега** — может любую страницу своего тега
5. **Назначенные редакторы** — если выданы права `can_edit`

### Кто может удалять страницу?

1. **Администраторы** — всегда могут
2. **Модераторы** — всегда могут
3. **Автор тега** — может любую страницу своего тега
4. **Назначенные редакторы** — если выданы права `can_delete`

## 🔤 Система транслитерации slug

При создании wiki страницы slug генерируется автоматически из заголовка:

- Русские символы транслитерируются в латиницу
- Пробелы заменяются на дефисы
- Специальные символы удаляются
- Slug приводится к нижнему регистру
- Максимальная длина — 200 символов

**Примеры:**
- `Руководство по использованию` → `rukovodstvo-po-ispolzovaniyu`
- `FAQ по тегу Meta` → `faq-po-tegu-meta`
- `Как работать с API` → `kak-rabotat-s-api`

Пользователь может вручную изменить slug при создании/редактировании страницы.

## 🔔 Система событий

Модуль генерирует три типа событий, которые могут быть использованы другими модулями:

### WikiPageCreated

Генерируется при создании новой wiki страницы.

```php
new WikiPageCreated(
    pageId: int,      // ID созданной страницы
    userId: int,      // ID автора
    tagId: ?int,      // ID тега (опционально)
    title: string     // Заголовок страницы
)
```

**Имя события:** `wiki.created`  
**Категория:** `general`

### WikiPageUpdated

Генерируется при обновлении wiki страницы.

```php
new WikiPageUpdated(
    pageId: int,              // ID страницы
    userId: int,              // ID автора изменений
    revisionNumber: int,      // Номер новой ревизии
    editSummary: ?string      // Описание изменений
)
```

**Имя события:** `wiki.updated`  
**Категория:** `general`

### WikiPageDeleted

Генерируется при удалении wiki страницы.

```php
new WikiPageDeleted(
    pageId: int,      // ID удалённой страницы
    userId: int       // ID пользователя, удалившего страницу
)
```

**Имя события:** `wiki.deleted`  
**Категория:** `moderation`

### Пример слушателя

```php
<?php

declare(strict_types=1);

namespace W3a\Core\Events\Listeners;

use W3a\Core\Events\WikiPageCreated;

class WikiNotificationListener
{
    public function handle(WikiPageCreated $event): void
    {
        $tagId = $event->getTagId();
        if ($tagId === null) {
            return;
        }
        
        // Отправить уведомления подписчикам тега
        // ...
    }
}
```

## 🏷️ Интеграция со страницей тега

На странице тега (`/t/{tag}`) автоматически отображается блок Wiki:

- **Основная страница** — показывается первой с превью содержимого (до 200 символов)
- **Другие страницы** — список до 5 дополнительных wiki страниц
- **Кнопка "Создать"** — отображается только если у пользователя есть права
- **Ссылка "Все wiki страницы"** — появляется если страниц больше 5

Передаваемые переменные в view тега:

```php
$wikiPages         // Список всех wiki страниц тега
$primaryWikiPage   // Основная wiki страница (если есть)
$wikiPagesCount    // Количество wiki страниц
$canCreateWiki     // Может ли текущий пользователь создать wiki
```

## 💻 Использование

### Создание wiki страницы

1. Перейдите на страницу тега: `/t/{tag}`
2. Нажмите "➕ Добавить статью" в блоке Wiki
3. Заполните форму:
   - **Заголовок** — название страницы
   - **URL (slug)** — автоматически генерируется из заголовка (можно изменить)
   - **Содержимое** — текст в Markdown
   - **Основная страница** — отметьте для главной документации тега
4. Нажмите "Создать страницу"

### Редактирование wiki страницы

1. Откройте wiki страницу: `/t/{tag}/wiki/{slug}`
2. Нажмите "✏️ Редактировать"
3. Внесите изменения
4. Укажите описание изменений (опционально)
5. Нажмите "Сохранить изменения"

### Управление правами

1. Перейдите в раздел прав: `/t/{tag}/wiki/permissions`
2. Введите имя пользователя
3. Отметьте права:
   - ✅ Может редактировать wiki страницы
   - ✅ Может удалять wiki страницы
4. Нажмите "Выдать права"

## 📝 Markdown синтаксис

Поддерживается стандартный Markdown:

```markdown
# Заголовок 1
## Заголовок 2
### Заголовок 3

**Жирный текст**
*Курсив*
[Ссылка](https://example.com)
`Код`

- Список 1
- Список 2
- Список 3

1. Нумерованный список
2. Второй пункт
3. Третий пункт

> Цитата

![Изображение](image.jpg)

| Таблица | Заголовок |
|---------|-----------|
| Ячейка 1 | Ячейка 2 |
```

## 🔍 Поиск

Поиск работает в пределах одного тега:

```
/t/{tag}/wiki/search?q=запрос
```

Ищет по:
- Заголовкам страниц
- Содержимому страниц

## 🔧 Настройка

### Регистрация модуля

Модуль автоматически регистрируется через `ModuleServiceProvider.php`:

```php
$container->singleton(WikiService::class, function (Container $c) {
    return new WikiService(
        $c->get(WikiPage::class),
        $c->get(WikiRevision::class),
        null // EventDispatcher (опционально)
    );
});

$container->singleton(WikiPermissionService::class, function (Container $c) {
    return new WikiPermissionService(
        $c->get(WikiPermission::class),
        $c->get(Tag::class)
    );
});
```

### Конструкторы сервисов

Оба сервиса поддерживают optional параметры для упрощения тестирования:

```php
// WikiService
public function __construct(
    WikiPage $wikiPage,
    WikiRevision $wikiRevision,
    ?EventDispatcher $eventDispatcher = null
)

// WikiPermissionService
public function __construct(
    ?WikiPermission $wikiPermission = null,
    ?Tag $tag = null
)
```

## 🚀 API для разработчиков

### WikiService

```php
$wikiService = app()->get(WikiService::class);

// Получить wiki страницы тега
$pages = $wikiService->getPagesForTag($tagId);

// Получить основную страницу тега
$primaryPage = $wikiService->getPrimaryPageForTag($tagId);

// Создать wiki страницу
$pageId = $wikiService->createPage([
    'tag_id' => $tagId,
    'title' => 'Название',
    'slug' => 'custom-slug', // опционально
    'content' => '# Текст',
    'is_primary' => 1,
    'status' => 'published'
], $userId);

// Обновить wiki страницу
$success = $wikiService->updatePage($pageId, [
    'title' => 'Новое название',
    'content' => '# Новый текст',
    'edit_summary' => 'Исправлена опечатка',
    'is_primary' => 1,
    'status' => 'published'
], $userId);

// Удалить wiki страницу
$success = $wikiService->deletePage($pageId, $userId);

// Поиск по wiki
$results = $wikiService->searchInTag($tagId, 'запрос');

// Получить страницу по slug
$page = $wikiService->getPageBySlug('my-slug', $tagId);

// Получить историю изменений
$revisions = $wikiService->getRevisions($pageId);
```

### WikiPermissionService

```php
$permissionService = app()->get(WikiPermissionService::class);

// Проверить права на создание wiki
$canCreate = $permissionService->canCreateWikiForTag($tagId, $userId);

// Проверить права на редактирование
$canEdit = $permissionService->canEditPage($page, $userId);

// Проверить права на удаление
$canDelete = $permissionService->canDeletePage($page, $userId);

// Выдать права
$success = $permissionService->grantPermission(
    $tagId,           // ID тега
    'username',       // Имя пользователя
    $grantedBy,       // ID того, кто выдаёт права
    true,             // can_edit
    false             // can_delete
);

// Отозвать права
$success = $permissionService->revokePermission(
    $tagId,           // ID тега
    $targetUserId,    // ID пользователя
    $revokedBy        // ID того, кто отзывает права
);

// Получить список редакторов
$editors = $permissionService->getTagEditors($tagId);
```

## 📊 Логирование (Audit)

Все действия логируются через `Audit::log()`:

| Действие | Код события | Категория |
|----------|-------------|-----------|
| Создание wiki | `wiki.created` | `wiki` |
| Обновление wiki | `wiki.updated` | `wiki` |
| Удаление wiki | `wiki.deleted` | `wiki` |
| Выдача прав | `wiki.permission_granted` | `wiki` |
| Отзыв прав | `wiki.permission_revoked` | `wiki` |

## 🎨 Примеры использования

### Пример 1: FAQ для тега "Meta"

```
/t/meta/wiki/faq
```

Содержимое:
```markdown
# Часто задаваемые вопросы

## Как изменить аватар?
Перейдите в настройки профиля...

## Как удалить свой пост?
Нажмите кнопку "Удалить" под постом...
```

### Пример 2: Правила тега "Programming"

```
/t/programming/wiki/rules
```

Содержимое:
```markdown
# Правила публикации в теге Programming

## Разрешено
- Вопросы по программированию
- Обсуждение технологий
- Код с пояснениями

## Запрещено
- Оффтопик
- Реклама
- Спам
```

### Пример 3: Гайд по использованию тега

```
/t/design/wiki/guide
```

Содержимое:
```markdown
# Как использовать тег Design

## Когда использовать
- Вопросы по дизайну
- Обсуждение UI/UX
- Критика макетов

## Примеры хороших постов
- "Как выбрать шрифт для мобильного приложения?"
- "Критика моего дизайна лендинга"
```

## 📈 Будущие улучшения

Возможные доработки в будущем:

- 🔍 Полнотекстовый поиск через FULLTEXT индексы MySQL
- 📊 Статистика (популярные страницы, активные редакторы)
- 🔔 Уведомления о изменениях wiki (через существующие события)
- 📝 Шаблоны для быстрого создания wiki
- 📎 Загрузка файлов (изображения, документы)
- 💬 Обсуждения (комментарии к wiki страницам)
- 🌐 Экспорт в PDF, Markdown, HTML
- 🔗 Привязка wiki к нескольким тегам (многие-ко-многим)

## 📄 Лицензия

Модуль является частью проекта w3a.

## 👥 Авторы

Evgeny Konchik (Evg)
Разработано для проекта w3a.