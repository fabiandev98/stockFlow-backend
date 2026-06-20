<?php

return [
    'database' => [
        'delete_restricted' => 'This record cannot be deleted because it is used by other records.',
    ],
    'materials' => [
        'delete_used_in_compositions' => 'Cannot delete this material because it is used in a product composition.',
        'delete_used_in_purchases' => 'Cannot delete this material because it is used in purchases.',
        'delete_has_inventory' => 'Cannot delete this material because it has inventory batches.',
        'delete_has_movements' => 'Cannot delete this material because it has inventory movements.',
    ],
    'material_categories' => [
        'delete_assigned' => 'Cannot delete a category assigned to materials.',
    ],
    'product_categories' => [
        'delete_assigned' => 'Cannot delete a category assigned to products.',
    ],
    'products' => [
        'delete_used_in_sales' => 'Cannot delete a product already used in sales.',
        'delete_has_inventory' => 'Cannot delete a product with inventory batches.',
        'composition_required' => 'Composed products require at least one material.',
    ],
    'suppliers' => [
        'delete_assigned' => 'Cannot delete a supplier assigned to purchases.',
    ],
];
