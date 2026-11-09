<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $faqs = Faq::with('category')->get();

        $formatted = $faqs->map(function ($f) {
            return [
                'id' => (string) $f->id,
                'category_id' => (string) $f->category_id,
                'category_name' => $f->category?->name ?? 'General',
                'question' => $f->question,
                'answer' => $f->answer,
            ];
        });

        return $this->success($formatted);
    }
}
