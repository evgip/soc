<?php
partial('Collections::_form', [
    'collection' => $collection,
    'formAction' => route('collections.update', ['id' => $collection['id']]),
    'submitLabel' => 'Сохранить изменения',
    'coverUrl' => $coverUrl ?? null,
]);
?>