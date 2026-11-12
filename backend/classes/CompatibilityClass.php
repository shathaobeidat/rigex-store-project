<?php
declare(strict_types=1);

class CompatibilityClass
{
    private function checkSpecificationsStatus(
        array $components,
        array &$checks
    ): void {
        foreach ($components as $type => $component) {

            if (($component['spec_data_valid'] ?? false) !== true) {
                $this->addCheck(
                    $checks,
                    'needs_verification',
                    "{$type}_specifications",
                    "Specifications data for {$type} is invalid."
                );

                continue;
            }

            if (($component['spec_status'] ?? null) !== 'verified') {
                $this->addCheck(
                    $checks,
                    'needs_verification',
                    "{$type}_specifications",
                    "Specifications for {$type} are not fully verified."
                );
            }
        }
    }

    public function validateBuild(array $components): array
    {
        $components = $this->indexComponents($components);

        $checks = [];
        $this->checkSpecificationsStatus($components, $checks);


        // Storage <-> Motherboard
        if (
            isset(
            $components['storage'],
            $components['motherboard']
        )
        ) {
            $this->checkStorageMotherboard(
                $components['storage'],
                $components['motherboard'],
                $checks
            );
        }

        // CPU <-> Motherboard
        if (isset($components['cpu'], $components['motherboard'])) {
            $this->checkCpuSocket(
                $components['cpu'],
                $components['motherboard'],
                $checks
            );
        }

        // RAM <-> Motherboard
        if (isset($components['ram'], $components['motherboard'])) {
            $this->checkRamType(
                $components['ram'],
                $components['motherboard'],
                $checks
            );
        }

        // RAM Speed: CPU + RAM + Motherboard
        if (
            isset(
            $components['cpu'],
            $components['ram'],
            $components['motherboard']
        )
        ) {
            $this->checkRamSpeed(
                $components['cpu'],
                $components['ram'],
                $components['motherboard'],
                $checks
            );
        }

        // Motherboard <-> Case
        if (isset($components['motherboard'], $components['case'])) {
            $this->checkMotherboardCase(
                $components['motherboard'],
                $components['case'],
                $checks
            );
        }

        // GPU <-> Case
        if (isset($components['gpu'], $components['case'])) {
            $this->checkGpuCase(
                $components['gpu'],
                $components['case'],
                $checks
            );
        }

        // GPU <-> PSU
        if (isset($components['gpu'], $components['psu'])) {
            $this->checkGpuPsu(
                $components['gpu'],
                $components['psu'],
                $checks
            );
        }

        // CPU <-> Cooler
        if (isset($components['cpu'], $components['cooler'])) {
            $this->checkCpuCooler(
                $components['cpu'],
                $components['cooler'],
                $checks
            );
        }

        return $this->buildResult($checks);
    }

    /*
    |--------------------------------------------------------------------------
    | Compatibility Rules
    |--------------------------------------------------------------------------
    */

    private function checkStorageMotherboard(
        array $storage,
        array $motherboard,
        array &$checks
    ): void {
        $storageSpecs = $this->getSpecifications($storage);
        $motherboardSpecs = $this->getSpecifications($motherboard);

        $interface = $this->getValue(
            $storageSpecs,
            ['interface', 'storage_interface']
        );

        $formFactor = $this->getValue(
            $storageSpecs,
            ['form_factor']
        );

        if ($interface === null) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'storage_interface',
                'Storage interface is missing.'
            );

            return;
        }

        $interface = strtolower((string) $interface);

        if (
            str_contains($interface, 'nvme') ||
            str_contains($interface, 'pcie')
        ) {
            $slots = $this->getNumericValue(
                $motherboardSpecs,
                ['m2_nvme_slots']
            );

            if ($slots === null) {
                $this->addCheck(
                    $checks,
                    'needs_verification',
                    'storage_interface',
                    'Motherboard NVMe slot information is missing.'
                );
            } elseif ($slots <= 0) {
                $this->addCheck(
                    $checks,
                    'incompatible',
                    'storage_interface',
                    'Motherboard does not have an available NVMe M.2 slot.'
                );
            } else {
                $this->addCheck(
                    $checks,
                    'compatible',
                    'storage_interface',
                    'Motherboard has NVMe M.2 slots for the selected storage interface.'
                );
            }

            if (
                $formFactor !== null &&
                stripos((string) $formFactor, 'M.2') !== false
            ) {
                $supportedSizes = $this->getValue(
                    $motherboardSpecs,
                    ['m2_supported_sizes', 'supported_m2_form_factors']
                );

                if ($supportedSizes === null) {
                    $this->addCheck(
                        $checks,
                        'needs_verification',
                        'storage_form_factor',
                        'Motherboard supported M.2 sizes are not specified.'
                    );
                }
            }

            return;
        }

        if (str_contains($interface, 'sata')) {
            $sataPorts = $this->getNumericValue(
                $motherboardSpecs,
                ['sata_ports']
            );

            if ($sataPorts === null) {
                $this->addCheck(
                    $checks,
                    'needs_verification',
                    'storage_interface',
                    'Motherboard SATA port information is missing.'
                );
            } elseif ($sataPorts <= 0) {
                $this->addCheck(
                    $checks,
                    'incompatible',
                    'storage_interface',
                    'Motherboard does not have SATA ports.'
                );
            } else {
                $this->addCheck(
                    $checks,
                    'compatible',
                    'storage_interface',
                    'Motherboard has SATA ports for the selected storage device.'
                );
            }

            return;
        }

        $this->addCheck(
            $checks,
            'needs_verification',
            'storage_interface',
            'Storage interface is not recognized by the compatibility checker.'
        );
    }

    private function checkCpuSocket(
        array $cpu,
        array $motherboard,
        array &$checks
    ): void {
        $cpuSpecs = $this->getSpecifications($cpu);
        $motherboardSpecs = $this->getSpecifications($motherboard);

        $cpuSocket = $this->getValue(
            $cpuSpecs,
            ['socket', 'cpu_socket', 'socket_type']
        );

        $motherboardSocket = $this->getValue(
            $motherboardSpecs,
            ['socket', 'cpu_socket', 'socket_type']
        );

        if ($cpuSocket === null || $motherboardSocket === null) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'cpu_socket',
                'CPU أو Motherboard socket specification is missing.'
            );

            return;
        }

        $compatible = $this->normalizeValue($cpuSocket)
            === $this->normalizeValue($motherboardSocket);

        $this->addCheck(
            $checks,
            $compatible ? 'compatible' : 'incompatible',
            'cpu_socket',
            $compatible
            ? 'CPU socket matches the motherboard socket.'
            : 'CPU socket does not match the motherboard socket.'
        );
    }

    private function checkRamType(
        array $ram,
        array $motherboard,
        array &$checks
    ): void {
        $ramSpecs = $this->getSpecifications($ram);
        $motherboardSpecs = $this->getSpecifications($motherboard);

        $ramType = $this->getValue(
            $ramSpecs,
            ['memory_type', 'ram_type', 'ddr_generation']
        );

        $motherboardRamType = $this->getValue(
            $motherboardSpecs,
            ['memory_type', 'ram_type', 'supported_memory_type']
        );

        if ($ramType === null || $motherboardRamType === null) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'ram_type',
                'RAM or motherboard memory type is missing.'
            );

            return;
        }

        $ramGeneration = $this->extractDdrGeneration($ramType);
        $motherboardGeneration = $this->extractDdrGeneration(
            $motherboardRamType
        );

        if ($ramGeneration === null || $motherboardGeneration === null) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'ram_type',
                'Could not identify the RAM generation from the specifications.'
            );

            return;
        }

        $compatible = $ramGeneration === $motherboardGeneration;

        $this->addCheck(
            $checks,
            $compatible ? 'compatible' : 'incompatible',
            'ram_type',
            $compatible
            ? 'RAM generation matches the motherboard.'
            : 'RAM generation does not match the motherboard.'
        );
    }

    private function checkRamSpeed(
        array $cpu,
        array $ram,
        array $motherboard,
        array &$checks
    ): void {
        $cpuSpecs = $this->getSpecifications($cpu);
        $ramSpecs = $this->getSpecifications($ram);
        $motherboardSpecs = $this->getSpecifications($motherboard);

        $cpuMaxSpeed = $this->getNumericValue(
            $cpuSpecs,
            ['max_memory_speed_mts']
        );

        $ramSpeed = $this->getNumericValue(
            $ramSpecs,
            ['speed_mts']
        );

        $motherboardMaxSpeed = $this->getNumericValue(
            $motherboardSpecs,
            ['max_memory_speed_mts']
        );

        if (
            $cpuMaxSpeed === null ||
            $ramSpeed === null ||
            $motherboardMaxSpeed === null
        ) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'ram_speed',
                'CPU, RAM or motherboard memory speed information is missing.'
            );

            return;
        }

        if ($ramSpeed > $motherboardMaxSpeed) {
            $this->addCheck(
                $checks,
                'incompatible',
                'ram_speed',
                'RAM speed exceeds the motherboard specified maximum memory speed.'
            );

            return;
        }

        if ($ramSpeed > $cpuMaxSpeed) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'ram_speed',
                'RAM speed exceeds the CPU officially specified memory speed. Memory overclocking configuration and stability must be verified.'
            );

            return;
        }

        $this->addCheck(
            $checks,
            'compatible',
            'ram_speed',
            'RAM speed is within the specified CPU and motherboard memory speed limits.'
        );
    }

    private function checkMotherboardCase(
        array $motherboard,
        array $case,
        array &$checks
    ): void {
        $motherboardSpecs = $this->getSpecifications($motherboard);
        $caseSpecs = $this->getSpecifications($case);

        $motherboardFormFactor = $this->getValue(
            $motherboardSpecs,
            ['form_factor', 'motherboard_form_factor']
        );

        $supportedFormFactors = $this->getValue(
            $caseSpecs,
            [
                'motherboard_form_factors',
                'supported_motherboard_form_factors',
                'motherboard_support',
                'supported_motherboards'
            ]
        );

        if (
            $motherboardFormFactor === null ||
            $supportedFormFactors === null
        ) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'motherboard_case',
                'Motherboard form factor or case motherboard support is missing.'
            );

            return;
        }

        $supported = $this->toValueArray($supportedFormFactors);

        $compatible = false;

        foreach ($supported as $supportedFactor) {
            if (
                $this->normalizeValue($motherboardFormFactor) ===
                $this->normalizeValue($supportedFactor)
            ) {
                $compatible = true;
                break;
            }
        }

        $this->addCheck(
            $checks,
            $compatible ? 'compatible' : 'incompatible',
            'motherboard_case',
            $compatible
            ? 'Motherboard form factor is supported by the case.'
            : 'Case does not list support for this motherboard form factor.'
        );
    }

    private function checkGpuCase(
        array $gpu,
        array $case,
        array &$checks
    ): void {
        $gpuSpecs = $this->getSpecifications($gpu);
        $caseSpecs = $this->getSpecifications($case);

        $gpuLength = $this->getNumericValue(
            $gpuSpecs,
            ['length_mm', 'gpu_length_mm', 'card_length_mm']
        );

        $maxGpuLength = $this->getNumericValue(
            $caseSpecs,
            ['max_gpu_length_mm', 'maximum_gpu_length_mm']
        );

        if ($gpuLength === null || $maxGpuLength === null) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'gpu_case',
                'GPU length or case maximum GPU clearance is missing.'
            );

            return;
        }

        $compatible = $gpuLength <= $maxGpuLength;

        $this->addCheck(
            $checks,
            $compatible ? 'compatible' : 'incompatible',
            'gpu_case',
            $compatible
            ? 'GPU length fits within the case clearance.'
            : 'GPU is longer than the case maximum GPU clearance.'
        );
    }

    private function checkGpuPsu(
        array $gpu,
        array $psu,
        array &$checks
    ): void {
        $gpuSpecs = $this->getSpecifications($gpu);
        $psuSpecs = $this->getSpecifications($psu);

        $recommendedWattage = $this->getNumericValue(
            $gpuSpecs,
            ['recommended_psu_wattage', 'recommended_psu_w']
        );

        $psuWattage = $this->getNumericValue(
            $psuSpecs,
            ['wattage', 'wattage_w', 'power_output_w']
        );

        if ($recommendedWattage === null || $psuWattage === null) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'gpu_psu',
                'GPU recommended PSU wattage or PSU wattage is missing.'
            );

            return;
        }

        $compatible = $psuWattage >= $recommendedWattage;

        $this->addCheck(
            $checks,
            $compatible ? 'compatible' : 'incompatible',
            'gpu_psu',
            $compatible
            ? 'PSU wattage meets the GPU manufacturer recommendation.'
            : 'PSU wattage is below the GPU manufacturer recommendation.'
        );
    }

    private function checkCpuCooler(
        array $cpu,
        array $cooler,
        array &$checks
    ): void {
        $cpuSpecs = $this->getSpecifications($cpu);
        $coolerSpecs = $this->getSpecifications($cooler);

        $cpuSocket = $this->getValue(
            $cpuSpecs,
            ['socket', 'cpu_socket', 'socket_type']
        );

        $coolerType = $this->getValue(
            $coolerSpecs,
            ['cooler_type']
        );

        $isCpuCooler = $this->getValue(
            $coolerSpecs,
            ['cpu_cooler', 'is_cpu_cooler']
        );

        // A case fan is not a CPU cooler.
        if (
            $this->normalizeValue($coolerType ?? '') === 'case_fan' ||
            $this->normalizeBoolean($isCpuCooler) === false
        ) {
            $this->addCheck(
                $checks,
                'incompatible',
                'cpu_cooler',
                'The selected cooling product is not a CPU cooler.'
            );

            return;
        }

        $supportedSockets = $this->getValue(
            $coolerSpecs,
            ['supported_sockets', 'socket_support', 'compatible_sockets']
        );

        if ($cpuSocket === null || $supportedSockets === null) {
            $this->addCheck(
                $checks,
                'needs_verification',
                'cpu_cooler',
                'CPU socket or cooler supported sockets are missing.'
            );

            return;
        }

        $supported = $this->toValueArray($supportedSockets);
        $compatible = false;

        foreach ($supported as $socket) {
            if (
                $this->normalizeValue($cpuSocket) ===
                $this->normalizeValue($socket)
            ) {
                $compatible = true;
                break;
            }
        }

        $this->addCheck(
            $checks,
            $compatible ? 'compatible' : 'incompatible',
            'cpu_cooler',
            $compatible
            ? 'CPU socket is supported by the selected cooler.'
            : 'Selected cooler does not list support for this CPU socket.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function indexComponents(array $components): array
    {
        $indexed = [];

        foreach ($components as $key => $component) {
            if (!is_array($component)) {
                continue;
            }

            $type = $component['component_type']
                ?? (is_string($key) ? $key : null);

            if ($type === null) {
                continue;
            }

            $indexed[strtolower(trim((string) $type))] = $component;
        }

        return $indexed;
    }

    private function getSpecifications(array $component): array
    {
        $specifications = $component['specifications'] ?? [];

        if (is_string($specifications)) {
            $decoded = json_decode($specifications, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($specifications) ? $specifications : [];
    }

    private function getValue(array $specifications, array $keys)
    {
        foreach ($keys as $key) {
            if (
                array_key_exists($key, $specifications) &&
                $specifications[$key] !== null &&
                $specifications[$key] !== ''
            ) {
                return $specifications[$key];
            }
        }

        return null;
    }

    private function getNumericValue(
        array $specifications,
        array $keys
    ): ?float {
        $value = $this->getValue($specifications, $keys);

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (
            is_string($value) &&
            preg_match('/\d+(?:\.\d+)?/', $value, $matches)
        ) {
            return (float) $matches[0];
        }

        return null;
    }

    private function extractDdrGeneration($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (preg_match('/DDR\s*([3-6])/i', $value, $matches)) {
            return 'ddr' . $matches[1];
        }

        return null;
    }

    private function normalizeValue($value): string
    {
        return strtolower(
            preg_replace('/[\s_-]+/', '', trim((string) $value))
        );
    }

    private function normalizeBoolean($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }

    private function toValueArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return [];
        }

        return array_filter(
            array_map('trim', preg_split('/[,;|]/', $value))
        );
    }

    private function addCheck(
        array &$checks,
        string $status,
        string $code,
        string $message
    ): void {
        $checks[] = [
            'code' => $code,
            'status' => $status,
            'message' => $message
        ];
    }

    private function buildResult(array $checks): array
    {
        $hasIncompatible = false;
        $hasVerificationNeeded = false;

        foreach ($checks as $check) {
            if ($check['status'] === 'incompatible') {
                $hasIncompatible = true;
            }

            if ($check['status'] === 'needs_verification') {
                $hasVerificationNeeded = true;
            }
        }

        if ($hasIncompatible) {
            $status = 'incompatible';
            $summary = 'One or more compatibility conflicts were found.';
        } elseif ($hasVerificationNeeded || empty($checks)) {
            $status = 'needs_verification';
            $summary = 'Some compatibility details are missing and require verification.';
        } else {
            $status = 'compatible';
            $summary = 'No conflicts were found in the compatibility checks performed.';
        }

        return [
            'status' => $status,
            'summary' => $summary,
            'checks' => $checks
        ];
    }
}