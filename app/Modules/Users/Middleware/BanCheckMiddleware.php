<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use W3a\Core\Foundation\Container;
use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Http\Session;
use W3a\Core\Support\Audit;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Http\Middleware\MiddlewareInterface;
use App\Modules\Users\Models\User;

class BanCheckMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly UserIdProviderInterface $userIdProvider
    ) {}

    public function handle(callable $next): mixed
    {
        $userId = $this->userIdProvider->getUserId();
        
        if ($userId === null || (int)$userId <= 0) {
            return $next();
        }
        
        $userModel = $this->container->get(User::class);
        
        if ($userModel->isBanned((int)$userId)) {
            $banInfo = $userModel->getBanInfo((int)$userId);
            
            $message = 'Ваш аккаунт заблокирован.';
            if (!empty($banInfo['reason'])) {
                $message .= ' Причина: ' . $banInfo['reason'];
            }
            if (!empty($banInfo['expires_at'])) {
                $message .= ' Срок до: ' . date('d.m.Y H:i', strtotime($banInfo['expires_at']));
            }
            
            $audit = $this->container->get(Audit::class);
            $audit->log('security.banned_access', 'Попытка доступа забаненного пользователя', 'security', [
                'user_id' => $userId,
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
            
            // Сохраняем flash-сообщения перед уничтожением сессии, чтобы показать их на главной
            $flash = $_SESSION['flash'] ?? null;
            
            $session = $this->container->get(Session::class);
            $session->destroy(); // Уничтожаем старую сессию (разлогиниваем пользователя)
            
            session_start(); // Начинаем новую чистую сессию для отображения сообщения
            
            if ($flash) {
                $_SESSION['flash'] = $flash;
            }
            
            $session->flash('error', $message);
            
            // Возвращаем объект RedirectResponse вместо выбрасывания исключения.
            // Это корректно прерывает цепочку middleware и отправляет редирект.
            return new RedirectResponse('/');
        }
        
        return $next();
    }
}