<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterPrompt;
use App\Models\McpServer;
use App\Models\Skill;
use App\Services\McpService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MasterPromptController extends Controller
{
    public function index()
    {
        $prompts = MasterPrompt::latest()->paginate(15);

        return view('admin.master_prompts.index', compact('prompts'));
    }

    public function create(McpService $mcpService)
    {
        $groupedTools = $mcpService->getGroupedTools();
        $serversMap = McpServer::where('is_active', true)->get()->keyBy('name');
        $availableSkills = Skill::select('id', 'name', 'slug', 'description')->orderBy('name')->get();

        return view('admin.master_prompts.create', compact('groupedTools', 'serversMap', 'availableSkills'));
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

    public function edit(MasterPrompt $masterPrompt, McpService $mcpService)
    {
        $groupedTools = $mcpService->getGroupedTools();
        $serversMap = McpServer::where('is_active', true)->get()->keyBy('name');
        $availableSkills = Skill::select('id', 'name', 'slug', 'description')->orderBy('name')->get();

        return view('admin.master_prompts.edit', [
            'prompt' => $masterPrompt,
            'groupedTools' => $groupedTools,
            'serversMap' => $serversMap,
            'availableSkills' => $availableSkills,
        ]);
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

    /**
     * Return rendered HTML for the MCP Explorer tree (for dynamic live refresh).
     */
    public function mcpTree(McpService $mcpService)
    {
        $groupedTools = $mcpService->getGroupedTools();
        $serversMap = McpServer::where('is_active', true)->get()->keyBy('name');

        $totalTools = 0;
        foreach ($groupedTools as $tools) {
            $totalTools += count($tools);
        }

        $html = view('admin.skills._mcp_tree', compact('groupedTools', 'serversMap'))->render();

        return response()->json([
            'success' => true,
            'total_tools' => $totalTools,
            'html' => $html,
        ]);
    }

    /**
     * Sync all active MCP servers and return the updated tree HTML.
     */
    public function syncAllMcp(McpService $mcpService)
    {
        $servers = McpServer::where('is_active', true)->get();
        $syncedCount = 0;
        $totalTools = 0;
        $errors = [];

        foreach ($servers as $server) {
            $res = $mcpService->syncTools($server);
            if ($res['success']) {
                $syncedCount++;
                $totalTools += $res['count'] ?? 0;
            } else {
                $errors[] = "{$server->name}: ".($res['error'] ?? 'Unknown error');
            }
        }

        $groupedTools = $mcpService->getGroupedTools();
        $serversMap = McpServer::where('is_active', true)->get()->keyBy('name');

        $calcTotalTools = 0;
        foreach ($groupedTools as $tools) {
            $calcTotalTools += count($tools);
        }

        $html = view('admin.skills._mcp_tree', compact('groupedTools', 'serversMap'))->render();

        return response()->json([
            'success' => true,
            'synced_servers' => $syncedCount,
            'total_tools' => $calcTotalTools,
            'errors' => $errors,
            'html' => $html,
            'message' => "Synced {$syncedCount} ".Str::plural('server', $syncedCount)." ({$calcTotalTools} ".Str::plural('tool', $calcTotalTools).' discovered).',
        ]);
    }

    /**
     * Render the Master Prompt in a clean standalone full page view.
     */
    public function preview(Request $request, MasterPrompt $masterPrompt)
    {
        $title = (string) $request->input('title', $masterPrompt->title);
        $description = (string) $request->input('description', $masterPrompt->description);
        $content = (string) $request->input('content', $masterPrompt->content);

        $markdown = $this->buildMasterPromptMarkdown($title, $description, $content);
        $markdown = Skill::resolveTemplate($markdown);

        return view('admin.master_prompts.preview', [
            'prompt' => $masterPrompt,
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'markdown' => $markdown,
        ]);
    }

    /**
     * Resolve templating and build markdown for inline live preview.
     */
    public function resolvePreview(Request $request)
    {
        $title = (string) $request->input('title', $request->input('name', ''));
        $description = (string) $request->input('description', '');
        $content = (string) $request->input('content', $request->input('instructions', ''));

        $markdown = $this->buildMasterPromptMarkdown($title, $description, $content);
        $resolved = Skill::resolveTemplate($markdown);

        return response()->json([
            'markdown' => $resolved,
        ]);
    }

    protected function buildMasterPromptMarkdown(?string $title, ?string $description, ?string $content): string
    {
        $title = trim((string) $title);
        $description = trim((string) $description);
        $content = trim((string) $content);

        $parts = [];

        // If content does not start with a top-level header (# ...), include title
        if ($title !== '' && ! preg_match('/^#\s+/m', $content)) {
            $parts[] = "# {$title}";
        }

        // Include description as blockquote if provided and not already included in content
        if ($description !== '' && ! str_contains($content, $description)) {
            $parts[] = "> {$description}";
        }

        if ($content !== '') {
            $parts[] = $content;
        }

        return implode("\n\n", $parts);
    }
}
