<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\WhatsAppService;

class ApiWhatsAppController extends Controller
{
    public function msg(WhatsAppService $wa, Request $request) {
        $phone = $request->input('phone');
        $msg = $request->input('msg');
        // dd($phone, $msg);
        // '49123456789'
        // 'Привет! Это бот на Laravel 🚀'
        return $wa->sendMessage($phone, $msg);
    }
}
