<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup;

class LookupController
{
    public function devices(Request $request, DevicePortLookup $lookup): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if ($query !== '') {
            return response()->json($lookup->deviceAutocomplete($query));
        }

        return response()->json($lookup->getAllDevices());
    }

    public function ports(int $id, DevicePortLookup $lookup): JsonResponse
    {
        $query = trim((string) request()->query('q', ''));
        $ports = $lookup->portsForDevice($id);
        if ($query !== '') {
            $lowercaseQuery = strtolower($query);
            $ports = array_values(array_filter($ports, function ($port) use ($lowercaseQuery) {
                $name = strtolower((string)($port['ifName'] ?? ''));
                $idx = strtolower((string)($port['ifIndex'] ?? ''));
                return str_contains($name, $lowercaseQuery) || str_contains($idx, $lowercaseQuery);
            }));
        }
        return response()->json([
            'device_id' => $id,
            'ports' => $ports,
        ]);
    }
}
