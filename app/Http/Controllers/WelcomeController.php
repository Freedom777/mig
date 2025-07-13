<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        // Здесь вы можете подготовить данные для страницы
        $images = [
            [
                'thumbnail' => asset('images/thumb1.jpg'),
                'full' => asset('images/full1.jpg')
            ],
            // ...
        ];

        return Inertia::render('Welcome', [
            'images' => $images,
            // Другие данные, которые нужно передать во фронтенд
        ]);
    }
}
