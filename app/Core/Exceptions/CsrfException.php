<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Исключение для ошибок CSRF-валидации
 */
class CsrfException extends HttpException
{
    protected array $context = [];
    
    /**
     * Конструктор
     * 
     * @param string $message Сообщение для пользователя
     * @param array $context Дополнительный контекст для логирования
     */
    public function __construct(
        string $message = 'Срок действия формы истёк. Пожалуйста, обновите страницу и попробуйте снова.',
        array $context = []
    ) {
        $this->context = $context;
        
        // В HttpException первым идет int $statusCode, вторым string $message
        parent::__construct(419, $message, null, $context);
    }

    /**
     * Получить контекст исключения (ожидается в Application.php)
     */
    public function getContext(): array
    {
        return $this->context;
    }
}