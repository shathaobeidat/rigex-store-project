<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/classes/ModelFactory.php';

/**
 * Escape output for safe HTML rendering.
 */
function pc_builder_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format store prices. The REGIX store uses JOD pricing.
 */
function pc_builder_money($value): string
{
    return number_format((float) $value, 2) . ' JOD';
}

/**
 * Convert technical purpose values into customer-facing labels.
 */
function pc_builder_purpose_label(string $purpose): string
{
    $labels = [
        'gaming' => 'Gaming',
        'workstation' => 'Workstation',
        'general_use' => 'Everyday use'
    ];

    return $labels[$purpose] ?? 'Custom PC';
}

/**
 * Convert internal compatibility states into clear customer messages.
 */
function pc_builder_compatibility_label(string $status): string
{
    if ($status === 'compatible') {
        return 'Compatibility checks passed';
    }

    if ($status === 'incompatible') {
        return 'Compatibility conflict found';
    }

    return 'Needs verification before purchase';
}

/**
 * Convert internal statuses into customer-facing labels.
 */
function pc_builder_status_label(string $status): string
{
    $labels = [
        'compatible' => 'Passed',
        'incompatible' => 'Conflict',
        'needs_verification' => 'Needs verification',
        'verified' => 'Verified',
        'incomplete' => 'Incomplete',
        'minimum_met' => 'Minimum met',
        'recommended_met' => 'Recommended capacity met'
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
}


function pc_builder_build_story(array $build, string $purpose): array
{
    $components = is_array($build['components'] ?? null)
        ? $build['components']
        : [];

    $requirements = is_array($build['purpose_requirements'] ?? null)
        ? $build['purpose_requirements']
        : [];

    $getComponentName = static function (string $type, string $fallback) use ($components): string {
        $component = $components[$type] ?? [];

        return is_array($component)
            ? (string) ($component['name'] ?? $fallback)
            : $fallback;
    };

    $cpu = $getComponentName('cpu', 'Selected processor');
    $motherboard = $getComponentName('motherboard', 'Selected motherboard');
    $gpu = $getComponentName('gpu', 'Selected graphics card');
    $storage = $getComponentName('storage', 'Selected SSD');
    $ram = $getComponentName('ram', 'Selected memory');

    $ramCapacity = $requirements['actual_ram_gb']
        ?? ($components['ram']['specifications']['capacity_gb'] ?? null);

    $memoryDescription = is_numeric($ramCapacity)
        ? number_format((float) $ramCapacity, 0) . ' GB of memory'
        : $ram;

    $purposeStories = [
        'gaming' => [
            'eyebrow' => 'GAMING-FOCUSED BUILD',
            'headline' => 'Built around the experience you want.',
            'description' => 'Gaming performance is the primary ranking focus for this configuration. The selected combination is evaluated using available benchmark data, memory requirements and your budget.',
            'focus' => 'Graphics and gaming performance'
        ],
        'workstation' => [
            'eyebrow' => 'WORKSTATION BUILD',
            'headline' => 'Ready for demanding work.',
            'description' => 'This configuration gives additional importance to processing performance and memory capacity, while keeping the overall build within your budget.',
            'focus' => 'Processing and memory capacity'
        ],
        'general_use' => [
            'eyebrow' => 'EVERYDAY BUILD',
            'headline' => 'A balanced setup for daily life.',
            'description' => 'This configuration aims for a practical balance of performance, memory capacity and budget fit for everyday tasks.',
            'focus' => 'Balanced everyday performance'
        ]
    ];

    $story = $purposeStories[$purpose] ?? $purposeStories['general_use'];

    return [
        'eyebrow' => $story['eyebrow'],
        'headline' => $story['headline'],
        'description' => $story['description'],
        'focus' => $story['focus'],

        'chapters' => [
            [
                'number' => '01',
                'title' => 'The foundation',
                'description' => $cpu . ' works alongside ' . $motherboard . '.'
            ],
            [
                'number' => '02',
                'title' => 'The performance',
                'description' => $gpu . ' is paired with ' . $memoryDescription . '.'
            ],
            [
                'number' => '03',
                'title' => 'The everyday experience',
                'description' => $storage . ' is selected as the primary storage for this build.'
            ]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| Form Configuration
|--------------------------------------------------------------------------
*/

$allowedPurposes = [
    'gaming',
    'workstation',
    'general_use'
];

$allowedResolutions = [
    '1080p',
    '1440p',
    '4k'
];

$formValues = [
    'budget' => '1200',
    'purpose' => 'gaming',
    'resolution' => '1440p'
];

$formErrors = [];
$result = null;

$hasSubmitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/*
|--------------------------------------------------------------------------
| Form Processing
|--------------------------------------------------------------------------
*/

if ($hasSubmitted) {

    $budgetInput = $_POST['budget'] ?? '';
    $purposeInput = $_POST['purpose'] ?? 'gaming';
    $resolutionInput = $_POST['resolution'] ?? '1440p';

    $formValues['budget'] = is_scalar($budgetInput)
        ? trim((string) $budgetInput)
        : '';

    $formValues['purpose'] = is_string($purposeInput)
        ? $purposeInput
        : 'gaming';

    $formValues['resolution'] = is_string($resolutionInput)
        ? $resolutionInput
        : '1440p';

    // Validate budget.
    if (
        $formValues['budget'] === '' ||
        !is_numeric($formValues['budget']) ||
        (float) $formValues['budget'] <= 0
    ) {
        $formErrors[] = 'Enter a valid budget greater than zero.';
    }

    // Validate PC purpose.
    if (!in_array($formValues['purpose'], $allowedPurposes, true)) {
        $formErrors[] = 'Choose a valid PC usage type.';
    }

    // Validate gaming resolution.
    if (!in_array($formValues['resolution'], $allowedResolutions, true)) {
        $formErrors[] = 'Choose a valid gaming resolution.';
    }

    /*
    |--------------------------------------------------------------------------
    | Generate PC Build
    |--------------------------------------------------------------------------
    */

    if (empty($formErrors)) {

        try {

            $builder = ModelFactory::pcBuilder();

            $result = $builder->generateBuild(
                (float) $formValues['budget'],
                $formValues['purpose'],
                3,
                $formValues['resolution']
            );

        } catch (Throwable $exception) {

            error_log(
                '[REGIX PC Builder] ' . $exception->getMessage()
            );

            $result = [
                'success' => false,
                'status' => 'server_error',
                'message' => 'We could not generate a PC build right now. Please try again.'
            ];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Process Build Results
|--------------------------------------------------------------------------
*/

$recommendedBuild = (
    is_array($result) &&
    ($result['success'] ?? false) === true &&
    isset($result['recommended_build']) &&
    is_array($result['recommended_build'])
)
    ? $result['recommended_build']
    : null;

$alternatives = (
    is_array($result) &&
    isset($result['alternatives']) &&
    is_array($result['alternatives'])
)
    ? $result['alternatives']
    : [];

/*
|--------------------------------------------------------------------------
| Component Labels
|--------------------------------------------------------------------------
*/

$componentLabels = [
    'cpu' => 'Processor',
    'motherboard' => 'Motherboard',
    'ram' => 'Memory',
    'gpu' => 'Graphics card',
    'storage' => 'Primary storage',
    'psu' => 'Power supply',
    'case' => 'PC case',
    'cooler' => 'CPU cooling'
];

$componentIcons = [
    'cpu' => 'CPU',
    'motherboard' => 'MB',
    'ram' => 'RAM',
    'gpu' => 'GPU',
    'storage' => 'SSD',
    'psu' => 'PSU',
    'case' => 'CASE',
    'cooler' => 'COOL'
];

/*
|--------------------------------------------------------------------------
| Load Existing Customer Layout
|--------------------------------------------------------------------------
|
| No changes to frontend/User/layout.php are required.
|
*/

require_once __DIR__ . '/layout.php';

/*
|--------------------------------------------------------------------------
| Load PC Builder CSS and JavaScript
|--------------------------------------------------------------------------
|
| Assets are injected into the <head> generated by store_page_start().
| This avoids changing the existing layout function.
|
*/

$pcBuilderCssPath = __DIR__ . '/../css/pc_builder.css';
$pcBuilderJsPath = __DIR__ . '/../js/pc_builder.js';

$pcBuilderCssVersion = is_file($pcBuilderCssPath)
    ? (string) filemtime($pcBuilderCssPath)
    : '1';

$pcBuilderJsVersion = is_file($pcBuilderJsPath)
    ? (string) filemtime($pcBuilderJsPath)
    : '1';

$pcBuilderAssets =
    '<link rel="stylesheet" href="../css/pc_builder.css?v='
    . $pcBuilderCssVersion . '">' . PHP_EOL .

    '<script src="../js/pc_builder.js?v='
    . $pcBuilderJsVersion . '" defer></script>' . PHP_EOL;

/*
|--------------------------------------------------------------------------
| Render Existing Store Header and Main Opening
|--------------------------------------------------------------------------
*/

ob_start();

store_page_start('PC Builder');

$storePageStart = (string) ob_get_clean();

/*
|--------------------------------------------------------------------------
| Inject Page-Specific Assets into Existing Head
|--------------------------------------------------------------------------
*/

$headPosition = stripos($storePageStart, '</head>');

if ($headPosition !== false) {

    $storePageStart = substr_replace(
        $storePageStart,
        $pcBuilderAssets,
        $headPosition,
        0
    );
}

echo $storePageStart;

?>

<!-- =========================================================
     PC BUILDER PAGE
========================================================= -->

<div class="pc-builder-page">


    <div class="pbx-page">

        <!-- HERO -->
        <section class="pbx-hero">

            <div class="pbx-hero-copy">
                <span class="pbx-eyebrow">
                    <span class="pbx-live-dot"></span>
                    REGIX BUILD EXPERIENCE
                </span>

                <h1>
                    Your next PC starts
                    <span>with your purpose.</span>
                </h1>

                <p>
                    Tell us what you want to achieve. We’ll turn your budget
                    and priorities into a PC configuration selected from
                    available REGIX components.
                </p>

                <a href="#pbx-builder" class="pbx-hero-cta">
                    Start your build
                    <span aria-hidden="true">↓</span>
                </a>

                <div class="pbx-trust-points">
                    <span>Inventory-aware</span>
                    <span>Compatibility checks</span>
                    <span>Purpose-based ranking</span>
                </div>
            </div>

            <div class="pbx-hero-visual" aria-hidden="true">
                <div class="pbx-visual-orbit pbx-orbit-a"></div>
                <div class="pbx-visual-orbit pbx-orbit-b"></div>

                <div class="pbx-visual-core">
                    <span class="pbx-core-light"></span>
                    <span class="pbx-core-brand">R</span>
                    <strong>REGIX</strong>
                    <small>BUILD YOUR RIG</small>
                </div>

                <div class="pbx-float-card pbx-float-top">
                    <span class="pbx-float-icon">01</span>
                    <div>
                        <strong>Your purpose</strong>
                        <small>Define your goal</small>
                    </div>
                </div>

                <div class="pbx-float-card pbx-float-bottom">
                    <span class="pbx-float-icon">02</span>
                    <div>
                        <strong>Your components</strong>
                        <small>Matched to your needs</small>
                    </div>
                </div>
            </div>

        </section>


        <!-- BUILDER JOURNEY -->
        <section class="pbx-workspace" id="pbx-builder">

            <div class="pbx-workspace-heading">
                <div>
                    <span class="pbx-section-kicker">THE BUILD JOURNEY</span>
                    <h2>Let’s define your next rig.</h2>
                    <p>Two simple steps. A configuration built around you.</p>
                </div>

                <span class="pbx-private-note">
                    <span>✦</span>
                    No technical knowledge required
                </span>
            </div>


            <div class="pbx-workspace-grid">

                <!-- WIZARD -->
                <div class="pbx-wizard-card">

                    <div class="pbx-stepper">

                        <div class="pbx-step-indicator is-active" data-step-indicator="1" aria-current="step">

                            <span class="pbx-step-number">01</span>

                            <div>
                                <strong>Your purpose</strong>
                                <small>What are you building for?</small>
                            </div>

                        </div>

                        <span class="pbx-step-line"></span>

                        <div class="pbx-step-indicator" data-step-indicator="2">

                            <span class="pbx-step-number">02</span>

                            <div>
                                <strong>Your budget</strong>
                                <small>Set your spending limit</small>
                            </div>

                        </div>

                    </div>


                    <?php if (!empty($formErrors)): ?>
                    <div class="pbx-form-errors" role="alert">
                        <strong>Let’s check a few details.</strong>

                        <?php foreach ($formErrors as $error): ?>
                        <p><?= pc_builder_e($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>


                    <form method="POST" action="pc_builder.php#build-results" id="pcBuilderForm" class="pbx-form">

                        <!-- STEP 1: PURPOSE -->
                        <section class="pbx-step-panel" data-step-panel="1">

                            <div class="pbx-panel-heading">
                                <span class="pbx-panel-index">STEP 01 / 02</span>
                                <h3>What matters most to you?</h3>
                                <p>
                                    Choose the main way you plan to use your PC.
                                    We’ll use this to guide the recommendation.
                                </p>
                            </div>


                            <div class="pbx-purpose-list">

                                <label
                                    class="pbx-purpose-card <?= $formValues['purpose'] === 'gaming' ? 'is-selected' : '' ?>">

                                    <input type="radio" name="purpose" value="gaming"
                                        <?= $formValues['purpose'] === 'gaming' ? 'checked' : '' ?> required>

                                    <span class="pbx-purpose-symbol">G</span>

                                    <span class="pbx-purpose-content">
                                        <strong>Gaming</strong>
                                        <small>
                                            For immersive games, higher frame rates
                                            and graphics-focused performance.
                                        </small>
                                        <span class="pbx-purpose-tags">
                                            <i>Gaming performance</i>
                                            <i>GPU priority</i>
                                        </span>
                                    </span>

                                    <span class="pbx-choice-indicator"></span>

                                </label>


                                <label
                                    class="pbx-purpose-card <?= $formValues['purpose'] === 'workstation' ? 'is-selected' : '' ?>">

                                    <input type="radio" name="purpose" value="workstation"
                                        <?= $formValues['purpose'] === 'workstation' ? 'checked' : '' ?>>

                                    <span class="pbx-purpose-symbol">W</span>

                                    <span class="pbx-purpose-content">
                                        <strong>Workstation</strong>
                                        <small>
                                            For development, creative applications,
                                            demanding workloads and multitasking.
                                        </small>
                                        <span class="pbx-purpose-tags">
                                            <i>Processing</i>
                                            <i>Memory capacity</i>
                                        </span>
                                    </span>

                                    <span class="pbx-choice-indicator"></span>

                                </label>


                                <label
                                    class="pbx-purpose-card <?= $formValues['purpose'] === 'general_use' ? 'is-selected' : '' ?>">

                                    <input type="radio" name="purpose" value="general_use"
                                        <?= $formValues['purpose'] === 'general_use' ? 'checked' : '' ?>>

                                    <span class="pbx-purpose-symbol">D</span>

                                    <span class="pbx-purpose-content">
                                        <strong>Everyday use</strong>
                                        <small>
                                            For studying, browsing, office work
                                            and everyday applications.
                                        </small>
                                        <span class="pbx-purpose-tags">
                                            <i>Balanced setup</i>
                                            <i>Budget fit</i>
                                        </span>
                                    </span>

                                    <span class="pbx-choice-indicator"></span>

                                </label>

                            </div>


                            <div class="pbx-step-actions pbx-actions-end">

                                <span class="pbx-step-hint">
                                    You can change this later.
                                </span>

                                <button type="button" class="pbx-button pbx-button-primary" id="pbxNextStep">
                                    Continue
                                    <span aria-hidden="true">→</span>
                                </button>

                            </div>

                        </section>


                        <!-- STEP 2: BUDGET -->
                        <section class="pbx-step-panel" data-step-panel="2" hidden>

                            <div class="pbx-panel-heading">
                                <span class="pbx-panel-index">STEP 02 / 02</span>
                                <h3>Let’s set your budget.</h3>
                                <p>
                                    Your budget is the maximum amount the
                                    recommended component combination should cost.
                                </p>
                            </div>


                            <div class="pbx-budget-field">

                                <label for="pbBudget">
                                    What is your maximum budget?
                                </label>

                                <div class="pbx-budget-input">

                                    <span>JOD</span>

                                    <input type="number" id="pbBudget" name="budget" min="1" max="1000000" step="0.01"
                                        value="<?= pc_builder_e($formValues['budget']) ?>" placeholder="1200" required>

                                </div>

                                <small>
                                    We’ll search for suitable combinations within this limit.
                                </small>

                            </div>


                            <!-- GAMING RESOLUTION -->
                            <div class="pbx-resolution-field" id="pbResolutionGroup"
                                <?= $formValues['purpose'] !== 'gaming' ? 'hidden' : '' ?>>

                                <label>What resolution do you want to play at?</label>

                                <p>
                                    This helps us use the appropriate gaming benchmark
                                    when ranking available graphics cards.
                                </p>

                                <div class="pbx-resolution-options">

                                    <label class="<?= $formValues['resolution'] === '1080p' ? 'is-selected' : '' ?>">
                                        <input type="radio" name="resolution" value="1080p"
                                            <?= $formValues['resolution'] === '1080p' ? 'checked' : '' ?>>
                                        <strong>1080p</strong>
                                        <small>Full HD</small>
                                    </label>

                                    <label class="<?= $formValues['resolution'] === '1440p' ? 'is-selected' : '' ?>">
                                        <input type="radio" name="resolution" value="1440p"
                                            <?= $formValues['resolution'] === '1440p' ? 'checked' : '' ?>>
                                        <strong>1440p</strong>
                                        <small>Quad HD</small>
                                    </label>

                                    <label class="<?= $formValues['resolution'] === '4k' ? 'is-selected' : '' ?>">
                                        <input type="radio" name="resolution" value="4k"
                                            <?= $formValues['resolution'] === '4k' ? 'checked' : '' ?>>
                                        <strong>4K</strong>
                                        <small>Ultra HD</small>
                                    </label>

                                </div>

                            </div>


                            <div class="pbx-step-actions">

                                <button type="button" class="pbx-button pbx-button-ghost" id="pbxBackStep">
                                    <span aria-hidden="true">←</span>
                                    Back
                                </button>

                                <button type="submit" class="pbx-button pbx-button-primary" id="pbGenerateButton">
                                    Build my PC
                                    <span aria-hidden="true">↗</span>
                                </button>

                            </div>

                        </section>

                    </form>

                </div>


                <!-- LIVE BUILD SUMMARY -->
                <aside class="pbx-summary-card">

                    <div class="pbx-summary-top">
                        <span class="pbx-summary-label">YOUR BUILD BRIEF</span>
                        <span class="pbx-summary-status">
                            <span></span>
                            In progress
                        </span>
                    </div>

                    <div class="pbx-summary-title">
                        <h3>A PC that fits your world.</h3>
                        <p>
                            Here’s what we know about your build so far.
                        </p>
                    </div>


                    <div class="pbx-summary-values">

                        <div class="pbx-summary-row">
                            <span>Primary purpose</span>
                            <strong id="pbxSummaryPurpose">
                                <?= pc_builder_e(
                                pc_builder_purpose_label($formValues['purpose'])
                            ) ?>
                            </strong>
                        </div>

                        <div class="pbx-summary-row">
                            <span>Maximum budget</span>
                            <strong id="pbxSummaryBudget">
                                <?= pc_builder_e($formValues['budget']) ?> JOD
                            </strong>
                        </div>

                        <div class="pbx-summary-row" id="pbxSummaryResolutionRow"
                            <?= $formValues['purpose'] !== 'gaming' ? 'hidden' : '' ?>>
                            <span>Gaming resolution</span>
                            <strong id="pbxSummaryResolution">
                                <?= pc_builder_e(strtoupper($formValues['resolution'])) ?>
                            </strong>
                        </div>

                    </div>


                    <div class="pbx-summary-divider"></div>


                    <div class="pbx-summary-process">

                        <span class="pbx-summary-label">
                            WHAT HAPPENS NEXT
                        </span>

                        <div class="pbx-mini-process">

                            <span class="pbx-mini-number">01</span>
                            <div>
                                <strong>Inventory matching</strong>
                                <small>Find available components</small>
                            </div>

                        </div>

                        <div class="pbx-mini-process">

                            <span class="pbx-mini-number">02</span>
                            <div>
                                <strong>Compatibility review</strong>
                                <small>Evaluate known component conflicts</small>
                            </div>

                        </div>

                        <div class="pbx-mini-process">

                            <span class="pbx-mini-number">03</span>
                            <div>
                                <strong>Build ranking</strong>
                                <small>Compare performance and budget fit</small>
                            </div>

                        </div>

                    </div>


                    <div class="pbx-summary-footer">
                        <span>✦</span>
                        Your build is based on current REGIX catalog data.
                    </div>

                </aside>

            </div>

        </section>


        <!-- BUILDING EXPERIENCE OVERLAY -->
        <div class="pbx-loading" id="pbxLoading" hidden role="status" aria-live="polite">

            <div class="pbx-loading-card">

                <div class="pbx-loader-visual">
                    <span class="pbx-loader-ring"></span>
                    <span class="pbx-loader-ring pbx-loader-ring-two"></span>
                    <strong>R</strong>
                </div>

                <span class="pbx-section-kicker">REGIX BUILD ENGINE</span>

                <h2 id="pbxLoadingTitle">
                    Your build is taking shape.
                </h2>

                <p id="pbxLoadingMessage">
                    We’re matching your preferences with available components.
                </p>

                <div class="pbx-loading-steps">

                    <span class="is-current" data-loading-step="0">
                        <i></i> Inventory
                    </span>

                    <span data-loading-step="1">
                        <i></i> Compatibility
                    </span>

                    <span data-loading-step="2">
                        <i></i> Performance ranking
                    </span>

                    <span data-loading-step="3">
                        <i></i> Budget fit
                    </span>

                </div>

                <small>
                    This may take a moment. Please keep this page open.
                </small>

            </div>

        </div>


        <!-- RESULTS -->
        <?php if ($hasSubmitted && $result !== null): ?>

        <section class="pbx-results" id="build-results">

            <?php if (($result['success'] ?? false) !== true): ?>

            <div class="pbx-result-error">

                <span class="pbx-result-error-icon">!</span>

                <div>
                    <span class="pbx-section-kicker">BUILD RESULT</span>

                    <h2>We couldn’t complete this build.</h2>

                    <p>
                        <?= pc_builder_e(
                                $result['message'] ?? 'No build could be generated.'
                            ) ?>
                    </p>

                    <?php if (isset($result['minimum_listed_budget'])): ?>
                    <p>
                        Minimum listed budget:
                        <strong>
                            <?= pc_builder_e(
                                        pc_builder_money($result['minimum_listed_budget'])
                                    ) ?>
                        </strong>
                    </p>
                    <?php endif; ?>

                    <?php if (!empty($result['missing_component_types'])): ?>
                    <p>
                        Unavailable categories:
                        <?= pc_builder_e(
                                    implode(', ', $result['missing_component_types'])
                                ) ?>
                    </p>
                    <?php endif; ?>

                    <a href="pc_builder.php#pbx-builder" class="pbx-button pbx-button-primary">
                        Adjust my requirements
                        <span>↗</span>
                    </a>
                </div>

            </div>

            <?php elseif ($recommendedBuild !== null): ?>

            <?php
                $recommendedComponents = $recommendedBuild['components'] ?? [];

                $performanceScore = $recommendedBuild['performance_score'] ?? null;

                $recommendationScore = $recommendedBuild['recommendation_score'] ?? null;

                $compatibilityStatus = (string) (
                    $recommendedBuild['compatibility_status'] ?? 'needs_verification'
                );

                $ramRequirements = $recommendedBuild['purpose_requirements'] ?? [];

                $compatibilityChecks = $recommendedBuild['compatibility_checks'] ?? [];

                $componentsNeedingVerification =
                    $recommendedBuild['components_needing_verification'] ?? [];

                $storagePolicy = $result['storage_policy'] ?? null;

                $buildStory = pc_builder_build_story(
                    $recommendedBuild,
                    $formValues['purpose']
                );
                ?>


            <!-- RESULT INTRO -->
            <div class="pbx-results-intro">

                <div>
                    <span class="pbx-section-kicker">
                        YOUR BUILD IS READY
                    </span>

                    <h2>
                        Here’s what we built
                        <span>for you.</span>
                    </h2>

                    <p>
                        Based on your <?= pc_builder_e(
                                strtolower(pc_builder_purpose_label($formValues['purpose']))
                            ) ?>
                        requirements and a maximum budget of
                        <?= pc_builder_e(pc_builder_money($formValues['budget'])) ?>.
                    </p>
                </div>

                <a href="pc_builder.php#pbx-builder" class="pbx-button pbx-button-ghost">
                    Change my brief
                    <span>↗</span>
                </a>

            </div>


            <!-- RECOMMENDED BUILD STORY -->
            <article class="pbx-story-card">

                <div class="pbx-story-intro">

                    <span class="pbx-story-eyebrow">
                        <?= pc_builder_e($buildStory['eyebrow']) ?>
                    </span>

                    <h3>
                        <?= pc_builder_e($buildStory['headline']) ?>
                    </h3>

                    <p>
                        <?= pc_builder_e($buildStory['description']) ?>
                    </p>

                    <div class="pbx-story-focus">
                        <span>PRIMARY FOCUS</span>
                        <strong><?= pc_builder_e($buildStory['focus']) ?></strong>
                    </div>

                </div>


                <div class="pbx-story-chapters">

                    <?php foreach ($buildStory['chapters'] as $chapter): ?>

                    <div class="pbx-story-chapter">

                        <span class="pbx-chapter-number">
                            <?= pc_builder_e($chapter['number']) ?>
                        </span>

                        <div>
                            <strong>
                                <?= pc_builder_e($chapter['title']) ?>
                            </strong>

                            <p>
                                <?= pc_builder_e($chapter['description']) ?>
                            </p>
                        </div>

                    </div>

                    <?php endforeach; ?>

                </div>

            </article>


            <!-- BUILD OVERVIEW -->
            <article class="pbx-build-card">

                <div class="pbx-build-top">

                    <div>
                        <span class="pbx-build-label">
                            RECOMMENDED CONFIGURATION
                        </span>

                        <h3>
                            <?= pc_builder_e(
                                    pc_builder_purpose_label($formValues['purpose'])
                                ) ?> PC
                        </h3>

                        <small>
                            Build #<?= pc_builder_e(
                                    $recommendedBuild['build_id'] ?? '—'
                                ) ?>
                        </small>
                    </div>

                    <div class="pbx-build-total">
                        <span>BUILD TOTAL</span>

                        <strong>
                            <?= pc_builder_e(
                                    pc_builder_money($recommendedBuild['total_price'] ?? 0)
                                ) ?>
                        </strong>

                        <small>
                            <?= pc_builder_e(
                                    pc_builder_money($recommendedBuild['remaining_budget'] ?? 0)
                                ) ?>
                            remaining
                        </small>
                    </div>

                </div>


                <!-- METRICS -->
                <div class="pbx-metrics">

                    <div class="pbx-metric">
                        <span>Performance score</span>

                        <strong>
                            <?= is_numeric($performanceScore)
                                    ? pc_builder_e(
                                        number_format((float) $performanceScore, 2)
                                    )
                                    : '—' ?>
                            <small>/100</small>
                        </strong>

                        <p>
                            Relative benchmark ranking
                        </p>
                    </div>


                    <div class="pbx-metric">
                        <span>Recommendation score</span>

                        <strong>
                            <?= is_numeric($recommendationScore)
                                    ? pc_builder_e(
                                        number_format((float) $recommendationScore, 2)
                                    )
                                    : '—' ?>
                            <small>/100</small>
                        </strong>

                        <p>
                            Overall recommendation fit
                        </p>
                    </div>


                    <div class="pbx-metric">
                        <span>Compatibility</span>

                        <strong class="<?= $compatibilityStatus === 'compatible' ? 'is-good' : 'is-warning' ?>">
                            <?= pc_builder_e(
                                    pc_builder_status_label($compatibilityStatus)
                                ) ?>
                        </strong>

                        <p>
                            <?= pc_builder_e(
                                    pc_builder_compatibility_label($compatibilityStatus)
                                ) ?>
                        </p>
                    </div>


                    <div class="pbx-metric">
                        <span>Memory capacity</span>

                        <strong>
                            <?= pc_builder_e(
                                    $ramRequirements['actual_ram_gb']
                                    ?? ($recommendedComponents['ram']['specifications']['capacity_gb'] ?? '—')
                                ) ?>
                            <small>GB</small>
                        </strong>

                        <p>
                            <?= pc_builder_e(
                                    pc_builder_status_label(
                                        (string) ($ramRequirements['ram_status'] ?? 'unknown')
                                    )
                                ) ?>
                        </p>
                    </div>

                </div>


                <!-- COMPONENTS -->
                <div class="pbx-parts-heading">

                    <div>
                        <span class="pbx-section-kicker">THE HARDWARE</span>
                        <h3>Meet your components.</h3>
                        <p>
                            Every part in your recommended configuration.
                        </p>
                    </div>

                    <span class="pbx-parts-count">
                        <?= count($recommendedComponents) ?> / 8 parts
                    </span>

                </div>


                <div class="pbx-parts-grid">

                    <?php foreach ($componentLabels as $type => $label): ?>

                    <?php if (!isset($recommendedComponents[$type])) {
                                continue;
                            } ?>

                    <?php $component = $recommendedComponents[$type]; ?>

                    <article class="pbx-part-card">

                        <div class="pbx-part-top">

                            <span class="pbx-part-type">
                                <?= pc_builder_e($componentIcons[$type]) ?>
                            </span>

                            <span class="pbx-part-category">
                                <?= pc_builder_e($label) ?>
                            </span>

                            <?php if (($component['spec_status'] ?? '') === 'verified'): ?>

                            <span class="pbx-part-status is-good" title="Specifications verified">
                                ✓
                            </span>

                            <?php else: ?>

                            <span class="pbx-part-status is-warning" title="Specifications need verification">
                                !
                            </span>

                            <?php endif; ?>

                        </div>


                        <h4>
                            <?= pc_builder_e(
                                        $component['name'] ?? 'Unknown component'
                                    ) ?>
                        </h4>


                        <div class="pbx-part-meta">

                            <span>
                                <?= pc_builder_e($component['brand'] ?? '') ?>
                            </span>

                            <span>
                                <?= pc_builder_e(
                                            ucfirst((string) ($component['condition_type'] ?? ''))
                                        ) ?>
                            </span>

                        </div>


                        <div class="pbx-part-price">
                            <?= pc_builder_e(
                                        pc_builder_money($component['price'] ?? 0)
                                    ) ?>
                        </div>

                    </article>

                    <?php endforeach; ?>

                </div>


                <!-- VERIFICATION NOTICE -->
                <?php if (!empty($componentsNeedingVerification)): ?>

                <div class="pbx-verification">

                    <span class="pbx-verification-icon">!</span>

                    <div>

                        <strong>
                            A few specifications need your attention.
                        </strong>

                        <p>
                            Some selected products have incomplete specification
                            records. Please review these details before purchasing.
                        </p>

                        <ul>

                            <?php foreach ($componentsNeedingVerification as $item): ?>

                            <li>
                                <?= pc_builder_e($item['name'] ?? 'Component') ?>

                                —

                                <?= pc_builder_e(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        (string) ($item['component_type'] ?? '')
                                                    )
                                                )
                                            ) ?>
                            </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                </div>

                <?php endif; ?>


                <!-- COMPATIBILITY DETAILS -->
                <?php if (!empty($compatibilityChecks)): ?>

                <details class="pbx-compatibility">

                    <summary>
                        <span>
                            Review compatibility details
                        </span>

                        <span>
                            <?= count($compatibilityChecks) ?> checks
                        </span>
                    </summary>


                    <div class="pbx-compatibility-list">

                        <?php foreach ($compatibilityChecks as $check): ?>

                        <?php
                                    $checkStatus = (string) (
                                        $check['status'] ?? 'needs_verification'
                                    );
                                    ?>

                        <div class="pbx-check-row">

                            <span class="pbx-check-dot <?= pc_builder_e($checkStatus) ?>"></span>

                            <strong>
                                <?= pc_builder_e(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        (string) ($check['code'] ?? 'Compatibility check')
                                                    )
                                                )
                                            ) ?>
                            </strong>

                            <p>
                                <?= pc_builder_e($check['message'] ?? '') ?>
                            </p>

                            <span class="pbx-check-result">
                                <?= pc_builder_e(
                                                pc_builder_status_label($checkStatus)
                                            ) ?>
                            </span>

                        </div>

                        <?php endforeach; ?>

                    </div>

                </details>

                <?php endif; ?>


                <?php if (is_array($storagePolicy)): ?>

                <p class="pbx-policy-note">
                    Primary storage is selected as an SSD.
                    Secondary HDD selection is not included in this build step yet.
                </p>

                <?php endif; ?>


                <div class="pbx-build-disclaimer">
                    Performance scores are relative rankings based on available
                    benchmark data, not predicted FPS for the complete PC.
                    A build marked “Needs verification” should be reviewed
                    before purchase.
                </div>

            </article>


            <!-- ALTERNATIVES -->
            <?php if (!empty($alternatives)): ?>

            <section class="pbx-alternatives">

                <div class="pbx-alternatives-heading">

                    <div>
                        <span class="pbx-section-kicker">
                            EXPLORE OTHER DIRECTIONS
                        </span>

                        <h3>There’s more than one way to build.</h3>

                        <p>
                            Compare other component combinations that
                            also fit your requirements.
                        </p>
                    </div>

                    <span>
                        <?= count($alternatives) ?> alternatives
                    </span>

                </div>


                <div class="pbx-alternatives-grid">

                    <?php foreach ($alternatives as $index => $alternative): ?>

                    <?php
                                $alternativeComponents =
                                    $alternative['components'] ?? [];

                                $alternativeRam =
                                    $alternative['purpose_requirements'] ?? [];
                                ?>

                    <article class="pbx-alternative-card">

                        <div class="pbx-alternative-top">

                            <span>
                                OPTION <?= (int) ($index + 1) ?>
                            </span>

                            <strong>
                                <?= is_numeric($alternative['recommendation_score'] ?? null)
                                                ? pc_builder_e(
                                                    number_format(
                                                        (float) $alternative['recommendation_score'],
                                                        2
                                                    )
                                                )
                                                : '—' ?>
                                <small>/100</small>
                            </strong>

                        </div>


                        <div class="pbx-alternative-price">
                            <?= pc_builder_e(
                                            pc_builder_money($alternative['total_price'] ?? 0)
                                        ) ?>
                        </div>


                        <div class="pbx-alternative-details">

                            <div>
                                <span>Processor</span>
                                <strong>
                                    <?= pc_builder_e(
                                                    $alternativeComponents['cpu']['name'] ?? '—'
                                                ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Graphics</span>
                                <strong>
                                    <?= pc_builder_e(
                                                    $alternativeComponents['gpu']['name'] ?? '—'
                                                ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Memory</span>
                                <strong>
                                    <?= pc_builder_e(
                                                    $alternativeRam['actual_ram_gb']
                                                    ?? ($alternativeComponents['ram']['specifications']['capacity_gb'] ?? '—')
                                                ) ?> GB
                                </strong>
                            </div>

                            <div>
                                <span>Storage</span>
                                <strong>
                                    <?= pc_builder_e(
                                                    $alternativeComponents['storage']['name'] ?? '—'
                                                ) ?>
                                </strong>
                            </div>

                        </div>


                        <div class="pbx-alternative-footer">

                            <span
                                class="<?= ($alternative['compatibility_status'] ?? '') === 'compatible' ? 'is-good' : 'is-warning' ?>">
                                <?= pc_builder_e(
                                                pc_builder_status_label(
                                                    (string) (
                                                        $alternative['compatibility_status']
                                                        ?? 'needs_verification'
                                                    )
                                                )
                                            ) ?>
                            </span>

                            <span>
                                <?= (int) ($alternative['verified_components'] ?? 0) ?>/8 verified
                            </span>

                        </div>

                    </article>

                    <?php endforeach; ?>

                </div>

            </section>

            <?php endif; ?>

            <?php endif; ?>

        </section>

        <?php endif; ?>


        <!-- FINAL CTA -->
        <section class="pbx-final-cta">

            <div>
                <span class="pbx-section-kicker">YOUR NEXT UPGRADE STARTS HERE</span>
                <h2>Ready to rethink your build?</h2>
                <p>
                    Change your budget, choose another purpose and explore
                    a different configuration.
                </p>
            </div>

            <a href="pc_builder.php#pbx-builder">
                Build another PC
                <span>↗</span>
            </a>

        </section>

    </div><!-- /.pbx-page -->
    </div><!-- /.pc-builder-page -->

    <?php

store_page_end();

?>
