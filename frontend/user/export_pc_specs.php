<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/config/db.php';

try {

    $db = DB_conn::getConnection();

    $sql = "
        SELECT
            p.id,
            p.name,
            s.component_type,
            s.specifications,
            s.spec_status,
            s.notes

        FROM products p

        INNER JOIN pc_component_specs s
            ON s.product_id = p.id

        WHERE p.id IN (1, 8, 11, 6, 14, 18, 21, 23)

        ORDER BY FIELD(
            p.id,
            1, 8, 11, 6, 14, 18, 21, 23
        )
    ";

    $statement = $db->query($sql);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {

        $row['id'] = (int) $row['id'];

        $row['specifications'] = json_decode(
            $row['specifications'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    unset($row);

    header('Content-Type: application/json; charset=utf-8');

    header(
        'Content-Disposition: attachment; filename="pc-components-specs.json"'
    );

    echo json_encode(
        $rows,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_THROW_ON_ERROR
    );

} catch (Throwable $e) {

    http_response_code(500);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}