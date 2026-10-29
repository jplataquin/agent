<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmProvider;
use App\Models\MasterPrompt;
use App\Models\Skill;
use App\Services\Llm\LlmAdapterFactory;
use App\Services\McpService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function doctor(McpService $mcpService, LlmAdapterFactory $adapterFactory)
    {
        $provider = LlmProvider::getDefault();

        if (! $provider) {
            return response()->json([
                'success' => false,
                'message' => 'No active LLM provider found. Please configure and activate a provider in the Admin Console.',
            ], 400);
        }

        // 1. Master Prompts
        $masterPrompts = MasterPrompt::where('is_active', true)->get();
        $promptsText = $masterPrompts->isEmpty()
            ? 'No active master prompts configured.'
            : $masterPrompts->map(fn ($p) => "### Master Prompt: {$p->title}\n{$p->content}")->implode("\n\n");

        // 2. Active Skills
        $skills = Skill::where('is_active', true)->get();
        $skillsText = $skills->isEmpty()
            ? 'No active skills configured.'
            : $skills->map(fn ($s) => "### Skill: {$s->name}\nInstructions:\n{$s->instructions}")->implode("\n\n");

        // 3. Active MCP & Internal Tools
        $tools = $mcpService->getAllActiveTools();
        $toolsText = empty($tools)
            ? 'No active tools configured.'
            : collect($tools)->map(function ($t) {
                $fn = $t['function'] ?? [];
                $desc = $fn['description'] ?? 'No description';
                $params = json_encode($fn['parameters'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                return "### Tool: {$fn['name']}\nDescription: {$desc}\nParameters:\n{$params}";
            })->implode("\n\n");

        $systemPrompt = <<<'PROMPT'
You are the AI System Coherence Doctor.
Your role is to critically analyze the configured Master Prompts, Active Skills, and available Tools (both Internal and external MCP tools) for:
1. Conflicting instructions or direct contradictions.
2. Overlapping or redundant instructions that might confuse the model.
3. Out-of-sync details (e.g. tools mentioned in instructions that are not declared, or instructions requiring formats that tools do not support).
4. Unclear or ambiguous guidelines.

Format your analysis clearly using Markdown with the following sections:
- **Executive Summary**: A high-level overview of the system's coherence (Healthy, Minor Issues, or Critical Contradictions).
- **Identified Issues**: Grouped by severity (High, Medium, Low), citing specific skills, prompts, or tools.
- **Recommendations**: Concrete, actionable steps to resolve the identified problems.

If everything is completely aligned and coherent, state that clearly and briefly highlight why the configuration is solid.
PROMPT;

        $userMessage = <<<MSG
Here is the current active system configuration:

# MASTER PROMPTS
{$promptsText}

# ACTIVE SKILLS
{$skillsText}

# DECLARED TOOLS
{$toolsText}

Please perform the diagnostic and output your report.
MSG;

        $messages = [
            ['role' => 'user', 'content' => $userMessage],
        ];

        try {
            $adapter = $adapterFactory->make($provider);
            $response = $adapter->send($provider, $systemPrompt, $messages, []);

            if ($response->isError()) {
                return response()->json([
                    'success' => false,
                    'message' => $response->errorMessage,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'report' => $response->content,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute Doctor protocol: '.$e->getMessage(),
            ], 500);
        }
    }

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
