<?php
partial('Collections::_form', [
    'collection' => null,
    'formAction' => route('collections.store'),
    'submitLabel' => 'Создать коллекцию',
    'coverUrl' => null,
]);
?>