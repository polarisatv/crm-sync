<?php

namespace CrmSync;

class ProductModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $SKU,
        public readonly string $name,
        public readonly string $date_created,
        public readonly string $date_last_update,
        public readonly string $printed_name,
        public readonly string $active,
        public readonly string $price_retail_PLN,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            SKU: $data['SKU'],
            name: $data['name'],
            date_created: $data['date_created'],
            date_last_update: $data['date_last_update'],
            printed_name: $data['printed_name'],
            active: $data['active'],
            price_retail_PLN: $data['price_retail_PLN'],
        );
    }
}
