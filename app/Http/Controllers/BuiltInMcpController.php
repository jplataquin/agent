<?php

namespace App\Http\Controllers;

use App\Services\BuiltInToolsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuiltInMcpController extends Controller
{
    public function __construct(
        protected BuiltInToolsService $toolsService
    ) {}

    /**
     * Handle incoming JSON-RPC 2.0 MCP requests.
     */
    public function handle(Request $request): JsonResponse
    {
        $id = $request->input('id', 1);
        $method = $request->input('method', '');
        $params = $request->input('params', []);

        switch ($method) {
            case 'tools/list':
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'tools' => $this->toolsService->getToolsDeclaration(),
                    ],
                ]);

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $arguments = (array) ($params['arguments'] ?? []);

                $result = $this->toolsService->execute($toolName, $arguments);

                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'content' => $result['output'] ?? '',
                        'isError' => ! ($result['success'] ?? false),
                    ],
                ]);

            case 'initialize':
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [
                            'tools' => (object) [],
                        ],
                        'serverInfo' => [
                            'name' => 'Laravel Built-in MCP Server',
                            'version' => '1.0.0',
                        ],
                    ],
                ]);

            default:
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => "Method '{$method}' not found.",
                    ],
                ], 404);
        }
    }
}
