<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterPrompt;
use Illuminate\Http\Request;

class MasterPromptController extends Controller
{
    public function index()
    {
        $prompts = MasterPrompt::latest()->paginate(15);

        return view('admin.master_prompts.index', compact('prompts'));
    }

    public function create()
    {
        return view('admin.master_prompts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $isActive = $request->has('is_active');

        if ($isActive) {
            MasterPrompt::query()->update(['is_active' => false]);
        }

        $validated['is_active'] = $isActive;

        MasterPrompt::create($validated);

        return redirect()->route('admin.master-prompts.index')->with('success', 'Master Prompt created.');
    }

    public function edit(MasterPrompt $masterPrompt)
    {
        return view('admin.master_prompts.edit', ['prompt' => $masterPrompt]);
    }

    public function update(Request $request, MasterPrompt $masterPrompt)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $isActive = $request->has('is_active');

        if ($isActive) {
            MasterPrompt::where('id', '!=', $masterPrompt->id)->update(['is_active' => false]);
        }

        $validated['is_active'] = $isActive;

        $masterPrompt->update($validated);

        return redirect()->route('admin.master-prompts.index')->with('success', 'Master Prompt updated.');
    }

    public function setActive(MasterPrompt $masterPrompt)
    {
        MasterPrompt::query()->update(['is_active' => false]);
        $masterPrompt->update(['is_active' => true]);

        return back()->with('success', "Master Prompt '{$masterPrompt->title}' is now active.");
    }

    public function destroy(MasterPrompt $masterPrompt)
    {
        $masterPrompt->delete();

        return redirect()->route('admin.master-prompts.index')->with('success', 'Master Prompt deleted.');
    }
}
