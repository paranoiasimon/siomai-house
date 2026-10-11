<?php

require_once "../../../config/cors.php";
require_once "../../../config/auth.php";

require_login();

require_once "../../../config/database.php";

try {

    // Keep the built-in product names synchronized with the labels printed
    // on their bundled images. This only affects the fixed it_1.jpg..it_9.jpg
    // assets and does not change custom product images.
    $imageLabels = [
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

    $sync = $pdo->prepare("UPDATE products SET product_name = ?, category = ? WHERE image = ?");
    foreach ($imageLabels as $image => [$name, $category]) {
        $sync->execute([$name, $category, $image]);
    }

    $sql = "
        SELECT
            id,
            product_name,
            category,
            price,
            stock,
            status,
            image,
            created_at
        FROM products
        ORDER BY id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $products = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "products" => $products
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to load products.",
        "error" => $e->getMessage()
    ]);
}