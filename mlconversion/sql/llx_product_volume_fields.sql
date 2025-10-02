-- Copyright (C) 2024 ML Conversion Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- Add volume tracking fields to products table for ML conversion functionality

-- Add volume_per_unit field (how many ML per unit of this product)
ALTER TABLE llx_product ADD COLUMN volume_per_unit DECIMAL(10,3) DEFAULT 1.000 COMMENT 'ML per unit for raw materials';

-- Add volume_capacity field (for finished products - bottle capacity)
ALTER TABLE llx_product ADD COLUMN volume_capacity DECIMAL(10,3) DEFAULT NULL COMMENT 'ML capacity per bottle for finished products';

-- Add track_in_ml flag (whether this product should be tracked in ML)
ALTER TABLE llx_product ADD COLUMN track_in_ml TINYINT(1) DEFAULT 0 COMMENT 'Whether to track this product in ML (1) or units (0)';

-- Create index for better performance
CREATE INDEX idx_product_volume ON llx_product(track_in_ml, volume_per_unit, volume_capacity);
