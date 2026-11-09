<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        $categories = FaqCategory::with('faqs')->get();
        $faqs = Faq::with('category')->orderBy('question')->paginate(20);

        return view('admin.faq.index', compact('categories', 'faqs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|uuid|exists:faq_categories,id',
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:2000',
        ]);

        Faq::create($data);

        return back()->with('success', 'FAQ entry created successfully.');
    }

    public function update(Request $request, Faq $faq)
    {
        $data = $request->validate([
            'category_id' => 'required|uuid|exists:faq_categories,id',
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:2000',
        ]);

        $faq->update($data);

        return back()->with('success', 'FAQ entry updated.');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();
        return back()->with('success', 'FAQ entry deleted.');
    }
}
