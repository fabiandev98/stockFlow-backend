<?php

return [
    'database' => [
        'delete_restricted' => 'No se puede eliminar este registro porque está siendo usado por otros registros.',
    ],
    'materials' => [
        'delete_used_in_compositions' => 'No se puede eliminar este material porque está usado en una composición de producto.',
        'delete_used_in_purchases' => 'No se puede eliminar este material porque está usado en compras.',
        'delete_has_inventory' => 'No se puede eliminar este material porque tiene lotes de inventario.',
        'delete_has_movements' => 'No se puede eliminar este material porque tiene movimientos de inventario.',
    ],
    'material_categories' => [
        'delete_assigned' => 'No se puede eliminar una categoría asignada a materiales.',
    ],
    'product_categories' => [
        'delete_assigned' => 'No se puede eliminar una categoría asignada a productos.',
    ],
    'products' => [
        'delete_used_in_sales' => 'No se puede eliminar un producto que ya fue usado en ventas.',
        'delete_has_inventory' => 'No se puede eliminar un producto con lotes de inventario.',
        'composition_required' => 'Los productos compuestos requieren al menos un material.',
    ],
    'suppliers' => [
        'delete_assigned' => 'No se puede eliminar un proveedor asignado a compras.',
    ],
];
