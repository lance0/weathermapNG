<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup;

class LookupController
{
    public function devices(Request $request, DevicePortLookup $lookup): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            return response()->json($lookup->deviceAutocomplete($q));
        }

        return response()->json($lookup->getAllDevices());
    }

    public function ports(int $id, DevicePortLookup $lookup): JsonResponse
    {
        $q = trim((string) request()->query('q', ''));
        $ports = $lookup->portsForDevice($id);
        if ($q !== '') {
            $lq = strtolower($q);
            $ports = array_values(array_filter($ports, function ($p) use ($lq) {
                $name = strtolower((string)($p['ifName'] ?? ''));
                $idx = strtolower((string)($p['ifIndex'] ?? ''));
                return str_contains($name, $lq) || str_contains($idx, $lq);
            }));
        }
        return response()->json([
            'device_id' => $id,
            'ports' => $ports,
        ]);
    }
}
