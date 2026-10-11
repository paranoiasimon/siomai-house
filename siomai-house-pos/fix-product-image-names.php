<?php
// One-time repair for the bundled Siomai House product images.
require_once __DIR__ . "/config/database.php";

header("Content-Type: application/json");

$items = [
    "it_1.jpg" => ["Pork Siomai", "Siomai"],
    "it_2.jpg" => ["Chicken Siomai", "Siomai"],
    "it_3.jpg" => ["Beef Siomai", "Siomai"],
    "it_4.jpg" => ["Lumpia", "Snack"],
    "it_5.jpg" => ["Siomai Rice", "Rice Meal"],
    "it_6.jpg" => ["Buchi", "Dessert"],
    "it_7.jpg" => ["Halo-Halo", "Dessert"],
    "it_8.jpg" => ["Iced Drinks", "Drinks"],
    "it_9.jpg" => ["Siomai House Combo", "Combo"]
];

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE products SET product_name = ?, category = ? WHERE image = ?");
    $changed = 0;
    foreach ($items as $image => [$name, $category]) {
        $stmt->execute([$name, $category, $image]);
        $changed += $stmt->rowCount();
    }
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Bundled product names now match the labels printed on their images.",
        "updated_rows" => $changed
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
