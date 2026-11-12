<?php
declare(strict_types=1);

require_once __DIR__ . '/DashboardClass.php';
require_once __DIR__ . '/PCBuilderClass.php';

class ModelFactory
{
    private function __construct()
    {
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    public static function dashboard(): DashboardBAL
    {
        $connection = DB_conn::getConnection();

        $dal = new DashboardDAL($connection);

        return new DashboardBAL($dal);
    }

    /*
    |--------------------------------------------------------------------------
    | PC BUILDER
    |--------------------------------------------------------------------------
    */

    public static function pcBuilder(): PCBuilderBAL
    {
        $connection = DB_conn::getConnection();

        $dal = new PCBuilderDAL($connection);

        $compatibility = new CompatibilityClass();

        $performanceRanking = new PerformanceRankingClass();

        $optimizer = new BuildOptimizerClass(
            $compatibility,
            $performanceRanking
        );

        return new PCBuilderBAL(
            $dal,
            $compatibility,
            $optimizer
        );
    }
}