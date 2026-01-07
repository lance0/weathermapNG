<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use LibreNMS\Plugins\WeathermapNG\Models\MapTemplate;

class MapTemplateController extends Controller
{
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

        $mapData = [
            'name' => $validated['name'],
            'title' => $template->title,
            'width' => $template->width,
            'height' => $template->height,
            'options' => $template->config,
        ];

        $map = Map::create($mapData);

        $config = json_decode($template->config, true);

        if (isset($config['default_nodes'])) {
            foreach ($config['default_nodes'] as $idx => $nodeData) {
                Node::create([
                    'map_id' => $map->id,
                    'label' => $nodeData['label'],
                    'x' => $nodeData['x'],
                    'y' => $nodeData['y'],
                    'device_id' => null,
                ]);
            }
        }

        if (isset($config['default_links'])) {
            $nodeIds = DB::table('wmng_nodes')
                ->where('map_id', $map->id)
                ->pluck('id')
                ->toArray();

            foreach ($config['default_links'] as $linkData) {
                if (isset($nodeIds[$linkData['src_node_idx']]) && isset($nodeIds[$linkData['dst_node_idx']])) {
                    Link::create([
                        'map_id' => $map->id,
                        'src_node_id' => $nodeIds[$linkData['src_node_idx']],
                        'dst_node_id' => $nodeIds[$linkData['dst_node_idx']],
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'map' => $map,
            'message' => 'Map created from template',
        ], 201);
    }
}
