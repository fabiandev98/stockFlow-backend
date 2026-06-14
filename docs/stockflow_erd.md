# StockFlow ERD

This document describes the current StockFlow inventory, purchasing, production, and sales data model.

## Main Flow

1. Materials are grouped by material categories.
2. Suppliers provide materials through purchases.
3. Each purchase contains purchase items.
4. Purchase items can create stock batches.
5. Products are grouped by product categories.
6. Product compositions define which materials are required to produce or sell a product.
7. Sales contain sale items.
8. Sale items consume materials through stock movements.
9. Stock movements track inventory changes and can be linked to users, sale items, and stock batches.

## Entities

### Users

Users represent system operators. Purchases, sales, and stock movements can reference the user who performed the action.

### Material Categories

Material categories group raw materials for filtering and organization.

Examples:

- Meat
- Dairy
- Packaging
- Cleaning supplies

### Materials

Materials represent raw inventory items.

Important fields:

- `unit`: base measurement unit, such as `kg`, `g`, `ml`, `l`, `u`, `box`, or `pack`.
- `minimum_stock`: threshold used for low-stock alerts.
- `is_perishable`: indicates whether the material can expire.
- `default_expiration_days`: suggested expiration window for perishable materials.

### Suppliers

Suppliers represent companies or people that provide materials.

Important fields:

- `name`: supplier name.
- `contact_name`: person to contact.
- `phone`: contact phone.
- `email`: contact email.

### Purchases

Purchases record material acquisition from suppliers.

Relationships:

- A supplier can have many purchases.
- A user can record many purchases.
- A purchase contains many purchase items.

### Purchase Items

Purchase items detail which materials were bought, in what quantity, and at what cost.

They can also include an expiration date when the material is perishable.

### Stock Batches

Stock batches represent inventory lots created from purchases.

They support batch-level tracking:

- initial quantity
- available quantity
- unit cost
- received date
- expiration date
- status

### Product Categories

Product categories group sellable or producible products.

### Products

Products represent final items that can be sold.

### Product Compositions

Product compositions define the bill of materials for each product.

Example:

```txt
Product: Burger
- Bread: 1 u
- Beef: 0.15 kg
- Cheese: 1 u
```

### Sales

Sales represent customer-facing transactions.

Relationships:

- A user can record many sales.
- A sale contains many sale items.

### Sale Items

Sale items detail which products were sold, the quantity, unit price, and total price.

### Stock Movements

Stock movements record inventory changes.

Examples:

- purchase entry
- sale consumption
- manual adjustment
- waste
- expiration

They can be linked to:

- material
- stock batch
- sale item
- user

## Mermaid Diagram

```mermaid
%% See docs/stockflow_erd.mmd for the editable Mermaid source.
```
