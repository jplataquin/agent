<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function index()
    {
        $skills = Skill::latest()->paginate(15);

        return view('admin.skills.index', compact('skills'));
    }

    public function create()
    {
        return view('admin.skills.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:skills',
            'description' => 'nullable|string',
            'instructions' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');

        Skill::create($validated);

        return redirect()->route('admin.skills.index')->with('success', 'Skill created successfully.');
    }

    public function edit(Skill $skill)
    {
        return view('admin.skills.edit', compact('skill'));
    }

    public function preview(Request $request, Skill $skill)
    {
        $name = (string) $request->input('name', $skill->name);
        $description = (string) $request->input('description', $skill->description);
        $instructions = (string) $request->input('instructions', $skill->instructions);

        $markdown = $this->buildSkillMarkdown($name, $description, $instructions);

        return view('admin.skills.preview', compact('skill', 'name', 'description', 'instructions', 'markdown'));
    }

    protected function buildSkillMarkdown(?string $name, ?string $description, ?string $instructions): string
    {
        $name = trim((string) $name);
        $description = trim((string) $description);
        $instructions = trim((string) $instructions);

        $parts = [];

        // If instructions do not start with a top-level header (# ...), include the title
        if ($name !== '' && ! preg_match('/^#\s+/m', $instructions)) {
            $parts[] = "# {$name}";
        }

        // Include description as blockquote if provided and not already included in instructions
        if ($description !== '' && ! str_contains($instructions, $description)) {
            $parts[] = "> {$description}";
        }

        if ($instructions !== '') {
            $parts[] = $instructions;
        }

        return implode("\n\n", $parts);
    }

    public function update(Request $request, Skill $skill)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('skills')->ignore($skill->id)],
            'description' => 'nullable|string',
            'instructions' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['slug']);
        $validated['is_active'] = $request->has('is_active');

        $skill->update($validated);

        return redirect()->route('admin.skills.index')->with('success', 'Skill updated successfully.');
    }

    public function toggle(Skill $skill)
    {
        $skill->update(['is_active' => ! $skill->is_active]);

        return back()->with('success', "Skill '{$skill->name}' status toggled.");
    }

    public function destroy(Skill $skill)
    {
        $skill->delete();

        return redirect()->route('admin.skills.index')->with('success', 'Skill deleted successfully.');
    }
}
