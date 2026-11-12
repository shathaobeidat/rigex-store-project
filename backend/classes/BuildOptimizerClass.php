<?php
declare(strict_types=1);

require_once __DIR__ . '/CompatibilityClass.php';
require_once __DIR__ . '/PerformanceRankingClass.php';

class BuildOptimizerClass
{
    private CompatibilityClass $compatibility;
    private PerformanceRankingClass $performanceRanking;

    private const REQUIRED_COMPONENT_TYPES = [
        'cpu',
        'motherboard',
        'ram',
        'gpu',
        'storage',
        'psu',
        'case',
        'cooler'
    ];

    /**
     * Initial product requirements by use case.
     * These are configurable project rules, not universal hardware laws.
     */
    private const PURPOSE_REQUIREMENTS = [
        'gaming' => [
            'minimum_ram_gb' => 16,
            'recommended_ram_gb' => 32
        ],
        'workstation' => [
            'minimum_ram_gb' => 32,
            'recommended_ram_gb' => 64
        ],
        'general_use' => [
            'minimum_ram_gb' => 16,
            'recommended_ram_gb' => 32
        ]
    ];

    /**
     * Initial spending preferences only.
     * These are not performance benchmarks or hard spending limits.
     */
    private const PURPOSE_ALLOCATIONS = [
        'gaming' => [
            'cpu' => 18,
            'motherboard' => 12,
            'ram' => 8,
            'gpu' => 36,
            'storage' => 7,
            'psu' => 9,
            'case' => 6,
            'cooler' => 4
        ],
        'workstation' => [
            'cpu' => 27,
            'motherboard' => 12,
            'ram' => 17,
            'gpu' => 21,
            'storage' => 9,
            'psu' => 8,
            'case' => 3,
            'cooler' => 3
        ],
        'general_use' => [
            'cpu' => 23,
            'motherboard' => 13,
            'ram' => 13,
            'gpu' => 12,
            'storage' => 16,
            'psu' => 9,
            'case' => 8,
            'cooler' => 6
        ]
    ];

    /**
     * Final recommendation weights.
     * The performance benchmark remains the largest factor.
     * RAM score is a soft preference after the minimum RAM requirement is met.
     * Budget fit is a secondary preference.
     */
    private const RECOMMENDATION_WEIGHTS = [
        'gaming' => [
            'performance' => 80,
            'ram' => 15,
            'budget' => 5
        ],
        'workstation' => [
            'performance' => 75,
            'ram' => 20,
            'budget' => 5
        ],
        'general_use' => [
            'performance' => 70,
            'ram' => 20,
            'budget' => 10
        ]
    ];

    public function __construct(
        CompatibilityClass $compatibility,
        PerformanceRankingClass $performanceRanking
    ) {
        $this->compatibility = $compatibility;
        $this->performanceRanking = $performanceRanking;
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE BUILD
    |--------------------------------------------------------------------------
    */

    public function generate(
        array $catalog,
        float $budget,
        string $purpose,
        int $limit = 3,
        string $resolution = '1440p'
    ): array {
        if (!is_finite($budget) || $budget <= 0) {
            throw new InvalidArgumentException(
                'Budget must be a finite number greater than zero.'
            );
        }

        $purpose = $this->normalizePurpose($purpose);
        $limit = max(1, min($limit, 5));

        $allocations = self::PURPOSE_ALLOCATIONS[$purpose];
        $purposeRequirements = self::PURPOSE_REQUIREMENTS[$purpose];

        // Keep only valid products that are currently available.
        $catalog = $this->prepareCatalog($catalog);
        $catalogBeforeRequirements = $catalog;

        // Apply hard minimum requirements for the selected purpose.
        $catalog = $this->applyPurposeRequirements(
            $catalog,
            $purpose
        );

        // The single storage slot in the current builder is reserved for a primary SSD.
        // HDD products remain in the store catalog for a future secondary-drive feature.
        $storagePolicyResult = $this->applyPrimaryStorageRequirement($catalog);
        $catalog = $storagePolicyResult['catalog'];
        $storagePolicy = $storagePolicyResult['policy'];

        $missingTypes = [];
        $filteredTypes = [];

        foreach (self::REQUIRED_COMPONENT_TYPES as $type) {
            if (empty($catalog[$type])) {
                $missingTypes[] = $type;

                if (!empty($catalogBeforeRequirements[$type])) {
                    $filteredTypes[] = $type;
                }
            }
        }

        $catalogCountsBefore = $this->getCatalogCounts(
            $catalogBeforeRequirements
        );
        $catalogCountsAfter = $this->getCatalogCounts($catalog);

        if (!empty($missingTypes)) {
            $requirementsNotMet = !empty($filteredTypes);
            $missingStorage = in_array('storage', $missingTypes, true);
            $primarySsdUnavailable = (
                $missingStorage &&
                ($storagePolicy['available_ssd_count'] ?? 0) === 0 &&
                (
                    ($storagePolicy['available_hdd_count'] ?? 0) > 0 ||
                    ($storagePolicy['unclassified_storage_count'] ?? 0) > 0
                )
            );

            return [
                'success' => false,
                'status' => $primarySsdUnavailable
                    ? 'primary_ssd_unavailable'
                    : ($requirementsNotMet ? 'requirements_unmet' : 'catalog_incomplete'),
                'message' => $primarySsdUnavailable
                    ? 'HDD products are available, but the current builder requires an SSD as the primary drive.'
                    : ($requirementsNotMet
                        ? 'Available products do not satisfy the minimum build requirements for this purpose.'
                        : 'Some required component categories have no available products.'),
                'missing_component_types' => $missingTypes,
                'filtered_by_requirements' => $filteredTypes,
                'purpose' => $purpose,
                'purpose_requirements' => $purposeRequirements,
                'storage_policy' => $storagePolicy,
                'catalog_counts_before_requirements' => $catalogCountsBefore,
                'catalog_counts_after_requirements' => $catalogCountsAfter
            ];
        }

        $minimumRequiredBudget = $this->calculateMinimumBudget($catalog);

        $stats = [
            'total_combinations' => $this->countAllCombinations($catalog),
            'combinations_evaluated' => 0,
            'combinations_over_budget' => 0,
            'combinations_rejected_incompatible' => 0,
            'accepted_candidates' => 0,
            'catalog_counts_before_requirements' => $catalogCountsBefore,
            'catalog_counts_after_requirements' => $catalogCountsAfter,
            'storage_policy' => $storagePolicy
        ];

        $candidates = [];

        $this->searchCandidates(
            $catalog,
            self::REQUIRED_COMPONENT_TYPES,
            0,
            [],
            0.0,
            $budget,
            $allocations,
            $candidates,
            $stats,
            $purpose
        );

        // Rank candidates using the relevant benchmark data.
        if (!empty($candidates)) {
            $candidates = $this->performanceRanking->rankBuilds(
                $candidates,
                $purpose,
                $resolution
            );

            $this->addPerformanceScopeMetadata(
                $candidates,
                $purpose
            );

            // Combine relative performance, RAM recommendation fit and budget fit.
            $candidates = $this->applyRecommendationScoring(
                $candidates,
                $purpose
            );
        }

        if (empty($candidates)) {
            $allOverBudget = (
                $stats['total_combinations'] > 0 &&
                $stats['combinations_over_budget'] === $stats['total_combinations']
            );

            return [
                'success' => false,
                'status' => $allOverBudget
                    ? 'budget_insufficient'
                    : 'no_compatible_build',
                'message' => $allOverBudget
                    ? 'The budget is insufficient for the minimum listed component prices.'
                    : 'No build passed the compatibility checks.',
                'budget' => round($budget, 2),
                'minimum_listed_budget' => $minimumRequiredBudget,
                'purpose' => $purpose,
                'purpose_requirements' => $purposeRequirements,
                'storage_policy' => $storagePolicy,
                'statistics' => $stats
            ];
        }

        // Preserve performance ordering while avoiding near-duplicate alternatives.
        $results = $this->selectDiverseBuilds(
            $candidates,
            $limit,
            2
        );

        $recommendedBuild = $results[0];

        return [
            'success' => true,
            'status' => $recommendedBuild['compatibility_status'],
            'purpose' => $purpose,
            'budget' => round($budget, 2),
            'purpose_requirements' => $purposeRequirements,
            'ram_recommendation_policy' => [
                'minimum_score' => 50,
                'recommended_score' => 100,
                'between_minimum_and_recommended' => 'linear',
                'note' => 'RAM minimum is a hard filter. Recommended capacity is a soft ranking preference.'
            ],
            'storage_policy' => $storagePolicy,
            'recommended_build' => $recommendedBuild,
            'alternatives' => array_slice($results, 1),
            'optimization_method' => 'performance_first_relative_ranking',
            'performance_ranking' => [
                'status' => $recommendedBuild['performance_data_status'],
                'purpose' => $purpose,
                'resolution' => $purpose === 'gaming'
                    ? $resolution
                    : null,
                'scope' => $this->getPerformanceScope($purpose),
                'normalization_basis' => 'Best candidate in the current search'
            ],
            'recommendation_ranking' => [
                'status' => $recommendedBuild['recommendation_data_status'],
                'method' => 'weighted_relative_performance_ram_budget',
                'weights_percent' => self::RECOMMENDATION_WEIGHTS[$purpose],
                'ram_scoring' => [
                    'minimum_ram_score' => 50,
                    'recommended_ram_score' => 100,
                    'between_thresholds' => 'linear'
                ],
                'note' => 'RAM is a soft preference after the purpose minimum is met. Builds are compared only when required performance benchmark data is available.'
            ],
            'target_allocations' => $allocations,
            'target_budget_utilization_percent' => 90,
            'statistics' => $stats,
            'scoring_note' =>
                'Recommendation score combines normalized available performance benchmarks, RAM recommendation fit and budget fit using the published weights. It is not predicted FPS or a complete application-specific benchmark.'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH ALL POSSIBLE COMBINATIONS
    |--------------------------------------------------------------------------
    */

    private function searchCandidates(
        array $catalog,
        array $types,
        int $index,
        array $selected,
        float $partialTotal,
        float $budget,
        array $allocations,
        array &$candidates,
        array &$stats,
        string $purpose
    ): void {
        // Stop exploring a branch once its current price exceeds the budget.
        if ($partialTotal > $budget) {
            $stats['combinations_over_budget'] +=
                $this->countRemainingCombinations(
                    $catalog,
                    $types,
                    $index
                );

            return;
        }

        // All required component types have been selected.
        if ($index >= count($types)) {
            $stats['combinations_evaluated']++;

            $compatibilityResult =
                $this->compatibility->validateBuild($selected);

            // Exclude combinations with a known compatibility conflict.
            if ($compatibilityResult['status'] === 'incompatible') {
                $stats['combinations_rejected_incompatible']++;
                return;
            }

            $candidate = $this->createCandidate(
                $selected,
                $partialTotal,
                $budget,
                $allocations,
                $compatibilityResult,
                $purpose
            );

            $candidates[] = $candidate;
            $stats['accepted_candidates']++;

            return;
        }

        $type = $types[$index];

        foreach ($catalog[$type] as $component) {
            $selected[$type] = $component;
            $newTotal = $partialTotal + (float) $component['price'];

            $this->searchCandidates(
                $catalog,
                $types,
                $index + 1,
                $selected,
                $newTotal,
                $budget,
                $allocations,
                $candidates,
                $stats,
                $purpose
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE CANDIDATE
    |--------------------------------------------------------------------------
    */

    private function createCandidate(
        array $components,
        float $totalPrice,
        float $budget,
        array $allocations,
        array $compatibilityResult,
        string $purpose
    ): array {
        $allocationDeviation = 0.0;
        $allocationDetails = [];
        $verifiedCount = 0;
        $needsVerification = [];

        foreach ($components as $type => $component) {
            $price = (float) $component['price'];
            $actualPercentage = $totalPrice > 0
                ? ($price / $totalPrice) * 100
                : 0.0;
            $targetPercentage = (float) $allocations[$type];

            $allocationDeviation += abs(
                $actualPercentage - $targetPercentage
            );

            $allocationDetails[$type] = [
                'price' => round($price, 2),
                'target_percent' => $targetPercentage,
                'actual_percent' => round($actualPercentage, 2)
            ];

            $isVerified =
                ($component['spec_status'] ?? null) === 'verified' &&
                ($component['spec_data_valid'] ?? false) === true;

            if ($isVerified) {
                $verifiedCount++;
            } else {
                $needsVerification[] = [
                    'product_id' => (int) $component['product_id'],
                    'name' => $component['name'],
                    'component_type' => $type
                ];
            }
        }

        // Budget allocation fit: secondary to benchmark performance.
        $allocationScore = max(
            0,
            100 - ($allocationDeviation / 2)
        );

        $budgetUtilization = $budget > 0
            ? $totalPrice / $budget
            : 0.0;

        $budgetScore = max(
            0,
            100 - (
                abs($budgetUtilization - 0.90) / 0.90
            ) * 100
        );

        $budgetFitScore = (
            ($allocationScore * 0.70) +
            ($budgetScore * 0.30)
        );

        $formattedComponents = [];
        $productIds = [];

        foreach ($components as $type => $component) {
            $formattedComponents[$type] =
                $this->formatComponent($component);

            $productIds[] = (int) $component['product_id'];
        }

        $ramCapacity = $this->getRamCapacity($components['ram'] ?? []);
        $ramRequirements = self::PURPOSE_REQUIREMENTS[$purpose];
        $meetsMinimumRam = (
            $ramCapacity !== null &&
            $ramCapacity >= $ramRequirements['minimum_ram_gb']
        );
        $meetsRecommendedRam = (
            $ramCapacity !== null &&
            $ramCapacity >= $ramRequirements['recommended_ram_gb']
        );

        $ramStatus = $meetsRecommendedRam
            ? 'recommended_met'
            : ($meetsMinimumRam ? 'minimum_met' : 'minimum_not_met');

        return [
            'build_id' => implode('-', $productIds),
            'purpose' => $purpose,
            'budget_fit_score' => round($budgetFitScore, 2),
            'purpose_requirements' => [
                'minimum_ram_gb' => $ramRequirements['minimum_ram_gb'],
                'recommended_ram_gb' => $ramRequirements['recommended_ram_gb'],
                'actual_ram_gb' => $ramCapacity,
                'meets_minimum' => $meetsMinimumRam,
                'meets_recommended' => $meetsRecommendedRam,
                'ram_status' => $ramStatus
            ],
            'compatibility_status' => $compatibilityResult['status'],
            'compatibility_summary' => $compatibilityResult['summary'],
            'compatibility_checks' => $compatibilityResult['checks'],
            'total_price' => round($totalPrice, 2),
            'remaining_budget' => round($budget - $totalPrice, 2),
            'budget_used_percent' => round($budgetUtilization * 100, 2),
            'verified_components' => $verifiedCount,
            'needs_verification_count' => count($needsVerification),
            'components_needing_verification' => $needsVerification,
            'allocation_details' => $allocationDetails,
            'components' => $formattedComponents
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RAM RECOMMENDATION + FINAL RECOMMENDATION SCORING
    |--------------------------------------------------------------------------
    */

    private function applyRecommendationScoring(
        array $candidates,
        string $purpose
    ): array {
        $weights = self::RECOMMENDATION_WEIGHTS[$purpose];

        foreach ($candidates as &$candidate) {
            $requirements = $candidate['purpose_requirements'] ??
                self::PURPOSE_REQUIREMENTS[$purpose];

            $ramCapacity = $requirements['actual_ram_gb'] ?? null;
            $minimumRam = (float) ($requirements['minimum_ram_gb'] ?? 0);
            $recommendedRam = (float) ($requirements['recommended_ram_gb'] ?? 0);

            $ramScore = $this->calculateRamRecommendationScore(
                is_numeric($ramCapacity) ? (float) $ramCapacity : null,
                $minimumRam,
                $recommendedRam
            );

            $performanceScore = $candidate['performance_score'] ?? null;
            $budgetFitScore = $candidate['budget_fit_score'] ?? null;

            $hasRequiredPerformance = is_numeric($performanceScore);
            $hasRamScore = $ramScore !== null;
            $hasBudgetScore = is_numeric($budgetFitScore);

            $recommendationScore = null;

            if ($hasRequiredPerformance && $hasRamScore && $hasBudgetScore) {
                $recommendationScore = (
                    ((float) $performanceScore * $weights['performance']) +
                    ($ramScore * $weights['ram']) +
                    ((float) $budgetFitScore * $weights['budget'])
                ) / 100;
            }

            $candidate['ram_recommendation_score'] = $ramScore !== null
                ? round($ramScore, 2)
                : null;

            $candidate['recommendation_score'] = $recommendationScore !== null
                ? round($recommendationScore, 2)
                : null;

            $candidate['recommendation_data_status'] = $recommendationScore !== null
                ? 'ranked'
                : 'missing_required_scoring_data';

            $candidate['recommendation_weights'] = $weights;

            $candidate['ram_recommendation_note'] =
                'The purpose minimum is a hard requirement. The recommended RAM capacity is a soft preference: minimum capacity scores 50, recommended capacity or higher scores 100, and values between them are scored linearly.';
        }

        unset($candidate);

        // Highest total recommendation score first; raw performance is the first tie-breaker.
        usort(
            $candidates,
            static function (array $a, array $b): int {
                $scoreA = is_numeric($a['recommendation_score'] ?? null)
                    ? (float) $a['recommendation_score']
                    : -1.0;
                $scoreB = is_numeric($b['recommendation_score'] ?? null)
                    ? (float) $b['recommendation_score']
                    : -1.0;

                if (abs($scoreA - $scoreB) > 0.0001) {
                    return $scoreB <=> $scoreA;
                }

                $performanceA = is_numeric($a['performance_score'] ?? null)
                    ? (float) $a['performance_score']
                    : -1.0;
                $performanceB = is_numeric($b['performance_score'] ?? null)
                    ? (float) $b['performance_score']
                    : -1.0;

                if (abs($performanceA - $performanceB) > 0.0001) {
                    return $performanceB <=> $performanceA;
                }

                $budgetA = (float) ($a['budget_fit_score'] ?? 0);
                $budgetB = (float) ($b['budget_fit_score'] ?? 0);

                if (abs($budgetA - $budgetB) > 0.0001) {
                    return $budgetB <=> $budgetA;
                }

                $verifiedA = (int) ($a['verified_components'] ?? 0);
                $verifiedB = (int) ($b['verified_components'] ?? 0);

                if ($verifiedA !== $verifiedB) {
                    return $verifiedB <=> $verifiedA;
                }

                return (float) ($a['total_price'] ?? 0)
                    <=> (float) ($b['total_price'] ?? 0);
            }
        );

        // Recommendation rank supports ties, while list position remains separate.
        $rank = 0;
        $lastScore = null;

        foreach ($candidates as $index => &$candidate) {
            $candidate['recommendation_position'] = $index + 1;
            $currentScore = $candidate['recommendation_score'];

            if (!is_numeric($currentScore)) {
                $candidate['recommendation_rank'] = null;
                continue;
            }

            $currentScore = round((float) $currentScore, 2);

            if ($lastScore === null || abs($currentScore - $lastScore) > 0.001) {
                $rank++;
            }

            $candidate['recommendation_rank'] = $rank;
            $lastScore = $currentScore;
        }

        unset($candidate);

        return $candidates;
    }

    private function calculateRamRecommendationScore(
        ?float $actualRam,
        float $minimumRam,
        float $recommendedRam
    ): ?float {
        if (
            $actualRam === null ||
            $minimumRam <= 0 ||
            $recommendedRam <= 0 ||
            $recommendedRam < $minimumRam
        ) {
            return null;
        }

        // Defensive guard: below-minimum RAM should have been filtered earlier.
        if ($actualRam < $minimumRam) {
            return 0.0;
        }

        if ($actualRam >= $recommendedRam) {
            return 100.0;
        }

        if ($recommendedRam === $minimumRam) {
            return 100.0;
        }

        $progress = ($actualRam - $minimumRam) /
            ($recommendedRam - $minimumRam);

        return 50.0 + ($progress * 50.0);
    }

    /*
    |--------------------------------------------------------------------------
    | PERFORMANCE SCOPE METADATA
    |--------------------------------------------------------------------------
    */

    private function addPerformanceScopeMetadata(
        array &$candidates,
        string $purpose
    ): void {
        $scope = $this->getPerformanceScope($purpose);

        foreach ($candidates as &$candidate) {
            $candidate['performance_scope'] = $scope;
            $candidate['performance_note'] = match ($purpose) {
                'gaming' =>
                    'Relative GPU rasterization gaming score with CPU single-thread as a proxy. This is not predicted FPS for the complete PC.',
                'workstation' =>
                    'CPU multi-thread synthetic benchmark proxy only. GPU-accelerated and application-specific workloads are not included.',
                'general_use' =>
                    'CPU single-thread and multi-thread synthetic benchmark proxy. This is not a complete application performance measurement.'
            };
        }

        unset($candidate);
    }

    private function getPerformanceScope(string $purpose): string
    {
        return match ($purpose) {
            'gaming' => 'GPU rasterization gaming + CPU single-thread proxy',
            'workstation' => 'CPU multi-thread synthetic benchmark proxy',
            'general_use' => 'CPU single-thread + multi-thread synthetic benchmark proxy',
            default => 'Available benchmark metrics'
        };
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT COMPONENT OUTPUT
    |--------------------------------------------------------------------------
    */

    private function formatComponent(array $component): array
    {
        $formatted = [
            'product_id' => (int) $component['product_id'],
            'name' => (string) $component['name'],
            'brand' => $component['brand'] ?? null,
            'component_type' => (string) $component['component_type'],
            'price' => (float) $component['price'],
            'quantity' => (int) ($component['quantity'] ?? 0),
            'condition_type' => $component['condition_type'] ?? null,
            'product_img' => $component['product_img'] ?? null,
            'spec_status' => $component['spec_status'] ?? 'incomplete',
            'spec_data_valid' => (bool) ($component['spec_data_valid'] ?? false),
            'specifications' => $component['specifications'] ?? []
        ];

        if (($component['component_type'] ?? null) === 'storage') {
            $formatted['storage_role'] = 'primary';
        }

        return $formatted;
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARE AVAILABLE CATALOG
    |--------------------------------------------------------------------------
    */

    private function prepareCatalog(array $catalog): array
    {
        $prepared = [];

        foreach (self::REQUIRED_COMPONENT_TYPES as $type) {
            $prepared[$type] = [];

            foreach (($catalog[$type] ?? []) as $component) {
                if (!is_array($component)) {
                    continue;
                }

                $price = $component['price'] ?? null;

                if (!is_numeric($price) || (float) $price <= 0) {
                    continue;
                }

                if (
                    array_key_exists('is_available', $component) &&
                    $component['is_available'] !== true
                ) {
                    continue;
                }

                if (
                    array_key_exists('quantity', $component) &&
                    (int) $component['quantity'] <= 0
                ) {
                    continue;
                }

                $component['component_type'] = $type;
                $component['price'] = (float) $price;
                $prepared[$type][] = $component;
            }

            // Cheapest products first for deterministic traversal.
            usort(
                $prepared[$type],
                static fn(array $a, array $b): int =>
                    $a['price'] <=> $b['price']
            );
        }

        return $prepared;
    }

    /*
    |--------------------------------------------------------------------------
    | PRIMARY STORAGE REQUIREMENT
    |--------------------------------------------------------------------------
    | The current build model has one storage slot. It is reserved for an SSD.
    | HDD remains available in the store catalog and can be added later as an
    | optional secondary drive without changing the existing product records.
    */

    private function applyPrimaryStorageRequirement(array $catalog): array
    {
        $ssdProducts = [];
        $hddCount = 0;
        $unclassifiedCount = 0;

        foreach (($catalog['storage'] ?? []) as $storage) {
            $driveType = $this->normalizeStorageDriveType($storage);

            if ($driveType === 'ssd') {
                $ssdProducts[] = $storage;
            } elseif ($driveType === 'hdd') {
                $hddCount++;
            } else {
                $unclassifiedCount++;
            }
        }

        // Only explicitly identified SSDs are eligible for the primary slot.
        $catalog['storage'] = $ssdProducts;

        return [
            'catalog' => $catalog,
            'policy' => [
                'primary_drive_type' => 'SSD',
                'selection_mode' => 'ssd_only',
                'available_ssd_count' => count($ssdProducts),
                'available_hdd_count' => $hddCount,
                'unclassified_storage_count' => $unclassifiedCount,
                'hdd_used_as_primary' => false,
                'secondary_hdd_support' => 'not_implemented',
                'message' => 'The current builder selects an SSD as the primary drive. HDD products remain in the store catalog but are not selected as the primary drive.'
            ]
        ];
    }

    private function normalizeStorageDriveType(array $storage): ?string
    {
        $specifications = $storage['specifications'] ?? [];
        $driveType = strtolower(trim((string) ($specifications['drive_type'] ?? '')));

        if (in_array($driveType, ['ssd', 'solid state drive', 'solid-state drive'], true)) {
            return 'ssd';
        }

        if (in_array($driveType, ['hdd', 'hard disk drive', 'hard-disk drive'], true)) {
            return 'hdd';
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY PURPOSE REQUIREMENTS
    |--------------------------------------------------------------------------
    */

    private function applyPurposeRequirements(
        array $catalog,
        string $purpose
    ): array {
        $minimumRam = self::PURPOSE_REQUIREMENTS[$purpose]['minimum_ram_gb'];

        $catalog['ram'] = array_values(
            array_filter(
                $catalog['ram'] ?? [],
                function (array $ram) use ($minimumRam): bool {
                    $capacity = $this->getRamCapacity($ram);

                    // Unknown capacity cannot prove that the minimum is met.
                    return $capacity !== null && $capacity >= $minimumRam;
                }
            )
        );

        return $catalog;
    }

    private function getRamCapacity(array $ram): ?float
    {
        $specifications = $ram['specifications'] ?? [];
        $capacity = $specifications['capacity_gb'] ?? null;

        if (!is_numeric($capacity) || (float) $capacity <= 0) {
            return null;
        }

        return (float) $capacity;
    }

    /*
    |--------------------------------------------------------------------------
    | CATALOG COUNTS
    |--------------------------------------------------------------------------
    */

    private function getCatalogCounts(array $catalog): array
    {
        $counts = [];

        foreach (self::REQUIRED_COMPONENT_TYPES as $type) {
            $counts[$type] = count($catalog[$type] ?? []);
        }

        return $counts;
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE MINIMUM LISTED BUDGET
    |--------------------------------------------------------------------------
    */

    private function calculateMinimumBudget(array $catalog): float
    {
        $minimum = 0.0;

        foreach (self::REQUIRED_COMPONENT_TYPES as $type) {
            $prices = array_column($catalog[$type], 'price');

            if (empty($prices)) {
                return 0.0;
            }

            $minimum += min($prices);
        }

        return round($minimum, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | COMBINATION COUNTERS
    |--------------------------------------------------------------------------
    */

    private function countAllCombinations(array $catalog): int
    {
        $count = 1;

        foreach (self::REQUIRED_COMPONENT_TYPES as $type) {
            $count *= count($catalog[$type]);
        }

        return $count;
    }

    private function countRemainingCombinations(
        array $catalog,
        array $types,
        int $index
    ): int {
        $count = 1;

        for ($i = $index; $i < count($types); $i++) {
            $count *= count($catalog[$types[$i]]);
        }

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE PURPOSE
    |--------------------------------------------------------------------------
    */

    private function normalizePurpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));
        $purpose = str_replace([' ', '-'], '_', $purpose);

        if (in_array($purpose, ['general', 'generaluse'], true)) {
            $purpose = 'general_use';
        }

        if (!array_key_exists($purpose, self::PURPOSE_ALLOCATIONS)) {
            throw new InvalidArgumentException(
                'Unsupported purpose. Use gaming, workstation or general_use.'
            );
        }

        return $purpose;
    }

    /*
    |--------------------------------------------------------------------------
    | SELECT DIVERSE BUILDS
    |--------------------------------------------------------------------------
    | Prefer alternatives that differ in at least the requested number
    | of component product IDs. If the catalog has too few diverse builds,
    | fill the remaining slots with the next highest-ranked candidates.
    */

    private function selectDiverseBuilds(
        array $rankedCandidates,
        int $limit,
        int $minimumDifferences = 2
    ): array {
        $selected = [];
        $selectedIds = [];

        foreach ($rankedCandidates as $candidate) {
            if (empty($selected)) {
                $selected[] = $candidate;
                $selectedIds[$candidate['build_id']] = true;

                if (count($selected) >= $limit) {
                    return $selected;
                }

                continue;
            }

            $isDiverse = true;

            foreach ($selected as $selectedBuild) {
                $differences = $this->countComponentDifferences(
                    $candidate,
                    $selectedBuild
                );

                if ($differences < $minimumDifferences) {
                    $isDiverse = false;
                    break;
                }
            }

            if ($isDiverse) {
                $selected[] = $candidate;
                $selectedIds[$candidate['build_id']] = true;
            }

            if (count($selected) >= $limit) {
                return $selected;
            }
        }

        // Fall back to highest-ranked remaining candidates if needed.
        foreach ($rankedCandidates as $candidate) {
            if (isset($selectedIds[$candidate['build_id']])) {
                continue;
            }

            $selected[] = $candidate;
            $selectedIds[$candidate['build_id']] = true;

            if (count($selected) >= $limit) {
                break;
            }
        }

        // The fallback pass may append a lower-ranked skipped build after a higher one.
        // Re-sort the selected subset so the displayed recommendations remain ordered.
        usort(
            $selected,
            static function (array $a, array $b): int {
                $scoreA = is_numeric($a['recommendation_score'] ?? null)
                    ? (float) $a['recommendation_score']
                    : -1.0;
                $scoreB = is_numeric($b['recommendation_score'] ?? null)
                    ? (float) $b['recommendation_score']
                    : -1.0;

                if (abs($scoreA - $scoreB) > 0.0001) {
                    return $scoreB <=> $scoreA;
                }

                return (float) ($b['performance_score'] ?? -1)
                    <=> (float) ($a['performance_score'] ?? -1);
            }
        );

        return $selected;
    }

    private function countComponentDifferences(
        array $firstBuild,
        array $secondBuild
    ): int {
        $firstComponents = $firstBuild['components'] ?? [];
        $secondComponents = $secondBuild['components'] ?? [];
        $differences = 0;

        foreach (self::REQUIRED_COMPONENT_TYPES as $type) {
            $firstId = $firstComponents[$type]['product_id'] ?? null;
            $secondId = $secondComponents[$type]['product_id'] ?? null;

            if ($firstId !== $secondId) {
                $differences++;
            }
        }

        return $differences;
    }
}