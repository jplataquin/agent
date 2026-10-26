<?php

namespace Database\Seeders;

use App\Models\LlmProvider;
use App\Models\MasterPrompt;
use App\Models\McpServer;
use App\Models\Skill;
use App\Models\User;
use App\Services\BuiltInToolsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin User
        User::firstOrCreate(
            ['email' => 'admin@company.com'],
            [
                'name' => 'Company Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        // Regular User
        User::firstOrCreate(
            ['email' => 'user@company.com'],
            [
                'name' => 'Jane Employee',
                'password' => Hash::make('password'),
                'role' => User::ROLE_REGULAR,
            ]
        );

        // Master Prompt
        MasterPrompt::firstOrCreate(
            ['title' => 'Default Company AI Agent Persona'],
            [
                'description' => 'Primary system instructions for all company AI chats.',
                'content' => 'You are the Company AI Agent, an intelligent, helpful, and secure workplace assistant. Your goal is to assist employees with information, problem-solving, and workflow automation. You have access to internal company systems and live web tools via HTTP MCP servers when needed. Be concise, professional, and clear in your explanations.',
                'is_active' => true,
            ]
        );

        // Default Skills
        Skill::firstOrCreate(
            ['slug' => 'it-troubleshooting'],
            [
                'name' => 'IT & Software Support',
                'description' => 'Guidelines on resolving internal tech, VPN, and credential issues.',
                'instructions' => "When users ask about internal technical problems, guide them through standard company protocols: check SSO credentials, confirm VPN connection to 'vpn.company.internal', and if unresolved, provide a link to submit a Jira service ticket.",
                'is_active' => true,
            ]
        );

        Skill::firstOrCreate(
            ['slug' => 'company-policy-lookup'],
            [
                'name' => 'HR & Company Policies',
                'description' => 'Assists with leaves, benefits, and workplace standards.',
                'instructions' => 'Reference standard corporate benefits, paid time off procedures (request via BambooHR 2 weeks in advance), and remote work allowances.',
                'is_active' => true,
            ]
        );

        // Ready-made Built-in Web & System MCP Server
        $builtInTools = app(BuiltInToolsService::class)->getToolsDeclaration();
        McpServer::updateOrCreate(
            ['name' => 'Built-in Web & System MCP'],
            [
                'endpoint_url' => url('/api/mcp'),
                'auth_type' => 'bearer',
                'auth_token' => 'builtin_local_token_xyz',
                'description' => 'Native built-in MCP server providing live web fetching, web searching, and internal company directory lookup.',
                'tools_cache' => $builtInTools,
                'is_active' => true,
            ]
        );

        // Sample External HTTP MCP Server
        McpServer::firstOrCreate(
            ['name' => 'Internal Knowledgebase MCP'],
            [
                'endpoint_url' => 'https://mcp.company.internal/api/v1/mcp',
                'auth_type' => 'bearer',
                'auth_token' => 'sample_mcp_secure_token_xyz',
                'description' => 'HTTP MCP server allowing search queries over internal documentation and wiki pages.',
                'tools_cache' => [
                    [
                        'name' => 'search_internal_docs',
                        'description' => 'Search company confluence and policy docs for a given query term',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => [
                                    'type' => 'string',
                                    'description' => 'The search query or keyword',
                                ],
                            ],
                            'required' => ['query'],
                        ],
                    ],
                ],
                'is_active' => true,
            ]
        );

        // Default LLM Provider
        LlmProvider::firstOrCreate(
            ['name' => 'OpenAI GPT-4o'],
            [
                'provider_type' => 'openai',
                'api_key' => 'sk-mock-key-please-replace-in-admin-settings',
                'model' => 'gpt-4o',
                'base_url' => 'https://api.openai.com/v1',
                'temperature' => 0.70,
                'max_tokens' => 2048,
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }
}
