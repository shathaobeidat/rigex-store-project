<?php
declare(strict_types=1);

class PerformanceRankingClass
{
    private const GAMING_WEIGHTS = [
        'gpu' => 0.80,
        'cpu_single_thread' => 0.20
    ];

    private const GENERAL_USE_WEIGHTS = [
        'cpu_single_thread' => 0.70,
        'cpu_multi_thread' => 0.30
    ];

    /*
    |--------------------------------------------------------------------------
    | RANK BUILDS
    |--------------------------------------------------------------------------
    | Scores are normalized against the best candidate in the current
    | search, not against a universal hardware performance scale.
    */

    public function rankBuilds(
        array $builds,
        string $purpose,
        string $resolution = '1440p'
    ): array {

        $purpose = $this->normalizePurpose($purpose);
        $resolution = $this->normalizeResolution($resolution);

        if (empty($builds)) {
            return [];
        }

        $metricsByBuild = [];

        $maxima = [
            'gpu_fps' => 0.0,
            'cpu_single_thread' => 0.0,
            'cpu_multi_thread' => 0.0
        ];

        /*
        |--------------------------------------------------------------------------
        | STEP 1: EXTRACT RAW BENCHMARKS
        |--------------------------------------------------------------------------
        */

        foreach ($builds as $index => $build) {

            $components = $build['components'] ?? [];

            $cpu = $components['cpu'] ?? [];
            $gpu = $components['gpu'] ?? [];

            $cpuBenchmarks = $this->extractCpuBenchmarks($cpu);
            $gpuBenchmark = $this->extractGpuBenchmark(
                $gpu,
                $resolution
            );

            $metricsByBuild[$index] = ['gpu_fps' => $gpuBenchmark,
                'cpu_single_thread' =>
                    $cpuBenchmarks['single_thread'] ?? null,
                'cpu_multi_thread' =>
                    $cpuBenchmarks['multi_thread'] ?? null
            ];

            foreach ($metricsByBuild[$index] as $metricName => $metric) {
                if (
                    is_array($metric) &&
                    isset($metric['value']) &&
                    $metric['value'] > $maxima[$metricName]
                ) {
                    $maxima[$metricName] = (float) $metric['value'];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 2: NORMALIZE AND CALCULATE SCORES
        |--------------------------------------------------------------------------
        */

        $rankedBuilds = [];

        foreach ($builds as $index => $build) {

            $raw = $metricsByBuild[$index];

            $gpuMetric = $raw['gpu_fps'];
            $cpuSingleMetric = $raw['cpu_single_thread'];
            $cpuMultiMetric = $raw['cpu_multi_thread'];

            $gpuNormalized = $this->normalizeMetric(
                $gpuMetric,
                $maxima['gpu_fps']
            );

            $cpuSingleNormalized = $this->normalizeMetric(
                $cpuSingleMetric,
                $maxima['cpu_single_thread']
            );

            $cpuMultiNormalized = $this->normalizeMetric(
                $cpuMultiMetric,
                $maxima['cpu_multi_thread']
            );

            $score = null;
            $formula = [];
            $metrics = [];

            /*
            |--------------------------------------------------------------------------
            | GAMING
            |--------------------------------------------------------------------------
            */

            if ($purpose === 'gaming') {

                if (
                    $gpuNormalized !== null &&
                    $cpuSingleNormalized !== null
                ) {

                    $score = (
                        $gpuNormalized *
                        self::GAMING_WEIGHTS['gpu']
                    ) + (
                        $cpuSingleNormalized *
                        self::GAMING_WEIGHTS['cpu_single_thread']
                    );

                    $formula = [
                        'gpu_weight_percent' => 80,
                        'cpu_single_thread_weight_percent' => 20
                    ];

                    $metrics = [
                        'gpu_gaming' => $this->formatMetric(
                            $gpuMetric,
                            $gpuNormalized,
                            'FPS'
                        ),

                        'cpu_single_thread' => $this->formatMetric(
                            $cpuSingleMetric,
                            $cpuSingleNormalized,
                            'PassMark rating points'
                        )
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | WORKSTATION
            |--------------------------------------------------------------------------
            | CPU-heavy synthetic workload proxy.
            | This is not a Blender, Premiere or application benchmark.
            */ elseif ($purpose === 'workstation') {

                if ($cpuMultiNormalized !== null) {

                    $score = $cpuMultiNormalized;

                    $formula = [
                        'cpu_multi_thread_weight_percent' => 100
                    ];

                    $metrics = [
                        'cpu_multi_thread' => $this->formatMetric(
                            $cpuMultiMetric,
                            $cpuMultiNormalized,
                            'PassMark CPU Mark points'
                        )
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | GENERAL USE
            |--------------------------------------------------------------------------
            */ elseif ($purpose === 'general_use') {

                if (
                    $cpuSingleNormalized !== null &&
                    $cpuMultiNormalized !== null
                ) {

                    $score = (
                        $cpuSingleNormalized *
                        self::GENERAL_USE_WEIGHTS['cpu_single_thread']
                    ) + (
                        $cpuMultiNormalized *
                        self::GENERAL_USE_WEIGHTS['cpu_multi_thread']
                    );

                    $formula = [
                        'cpu_single_thread_weight_percent' => 70,
                        'cpu_multi_thread_weight_percent' => 30
                    ];

                    $metrics = [
                        'cpu_single_thread' => $this->formatMetric(
                            $cpuSingleMetric,
                            $cpuSingleNormalized,
                            'PassMark rating points'
                        ),

                        'cpu_multi_thread' => $this->formatMetric(
                            $cpuMultiMetric,
                            $cpuMultiNormalized,
                            'PassMark CPU Mark points'
                        )
                    ];
                }
            }

            $build['performance_score'] = $score !== null
                ? round($score, 2)
                : null;

            $build['performance_data_status'] = $score !== null
                ? 'ranked'
                : 'missing_required_benchmarks';

            $build['performance_metrics'] = $metrics;

            $build['performance_formula'] = $formula;

            $build['performance_resolution'] =
                $purpose === 'gaming'
                ? $resolution
                : null;

            $build['performance_scope'] = match ($purpose) {

                'gaming' =>
                    'GPU rasterization gaming + CPU single-thread proxy',

                'workstation' =>
                    'CPU multi-thread synthetic benchmark proxy',

                'general_use' =>
                    'CPU single-thread + multi-thread synthetic benchmark proxy'
            };

            $build['performance_note'] = match ($purpose) {

                'gaming' =>
                    'Relative gaming benchmark ranking. Not predicted FPS for this complete PC.',

                'workstation' =>
                    'CPU-heavy performance proxy only. GPU-accelerated and application-specific workloads are not included.',

                'general_use' =>
                    'General CPU benchmark proxy. Not a complete application performance measurement.'
            };

            $rankedBuilds[] = $build;
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 3: SORT BY PERFORMANCE FIRST
        |--------------------------------------------------------------------------
        */

        usort(
            $rankedBuilds,
            function (array $a, array $b): int {

                $scoreA = is_numeric($a['performance_score'] ?? null)
                    ? (float) $a['performance_score']
                    : -1;

                $scoreB = is_numeric($b['performance_score'] ?? null)
                    ? (float) $b['performance_score']
                    : -1;

                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }

                // Prefer explicitly compatible builds on a score tie.
                $compatibilityA =
                    ($a['compatibility_status'] ?? null) === 'compatible'
                    ? 1 : 0;

                $compatibilityB =
                    ($b['compatibility_status'] ?? null) === 'compatible'
                    ? 1 : 0;

                if ($compatibilityA !== $compatibilityB) {
                    return $compatibilityB <=> $compatibilityA;
                }

                // Budget fit is a secondary criterion.
                $budgetFitA = (float) (
                    $a['budget_fit_score'] ?? 0
                );

                $budgetFitB = (float) (
                    $b['budget_fit_score'] ?? 0
                );

                if ($budgetFitA !== $budgetFitB) {
                    return $budgetFitB <=> $budgetFitA;
                }

                // Prefer better documented components.
                $verifiedA = (int) (
                    $a['verified_components'] ?? 0
                );

                $verifiedB = (int) (
                    $b['verified_components'] ?? 0
                );

                if ($verifiedA !== $verifiedB) {
                    return $verifiedB <=> $verifiedA;
                }

                // Final tie-breaker: lower total price.
                return (
                    (float) ($a['total_price'] ?? 0)
                    <=>
                    (float) ($b['total_price'] ?? 0)
                );
            }
        );

        /*
|--------------------------------------------------------------------------
| STEP 4: ASSIGN RANK POSITIONS
|--------------------------------------------------------------------------
*/

        $rankingPosition = 0;
        $performanceRank = 0;
        $lastScore = null;

        foreach ($rankedBuilds as $index => &$build) {

            $rankingPosition = $index + 1;

            $currentScore = $build['performance_score'];

            $build['ranking_position'] = $rankingPosition;

            if ($currentScore === null) {

                $build['performance_rank'] = null;

                continue;
            }

            $currentScore = round((float) $currentScore, 2);

            if (
                $lastScore === null ||
                abs($currentScore - $lastScore) > 0.001
            ) {
                $performanceRank++;
            }

            $build['performance_rank'] = $performanceRank;

            $lastScore = $currentScore;
        }

        unset($build);

        return $rankedBuilds;
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT CPU BENCHMARKS
    |--------------------------------------------------------------------------
    */

    private function extractCpuBenchmarks(array $cpu): array
    {
        $specifications = $cpu['specifications'] ?? [];

        $benchmark = $specifications[
            'performance_benchmarks'
        ]['cpu'] ?? [];

        if (!is_array($benchmark)) {
            return [];
        }

        $source = $benchmark['source'] ?? '';

        if (
            !is_string($source) ||
            stripos($source, 'PassMark') === false
        ) {
            return [];
        }

        if (empty($benchmark['source_url'])) {
            return [];
        }

        $scores = $benchmark['scores'] ?? [];

        if (!is_array($scores)) {
            return [];
        }

        $result = [];

        foreach (['single_thread', 'multi_thread'] as $key) {

            $value = $scores[$key] ?? null;

            if (!is_numeric($value) || (float) $value <= 0) {
                continue;
            }

            $result[$key] = [
                'value' => (float) $value,
                'source' => $source,
                'source_url' => $benchmark['source_url'],
                'snapshot_date' => $benchmark['snapshot_date'] ?? null,
                'sample_count' => $benchmark['sample_count'] ?? null
            ];
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT GPU GAMING BENCHMARK
    |--------------------------------------------------------------------------
    */

    private function extractGpuBenchmark(
        array $gpu,
        string $resolution
    ): ?array {

        $specifications = $gpu['specifications'] ?? [];

        $benchmark = $specifications[
            'performance_benchmarks'
        ]['gpu'] ?? [];

        if (!is_array($benchmark)) {
            return null;
        }

        // Only use native rasterization gaming results.
        if (
            ($benchmark['benchmark_type'] ?? null)
            !== 'rasterization_gaming'
        ) {
            return null;
        }

        // Do not mix upscaled or generated-frame results.
        if (
            ($benchmark['upscaling'] ?? null) !== false ||
            ($benchmark['frame_generation'] ?? null) !== false
        ) {
            return null;
        }

        if (empty($benchmark['source_url'])) {
            return null;
        }

        $scoreKey = 'geomean_fps_' . $resolution;

        $scores = $benchmark['scores'] ?? [];

        $value = $scores[$scoreKey] ?? null;

        if (!is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return [
            'value' => (float) $value,
            'source' => $benchmark['source'] ?? null,
            'source_url' => $benchmark['source_url'],
            'snapshot_date' => $benchmark['snapshot_date'] ?? null,
            'benchmark_type' => $benchmark['benchmark_type'],
            'test_suite' => $benchmark['test_suite'] ?? null
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE METRIC
    |--------------------------------------------------------------------------
    */

    private function normalizeMetric(
        ?array $metric,
        float $maximum
    ): ?float {

        if (
            $metric === null ||
            $maximum <= 0 ||
            !isset($metric['value'])
        ) {
            return null;
        }

        return min(
            100,
            ((float) $metric['value'] / $maximum) * 100
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT METRIC FOR API / FRONTEND
    |--------------------------------------------------------------------------
    */

    private function formatMetric(
        array $metric,
        float $normalizedScore,
        string $unit
    ): array {

        return [
            'raw_value' => $metric['value'],
            'unit' => $unit,
            'normalized_score' => round($normalizedScore, 2),
            'source' => $metric['source'],
            'source_url' => $metric['source_url'],
            'snapshot_date' => $metric['snapshot_date'] ?? null,
            'sample_count' => $metric['sample_count'] ?? null
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE PURPOSE
    |--------------------------------------------------------------------------
    */

    private function normalizePurpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));

        $purpose = str_replace(
            [' ', '-'],
            '_',
            $purpose
        );

        if (in_array($purpose, ['general', 'generaluse'], true)) {
            $purpose = 'general_use';
        }

        if (
            !in_array(
                $purpose,
                ['gaming', 'workstation', 'general_use'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported performance ranking purpose.'
            );
        }

        return $purpose;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE RESOLUTION
    |--------------------------------------------------------------------------
    */

    private function normalizeResolution(string $resolution): string
    {
        $resolution = strtolower(trim($resolution));

        if ($resolution === '2160p') {
            $resolution = '4k';
        }

        if (
            !in_array(
                $resolution,
                ['1080p', '1440p', '4k'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported gaming resolution. Use 1080p, 1440p or 4k.'
            );
        }

        return $resolution;
    }
}