<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\MapTemplate;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\AdminCheck;

class MapTemplateController extends Controller
{
    use AdminCheck;

    public function index(): JsonResponse
    {
        $templates = MapTemplate::all();

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $template = MapTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'template' => $template,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'width' => 'integer|min:100|max:4096',
            'height' => 'integer|min:100|max:4096',
            'icon' => 'string|max:50',
            'category' => 'string|max:50',
            'config' => 'array',
        ]);

        $validated['is_built_in'] = false;
        $validated['icon'] = $validated['icon'] ?? 'fas fa-map';

        $template = MapTemplate::create($validated);

        return response()->json([
            'success' => true,
            'template' => $template,
            'message' => 'Template created successfully',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireAdmin();
        $template = MapTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        if ($template->is_built_in) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify built-in templates',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'template' => $template,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();
        $template = MapTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        if ($template->is_built_in) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete built-in templates',
            ], 403);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    public function createFromTemplate(Request $request, int $id): JsonResponse
    {
        $this->requireAdmin();
        $template = MapTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:wmng_maps,name',
        ]);

        $config = $this->decodeTemplateConfig($template->config);
        if ($config === null) {
            return response()->json([
                'success' => false,
                'message' => 'Template config is not valid JSON',
            ], 400);
        }

        $configError = $this->validateTemplateConfig($config);
        if ($configError !== null) {
            return response()->json([
                'success' => false,
                'message' => $configError,
            ], 400);
        }

        $map = null;
        try {
            DB::transaction(function () use ($validated, $template, $config, &$map) {
                $options = $config;
                $options['width'] = $template->width;
                $options['height'] = $template->height;

                $map = Map::create([
                    'name' => $validated['name'],
                    'title' => $template->title,
                    'options' => $options,
                ]);

                $nodeIds = [];
                foreach ($config['default_nodes'] as $nodeData) {
                    $node = Node::create([
                        'map_id' => $map->id,
                        'label' => $nodeData['label'] ?? 'Node',
                        'x' => $nodeData['x'] ?? 0,
                        'y' => $nodeData['y'] ?? 0,
                        'device_id' => null,
                    ]);
                    $nodeIds[] = $node->id;
                }

                foreach ($config['default_links'] as $linkData) {
                    $srcIdx = $linkData['src_node_idx'] ?? null;
                    $dstIdx = $linkData['dst_node_idx'] ?? null;

                    if (!array_key_exists($srcIdx, $nodeIds) || !array_key_exists($dstIdx, $nodeIds)) {
                        throw new \InvalidArgumentException(
                            "Link references invalid node index (src={$srcIdx}, dst={$dstIdx})"
                        );
                    }

                    Link::create([
                        'map_id' => $map->id,
                        'src_node_id' => $nodeIds[$srcIdx],
                        'dst_node_id' => $nodeIds[$dstIdx],
                    ]);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create map from template: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'map' => $map,
            'message' => 'Map created from template',
        ], 201);
    }

    private function decodeTemplateConfig($config): ?array
    {
        if (is_array($config)) {
            return $config;
        }

        $decoded = json_decode($config, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function validateTemplateConfig(array $config): ?string
    {
        if (!array_key_exists('default_nodes', $config) || !is_array($config['default_nodes'])) {
            return 'Template config must contain a "default_nodes" array';
        }

        if (!array_key_exists('default_links', $config) || !is_array($config['default_links'])) {
            return 'Template config must contain a "default_links" array';
        }

        foreach ($config['default_nodes'] as $i => $node) {
            if (!is_array($node)) {
                return "default_nodes[$i] must be an object";
            }
        }

        foreach ($config['default_links'] as $i => $link) {
            if (!is_array($link)) {
                return "default_links[$i] must be an object";
            }
            if (!array_key_exists('src_node_idx', $link) || !array_key_exists('dst_node_idx', $link)) {
                return "default_links[$i] must contain src_node_idx and dst_node_idx";
            }
        }

        return null;
    }
}
