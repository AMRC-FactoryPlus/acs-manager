<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Nodes\Actions;

use App\Domain\Devices\Models\Device;
use App\Domain\Nodes\Models\Node;
use App\Domain\Support\Actions\MakeConsumptionFrameworkRequest;
use App\EdgeAgentConfiguration;
use App\Exceptions\ActionFailException;
use App\Exceptions\ActionForbiddenException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class UpdateEdgeAgentConfigurationForNodeAction
{
    /**
     * This action creates a EdgeAgentConfiguration for the supplied node, uploads it to storage and sets the `configuration_file` on
     * the node. The device must have an active connection and an active configuration otherwise this doesn't run.
     **/
    public function execute(Node $node)
    {
        // =========================
        // Validate User Permissions
        // =========================
        if ((! auth()->user()->administrator) && ! in_array(
            $node->id,
            auth()
                ->user()
                ->accessibleNodes()
                ->get()
                ->pluck('id')
                ->all(),
            true
        )) {
            throw new ActionForbiddenException('You do not have permission to generate edge agent configurations for this node.');
        }

        // ===================
        // Perform the Action
        // ==================

        $config = [
            '$schema' => 'https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/Edge_Agent_Config.json',
            'sparkplug' => [
                'serverUrl' => config('manager.mqtt_server_from_edge'),
                'groupId' => $node->group->name,
                'edgeNode' => $node->node_id,
                'username' => $node->principal,
                'password' => '__mqtt-password__', // This is replaced with the keytab at the edge
                'asyncPubMode' => true,
            ],
            'deviceConnections' => [],
        ];

        $node->refresh();

        foreach ($node->deviceConnections as $deviceConnection) {
            $devices = $deviceConnection->devices()
                                        ->where('node_id', $node->id)
                                        ->whereNotNull('device_id')
                                        ->whereHas('activeOriginMap')
                                        ->whereHas('deviceConnection')
                                        ->with(
                                            'activeOriginMap',
                                            'deviceConnection'
                                        )
                                        ->get();

            // Don't bother writing the connection to the configuration if we have no devices using it
            if ($devices->count() === 0) {
                continue;
            }

            // Get the connection .json file from storage and add the data here
            $deviceConnectionConfig = json_decode(
                Storage::disk('device-connections')
                       ->get($deviceConnection->file),
                false,
                512,
                JSON_THROW_ON_ERROR
            );

            // Get our index in the array, which is the size before we're added.
            $newIndex = count($config['deviceConnections']);
            $config['deviceConnections'][] = $deviceConnectionConfig;

            foreach (
                $devices as $device
            ) {
                // Get the device configuration .json file from storage and add the data here
                $deviceConfiguration = json_decode(
                    Storage::disk('device-configurations')
                           ->get($device->activeOriginMap->file),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $it = new RecursiveIteratorIterator(
                    new RecursiveArrayIterator($deviceConfiguration), RecursiveIteratorIterator::SELF_FIRST
                );
                $it->next();

                $tags = [];

                foreach ($it as $key => $v) {
                    $path = [];
                    foreach (range(0, $it->getDepth()) as $depth) {
                        $path[] = $it->getSubIterator($depth)
                                     ->key();
                    }

                    if (is_array($v) && array_key_exists('Sparkplug_Type', $v)) {
                        // If we have an array that contains a metric then extract it and only include properties that are not null to keep the size down
                        $tags[] = (object) array_filter(
                            [
                                'Name' => implode('/', $path),
                                'type' => $v['Sparkplug_Type'],
                                'method' => $v['Method'] ?? 'GET',
                                'address' => $v['Address'] ?? null,
                                'path' => $v['Path'] ?? null,
                                'value' => $v['Value'] ?? null,
                                'engUnit' => $v['Eng_Unit'] ?? null,
                                'engLow' => $v['Eng_Low'] ?? null,
                                'engHigh' => $v['Eng_High'] ?? null,
                                'deadBand' => $v['Deadband'] ?? null,
                                'tooltip' => $v['Tooltip'] ?? null,
                                'docs' => $v['Documentation'] ?? null,
                                'recordToDB' => $v['Record_To_Historian'],
                            ], static function ($val) {
                                return $val;
                            }
                        );
                    } elseif ($key === 'Schema_UUID') {
                        $tags[] = (object) [
                            'type' => 'UUID',
                            'method' => 'GET',
                            'value' => $v,
                            'Name' => implode('/', $path),
                            'docs' => 'A reference to the schema used for this object.',
                            'recordToDB' => true,
                        ];
                    } elseif ($key === 'Instance_UUID') {
                        $tags[] = (object) [
                            'type' => 'UUID',
                            'method' => 'GET',
                            'value' => $v,
                            'Name' => implode('/', $path),
                            'docs' => 'A reference to the instance of this object.',
                            'recordToDB' => true,
                        ];
                    }
                }

                $config['deviceConnections'][$newIndex]->devices[] = (object) [
                    'deviceId' => $device->device_id,
                    'deviceType' => $deviceConfiguration['Schema_UUID'],
                    'templates' => [],
                    'tags' => $tags,

                    // We currently copy across the same pollInt and payloadFormat to every device from the deviceConnection. If the need arises we can
                    // natively move these to properties of the device.
                    'pollInt' => $deviceConnectionConfig->pollInt,
                    'payloadFormat' => $deviceConnectionConfig->payloadFormat ?? 'Defined by Protocol',
                ];
            }
        }

        // Get all devices for this node that have an active origin map and an active device config and build the edge agent config
        $config = json_decode(json_encode($config, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        if (count($config->deviceConnections) < 1) {
            return action_success();
        }

        // Validate the resulting config against the edge agent schema
        $validator = new Validator;
        $validator->resolver()
                  ->registerRaw(
                      file_get_contents('https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/Edge_Agent_Config.json'),
                      'https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/Edge_Agent_Config.json'
                  );
        $validated = $validator->validate(
            $config,
            'https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/Edge_Agent_Config.json'
        );
        if (! $validated->isValid()) {
            // Get the error
            $error = $validated->error();

            // Create an error formatter
            $formatter = new ErrorFormatter;

            $custom = function (ValidationError $error) use ($formatter) {
                return [
                    'keyword' => $error->keyword(),
                    'message' => $formatter->formatErrorMessage($error),

                    'dataPath' => $formatter->formatErrorKey($error),
                    'dataValue' => $error->data()
                                         ->value(),
                ];
            };

            $errors = $formatter->formatFlat($error, $custom);
            Log::error(
                'Edge Agent config failed schema validation!', [
                    'node' => $node->id,
                    'error' => $errors[count($errors) - 1],
                ]
            );

            throw new ActionFailException('Failed to validate Edge Agent configuration.');
        }

        // Generate a unique filename for the config
        $filename = uniqid('', true) . '.json';
        \Storage::disk('edge-agent-configs')
                ->put($filename, json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        EdgeAgentConfiguration::create(
            [
                'node_id' => $node->id,
                'file' => $filename,
            ]
        );

        if (! in_array(config('app.env'), ['local', 'testing'])) {
            // Ask the edge agent to reload the config
            (new MakeConsumptionFrameworkRequest)->execute(
                type: 'post',
                service: 'cmdesc',
                url: config('manager.cmdesc_service_url') . '/v1/address/' . $node->group->name . '/' . $node->node_id,
                payload: [
                    'name' => 'Node Control/Reload Edge Agent Config',
                    'value' => true,
                ]
            );
        }

        return action_success();
    }
}
