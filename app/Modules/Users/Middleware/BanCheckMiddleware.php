<?php

declare(strict_types=1);

namespace App\Modules\Users\Middleware;

use W3a\Core\Container;
use W3a\Core\Contracts\UserIdProviderInterface;
use W3a\Core\Session;
use W3a\Core\Audit;
use W3a\Core\Exceptions\RedirectException;
use W3a\Core\Middleware\MiddlewareInterface;
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
            
            $session = $this->container->get(Session::class);
            $flash = $_SESSION['flash'] ?? null;
            $session->destroy();
            session_start();
            
            if ($flash) {
                $_SESSION['flash'] = $flash;
            }
            
            $session->flash('error', $message);
            throw new RedirectException('/');
        }
        
        return $next();
    }
}