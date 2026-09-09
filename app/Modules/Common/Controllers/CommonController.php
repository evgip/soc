<?php

declare(strict_types=1);

namespace App\Modules\Common\Controllers;

use App\BaseController;

class CommonController extends BaseController
{
    public function index()
    {
        return $this->render('donations', [
            'title' => 'Помощь',
        ]);
    }
}