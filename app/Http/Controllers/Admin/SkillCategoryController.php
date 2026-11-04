<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SkillCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SkillCategoryController extends Controller
{
    public function index()
    {
        $categories = SkillCategory::withCount('skills')->orderBy('name')->get();

        if (request()->wantsJson()) {
            return response()->json(['categories' => $categories]);
        }

        return redirect()->route('admin.skills.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:skill_categories,slug',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        $category = SkillCategory::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'category' => $category,
                'message' => "Category '{$category->name}' created successfully.",
            ]);
        }

        return back()->with('success', "Category '{$category->name}' created successfully.");
    }

    public function update(Request $request, SkillCategory $skillCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('skill_categories', 'slug')->ignore($skillCategory->id)],
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        $skillCategory->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'category' => $skillCategory,
                'message' => "Category '{$skillCategory->name}' updated successfully.",
            ]);
        }

        return back()->with('success', "Category '{$skillCategory->name}' updated successfully.");
    }

    public function destroy(Request $request, SkillCategory $skillCategory)
    {
        $name = $skillCategory->name;
        $skillCategory->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Category '{$name}' deleted successfully.",
            ]);
        }

        return back()->with('success', "Category '{$name}' deleted successfully.");
    }
}
